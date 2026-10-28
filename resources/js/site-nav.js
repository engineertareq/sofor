/**
 * The public site's header (components/site-header): mega menus that open
 * when the mouse rests on them and on a click, tap or Enter otherwise, and
 * the slide-in menu on phones and tablets. `scrolled` lifts the bar off the
 * page once it has something under it.
 */
export default function siteNav() {
    let timer = null;
    let openedAt = 0;

    return {
        open: null,
        menu: false,
        scrolled: false,

        init() {
            const update = () => (this.scrolled = window.scrollY > 4);

            update();
            window.addEventListener('scroll', update, { passive: true });
        },

        /** The mouse arrived: open after a short pause, so passing over the bar does nothing. */
        hover(name, event) {
            if (event.pointerType !== 'mouse') {
                return;
            }

            clearTimeout(timer);
            timer = setTimeout(() => {
                if (this.open !== name) {
                    this.open = name;
                    openedAt = Date.now();
                }
            }, this.open ? 0 : 90);
        },

        /** The mouse left the trigger and its panel: close, unless it comes straight back. */
        unhover(event) {
            if (event.pointerType !== 'mouse') {
                return;
            }

            clearTimeout(timer);
            timer = setTimeout(() => (this.open = null), 200);
        },

        toggle(name) {
            clearTimeout(timer);

            // A click just after the hover opened it means "this one", not "close it".
            if (this.open === name && Date.now() - openedAt < 600) {
                return;
            }

            this.open = this.open === name ? null : name;
            openedAt = 0;
        },

        /** Escape, or focus moving on: close, handing focus back to the trigger when asked. */
        close(refocus = false) {
            const name = this.open;

            clearTimeout(timer);
            this.open = null;

            if (refocus && name) {
                document.querySelector(`[data-mega-trigger="${name}"]`)?.focus();
            }
        },
    };
}
