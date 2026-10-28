/**
 * The cost timeline (trips/partials/timeline): the dotted road fills with
 * marigold as you scroll, each cost's picture lights up as it passes the
 * middle of the screen, the day on screen is marked in the navigator, and the
 * category filter hides costs (and days with none left).
 *
 * The trip's map (trips/partials/timeline-map) follows along: `marks` says how
 * far along the route each day got, so the route draws on to there and the
 * day's places glow.
 *
 * @param {{ days: Object<string, string[]>, marks: Object<string, number> }} options
 *        days: day key => categories spent on that day; marks: day key => 0..1
 */
export default function tripTimeline({ days, marks = {} }) {
    const keys = Object.keys(days);
    const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    return {
        filter: 'all',
        active: keys[0] ?? null,
        items: [],
        sections: [],
        frame: null,

        init() {
            this.items = [...this.$root.querySelectorAll('.tl-item')];
            this.sections = [...this.$root.querySelectorAll('.tl-day')];

            const queue = () => {
                if (this.frame === null) {
                    this.frame = requestAnimationFrame(() => {
                        this.frame = null;
                        this.update();
                    });
                }
            };

            window.addEventListener('scroll', queue, { passive: true });
            window.addEventListener('resize', queue, { passive: true });
            this.$watch('filter', () => this.$nextTick(queue));
            this.$watch('active', (key) => this.followStrip(key));
            this.update();
        },

        /** Fill the road up to the middle of the screen, light what it has passed, mark today. */
        update() {
            const track = this.$refs.track;

            if (!track) {
                return;
            }

            const middle = window.innerHeight * 0.5;
            const box = track.getBoundingClientRect();

            // The road stops at the finish pin, not at the bottom of the card beside it.
            const finish = track.querySelector('.tl-pin-end')?.getBoundingClientRect();

            if (finish) {
                track.style.setProperty('--tl-end', `${Math.max(0, box.bottom - (finish.top + finish.height / 2)).toFixed(1)}px`);
            }
            const progress = Math.min(1, Math.max(0, (middle - box.top) / Math.max(box.height, 1)));
            track.style.setProperty('--tl-progress', `${(progress * 100).toFixed(2)}%`);

            for (const item of this.items) {
                item.classList.toggle('is-lit', item.getBoundingClientRect().top < middle);
            }

            let current = null;

            for (const section of this.sections) {
                if (section.offsetParent !== null && section.getBoundingClientRect().top < window.innerHeight * 0.4) {
                    current = section.dataset.day;
                }
            }

            this.active = current ?? this.sections.find((section) => section.offsetParent !== null)?.dataset.day ?? this.active;
        },

        dayVisible(key) {
            return this.filter === 'all' || (days[key] ?? []).includes(this.filter);
        },

        /** How much of the route is travelled by the end of a day (0..1). */
        mark(key) {
            return marks[key] ?? 0;
        },

        /** Whether a day is today or behind us, on the way down the timeline. */
        reached(key) {
            return keys.indexOf(key) <= keys.indexOf(this.active);
        },

        /** Jump to a day, or to one cost on it (which then glows for a moment). */
        go(key, id = null) {
            const target = document.getElementById(id ? `tl-cost-${id}` : `tl-${key}`);

            if (!target) {
                return;
            }

            if (id && this.filter !== 'all' && target.dataset.cat !== this.filter) {
                this.filter = 'all';
            }

            this.$nextTick(() => {
                target.scrollIntoView({ behavior: calm ? 'auto' : 'smooth', block: id ? 'center' : 'start' });

                if (id) {
                    target.classList.remove('is-flash');
                    void target.offsetWidth;
                    target.classList.add('is-flash');
                }
            });
        },

        /** Keep the marked day in view in the phone strip. */
        followStrip(key) {
            const strip = this.$refs.strip;
            const chip = strip?.querySelector(`[data-strip="${key}"]`);

            if (strip && chip && strip.scrollWidth > strip.clientWidth) {
                strip.scrollTo({ left: chip.offsetLeft - 16, behavior: calm ? 'auto' : 'smooth' });
            }
        },
    };
}
