import { t } from './i18n';
/**
 * bKash Checkout, browser flow.
 *
 * Merchant accounts on the legacy Checkout API do not get a payment page URL with
 * the payment: bKash's Checkout JS (BKASH_SCRIPT_URL) renders that page in an
 * overlay inside our own page. The script exposes a global `bKash` object built
 * around three calls:
 *
 *   bKash.init({ paymentMode, paymentRequest, createRequest,
 *                executeRequestOnAuthorization, onClose })
 *   bKash.create().onSuccess(<create response>)   // opens the payment overlay
 *   bKash.execute().onSuccess()                   // closes it, payment done
 *
 * The flow starts on a click on an element with id="bKash_button", so `pay()`
 * renders a hidden trigger and clicks it. Sofor's own endpoints stay the source of
 * truth: the browser asks for a payment (step 1) and asks Sofor to execute it once
 * the traveller has authorised it (step 2), and the server recomputes the amount
 * with the MFS charge on every step.
 */

const messages = {
    missingScript: t('bKash checkout could not be loaded. Check your connection and try again.'),
    cancelled: t('The bKash payment was cancelled, so nothing was recorded.'),
    failed: t('bKash could not start the payment. Please try again in a moment.'),
};

function toast(message, tone = 'error') {
    if (window.Sofor?.toast) {
        window.Sofor.toast(message, tone);

        return;
    }

    window.console.warn(message);
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value
        || '';
}

function post(url, payload) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(payload),
    }).then(async (response) => {
        let data = {};

        try {
            data = await response.json();
        } catch (error) {
            data = {};
        }

        if (!response.ok || data.ok === false) {
            throw new Error(data.message || messages.failed);
        }

        return data;
    });
}

let scriptPromise = null;

/** Loads bKash's Checkout JS once per page. */
function loadBkashScript(src) {
    if (window.bKash) {
        return Promise.resolve(window.bKash);
    }

    if (scriptPromise) {
        return scriptPromise;
    }

    scriptPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = () => (window.bKash ? resolve(window.bKash) : reject(new Error(messages.missingScript)));
        script.onerror = () => reject(new Error(messages.missingScript));
        document.head.appendChild(script);
    });

    return scriptPromise;
}

/**
 * The script starts its flow from a click on #bKash_button, so the sheet's button
 * plays that part through a hidden, off screen element.
 */
function ensureTrigger() {
    let trigger = document.getElementById('bKash_button');

    if (!trigger) {
        trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.id = 'bKash_button';
        trigger.setAttribute('aria-hidden', 'true');
        trigger.tabIndex = -1;
        trigger.style.cssText = 'position:fixed;left:-9999px;top:0;width:1px;height:1px;opacity:0;';
        document.body.appendChild(trigger);
    }

    return trigger;
}

/** Close the overlay, tolerating whichever handlers the script exposes. */
function settle(bkash, success) {
    const handle = bkash.execute();

    if (success && typeof handle.onSuccess === 'function') {
        handle.onSuccess();

        return;
    }

    handle.onError?.();
}

/**
 * Start a payment.
 *
 * @param {object}   options
 * @param {string}   options.scriptUrl   bKash's Checkout JS URL.
 * @param {string}   options.createUrl   Sofor endpoint that creates the payment.
 * @param {string}   options.executeUrl  Sofor endpoint that executes it.
 * @param {number}   options.amount      The traveller's own amount, before the MFS charge.
 * @param {object}   [options.payload]   Extra fields for the create call.
 * @param {Function} [options.onStart]   Called once the script is ready.
 * @param {Function} [options.onDone]    Called with the execute response.
 */
async function pay({ scriptUrl, createUrl, executeUrl, amount, payload = {}, onStart, onDone }) {
    let bkash;

    try {
        onStart?.();
        bkash = await loadBkashScript(scriptUrl);
    } catch (error) {
        toast(error.message);

        return false;
    }

    const trigger = ensureTrigger();
    let intent = null;

    bkash.init({
        paymentMode: 'checkout',
        paymentRequest: {
            // Sofor adds the MFS charge server side, so the amount sent here is the
            // traveller's own share and never the charged total.
            amount: Number(amount).toFixed(2),
            intent: 'sale',
        },
        createRequest: function (request) {
            post(createUrl, { amount: request?.amount ?? amount, ...payload })
                .then((data) => {
                    intent = data;
                    // bKash's own samples hand the whole create response back.
                    bkash.create().onSuccess(data);
                })
                .catch((error) => {
                    toast(error.message);
                    bkash.create().onError?.();
                });
        },
        executeRequestOnAuthorization: function () {
            post(executeUrl, { paymentID: intent?.paymentID })
                .then((data) => {
                    settle(bkash, true);
                    toast(data.message || t('Payment received.'), 'success');
                    onDone?.(data);
                })
                .catch((error) => {
                    settle(bkash, false);
                    toast(error.message);
                });
        },
        onClose: function () {
            toast(messages.cancelled, 'warning');
        },
    });

    trigger.click();

    return true;
}

window.SoforBkash = { pay, loadBkashScript };
