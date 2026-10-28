const stack = () => document.getElementById('toast-stack');

const styles = {
    success: 'border-positive/40 text-positive',
    error: 'border-negative/40 text-negative',
    warning: 'border-warning/40 text-warning',
    info: 'border-brand/40 text-brand',
};

export function toast(message, tone = 'info', timeout = 4200) {
    const container = stack();

    if (!container || !message) {
        return;
    }

    const element = document.createElement('div');
    element.className = `pointer-events-auto rise flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm glass shadow-soft ${
        styles[tone] ?? styles.info
    }`;
    element.setAttribute('role', 'status');

    element.innerHTML = `
        <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-current/10">
            <svg viewBox="0 0 20 20" fill="currentColor" class="size-4"><path d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm3.7 6.3-4.2 4.2a1 1 0 0 1-1.4 0L6.3 10.7a1 1 0 1 1 1.4-1.4l1.1 1.1 3.5-3.5a1 1 0 0 1 1.4 1.4Z"/></svg>
        </span>
        <p class="flex-1 leading-snug text-ink"></p>
    `;

    element.querySelector('p').textContent = message;
    container.appendChild(element);

    setTimeout(() => {
        element.style.transition = 'opacity .25s ease, transform .25s ease';
        element.style.opacity = '0';
        element.style.transform = 'translateY(-6px)';
        setTimeout(() => element.remove(), 260);
    }, timeout);
}

export function bootToasts() {
    // Server flashes are rendered as hidden markers and turned into toasts here.
    document.querySelectorAll('[data-toast]').forEach((marker) => {
        toast(marker.dataset.toast, marker.dataset.tone || 'info');
    });

    window.Sofor = window.Sofor || {};
    window.Sofor.toast = toast;
}
