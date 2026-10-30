import { t, num } from './i18n';
import { changed } from './tab-sync';

/**
 * The alerts panel (notifications/partials/panel): the latest alerts without
 * leaving the page.
 *
 * The rows are rendered by the server, with the same partial as the alerts
 * page, and come with the unread count and the next page's address
 * (NotificationController::feed). The first opening shows a skeleton; later
 * ones show the last list at once and refresh it behind. More load as the
 * list scrolls to its end.
 *
 * Each row is a form that marks it read: here it is sent with fetch, the row
 * and every badge ([data-alerts-badge]) update, and the alert's link opens.
 */
const json = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export default function alerts({ feedUrl, readAllUrl, unread = 0 }) {
    let controller = null;
    let observer = null;
    // Bumped whenever the list starts over, so an older answer cannot land in a newer list.
    let generation = 0;

    async function getJson(url, signal) {
        const response = await fetch(url, { headers: json, credentials: 'same-origin', cache: 'no-store', signal });

        if (response.status === 401 || response.status === 419) {
            // Signed out meanwhile: the page takes them to sign in.
            window.location.reload();
            throw new Error('signed-out');
        }

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        return response.json();
    }

    return {
        filter: 'all',
        // idle, loading, ready or error
        state: 'idle',
        empty: true,
        next: null,
        loadingMore: false,
        unread,

        init() {
            this.$watch('$store.sheets.current', (current) => current === 'alerts' && this.load());

            observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    this.more();
                }
            }, { root: this.$refs.scroller, rootMargin: '0px 0px 160px 0px' });
        },

        show(filter) {
            if (filter !== this.filter) {
                this.filter = filter;
                this.load(true);
            }
        },

        async load(fresh = false) {
            controller?.abort();
            controller = new AbortController();
            const mine = ++generation;

            // A skeleton only when there is nothing to show yet; otherwise refresh behind the list.
            if (fresh || this.state !== 'ready') {
                this.state = 'loading';
                this.$refs.items.innerHTML = '';
                this.empty = true;
                this.next = null;
            }

            try {
                const data = await getJson(`${feedUrl}${this.filter === 'unread' ? '?unread=1' : ''}`, controller.signal);

                if (mine !== generation) {
                    return;
                }

                this.$refs.items.innerHTML = data.html;
                this.empty = data.html.trim() === '';
                this.next = data.next;
                this.setUnread(data.unread);
                this.state = 'ready';
                this.watchEnd();
            } catch (error) {
                // A failed refresh keeps the list already showing.
                if (error.name !== 'AbortError' && mine === generation && this.state !== 'ready') {
                    this.state = 'error';
                }
            }
        },

        async more() {
            if (!this.next || this.loadingMore || this.state !== 'ready') {
                return;
            }

            const mine = generation;
            this.loadingMore = true;

            try {
                const data = await getJson(this.next);

                if (mine === generation) {
                    this.$refs.items.insertAdjacentHTML('beforeend', data.html);
                    this.next = data.next;
                    this.setUnread(data.unread);
                }
            } catch (error) {
                // Scrolling to the end again tries again.
            } finally {
                this.loadingMore = false;

                if (mine === generation) {
                    this.watchEnd();
                }
            }
        },

        /** Watch the end of the list afresh: a short page leaves it in view, and that must still load more. */
        watchEnd() {
            observer.unobserve(this.$refs.end);

            if (this.next) {
                this.$nextTick(() => observer.observe(this.$refs.end));
            }
        },

        /** A row was opened: mark it read here, then go where it points. */
        async open(event) {
            const form = event.target.closest('form[data-alert]');

            if (!form) {
                return;
            }

            event.preventDefault();

            if (form.dataset.sending) {
                return;
            }

            form.dataset.sending = '1';

            try {
                const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: json, credentials: 'same-origin' });

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                const data = await response.json();
                form.removeAttribute('data-unread');
                this.setUnread(data.unread);
                changed();
                this.$store.sheets.close();
                window.location.assign(data.redirect);
            } catch (error) {
                // The plain form still works: it marks the alert read and follows it.
                form.submit();
            }
        },

        async readAll() {
            try {
                const response = await fetch(readAllUrl, {
                    method: 'POST',
                    headers: { ...json, 'X-CSRF-TOKEN': csrf() },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                this.$refs.items.querySelectorAll('[data-unread]').forEach((row) => row.removeAttribute('data-unread'));
                this.setUnread(0);
                changed();

                if (this.filter === 'unread') {
                    generation++;
                    this.$refs.items.innerHTML = '';
                    this.empty = true;
                    this.next = null;
                }
            } catch (error) {
                window.Sofor?.toast(t('That did not save. Check your connection and try again.'), 'error');
            }
        },

        /** The unread count everywhere it shows: the bell, the tab bar and the sidebar. */
        setUnread(count) {
            this.unread = count;

            document.querySelectorAll('[data-alerts-badge]').forEach((badge) => {
                const cap = Number(badge.dataset.cap || 0);
                badge.textContent = cap && count > cap ? `${num(cap)}+` : num(count);
                badge.hidden = count === 0;
            });
        },

        get unreadLabel() {
            return num(this.unread);
        },
    };
}
