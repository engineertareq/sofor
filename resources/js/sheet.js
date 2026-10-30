/**
 * Panels that open over the page: the phone menu and the alerts (layouts/app).
 *
 * On a phone each is a bottom sheet that follows the finger: drag it down by
 * its handle, or from the top of its list ([data-sheet-list]), and let go far
 * enough, or flick it, to close. From a laptop up (lg) a panel that opens
 * there is a card under the button that opened it.
 *
 * One is open at a time, named in Alpine.store('sheets'): any button can
 * open one, and focus goes back to that button when it closes.
 */
const WIDE = '(min-width: 1024px)';
const EASE = 'cubic-bezier(0.32, 0.72, 0, 1)';

export const sheets = {
    current: null,
    opener: null,

    open(name, opener = document.activeElement) {
        this.opener = opener;
        this.current = name;
    },

    close() {
        if (this.current === null) {
            return;
        }

        const opener = this.opener;
        this.current = null;
        this.opener = null;
        opener?.focus?.({ preventScroll: true });
    },

    toggle(name, opener) {
        if (this.current === name) {
            this.close();
        } else {
            this.open(name, opener);
        }
    },

    /** A phone's sheet holds the page behind it still; a laptop's card leaves it be. */
    get locked() {
        return this.current !== null && !window.matchMedia(WIDE).matches;
    },
};

export function sheet(name) {
    return {
        drag: 0,
        dragging: false,
        startY: 0,
        startAt: 0,
        wide: window.matchMedia(WIDE).matches,

        get shown() {
            return this.$store.sheets.current === name;
        },

        init() {
            const query = window.matchMedia(WIDE);
            query.addEventListener('change', () => (this.wide = query.matches));

            this.$watch('shown', (shown) => {
                this.drag = 0;
                this.dragging = false;

                if (shown) {
                    this.$nextTick(() => this.$refs.sheet?.focus({ preventScroll: true }));
                }
            });
        },

        hide() {
            if (this.shown) {
                this.$store.sheets.close();
            }
        },

        list() {
            return this.$refs.sheet?.querySelector('[data-sheet-list]');
        },

        grab(event) {
            const list = this.list();

            // From inside the list only at its very top, so it still scrolls as usual.
            if (this.wide || (list?.contains(event.target) && list.scrollTop > 0)) {
                return;
            }

            this.dragging = true;
            this.startY = event.touches[0].clientY;
            this.startAt = event.timeStamp;
        },

        pull(event) {
            if (!this.dragging) {
                return;
            }

            const y = event.touches[0].clientY;
            const list = this.list();

            // Pulling up (or a list scrolled away from its top) scrolls the list instead.
            if (y <= this.startY || (list?.contains(event.target) && list.scrollTop > 0)) {
                this.startY = Math.min(this.startY, y);
                this.drag = 0;

                return;
            }

            event.preventDefault();
            this.drag = y - this.startY;
        },

        letGo(event) {
            if (!this.dragging) {
                return;
            }

            const flick = this.drag > 24 && this.drag / Math.max(1, event.timeStamp - this.startAt) > 0.5;
            this.dragging = false;

            if (flick || this.drag > this.$refs.sheet.offsetHeight / 4) {
                this.hide();
            } else {
                this.drag = 0;
            }
        },

        get sheetStyle() {
            const still = this.dragging || window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (this.wide) {
                return `opacity: ${this.shown ? 1 : 0}; transform-origin: top right;`
                    + ` transform: translate3d(0, ${this.shown ? 0 : -6}px, 0) scale(${this.shown ? 1 : 0.97});`
                    + ` transition: ${still ? 'none' : `opacity 0.16s ease, transform 0.22s ${EASE}`};`;
            }

            return `transform: translate3d(0, ${this.shown ? `${this.drag}px` : '100%'}, 0);`
                + ` transition: ${still ? 'none' : `transform 0.42s ${EASE}`};`;
        },
    };
}
