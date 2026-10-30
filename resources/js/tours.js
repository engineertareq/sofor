import { num } from './i18n';

/**
 * Organiser mode in the browser: the booking form on a tour's public page
 * (tours/public) and the organiser's dashboard (tours/show). Everything is
 * still a plain form the server checks; this only makes it pleasant.
 */
const money = (amount) => `৳${num(Math.round(amount))}`;

/**
 * The public booking form: the total as the package and quantity change, and
 * a warning before booking when the seats asked for would not fit.
 *
 * @param {{ packages: {id: number, price: number, seats: number}[], seatsLeft: ?number, selected: ?number, quantity: number }} options
 */
export function tourBooking({ packages, seatsLeft = null, selected = null, quantity = 1 }) {
    return {
        packageId: selected ?? packages[0]?.id ?? null,
        quantity,

        get package() {
            return packages.find((item) => item.id === Number(this.packageId)) ?? null;
        },

        get seats() {
            return (this.package?.seats ?? 1) * this.quantity;
        },

        get total() {
            return (this.package?.price ?? 0) * this.quantity;
        },

        get totalLabel() {
            return money(this.total);
        },

        /** It does not fit: the booking goes on the waitlist. */
        get waitlist() {
            return seatsLeft !== null && this.seats > seatsLeft;
        },

        /** The most of this package that still fits, or ten. */
        get most() {
            if (seatsLeft === null || seatsLeft === 0) {
                return 10;
            }

            return Math.max(1, Math.min(10, Math.floor(seatsLeft / (this.package?.seats ?? 1))));
        },

        step(by) {
            this.quantity = Math.min(10, Math.max(1, this.quantity + by));
        },
    };
}

/**
 * The organiser's dashboard: its tabs (kept across the saves that reload the
 * page), the search and filter over bookings, and the sheets each action
 * opens - one sheet per form, told what to do by an event.
 */
export function tourDashboard({ key }) {
    const store = `sofor.tour.${key}.tab`;

    return {
        tab: 'bookings',
        query: '',
        state: 'all',

        init() {
            try {
                this.tab = window.location.hash.slice(1) || sessionStorage.getItem(store) || 'bookings';
            } catch (error) {
                this.tab = 'bookings';
            }

            this.$watch('tab', (tab) => {
                try {
                    sessionStorage.setItem(store, tab);
                } catch (error) {
                    // Private browsing: the tab simply starts on bookings next time.
                }

                history.replaceState(null, '', `#${tab}`);
            });

            // A link to #costs, or the address changed by hand.
            window.addEventListener('hashchange', () => {
                const tab = window.location.hash.slice(1);

                if (['bookings', 'payments', 'costs', 'account'].includes(tab)) {
                    this.tab = tab;
                }
            });
        },

        /** A booking row (data-state, data-search) under the current search and filter. */
        shows(row) {
            const query = this.query.trim().toLowerCase();

            return (this.state === 'all' || row.dataset.state === this.state)
                && (query === '' || row.dataset.search.includes(query));
        },
    };
}
