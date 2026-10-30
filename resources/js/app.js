import './bootstrap';
import { t, num, locale } from './i18n';
import '../css/app.css';

import Alpine from 'alpinejs';
import { bootToasts } from './toasts';
import { bootTripLive } from './trip-live';
import travelMap from './travel-map';
import siteNav from './site-nav';
import splitCalculator from './split-calculator';
import tripTimeline from './trip-timeline';
import avatarCropper from './avatar-cropper';
import './map-tips';
import './bkash-checkout';
import { bootOffline, uuid } from './offline';
import { bootTabSync } from './tab-sync';
import { sheets, sheet } from './sheet';
import alerts from './alerts';
// The taka sign (৳) and Bangla digits for the recap card's exported PNG: about 2 KB each.
import takaRegular from '../fonts/taka/taka-400.woff2?inline';
import takaBold from '../fonts/taka/taka-700.woff2?inline';

window.Alpine = Alpine;

/**
 * Tiny Alpine helpers shared by the whole app.
 */
Alpine.data('clipboard', (value, message = t('Invite link copied to clipboard')) => ({
    copied: false,
    async copy() {
        try {
            await navigator.clipboard.writeText(value);
        } catch (error) {
            const area = document.createElement('textarea');
            area.value = value;
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
        }

        this.copied = true;
        window.Sofor?.toast(message, 'success');
        setTimeout(() => (this.copied = false), 2200);
    },
}));

/**
 * Pick an image, check it in the browser and preview it before uploading.
 * The server checks everything again; this only saves a wasted upload.
 */
Alpine.data('imagePicker', (maxKilobytes) => ({
    preview: null,
    error: '',
    saving: false,
    width: 0,
    height: 0,
    pick(event) {
        const file = event.target.files[0];
        this.clear();

        if (!file) {
            return;
        }

        if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
            this.error = t('Use a PNG, JPG or WebP image.');
            event.target.value = '';

            return;
        }

        if (file.size > maxKilobytes * 1024) {
            this.error = t('Keep the image under :size MB.', { size: num(Math.round(maxKilobytes / 1024)) });
            event.target.value = '';

            return;
        }

        this.preview = URL.createObjectURL(file);
    },
    measure(event) {
        this.width = event.target.naturalWidth;
        this.height = event.target.naturalHeight;
    },
    get notSquare() {
        return this.width > 0 && Math.abs(this.width - this.height) / Math.max(this.width, this.height) > 0.1;
    },
    clear() {
        if (this.preview) {
            URL.revokeObjectURL(this.preview);
        }

        this.preview = null;
        this.error = '';
        this.width = 0;
        this.height = 0;
    },
    cancel() {
        this.clear();
        this.$refs.file.value = '';
    },
}));

Alpine.data('travelMap', travelMap);
Alpine.data('siteNav', siteNav);
Alpine.data('splitCalculator', splitCalculator);
Alpine.data('tripTimeline', tripTimeline);
Alpine.data('avatarCropper', avatarCropper);
Alpine.store('sheets', sheets);
Alpine.data('sheet', sheet);
Alpine.data('alerts', alerts);

/**
 * The texted code field (components/code-input): keeps digits only, draws
 * them into the boxes, and on the verify page sends the form once the last
 * digit is in.
 */
Alpine.data('codeInput', (length = 6, autoSubmit = false) => ({
    code: '',
    focused: false,

    typed(event) {
        this.code = event.target.value.replace(/\D/g, '').slice(0, length);
        event.target.value = this.code;

        if (autoSubmit && this.code.length === length) {
            this.$nextTick(() => event.target.form?.requestSubmit());
        }
    },

    slotClass(index) {
        const active = this.focused && (index === this.code.length || (this.code.length === length && index === length - 1));

        if (active) {
            return 'border-brand ring-4 ring-brand/15';
        }

        return index < this.code.length ? 'border-line-strong' : 'border-line';
    },
}));

/** "Resend in 0:42": counts down the wait the server also enforces. */
Alpine.data('cooldown', (seconds = 0) => ({
    left: Math.max(0, Number(seconds) || 0),

    init() {
        const timer = setInterval(() => {
            this.left = Math.max(0, this.left - 1);

            if (this.left === 0) {
                clearInterval(timer);
            }
        }, 1000);
    },

    get label() {
        return `${Math.floor(this.left / 60)}:${String(this.left % 60).padStart(2, '0')}`;
    },
}));

/**
 * Home page hero: once the map is on screen, colour in each district of the
 * route in turn while the dashed line draws itself between them. `stops`
 * counts the districts coloured so far.
 */
Alpine.data('heroJourney', (route) => ({
    stops: 0,

    init() {
        const mark = (index) => {
            this.$root.querySelector(`.hero-map [data-area="${route[index]}"]`)?.classList.add('is-visited');
            this.$root.querySelector(`[data-stop="${index}"]`)?.classList.add('is-on');
            this.stops = index + 1;
        };

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
            route.forEach((_, index) => mark(index));
            this.$root.classList.add('is-drawn');

            return;
        }

        const observer = new IntersectionObserver((entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) {
                return;
            }

            observer.disconnect();
            this.$root.classList.add('is-drawing');
            route.forEach((_, index) => setTimeout(() => mark(index), 500 + index * 650));
        }, { threshold: 0.25 });

        observer.observe(this.$root);
    },
}));

/**
 * The username field: tidies what is typed, checks it is free as you go, and
 * on sign up offers one made from the name until the person types their own.
 */
Alpine.data('usernameField', ({ value = '', url, suggestFrom = null, current = null }) => ({
    username: value,
    touched: value !== '',
    status: null,
    message: '',
    timer: null,
    seq: 0,

    init() {
        if (suggestFrom) {
            document.getElementById(suggestFrom)?.addEventListener('input', (event) => this.suggest(event.target.value));
        }

        if (this.username && this.username !== current) {
            this.check();
        }
    },

    suggest(name) {
        if (this.touched) {
            return;
        }

        this.username = name.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '').slice(0, 25);
        this.check();
    },

    typed() {
        this.username = this.username.toLowerCase().replace(/^@+/, '').replace(/[^a-z0-9_]/g, '').slice(0, 30);
        this.touched = this.username !== '';
        this.check();
    },

    check() {
        clearTimeout(this.timer);
        this.status = null;
        this.message = '';

        if (this.username.length < 3 || this.username === current) {
            return;
        }

        this.timer = setTimeout(() => this.lookup(), 350);
    },

    async lookup() {
        const seq = ++this.seq;

        try {
            const response = await fetch(`${url}?username=${encodeURIComponent(this.username)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await response.json();

            if (seq === this.seq && response.ok) {
                this.status = data.available ? 'ok' : 'bad';
                this.message = data.message;
            }
        } catch (error) {
            // Offline or rate limited: the form still checks on submit.
        }
    },
}));

/**
 * The trip budget field: one amount, per person or for the whole group, and
 * what trips to the same place usually cost on Sofor. The estimate follows
 * the destination, currency and dates typed elsewhere in the same form.
 */
const currencySymbols = { BDT: '৳', USD: '$', EUR: '€', GBP: '£', INR: '₹', AED: 'AED ', MYR: 'RM ', SGD: 'S$', THB: '฿' };

Alpine.data('budgetPlanner', ({ mode = 'person', amount = '', url = null }) => ({
    mode,
    amount,
    estimate: null,
    loading: false,
    timer: null,
    seq: 0,
    form: null,

    init() {
        this.form = this.$root.closest('form');

        if (!url || !this.form) {
            return;
        }

        ['currency', 'starts_on', 'ends_on'].forEach((name) => {
            this.field(name)?.addEventListener('change', () => this.refresh());
        });
        this.field('destination')?.addEventListener('input', () => this.refresh(450));
        this.refresh(0);
    },

    field(name) {
        return this.form?.querySelector(`[name="${name}"]`);
    },

    value(name) {
        return (this.field(name)?.value ?? '').trim();
    },

    currency() {
        return this.value('currency') || 'BDT';
    },

    refresh(delay = 200) {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.load(), delay);
    },

    async load() {
        const destination = this.value('destination');

        if (destination.length < 3) {
            this.estimate = null;
            return;
        }

        const seq = ++this.seq;
        this.loading = true;

        try {
            const params = new URLSearchParams({
                destination,
                currency: this.currency(),
                starts_on: this.value('starts_on'),
                ends_on: this.value('ends_on'),
            });
            const response = await fetch(`${url}?${params}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await response.json();

            if (seq === this.seq && response.ok) {
                this.estimate = data.estimate;
            }
        } catch (error) {
            // The estimate is a nice-to-have; the form works without it.
        } finally {
            if (seq === this.seq) {
                this.loading = false;
            }
        }
    },

    money(value) {
        const symbol = currencySymbols[this.estimate?.currency ?? this.currency()] ?? `${this.currency()} `;

        return symbol + Number(value || 0).toLocaleString(locale, { maximumFractionDigits: 0 });
    },

    use() {
        if (!this.estimate) {
            return;
        }

        this.mode = 'person';
        this.amount = String(this.estimate.per_person);
        this.$nextTick(() => this.$refs.amount?.focus());
    },
}));

/**
 * The "Log a trip cost" sheet: equal, custom or itemised splits, and a photo
 * of the bill. Editing a cost restores exactly how it was split.
 */
const round2 = (value) => Math.round((Number(value) || 0) * 100) / 100;

Alpine.data('expenseSheet', (initial, maxKilobytes = 8192) => ({
    ...JSON.parse(JSON.stringify(initial)),
    items: [],
    existingReceipt: null,
    removeReceipt: false,
    receiptPreview: null,
    receiptName: '',
    // Made on the phone, so a cost sent twice (offline queue, retries) is saved once.
    clientUuid: uuid(),

    reset() {
        const fresh = JSON.parse(JSON.stringify(initial));
        Object.assign(this, {
            editAction: null,
            title: '',
            amount: '',
            notes: '',
            category: fresh.category,
            spentOn: fresh.spentOn,
            payer: fresh.payer,
            splitType: 'equal',
            participants: fresh.participants,
            items: [],
            existingReceipt: null,
            removeReceipt: false,
            clientUuid: uuid(),
        });
        this.clearReceipt();
    },

    add() {
        this.reset();
        this.open = true;
    },

    edit(detail) {
        this.reset();

        const shares = detail.shares || {};
        const shared = Object.keys(shares).length > 0;

        Object.assign(this, {
            editAction: detail.action,
            title: detail.title,
            amount: detail.amount,
            category: detail.category,
            spentOn: detail.spent_on,
            payer: String(detail.payer),
            notes: detail.notes,
            splitType: detail.split_type || 'equal',
            existingReceipt: detail.receipt || null,
        });

        this.participants = this.participants.map((p) => ({
            ...p,
            selected: shared ? String(p.id) in shares : p.selected,
            custom: shares[p.id] !== undefined ? String(shares[p.id]) : '',
        }));

        this.items = (detail.items || []).map((item) => this.newItem(item.name, String(item.price), item.members.map(Number)));
        this.open = true;
    },

    selectedCount() {
        return this.participants.filter((p) => p.selected).length;
    },

    perShare() {
        const units = this.participants.filter((p) => p.selected).reduce((sum, p) => sum + Number(p.units || 1), 0);

        return (Number(this.amount) || 0) / Math.max(1, units);
    },

    /* Itemised bill ---------------------------------------------------- */

    newItem(name = '', price = '', members = null) {
        return {
            key: `${Date.now()}-${Math.random()}`,
            name,
            price,
            members: members ?? this.participants.filter((p) => p.selected).map((p) => p.id),
        };
    },

    useItems() {
        this.splitType = 'itemised';

        if (this.items.length === 0) {
            this.addItem();
        }
    },

    addItem() {
        if (this.items.length >= 40) {
            return;
        }

        this.items.push(this.newItem());
        this.$nextTick(() => {
            const fields = document.querySelectorAll('[data-item-name]');
            fields[fields.length - 1]?.focus();
        });
    },

    removeItem(index) {
        this.items.splice(index, 1);
    },

    toggleItemMember(item, id) {
        const index = item.members.indexOf(id);

        if (index === -1) {
            item.members.push(id);
        } else if (item.members.length > 1) {
            item.members.splice(index, 1);
        }
    },

    itemsSubtotal() {
        return round2(this.items.reduce((sum, item) => sum + (Number(item.price) || 0), 0));
    },

    /** What one traveller ordered, before VAT, service and tips. */
    ordered(id) {
        return this.items.reduce((sum, item) => (item.members.includes(id) && item.members.length
            ? sum + (Number(item.price) || 0) / item.members.length
            : sum), 0);
    },

    /** The bill total minus the items: VAT, service and tips (or a discount when negative). */
    extras() {
        return round2((Number(this.amount) || 0) - this.itemsSubtotal());
    },

    itemShare(id) {
        const subtotal = this.itemsSubtotal();

        return subtotal > 0 ? this.ordered(id) * (Number(this.amount) || subtotal) / subtotal : 0;
    },

    itemPeople() {
        return this.participants.filter((p) => this.items.some((item) => item.members.includes(p.id)));
    },

    useItemsTotal() {
        this.amount = String(this.itemsSubtotal());
    },

    /** "+10% service" adds that share of the items to the bill total. */
    addCharge(percent) {
        const base = Number(this.amount) || this.itemsSubtotal();
        this.amount = String(round2(base + this.itemsSubtotal() * percent / 100));
    },

    /* Receipt photo ---------------------------------------------------- */

    pickReceipt(event) {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        if (file.size > maxKilobytes * 1024) {
            window.Sofor?.toast(t('That photo is over :size MB. Try a smaller one.', { size: num(Math.round(maxKilobytes / 1024)) }), 'warning');
            event.target.value = '';
            return;
        }

        if (this.receiptPreview) {
            URL.revokeObjectURL(this.receiptPreview);
        }

        this.receiptPreview = URL.createObjectURL(file);
        this.receiptName = file.name;
        this.removeReceipt = false;
    },

    clearReceipt() {
        if (this.receiptPreview) {
            URL.revokeObjectURL(this.receiptPreview);
        }

        this.receiptPreview = null;
        this.receiptName = '';

        document.querySelectorAll('[data-receipt-input]').forEach((input) => {
            input.value = '';
        });
    },
}));

/**
 * The trip recap card: the SVG on the page is drawn onto a canvas and saved
 * or shared as a PNG. Everything happens in the browser.
 */
Alpine.data('recapCard', (slug = 'trip') => ({
    busy: false,
    canShare: false,
    fontFace: undefined,

    init() {
        try {
            const probe = new File([''], 'probe.png', { type: 'image/png' });
            this.canShare = !!(navigator.canShare && navigator.canShare({ files: [probe] }));
        } catch (error) {
            this.canShare = false;
        }
    },

    /**
     * An SVG drawn as an image cannot use the page's web fonts, so the taka
     * sign, the Bangla digits and Poppins Bold are embedded in the exported copy. The taka font
     * ships with the app; without a connection the rest falls back to the
     * closest system font.
     */
    async embeddedFont() {
        if (this.fontFace !== undefined) {
            return this.fontFace;
        }

        const taka = `@font-face { font-family: 'Sofor Taka'; font-weight: 100 549; src: url(${takaRegular}) format('woff2'); }`
            + ` @font-face { font-family: 'Sofor Taka'; font-weight: 550 900; src: url(${takaBold}) format('woff2'); }`;

        try {
            const css = await (await fetch('https://fonts.googleapis.com/css2?family=Poppins:wght@700&display=swap')).text();
            // The last block is the basic Latin subset, which covers the card's text.
            const url = [...css.matchAll(/url\((https:[^)]+\.woff2)\)/g)].pop()?.[1];
            const bytes = new Uint8Array(await (await fetch(url)).arrayBuffer());
            let binary = '';
            bytes.forEach((byte) => { binary += String.fromCharCode(byte); });
            this.fontFace = `${taka} @font-face { font-family: 'Poppins'; font-weight: 700; src: url(data:font/woff2;base64,${btoa(binary)}) format('woff2'); }`;
        } catch (error) {
            this.fontFace = taka;
        }

        return this.fontFace;
    },

    async png() {
        const svg = this.$refs.card.querySelector('svg').cloneNode(true);
        const fontFace = await this.embeddedFont();

        if (fontFace) {
            const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
            style.textContent = fontFace;
            svg.insertBefore(style, svg.firstChild);
        }

        const source = new XMLSerializer().serializeToString(svg);
        const url = URL.createObjectURL(new Blob([source], { type: 'image/svg+xml;charset=utf-8' }));

        try {
            const image = new Image();
            image.decoding = 'async';
            await new Promise((resolve, reject) => {
                image.onload = resolve;
                image.onerror = reject;
                image.src = url;
            });

            const canvas = document.createElement('canvas');
            canvas.width = 1080;
            canvas.height = 1350;
            canvas.getContext('2d').drawImage(image, 0, 0, 1080, 1350);

            return await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        } finally {
            URL.revokeObjectURL(url);
        }
    },

    async download() {
        if (this.busy) {
            return;
        }

        this.busy = true;

        try {
            const blob = await this.png();
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `sofor-${slug}-recap.png`;
            link.click();
            setTimeout(() => URL.revokeObjectURL(link.href), 4000);
        } catch (error) {
            window.Sofor?.toast(t('The image could not be made in this browser. Try a screenshot instead.'), 'warning');
        } finally {
            this.busy = false;
        }
    },

    async share() {
        try {
            const blob = await this.png();
            const file = new File([blob], `sofor-${slug}-recap.png`, { type: 'image/png' });
            await navigator.share({ files: [file], title: t('Our trip on Sofor') });
        } catch (error) {
            if (error?.name !== 'AbortError') {
                this.download();
            }
        }
    },
}));

Alpine.start();

/**
 * [data-reveal] blocks ease in as they scroll into view. The hiding CSS only
 * applies once `reveal-ready` is set here, so without JavaScript (or without
 * IntersectionObserver) everything is simply visible.
 */
function bootReveal() {
    const blocks = document.querySelectorAll('[data-reveal]');

    if (blocks.length === 0 || !('IntersectionObserver' in window)) {
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-revealed');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });

    document.documentElement.classList.add('reveal-ready');
    blocks.forEach((block) => observer.observe(block));
}

document.addEventListener('DOMContentLoaded', () => {
    bootToasts();
    bootTabSync();
    bootTripLive();
    bootReveal();
    bootOffline();
});
