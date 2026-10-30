import { t, num, locale } from './i18n';
import { changed } from './tab-sync';
/**
 * The travel map page: tap areas on the map or in the list to mark them,
 * accept suggestions from trips, and download the map as a PNG.
 *
 * Signed in, everything is saved straight away; the server's answer is the
 * source of truth, so a failed save puts the map back the way it was.
 *
 * The public map maker (`local: true`) keeps the marks in this browser
 * only, so anyone can make a map and download it without an account. A
 * traveller who signs up later is offered those places on their own map.
 */

const draftKey = (mapKey) => `sofor.mapmaker.${mapKey}`;
const NAME_KEY = 'sofor.mapmaker.name';

const readDraft = (mapKey) => {
    try {
        const ids = JSON.parse(localStorage.getItem(draftKey(mapKey)) ?? '[]');

        return Array.isArray(ids) ? ids.filter((id) => typeof id === 'string') : [];
    } catch (error) {
        return [];
    }
};

const writeDraft = (mapKey, ids) => {
    try {
        if (ids.length > 0) {
            localStorage.setItem(draftKey(mapKey), JSON.stringify(ids));
        } else {
            localStorage.removeItem(draftKey(mapKey));
        }
    } catch (error) {
        // Private browsing: the map works for this visit only.
    }
};

/** Colour themes for the downloaded image. */
export const MAP_THEMES = {
    sofor: { label: 'Sofor', background: '#fbf8f2', land: '#e6dfd0', visited: '#0f6f6a', border: '#fbf8f2', text: '#17211f', muted: '#6b7470' },
    night: { label: t('Night'), background: '#10201e', land: '#26413d', visited: '#f2a71b', border: '#10201e', text: '#ffffff', muted: '#9fb3ae' },
    sky: { label: t('Sky'), background: '#ffffff', land: '#e3e9f2', visited: '#2f7fd0', border: '#ffffff', text: '#16223a', muted: '#6b7a90' },
};

const FONT = "'Helvetica Neue', Helvetica, Arial, sans-serif";

const normalize = (text) => String(text ?? '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/'/g, '')
    .toLowerCase();

const escapeXml = (text) => String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export default (config) => ({
    map: config.map,
    areas: config.areas,
    visited: Object.fromEntries(config.visited.map((id) => [id, true])),
    suggestions: config.suggestions,
    dismissed: [],
    hovered: null,
    query: '',
    busy: false,
    theme: 'sofor',
    themes: MAP_THEMES,
    exporting: false,
    canShare: false,
    local: config.local === true,
    travellerName: '',
    draft: [],

    init() {
        this.byId = Object.fromEntries(this.areas.map((area) => [area.id, area]));

        if (this.local) {
            // The map maker: this browser is the only place the marks live.
            this.visited = Object.fromEntries(readDraft(this.map.key).filter((id) => this.byId[id]).map((id) => [id, true]));
            Object.keys(this.map.tabs).forEach((key) => {
                if (key !== this.map.key) {
                    this.map.tabs[key] = readDraft(key).length;
                }
            });

            try {
                this.travellerName = localStorage.getItem(NAME_KEY) ?? '';
            } catch (error) {
                this.travellerName = '';
            }

            this.$watch('travellerName', (name) => {
                try {
                    localStorage.setItem(NAME_KEY, name.slice(0, 40));
                } catch (error) {
                    // Nothing to keep it in; the name still works for this visit.
                }
            });
        } else {
            // Places marked on the map maker before signing in, not on this map yet.
            this.draft = readDraft(this.map.key).filter((id) => this.byId[id]);
        }
        this.searchText = Object.fromEntries(this.areas.map((area) => [
            area.id,
            normalize([area.name, ...(area.aliases ?? [])].join(' ')),
        ]));

        try {
            this.dismissed = JSON.parse(localStorage.getItem(this.dismissKey()) ?? '[]');
        } catch (error) {
            this.dismissed = [];
        }

        try {
            const probe = new File([''], 'probe.png', { type: 'image/png' });
            this.canShare = typeof navigator.canShare === 'function' && navigator.canShare({ files: [probe] });
        } catch (error) {
            this.canShare = false;
        }

        // Keep the SVG's classes in step with what is visited.
        this.$watch('visited', () => this.paint());
        this.paint();

        // The same map open in another tab: show its marks here too, so neither overwrites the other.
        window.addEventListener('storage', (event) => this.restore(event));
    },

    /**
     * Another tab changed what this browser keeps for the map maker. Marks are
     * only written on a tap, so taking them here never writes them back.
     */
    restore({ key, newValue }) {
        if (key === draftKey(this.map.key)) {
            const ids = readDraft(this.map.key).filter((id) => this.byId[id]);

            if (this.local) {
                this.visited = Object.fromEntries(ids.map((id) => [id, true]));
            } else {
                this.draft = ids;
            }
        } else if (this.local && key !== NAME_KEY && key?.startsWith(draftKey(''))) {
            const tab = key.slice(draftKey('').length);

            if (tab in this.map.tabs) {
                this.map.tabs[tab] = readDraft(tab).length;
            }
        } else if (key === this.dismissKey()) {
            try {
                this.dismissed = JSON.parse(newValue ?? '[]');
            } catch (error) {
                this.dismissed = [];
            }
        }
    },

    // ------------------------------------------------------------ state

    isVisited(id) {
        return this.visited[id] === true;
    },

    /** The count on a map's tab: live for this map, kept in step for the world map. */
    tabVisited(key) {
        return key === this.map.key ? this.stats.visited : (this.map.tabs[key] ?? 0);
    },

    get stats() {
        const ids = Object.keys(this.visited).filter((id) => this.byId[id]);
        const counted = ids.filter((id) => this.byId[id].counted !== false);
        const groups = new Set(ids.map((id) => this.byId[id].group));

        return {
            visited: counted.length,
            total: this.map.total,
            percent: this.map.total ? Math.floor((counted.length * 100) / this.map.total) : 0,
            extra: ids.length - counted.length,
            groupsVisited: groups.size,
            groupsTotal: Object.keys(this.map.groups).length,
        };
    },

    get caption() {
        if (!this.hovered) {
            return t('Tap a :unit to mark it as visited', { unit: this.map.unit });
        }

        const area = this.byId[this.hovered];
        const group = this.map.groups[area.group];

        return `${area.name}${group && group !== area.name ? ` - ${group}` : ''}${this.isVisited(area.id) ? ' - visited' : ''}`;
    },

    /** The list, grouped like the map (divisions, continents) and filtered by the search. */
    get groupedAreas() {
        const query = normalize(this.query.trim());

        return Object.entries(this.map.groups)
            .map(([code, name]) => {
                const all = this.areas.filter((area) => area.group === code);

                return {
                    code,
                    name,
                    visited: all.filter((area) => this.isVisited(area.id)).length,
                    total: all.length,
                    areas: query ? all.filter((area) => this.searchText[area.id].includes(query)) : all,
                };
            })
            .filter((group) => group.areas.length > 0);
    },

    /** Map maker places that could join this traveller's own map. */
    get pendingDraft() {
        return this.draft.filter((id) => !this.isVisited(id));
    },

    async addDraft() {
        await this.addMany(this.pendingDraft);
        writeDraft(this.map.key, []);
        this.draft = [];
    },

    forgetDraft() {
        writeDraft(this.map.key, []);
        this.draft = [];
    },

    /** Map maker: empty this map in this browser. */
    clearLocal(question) {
        if (Object.keys(this.visited).length === 0 || !window.confirm(question)) {
            return;
        }

        this.visited = {};
        writeDraft(this.map.key, []);
    },

    get visibleSuggestions() {
        return this.suggestions.filter((s) => !this.isVisited(s.id) && !this.dismissed.includes(s.id));
    },

    // ------------------------------------------------------------ map

    paint() {
        const visited = this.visited;

        this.$refs.svg?.querySelectorAll('[data-area]').forEach((element) => {
            element.classList.toggle('is-visited', visited[element.dataset.area] === true);
        });
    },

    pick(event) {
        const id = event.target.closest('[data-area]')?.dataset.area;

        if (id) {
            this.hovered = id;
            this.toggle(id);
        }
    },

    hover(event) {
        this.hovered = event.target.closest('[data-area]')?.dataset.area ?? this.hovered;
    },

    // ------------------------------------------------------------ saving

    async toggle(id) {
        if (this.local) {
            if (this.isVisited(id)) {
                delete this.visited[id];
            } else {
                this.visited[id] = true;
            }

            writeDraft(this.map.key, Object.keys(this.visited));

            return;
        }

        if (this.isVisited(id)) {
            await this.save(() => delete this.visited[id], 'DELETE', this.map.urls.area.replace('AREAID', encodeURIComponent(id)));

            return;
        }

        await this.save(() => (this.visited[id] = true), 'POST', this.map.urls.store, { areas: [id] }, true);
    },

    /** Add several at once, crediting the trip they came from. */
    async addMany(ids, trip = null) {
        const fresh = ids.filter((id) => !this.isVisited(id));

        if (fresh.length === 0) {
            return;
        }

        if (this.local) {
            fresh.forEach((id) => (this.visited[id] = true));
            writeDraft(this.map.key, Object.keys(this.visited));

            return;
        }

        await this.save(() => fresh.forEach((id) => (this.visited[id] = true)), 'POST', this.map.urls.store, { areas: fresh, trip }, true);
    },

    async addSuggestion(suggestion) {
        await this.addMany([suggestion.id], suggestion.trips[0]?.id ?? null);
    },

    async addAllSuggestions() {
        // One request per trip, so every visit is credited to where it came from.
        const byTrip = new Map();

        this.visibleSuggestions.forEach((suggestion) => {
            const trip = suggestion.trips[0]?.id ?? null;
            byTrip.set(trip, [...(byTrip.get(trip) ?? []), suggestion.id]);
        });

        for (const [trip, ids] of byTrip) {
            await this.addMany(ids, trip);
        }
    },

    dismiss(suggestion) {
        this.dismissed = [...this.dismissed, suggestion.id];

        try {
            localStorage.setItem(this.dismissKey(), JSON.stringify(this.dismissed));
        } catch (error) {
            // Private browsing: the suggestion simply comes back next visit.
        }
    },

    dismissKey() {
        return `sofor.travel.dismissed.${this.map.key}`;
    },

    /**
     * Apply the change on screen first, then save it. The server answers with
     * the full visited list, which replaces whatever the page assumed.
     */
    async save(apply, method, url, body = null, announce = false) {
        const before = { ...this.visited };
        apply();
        this.busy = true;

        try {
            const response = await fetch(url, {
                method,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body ? JSON.stringify(body) : undefined,
            });

            if (response.status === 401 || response.status === 419) {
                window.location.reload();

                return;
            }

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(data.message || t('That did not save.'));
            }

            this.visited = Object.fromEntries(data.visited.map((id) => [id, true]));
            changed();

            // A first district also put its country on the world map.
            if (data.country && this.map.worldKey in this.map.tabs) {
                this.map.tabs[this.map.worldKey] += 1;
            }

            // Only worth a toast when something happened beyond the tap itself.
            if (announce && (data.country || (body?.areas?.length ?? 0) > 1)) {
                window.Sofor?.toast(data.message, 'success');
            }
        } catch (error) {
            this.visited = before;
            window.Sofor?.toast(error.message || t('That did not save. Check your connection and try again.'), 'error');
        } finally {
            this.busy = false;
        }
    },

    // ------------------------------------------------------------ image

    async download() {
        const blob = await this.render();

        if (!blob) {
            return;
        }

        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = this.fileName();
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 2000);
    },

    async share() {
        const blob = await this.render();

        if (!blob) {
            return;
        }

        try {
            await navigator.share({
                files: [new File([blob], this.fileName(), { type: 'image/png' })],
                title: this.title(),
                text: this.subtitle(),
            });
        } catch (error) {
            if (error?.name !== 'AbortError') {
                this.download();
            }
        }
    },

    fileName() {
        return `sofor-${this.map.key}-travel-map.png`;
    },

    title() {
        const first = String((this.local ? this.travellerName : this.map.traveller) || '').trim().split(/\s+/)[0];

        return first ? t(":name's :map", { name: first, map: this.map.label }) : t('My :map', { map: this.map.label });
    },

    subtitle() {
        const s = this.stats;

        return t(':visited of :total :units visited - :percent%', { visited: num(s.visited), total: num(s.total), units: s.total === 1 ? this.map.unit : this.map.units, percent: num(s.percent) });
    },

    /** Draw the map, title and counts as an SVG, then rasterise it at 2x. */
    async render() {
        this.exporting = true;

        try {
            const theme = MAP_THEMES[this.theme];
            const s = this.stats;
            const width = 1200;
            const pad = 64;
            const header = 176;
            const footer = 96;

            // Tall maps (Bangladesh) are capped so the image stays phone shaped.
            let scale = (width - pad * 2) / this.map.width;
            scale = Math.min(scale, 1100 / this.map.height);
            const mapWidth = this.map.width * scale;
            const mapHeight = this.map.height * scale;
            const height = Math.round(pad + header + mapHeight + footer + pad);
            const mapX = (width - mapWidth) / 2;
            const mapY = pad + header;

            const shapes = [...this.$refs.svg.querySelectorAll('path[data-area]')]
                .map((path) => `<path d="${path.getAttribute('d')}" fill="${this.isVisited(path.dataset.area) ? theme.visited : theme.land}"/>`)
                .join('');

            const dots = [...this.$refs.svg.querySelectorAll('circle[data-area]')]
                .filter((dot) => this.isVisited(dot.dataset.area))
                .map((dot) => `<circle cx="${dot.getAttribute('cx')}" cy="${dot.getAttribute('cy')}" r="${7 / scale}" fill="${theme.visited}" stroke="${theme.background}" stroke-width="${2 / scale}"/>`)
                .join('');

            const groups = t(':visited of :total :units', { visited: num(s.groupsVisited), total: num(s.groupsTotal), units: this.map.groupUnits })
                + (s.extra > 0 ? `  -  ${t(s.extra === 1 ? '+:count territory' : '+:count territories', { count: num(s.extra) })}` : '');

            const brand = await this.brandMark(theme);
            const date = new Date().toLocaleDateString(locale === 'bn-BD' ? 'bn-BD' : 'en-GB', { day: 'numeric', month: 'long', year: 'numeric' });

            const svg = `<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
                <rect width="100%" height="100%" fill="${theme.background}"/>
                <text x="${pad}" y="${pad + 52}" font-family="${FONT}" font-size="54" font-weight="700" fill="${theme.text}">${escapeXml(this.title())}</text>
                <text x="${pad}" y="${pad + 104}" font-family="${FONT}" font-size="30" font-weight="600" fill="${theme.visited}">${escapeXml(this.subtitle())}</text>
                <text x="${pad}" y="${pad + 146}" font-family="${FONT}" font-size="22" fill="${theme.muted}">${escapeXml(groups)}</text>
                <g transform="translate(${mapX} ${mapY}) scale(${scale})" stroke="${theme.border}" stroke-width="${1.2 / scale}" stroke-linejoin="round">${shapes}</g>
                <g transform="translate(${mapX} ${mapY}) scale(${scale})">${dots}</g>
                ${brand.replace('__Y__', String(height - pad - 34))}
                <text x="${width - pad}" y="${height - pad - 8}" text-anchor="end" font-family="${FONT}" font-size="20" fill="${theme.muted}">${escapeXml(date)}</text>
                <text x="${width / 2}" y="${height - pad - 8}" text-anchor="middle" font-family="${FONT}" font-size="20" font-weight="600" fill="${theme.muted}">${escapeXml(t('Make yours: :url', { url: `${window.location.host}/travel-map` }))}</text>
            </svg>`;

            return await this.rasterise(svg, width, height, 2);
        } catch (error) {
            window.Sofor?.toast(t('The image could not be made in this browser. Try another browser.'), 'error');

            return null;
        } finally {
            this.exporting = false;
        }
    },

    /** The uploaded logo inlined as data, or the Sofor wordmark as text. */
    async brandMark(theme) {
        if (this.map.logoUrl) {
            try {
                const blob = await (await fetch(this.map.logoUrl, { credentials: 'same-origin' })).blob();
                const dataUrl = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = reject;
                    reader.readAsDataURL(blob);
                });

                return `<image x="64" y="__Y__" height="40" width="300" preserveAspectRatio="xMinYMid meet" href="${dataUrl}" xlink:href="${dataUrl}"/>`;
            } catch (error) {
                // Fall through to the wordmark.
            }
        }

        return `<text x="64" y="__Y__" dy="30" font-family="${FONT}" font-size="30" font-weight="700" fill="${theme.visited}">Sofor</text>`;
    },

    rasterise(svg, width, height, ratio) {
        return new Promise((resolve, reject) => {
            const image = new Image();

            image.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = width * ratio;
                canvas.height = height * ratio;
                const context = canvas.getContext('2d');
                context.scale(ratio, ratio);
                context.drawImage(image, 0, 0, width, height);
                canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('empty image'))), 'image/png');
            };

            image.onerror = () => reject(new Error('svg failed to load'));
            image.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
        });
    },
});
