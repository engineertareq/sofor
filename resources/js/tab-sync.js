/**
 * Keeps every open Sofor tab in step with the others.
 *
 * Each page says who it was made for, in which language and when
 * (<meta name="sofor-session">, see components/head). The newest of those is
 * kept in localStorage, and the other tabs hear it through the `storage` event:
 *
 * - Signed out, signed in, or signed in as someone else in another tab: this
 *   page belongs to the old session, so it reloads straight away.
 * - A new language picked in another tab: this page reloads into it, once
 *   nobody is in the middle of something here. A page opened as ?lang= keeps
 *   the language it asked for (App\Support\Seo::PARAM).
 * - Something saved in another tab (a cost, a payment, an alert read): a trip
 *   page pulls its live figures; other pages of the app (data-reload-on-change)
 *   reload once they are on screen and nobody is in the middle of something.
 *
 * localStorage rather than BroadcastChannel because it remembers: a tab back
 * from the back-forward cache, or on a phone waking up, sees what it missed.
 */
const PREFIX = 'sofor.tabs.';
// In sessionStorage while a form from this tab is on its way; the page that answers tells the others.
const SENT = 'sofor.tabs.sent';
const FIELDS = 'input, textarea, select';

const meta = document.querySelector('meta[name="sofor-session"]');
const page = meta ? { user: meta.dataset.user, locale: meta.dataset.locale, at: Number(meta.dataset.at) } : null;
const tab = Math.random().toString(36).slice(2);
const pinned = new URLSearchParams(window.location.search).has('lang');

// Changes announced before this moment are already on the page.
let seen = Date.now();
let stale = false;
let timer = null;
const touched = new Set();

function read(topic) {
    try {
        return JSON.parse(localStorage.getItem(PREFIX + topic) ?? 'null');
    } catch (error) {
        return null;
    }
}

/** Tell the other tabs about `topic`, stamped with the time and this tab. */
export function announce(topic, detail = {}) {
    try {
        localStorage.setItem(PREFIX + topic, JSON.stringify({ at: Date.now(), ...detail, tab }));
    } catch (error) {
        // Private browsing or a full disk: this tab just works on its own.
    }
}

/** Run `handler` whenever another tab announces `topic`. */
export function hear(topic, handler) {
    window.addEventListener('storage', (event) => {
        if (event.key !== PREFIX + topic || !event.newValue) {
            return;
        }

        let detail;

        try {
            detail = JSON.parse(event.newValue);
        } catch (error) {
            return;
        }

        handler(detail);
    });
}

/** Something was saved from this tab: the same traveller's other tabs catch up. */
export function changed() {
    if (page?.user) {
        announce('changed', { user: page.user });
    }
}

/* -------------------------------------------------------------------------
   Following the other tabs
------------------------------------------------------------------------- */

/** A newer page, in another tab, was made for someone else or in another language. */
function follow(latest) {
    if (!page || !latest || !(latest.at > page.at)) {
        return;
    }

    if (latest.user !== page.user) {
        // Nothing on this page is right for the new session; signed out, the server sends it to sign in.
        window.location.reload();
    } else if (latest.locale !== page.locale && !pinned) {
        stale = true;
        settle();
    }
}

function onChange(change) {
    seen = Math.max(seen, change.at);

    if (!page?.user || change.user !== page.user || change.tab === tab) {
        return;
    }

    if (window.Sofor?.refreshTrip) {
        // The trip page patches in its fresh figures itself (trip-live.js).
        window.Sofor.refreshTrip();
    } else if (document.body.hasAttribute('data-reload-on-change')) {
        stale = true;
        settle();
    }
}

/** Back from the back-forward cache, or shown again: anything missed meanwhile. */
function catchUp() {
    follow(read('session'));

    const change = read('changed');

    if (change && change.at > seen) {
        onChange(change);
    }

    settle(0);
}

/* -------------------------------------------------------------------------
   Reloading without getting in the way
------------------------------------------------------------------------- */

// On screen: laid out and not visibility:hidden (the phone menu waits hidden that way).
const shown = (element) => element.isConnected && (element.checkVisibility
    ? element.checkVisibility({ visibilityProperty: true })
    : element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden');

/** A field changed from what the page came with. */
function unsent(field) {
    if (field.type === 'checkbox' || field.type === 'radio') {
        return field.checked !== field.defaultChecked;
    }

    if (field.type === 'file') {
        return field.files.length > 0;
    }

    if (field instanceof HTMLSelectElement) {
        return [...field.options].some((option) => option.selected !== option.defaultSelected);
    }

    return field.value !== field.defaultValue;
}

/** A sheet is open, or someone is typing, or has typed something not sent yet. */
function busy() {
    if ([...document.querySelectorAll('[aria-modal="true"], dialog[open]')].some(shown)) {
        return true;
    }

    return [...touched].some((field) => shown(field) && (field === document.activeElement || unsent(field)));
}

/** Reload a page that has fallen behind, once it is on screen and nobody is busy with it. */
function settle(delay = 400) {
    clearTimeout(timer);
    timer = setTimeout(() => {
        if (stale && document.visibilityState === 'visible' && navigator.onLine && !busy()) {
            window.location.reload();
        }
    }, delay);
}

/* -------------------------------------------------------------------------
   Boot
------------------------------------------------------------------------- */

function onSubmit(event) {
    const form = event.target;

    if (event.defaultPrevented || form.method !== 'post' || (form.target && form.target !== '_self')) {
        return;
    }

    try {
        sessionStorage.setItem(SENT, '1');
    } catch (error) {
        // The other tabs catch up when they next load a page instead.
    }
}

function touch(event) {
    if (event.isTrusted && event.target.matches?.(FIELDS)) {
        touched.add(event.target);
    }
}

export function bootTabSync() {
    const latest = read('session');
    // Signed in, signed out or a new language: the other tabs follow the session itself.
    const moved = !!(page && latest) && (latest.user !== page.user || latest.locale !== page.locale);

    if (page && !(latest?.at >= page.at)) {
        announce('session', page);
    }

    follow(latest);

    try {
        if (sessionStorage.getItem(SENT)) {
            sessionStorage.removeItem(SENT);

            // Unless the form came back with an error, what it sent is saved.
            if (!moved && !document.querySelector('[data-toast][data-tone="error"]')) {
                changed();
            }
        }
    } catch (error) {
        // No sessionStorage, so nothing was marked.
    }

    hear('session', follow);
    hear('changed', onChange);

    window.addEventListener('submit', onSubmit);
    ['pointerdown', 'input', 'change'].forEach((type) => document.addEventListener(type, touch, true));
    document.addEventListener('focusout', () => stale && settle());
    window.addEventListener('focus', () => stale && settle());
    window.addEventListener('online', () => stale && settle());
    document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && catchUp());
    window.addEventListener('pageshow', (event) => event.persisted && catchUp());
}
