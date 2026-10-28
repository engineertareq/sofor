/**
 * Offline mode.
 *
 * Signal drops on the road to Sajek. When there is no connection, a form
 * marked `data-offline-queue` (the "Log a trip cost" sheet) is saved in
 * IndexedDB instead of being sent - receipt photo included - and sent as
 * soon as the phone is back online. Each cost carries a `client_uuid`, so a
 * send that is retried never logs it twice.
 *
 * The service worker (public/sw.js) keeps the pages themselves available.
 */
const DB_NAME = 'sofor-offline';
const STORE = 'queue';
const TOKEN_URL = '/session/token';

let flushing = false;
let pill = null;

function open() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => request.result.createObjectStore(STORE, { keyPath: 'id' });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function run(mode, action) {
    const db = await open();

    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE, mode);
        const request = action(transaction.objectStore(STORE));
        transaction.oncomplete = () => resolve(request?.result);
        transaction.onerror = () => reject(transaction.error);
    });
}

const queue = {
    add: (item) => run('readwrite', (store) => store.put(item)),
    all: async () => ((await run('readonly', (store) => store.getAll())) ?? []).sort((a, b) => a.created - b.created),
    remove: (id) => run('readwrite', (store) => store.delete(id)),
};

export function uuid() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    // Older browsers, or a page not served over https.
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
}

import { t, num } from './i18n';

const toast = (message, type = 'info') => window.Sofor?.toast(message, type);

/* -------------------------------------------------------------------------
   Saving a form for later
------------------------------------------------------------------------- */

async function enqueue(form) {
    const entries = [];

    for (const [name, value] of new FormData(form).entries()) {
        // An empty file input still sends a nameless, empty file.
        if (value instanceof File && value.size === 0) {
            continue;
        }

        entries.push([name, value]);
    }

    const fields = Object.fromEntries(entries.filter(([, value]) => typeof value === 'string'));

    await queue.add({
        id: fields.client_uuid || uuid(),
        url: form.action,
        title: fields.title || 'A cost',
        entries,
        created: Date.now(),
    });
}

function onSubmit(event) {
    const form = event.target.closest?.('form[data-offline-queue]');

    if (!form || navigator.onLine) {
        return;
    }

    event.preventDefault();

    if (form.querySelector('input[name="_method"]')) {
        toast(t('Editing a cost needs a connection. Try again when you have signal.'), 'warning');
        return;
    }

    enqueue(form)
        .then(() => {
            toast(t('No signal - the cost is saved on this phone and will be sent when you are back online.'), 'success');
            form.dispatchEvent(new CustomEvent('sofor-queued', { bubbles: true }));
            render();
        })
        .catch(() => toast(t('This browser could not save the cost offline. Keep the details and try again with signal.'), 'error'));
}

/* -------------------------------------------------------------------------
   Sending what was saved
------------------------------------------------------------------------- */

async function freshToken() {
    const response = await fetch(TOKEN_URL, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        cache: 'no-store',
    });

    if (!response.ok || !response.headers.get('content-type')?.includes('json')) {
        throw new Error('signed-out');
    }

    return (await response.json()).token;
}

async function send(item, token) {
    const body = new FormData();

    for (const [name, value] of item.entries) {
        if (name !== '_token') {
            body.append(name, value);
        }
    }

    body.append('_token', token);

    return fetch(item.url, {
        method: 'POST',
        body,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
}

export async function flush() {
    if (flushing || !navigator.onLine) {
        return;
    }

    flushing = true;
    let synced = 0;

    try {
        const items = await queue.all();

        if (items.length === 0) {
            return;
        }

        let token = await freshToken();

        for (const item of items) {
            let response;

            try {
                response = await send(item, token);

                if (response.status === 419) {
                    token = await freshToken();
                    response = await send(item, token);
                }
            } catch (error) {
                break; // The signal went again; keep the rest for later.
            }

            if (response.ok) {
                await queue.remove(item.id);
                synced++;
            } else if (response.status === 429 || response.status >= 500) {
                break; // Try again later.
            } else {
                // Refused for good (the trip ended, a field was wrong): tell them, do not retry forever.
                let reason = '';

                try {
                    const data = await response.json();
                    reason = Object.values(data.errors ?? {})[0]?.[0] ?? data.message ?? '';
                } catch (error) {
                    reason = '';
                }

                await queue.remove(item.id);
                toast(reason ? t('":title" could not be saved: :reason. Add it again.', { title: item.title, reason }) : t('":title" could not be saved. Add it again.', { title: item.title }), 'error');
            }
        }
    } catch (error) {
        // Signed out, or the token request failed: try again on the next chance.
    } finally {
        flushing = false;
        render();

        if (synced > 0) {
            toast(synced === 1 ? t('Back online - 1 cost saved offline was added.') : t('Back online - :count costs saved offline were added.', { count: num(synced) }), 'success');
            document.dispatchEvent(new CustomEvent('sofor-synced', { detail: { count: synced } }));
        }
    }
}

/* -------------------------------------------------------------------------
   "2 costs waiting for signal"
------------------------------------------------------------------------- */

async function render() {
    let count = 0;

    try {
        count = (await queue.all()).length;
    } catch (error) {
        count = 0;
    }

    if (count === 0) {
        pill?.remove();
        pill = null;
        return;
    }

    if (!pill) {
        pill = document.createElement('button');
        pill.type = 'button';
        pill.className = 'fixed bottom-24 left-4 z-50 flex items-center gap-2 rounded-full border-[1.5px] border-ink bg-ink px-3.5 py-2 text-[12px] font-semibold text-white shadow-sticker-sm lg:bottom-6';
        pill.addEventListener('click', () => (navigator.onLine ? flush() : toast(t('Still no signal. Sofor sends them as soon as there is.'), 'info')));
        document.body.appendChild(pill);
    }

    pill.textContent = count === 1 ? t('1 cost waiting for signal') : t(':count costs waiting for signal', { count: num(count) });
    pill.setAttribute('aria-label', t(':text. Tap to send now.', { text: pill.textContent }));
}

/* -------------------------------------------------------------------------
   Boot
------------------------------------------------------------------------- */

function registerServiceWorker() {
    if (!('serviceWorker' in navigator) || !import.meta.env.PROD) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
}

/** Signing out forgets the saved pages; it warns first if costs are still waiting. */
function onSignOut(event) {
    const form = event.target.closest?.('form');

    if (!form || !/\/logout$/.test(new URL(form.action, location.href).pathname)) {
        return;
    }

    queue.all().then((items) => items.length).catch(() => 0).then((waiting) => {
        if (waiting > 0 && !window.confirm(waiting === 1 ? t('1 cost is still waiting for signal on this phone. Sign out anyway? It will not be sent.') : t(':count costs are still waiting for signal on this phone. Sign out anyway? They will not be sent.', { count: num(waiting) }))) {
            return;
        }

        navigator.serviceWorker?.controller?.postMessage('sofor:clear-pages');
        window.caches?.delete('sofor-pages');
        form.submit();
    });

    event.preventDefault();
}

export function bootOffline() {
    if (!('indexedDB' in window)) {
        registerServiceWorker();
        return;
    }

    registerServiceWorker();
    document.addEventListener('submit', onSubmit, true);
    document.addEventListener('submit', onSignOut);
    window.addEventListener('online', () => flush());
    document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && flush());
    setInterval(() => flush(), 60000);

    render();
    flush();
}
