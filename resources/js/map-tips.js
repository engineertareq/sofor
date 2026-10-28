import { t } from './i18n';

/**
 * Place names on every travel map. Point at a district or country and a small
 * label shows its name, its division or continent, and a line about it -
 * visited or not, or how many explorers have been. On a touch screen the
 * label pops up above the finger and fades a moment later.
 *
 * Maps opt in with data-map-tips on the <svg> (components/travel-map); each
 * place carries data-name, data-group and optionally data-tip.
 */

const AREA = 'svg[data-map-tips] [data-area]';
const TOUCH_LINGER = 1800;

let tip = null;
let current = null;
let hideTimer = null;
let pointer = { x: 0, y: 0, touch: false };

function element() {
    if (tip) {
        return tip;
    }

    tip = document.createElement('div');
    tip.className = 'map-tip';
    tip.setAttribute('aria-hidden', 'true');
    tip.innerHTML = '<span class="map-tip-name"></span><span class="map-tip-meta"><span class="map-tip-dot"></span><span class="map-tip-text"></span></span>';
    document.body.appendChild(tip);

    return tip;
}

/** The line under the name: the map's own text, else visited or not. */
function describe(area) {
    const svg = area.closest('svg');
    const visited = area.classList.contains('is-visited');
    let detail = area.dataset.tip ?? '';
    let state = '';

    if (!detail && svg?.dataset.mapTips === 'state') {
        state = visited ? 'visited' : 'open';
        detail = visited ? t('Visited') : (svg.hasAttribute('data-map-interactive') ? t('Click or tap to mark as visited') : t('Not yet'));
    }

    const group = area.dataset.group && area.dataset.group !== area.dataset.name ? area.dataset.group : '';

    return { name: area.dataset.name || area.dataset.area, meta: [group, detail].filter(Boolean).join(' · '), state };
}

function fill(area) {
    const box = element();
    const { name, meta, state } = describe(area);

    box.querySelector('.map-tip-name').textContent = name;
    box.querySelector('.map-tip-text').textContent = meta;
    box.querySelector('.map-tip-meta').hidden = meta === '';
    box.dataset.state = state;
    // On a night map visited places glow marigold, so the dot does too.
    box.toggleAttribute('data-night', area.closest('svg')?.classList.contains('tm-night') === true);
}

/** Above the point when there is room, below it otherwise; never off screen. */
function place(x, y, touch) {
    const box = element();
    const { width, height } = box.getBoundingClientRect();
    const gap = touch ? 30 : 16;
    const margin = 8;

    pointer = { x, y, touch };

    let left = Math.min(Math.max(x - width / 2, margin), window.innerWidth - width - margin);
    let top = y - height - gap;
    let below = false;

    if (top < margin) {
        top = y + gap;
        below = true;
    }

    box.dataset.placement = below ? 'below' : 'above';
    box.style.setProperty('--tip-arrow', `${Math.min(Math.max(x - left, 14), width - 14)}px`);
    box.style.transform = `translate(${Math.round(left)}px, ${Math.round(top)}px)`;
}

function show(area, x, y, touch) {
    clearTimeout(hideTimer);

    if (current !== area) {
        current?.classList.remove('is-tipped');
        current = area;
        area.classList.add('is-tipped');
        fill(area);
    }

    element().classList.add('is-shown');
    place(x, y, touch);

    if (touch) {
        hideTimer = setTimeout(hide, TOUCH_LINGER);
    }
}

function hide() {
    clearTimeout(hideTimer);
    current?.classList.remove('is-tipped');
    current = null;
    tip?.classList.remove('is-shown');
}

const areaAt = (event) => (event.target instanceof Element ? event.target.closest(AREA) : null);

// Mouse and pen: follow the pointer.
document.addEventListener('pointermove', (event) => {
    if (event.pointerType === 'touch') {
        return;
    }

    const area = areaAt(event);

    if (area) {
        show(area, event.clientX, event.clientY, false);
    } else if (current) {
        hide();
    }
}, { passive: true });

// Touch: pop up above the finger.
document.addEventListener('pointerdown', (event) => {
    if (event.pointerType !== 'touch') {
        return;
    }

    const area = areaAt(event);

    if (area) {
        show(area, event.clientX, event.clientY, true);
    } else if (current) {
        hide();
    }
}, { passive: true });

// A tap that marks or unmarks a place repaints it a moment later (travel-map.js):
// when its visited class changes, the label follows - and is centred again,
// since "visited" is shorter than "click or tap to mark".
const repaint = new MutationObserver(() => {
    if (current) {
        fill(current);
        place(pointer.x, pointer.y, pointer.touch);
    }
});

document.addEventListener('pointerover', (event) => {
    const area = areaAt(event);

    if (area) {
        repaint.disconnect();
        repaint.observe(area, { attributes: true, attributeFilter: ['class'] });
    }
}, { passive: true });

window.addEventListener('scroll', () => current && hide(), { passive: true });

// The mouse leaving the window. A lifted finger also "leaves", but its label
// stays up for a moment on purpose.
document.addEventListener('pointerleave', (event) => {
    if (event.pointerType !== 'touch') {
        hide();
    }
});
