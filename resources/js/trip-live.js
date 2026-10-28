import { t } from './i18n';
import { toast } from './toasts';

/**
 * Keeps a trip dashboard in sync. The server streams a tiny fingerprint of the
 * trip over server-sent events; when it changes we pull fresh HTML fragments
 * and patch them in, so nobody has to refresh to see a new cost or payment.
 */
export function bootTripLive() {
    const root = document.querySelector('[data-trip-live]');

    if (!root) {
        return;
    }

    const streamUrl = root.dataset.streamUrl;
    const liveUrl = root.dataset.liveUrl;
    const indicator = document.querySelector('[data-live-indicator]');

    let lastStamp = root.dataset.stamp;
    let lastActivityId = Number(root.dataset.activityId || 0);
    let firstLoad = true;
    let refreshing = false;
    let polling = null;
    let source = null;

    const setState = (state) => {
        if (!indicator) {
            return;
        }

        indicator.dataset.state = state;
        const label = indicator.querySelector('[data-live-label]');

        if (label) {
            label.textContent = state === 'live' ? t('Live') : state === 'polling' ? t('Syncing') : t('Reconnecting');
        }
    };

    async function refresh() {
        if (refreshing) {
            return;
        }

        refreshing = true;

        try {
            const response = await fetch(`${liveUrl}?since_activity=${lastActivityId}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            });

            if (response.status === 401 || response.status === 419) {
                // Session expired while watching - go back through the login screen.
                stop();
                window.location.href = '/login';

                return;
            }

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            lastStamp = data.stamp;

            Object.entries(data.html ?? {}).forEach(([key, html]) => {
                const target = document.querySelector(`[data-live="${key}"]`);

                if (target) {
                    target.innerHTML = html;
                }
            });

            if (!firstLoad) {
                (data.events ?? []).slice(0, 3).forEach((event) => {
                    toast(event.description, event.type === 'settlement' ? 'success' : 'info');
                });
            }

            lastActivityId = data.latest_activity_id ?? lastActivityId;
            root.dataset.stamp = lastStamp;
            root.dataset.activityId = lastActivityId;

            window.dispatchEvent(new CustomEvent('sofor:trip-updated', { detail: data.stats }));
        } catch (error) {
            // Offline or the tab was backgrounded - the next tick retries.
        } finally {
            firstLoad = false;
            refreshing = false;
        }
    }

    function startPolling() {
        if (polling) {
            return;
        }

        setState('polling');
        polling = setInterval(refresh, 10000);
    }

    function stop() {
        if (polling) {
            clearInterval(polling);
            polling = null;
        }

        if (source) {
            source.close();
            source = null;
        }
    }

    function connect() {
        if (!('EventSource' in window)) {
            startPolling();

            return;
        }

        try {
            source = new EventSource(streamUrl);
        } catch (error) {
            startPolling();

            return;
        }

        source.addEventListener('ready', () => {
            if (polling) {
                clearInterval(polling);
                polling = null;
            }
            setState('live');
        });

        source.addEventListener('change', () => {
            setState('live');
            refresh();
        });

        source.addEventListener('reconnect', () => {
            // The server closes every ~45s on purpose; EventSource reopens it.
            source.close();
            setTimeout(connect, 250);
        });

        source.addEventListener('poll', () => {
            // Server told us streaming is not available here - poll instead.
            source.close();
            source = null;
            startPolling();
        });

        source.addEventListener('closed', () => {
            source.close();
            source = null;
            startPolling();
        });

        source.addEventListener('error', () => {
            setState('offline');

            if (source && source.readyState === EventSource.CLOSED) {
                startPolling();
                setTimeout(connect, 5000);
            }
        });
    }

    // Refresh immediately after the user's own action so the UI never lags.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refresh();
        }
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            refresh();
        }
    });

    connect();
    setState('polling');

    window.Sofor = window.Sofor || {};
    window.Sofor.refreshTrip = refresh;
}
