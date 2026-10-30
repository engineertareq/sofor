import { num } from './i18n';

/**
 * The free trip split calculator (/trip-split-calculator): who paid what in,
 * each person's fair share and who pays whom out - in the fewest payments,
 * the same way a Sofor trip settles up. Everything happens in the browser;
 * the list is kept in this browser only, so a refresh does not lose it.
 *
 * Amounts are worked in poisha (whole numbers), so the shares always add up
 * to the exact total: the leftover poisha go one each to the first people.
 */
const STORE = 'sofor.split-calculator';
const BANGLA = '০১২৩৪৫৬৭৮৯';

/** "১,২০০.৫০" or "1,200.50" -> 120050 poisha. */
function poisha(value) {
    const latin = String(value ?? '').replace(/[০-৯]/g, (digit) => BANGLA.indexOf(digit)).replace(/[,\s৳]/g, '');
    const amount = Number.parseFloat(latin);

    return Number.isFinite(amount) && amount > 0 ? Math.round(amount * 100) : 0;
}

export default function splitCalculator({ labels }) {
    return {
        people: [],
        copied: false,

        init() {
            try {
                const saved = JSON.parse(localStorage.getItem(STORE) ?? 'null');
                this.people = Array.isArray(saved) && saved.length >= 2 ? saved.slice(0, 30) : [];
            } catch (error) {
                this.people = [];
            }

            if (this.people.length < 2) {
                this.people = [{ name: '', paid: '' }, { name: '', paid: '' }, { name: '', paid: '' }];
            }

            // The list last heard from another tab. Writing it back could land after newer
            // typing there and undo it, so it is never written back.
            let heard = null;

            this.$watch('people', (people) => {
                const list = JSON.stringify(people);

                if (list === heard) {
                    return;
                }

                heard = null;

                try {
                    localStorage.setItem(STORE, list);
                } catch (error) {
                    // Private window: the calculator still works, it just forgets on refresh.
                }
            }, { deep: true });

            // The calculator open in another tab: keep one list, so neither overwrites the other.
            window.addEventListener('storage', (event) => {
                if (event.key !== STORE || !event.newValue) {
                    return;
                }

                try {
                    const people = JSON.parse(event.newValue);

                    if (Array.isArray(people) && people.length >= 2) {
                        heard = JSON.stringify(people.slice(0, 30));
                        this.people = people.slice(0, 30);
                    }
                } catch (error) {
                    // Not a list this calculator wrote.
                }
            });
        },

        label(index) {
            return this.people[index]?.name.trim() || labels.person.replace(':n', num(index + 1));
        },

        add() {
            if (this.people.length < 30) {
                this.people.push({ name: '', paid: '' });
                this.$nextTick(() => this.$root.querySelector(`[data-person="${this.people.length - 1}"]`)?.focus());
            }
        },

        remove(index) {
            if (this.people.length > 2) {
                this.people.splice(index, 1);
            }
        },

        reset() {
            this.people = [{ name: '', paid: '' }, { name: '', paid: '' }, { name: '', paid: '' }];
        },

        money(amount) {
            return '৳' + num(amount / 100, { maximumFractionDigits: 2 });
        },

        get result() {
            const paid = this.people.map((person) => poisha(person.paid));
            const total = paid.reduce((sum, amount) => sum + amount, 0);
            const count = this.people.length;
            const base = Math.floor(total / count);
            const leftover = total - base * count;

            const balances = paid.map((amount, index) => ({
                index,
                name: this.label(index),
                paid: amount,
                share: base + (index < leftover ? 1 : 0),
            })).map((person) => ({ ...person, balance: person.paid - person.share }));

            // Fewest payments: the biggest debt pays the biggest credit, again and again.
            const owe = balances.filter((person) => person.balance < 0).map((person) => ({ ...person, left: -person.balance }));
            const get = balances.filter((person) => person.balance > 0).map((person) => ({ ...person, left: person.balance }));
            const payments = [];

            while (owe.length && get.length) {
                owe.sort((a, b) => b.left - a.left);
                get.sort((a, b) => b.left - a.left);

                const amount = Math.min(owe[0].left, get[0].left);
                payments.push({ from: owe[0].name, to: get[0].name, amount });
                owe[0].left -= amount;
                get[0].left -= amount;

                if (owe[0].left === 0) owe.shift();
                if (get[0].left === 0) get.shift();
            }

            return { total, share: count ? total / count : 0, balances, payments };
        },

        /** A plain-text summary to paste into the group chat. */
        summary() {
            const { total, share, payments } = this.result;
            const lines = [
                labels.heading,
                labels.totals.replace(':total', this.money(total)).replace(':share', this.money(Math.round(share))),
                '',
                ...(payments.length
                    ? payments.map((payment) => `${payment.from} → ${payment.to}: ${this.money(payment.amount)}`)
                    : [labels.even]),
                '',
                labels.link,
            ];

            return lines.join('\n');
        },

        async copy() {
            try {
                await navigator.clipboard.writeText(this.summary());
            } catch (error) {
                const area = document.createElement('textarea');
                area.value = this.summary();
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                area.remove();
            }

            this.copied = true;
            window.Sofor?.toast(labels.copied, 'success');
            setTimeout(() => (this.copied = false), 2200);
        },
    };
}
