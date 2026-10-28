<?php

namespace App\Support;

/**
 * Sentences written by the browser (resources/js), sent to the page as
 * window.SoforI18n so the scripts can show them in the reader's language.
 */
final class JsStrings
{
    public const KEYS = [
        // app.js
        'Invite link copied to clipboard',
        'Copied',
        'Use a PNG, JPG or WebP image.',
        'Keep the image under :size MB.',
        'That photo is over :size MB. Try a smaller one.',
        'The image could not be made in this browser. Try a screenshot instead.',
        'Our trip on Sofor',
        'This browser cannot share a location.',
        'Location was not shared. The check-in still works without it.',
        'A trip can hold up to 40 travellers.',
        ':name added from a past trip.',
        // offline.js
        'Editing a cost needs a connection. Try again when you have signal.',
        'No signal - the cost is saved on this phone and will be sent when you are back online.',
        'This browser could not save the cost offline. Keep the details and try again with signal.',
        '":title" could not be saved: :reason. Add it again.',
        '":title" could not be saved. Add it again.',
        'Back online - 1 cost saved offline was added.',
        'Back online - :count costs saved offline were added.',
        '1 cost waiting for signal',
        ':count costs waiting for signal',
        ':text. Tap to send now.',
        'Still no signal. Sofor sends them as soon as there is.',
        '1 cost is still waiting for signal on this phone. Sign out anyway? It will not be sent.',
        ':count costs are still waiting for signal on this phone. Sign out anyway? They will not be sent.',
        // trip-live.js
        'Live',
        'Syncing',
        'Reconnecting',
        // bkash-checkout.js
        'bKash checkout could not be loaded. Check your connection and try again.',
        'The bKash payment was cancelled, so nothing was recorded.',
        'bKash could not start the payment. Please try again in a moment.',
        'Payment received.',
        // travel-map.js
        'Tap a :unit to mark it as visited',
        ':visited of :total :units visited - :percent%',
        ':visited of :total :units',
        'That did not save.',
        'That did not save. Check your connection and try again.',
        'The image could not be made in this browser. Try another browser.',
        'Night',
        'Sky',
        '+:count territory',
        '+:count territories',
        ":name's :map",
        'My :map',
        'Make yours: :url',
        // avatar-cropper.js
        'Use a JPG, PNG or WebP photo.',
        'Keep the photo under :size MB.',
        'That photo is too small. Use one at least 64 pixels across.',
        'Your photo could not be loaded. Check your connection and try again.',
        // map-tips.js
        'Visited',
        'Not yet',
        'Click or tap to mark as visited',
    ];

    /** @return array<string, string> Only the strings that differ from English. */
    public static function dictionary(): array
    {
        if (app()->getLocale() === 'en') {
            return [];
        }

        $out = [];

        foreach (self::KEYS as $key) {
            $value = __($key);
            if ($value !== $key) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
