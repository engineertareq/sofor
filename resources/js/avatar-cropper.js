import { t, num } from './i18n';

/**
 * The profile photo cropper (profile/partials/details). Picking a photo opens
 * it first, so the traveller places the square themselves instead of the
 * server cutting the centre (which cut heads off tall photos). "Adjust" opens
 * it again on the whole photo kept from the upload, at the square they chose.
 *
 * The square is sent as fractions of the photo, the way AvatarService stores
 * it: x and y are its left and top edges over the width and height, size its
 * side over the photo's short side. Everything here works in the photo's own
 * pixels (left, top, side); only drawing turns them into screen pixels.
 *
 * @param {{ maxKilobytes: number, maxZoom: number, original: string|null, saved: {x: number, y: number, size: number}|null }} options
 */
export default function avatarCropper({ maxKilobytes, maxZoom = 5, original = null, saved = null }) {
    const MIN_EDGE = 64;

    return {
        open: false,
        mode: 'upload',
        src: null,
        ready: false,
        error: '',
        failed: '',
        saving: false,
        dragging: false,
        nw: 0,
        nh: 0,
        left: 0,
        top: 0,
        side: 1,
        stageSize: 0,
        pointers: new Map(),
        pinch: null,

        init() {
            this.$watch('open', (open) => {
                document.body.classList.toggle('overflow-hidden', open);

                // Closed without saving: forget the photo, so picking the same file again opens it again.
                if (!open && !this.saving) {
                    this.reset();
                    this.$refs.file.value = '';
                }
            });
        },

        /** A photo was chosen: check it, then open the cropper on it. */
        pick(event) {
            const file = event.target.files[0];
            this.error = '';

            if (!file) {
                return;
            }

            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                return this.refuse(t('Use a JPG, PNG or WebP photo.'));
            }

            if (file.size > maxKilobytes * 1024) {
                return this.refuse(t('Keep the photo under :size MB.', { size: num(Math.round(maxKilobytes / 1024)) }));
            }

            this.show('upload', URL.createObjectURL(file));
        },

        /** Open on the photo already uploaded, at the square chosen last time. */
        adjust() {
            if (original) {
                // A fresh address each time, so the photo loads (and reports its size) again.
                this.error = '';
                this.show('adjust', `${original}${original.includes('?') ? '&' : '?'}v=${Date.now()}`);
            }
        },

        show(mode, src) {
            this.reset();
            this.mode = mode;
            this.src = src;
            this.open = true;
        },

        refuse(message) {
            this.error = message;
            this.$refs.file.value = '';
        },

        reset() {
            if (this.src?.startsWith('blob:')) {
                URL.revokeObjectURL(this.src);
            }

            Object.assign(this, { src: null, ready: false, failed: '', nw: 0, nh: 0, dragging: false, pinch: null });
            this.pointers.clear();
        },

        /** The photo has loaded: its size is known (turned upright already), so place the square. */
        loaded(event) {
            this.nw = event.target.naturalWidth;
            this.nh = event.target.naturalHeight;
            const short = Math.min(this.nw, this.nh);

            if (this.mode === 'upload' && short < MIN_EDGE) {
                this.open = false;

                return this.refuse(t('That photo is too small. Use one at least 64 pixels across.'));
            }

            if (this.mode === 'adjust' && saved) {
                this.side = short * saved.size;
                this.left = saved.x * this.nw;
                this.top = saved.y * this.nh;
            } else {
                // As big as fits, centred across; on a tall photo, higher than
                // the middle, where heads usually are.
                this.side = short;
                this.left = (this.nw - short) / 2;
                this.top = (this.nh - short) * (this.nh > this.nw ? 0.25 : 0.5);
            }

            this.clamp();
            this.ready = true;
        },

        broken() {
            this.failed = t('Your photo could not be loaded. Check your connection and try again.');
        },

        /** The stage is sized by CSS; follow it (the sheet animates in, phones turn). */
        observe(stage) {
            new ResizeObserver(() => {
                this.stageSize = stage.clientWidth;
            }).observe(stage);
        },

        /* ---------------------------------------------------------------
           Geometry. The frame is the square that will be saved, inset in
           the stage so a little of the photo around it stays in view.
        --------------------------------------------------------------- */

        get inset() {
            return Math.round(this.stageSize * 0.09);
        },

        get frame() {
            return this.stageSize - this.inset * 2;
        },

        /** Screen pixels per photo pixel. */
        get scale() {
            return this.frame / Math.max(this.side, 1);
        },

        get zoom() {
            return Math.min(this.nw, this.nh) / Math.max(this.side, 1);
        },

        set zoom(level) {
            this.zoomTo(Number(level), this.stageSize / 2, this.stageSize / 2);
        },

        /** The photo, placed so the square sits in the frame. */
        imageStyle() {
            return this.place(this.scale, this.inset);
        },

        /** The same picture, drawn at a preview's size. */
        previewStyle(size) {
            return this.place(size / Math.max(this.side, 1), 0);
        },

        place(s, offset) {
            const x = (offset - this.left * s).toFixed(2);
            const y = (offset - this.top * s).toFixed(2);

            return `width:${this.nw}px;height:${this.nh}px;transform:translate3d(${x}px,${y}px,0) scale(${s.toFixed(5)})`;
        },

        /** The square as the server takes it. */
        get chosen() {
            const short = Math.min(this.nw, this.nh) || 1;

            return {
                x: (this.left / (this.nw || 1)).toFixed(5),
                y: (this.top / (this.nh || 1)).toFixed(5),
                size: Math.min(1, this.side / short).toFixed(5),
            };
        },

        /** Keep the square inside the photo, and no smaller than the zoom allows. */
        clamp() {
            const short = Math.min(this.nw, this.nh);
            this.side = Math.min(short, Math.max(short / maxZoom, this.side));
            this.left = Math.min(this.nw - this.side, Math.max(0, this.left));
            this.top = Math.min(this.nh - this.side, Math.max(0, this.top));
        },

        /** Zoom to a level, keeping the bit of photo under (qx, qy) on the stage where it is. */
        zoomTo(level, qx, qy) {
            const short = Math.min(this.nw, this.nh);

            if (!short || this.frame <= 0) {
                return;
            }

            const px = this.left + (qx - this.inset) / this.scale;
            const py = this.top + (qy - this.inset) / this.scale;
            this.side = short / Math.min(maxZoom, Math.max(1, level));
            this.left = px - (qx - this.inset) / this.scale;
            this.top = py - (qy - this.inset) / this.scale;
            this.clamp();
        },

        zoomBy(factor, qx = this.stageSize / 2, qy = this.stageSize / 2) {
            this.zoomTo(this.zoom * factor, qx, qy);
        },

        /** Move the photo by screen pixels: it follows the finger. */
        pan(dx, dy) {
            this.left -= dx / this.scale;
            this.top -= dy / this.scale;
            this.clamp();
        },

        recentre() {
            const short = Math.min(this.nw, this.nh);
            this.side = short;
            this.left = (this.nw - short) / 2;
            this.top = (this.nh - short) / 2;
        },

        /* ---------------------------------------------------------------
           Drag with one pointer, pinch with two, wheel or keys to zoom.
        --------------------------------------------------------------- */

        local(event) {
            const box = this.$refs.stage.getBoundingClientRect();

            return [event.clientX - box.left, event.clientY - box.top];
        },

        down(event) {
            if (!this.ready) {
                return;
            }

            this.$refs.stage.setPointerCapture?.(event.pointerId);
            this.pointers.set(event.pointerId, this.local(event));
            this.dragging = true;
            this.pinch = null;
        },

        move(event) {
            const last = this.pointers.get(event.pointerId);

            if (!last) {
                return;
            }

            const now = this.local(event);
            this.pointers.set(event.pointerId, now);

            if (this.pointers.size === 1) {
                this.pan(now[0] - last[0], now[1] - last[1]);

                return;
            }

            // Two fingers: zoom by how far apart they are, around the point between them.
            const [a, b] = [...this.pointers.values()];
            const gap = Math.hypot(a[0] - b[0], a[1] - b[1]);
            const mid = [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2];

            if (this.pinch) {
                this.pan(mid[0] - this.pinch.mid[0], mid[1] - this.pinch.mid[1]);
                this.zoomBy(gap / Math.max(this.pinch.gap, 1), mid[0], mid[1]);
            }

            this.pinch = { gap, mid };
        },

        up(event) {
            this.pointers.delete(event.pointerId);
            this.pinch = null;
            this.dragging = this.pointers.size > 0;
        },

        wheel(event) {
            if (!this.ready) {
                return;
            }

            const [qx, qy] = this.local(event);
            // Trackpad pinches arrive as small ctrl+wheel steps; mouse wheels as big ones.
            this.zoomBy(Math.exp(-event.deltaY * (event.ctrlKey ? 0.01 : 0.0015)), qx, qy);
        },

        /** Arrows move the photo their way (shift for bigger steps); + and - zoom. */
        key(event) {
            const step = event.shiftKey ? 40 : 8;
            const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };

            if (moves[event.key]) {
                this.pan(...moves[event.key]);
            } else if (['+', '='].includes(event.key)) {
                this.zoomBy(1.15);
            } else if (['-', '_'].includes(event.key)) {
                this.zoomBy(1 / 1.15);
            } else {
                return;
            }

            event.preventDefault();
        },

        /* ---------------------------------------------------------------
           Saving: the square goes into the form for the mode, which posts
           it (with the file, for a new photo).
        --------------------------------------------------------------- */

        save() {
            if (!this.ready || this.saving) {
                return;
            }

            this.saving = true;
            const form = this.mode === 'upload' ? this.$refs.upload : this.$refs.recrop;

            for (const [field, value] of Object.entries(this.chosen)) {
                form.querySelector(`[name="crop_${field}"]`).value = value;
            }

            form.submit();
        },
    };
}
