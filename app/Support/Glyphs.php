<?php

namespace App\Support;

/**
 * Sofor's own picture icons: small two-tone stickers in the brand palette
 * (teal, marigold, coral, sand) with the ink outline the cards use. They
 * stand in for emoji, which every phone draws differently.
 *
 * Drawn on a 40x40 grid. Shapes inherit the ink stroke from the wrapper;
 * detail lines set fill="none".
 */
final class Glyphs
{
    private const INK = '#17211f';

    /** The pictures a trip can wear, in picker order. */
    public const TRIP = ['compass', 'beach', 'hills', 'tea', 'peak', 'roadtrip', 'backpack', 'sea', 'camp', 'mosque', 'food', 'camera', 'boat', 'forest', 'city', 'plane'];

    /** Emoji the trip picker used to offer, and what each one is now. */
    private const FROM_EMOJI = [
        '🧭' => 'compass', '🏖️' => 'beach', '🏖' => 'beach', '⛰️' => 'hills', '⛰' => 'hills', '🍃' => 'tea',
        '🏔️' => 'peak', '🏔' => 'peak', '🚐' => 'roadtrip', '🎒' => 'backpack', '🌊' => 'sea', '🏕️' => 'camp',
        '🏕' => 'camp', '🕌' => 'mosque', '🍢' => 'food', '📸' => 'camera', '⛵' => 'boat', '🚤' => 'boat',
        '🌲' => 'forest', '🌳' => 'forest', '🏙️' => 'city', '🏙' => 'city', '✈️' => 'plane', '✈' => 'plane',
    ];

    private const SHAPES = [
        // ---- Trips -------------------------------------------------------
        'compass' => '<circle cx="20" cy="20" r="15.5" fill="#fdf1d7"/><circle cx="20" cy="20" r="11" fill="#ffffff"/>'
            .'<path d="M20 2.8v3M20 34.2v3M2.8 20h3M34.2 20h3" fill="none"/>'
            .'<g transform="rotate(32 20 20)"><path d="M20 9.6 23.4 20h-6.8Z" fill="#ef7b5a"/><path d="M20 30.4 16.6 20h6.8Z" fill="#0f6f6a"/></g>'
            .'<circle cx="20" cy="20" r="1.9" fill="#17211f" stroke="none"/>',

        'beach' => '<circle cx="31" cy="9" r="4.4" fill="#f2a71b"/>'
            .'<path d="M2.8 31.5c4.6-3.2 10.4-4.6 17.2-4.6s12.6 1.4 17.2 4.6v4.7H2.8Z" fill="#f4dcae"/>'
            .'<path d="M17.4 14.6 21.6 29.2" fill="none"/>'
            .'<path d="M5 18.8C6.2 11 13.3 5.7 21 7c4.1.7 7.4 2.9 9.4 6.2-2.3-.5-4.5.4-5.7 2-1.8-1.5-4.3-2-6.6-1.1-1.8-1.6-4.6-2-6.8-.8-1.9-1.4-4.4-1.6-6.3-.5Z" fill="#ef7b5a"/>'
            .'<path d="M18.4 13.1c-.5-2.7.1-5 1.5-6.2M11.5 14.6c-.2-2.8 1-5.2 3-6.5" fill="none"/>'
            .'<path d="M4.5 35.6c2.1-1.3 4.2-1.3 6.3 0" fill="none" stroke="#0f6f6a"/>',

        'hills' => '<path d="M2.8 34.5 13.5 17l6.2 8.4 5.6-7.2 11.9 16.3Z" fill="#7cc4b6"/>'
            .'<path d="M2.8 34.5 11 25.6l5 4.4 7.6-8.3 13.6 12.8Z" fill="#0f6f6a"/>'
            .'<path d="M8.2 16.6a3.6 3.6 0 0 1 .6-7.1 5.4 5.4 0 0 1 10.1-1.4 3.9 3.9 0 0 1 5.6 3.6 2.9 2.9 0 0 1-.4 5Z" fill="#ffffff"/>'
            .'<circle cx="31.5" cy="8.8" r="3.4" fill="#f2a71b"/>',

        'tea' => '<path d="M20 36.5c-.3-6.4.4-12.6 2.6-18.6" fill="none"/>'
            .'<path d="M21.5 22.3C13.6 24.6 6.6 20.4 5.2 11.4c8.7-1.9 15.4 2.6 16.3 10.9Z" fill="#4caf6a"/>'
            .'<path d="M21 21.3c-3.7-2.5-8-5.4-12.6-8" fill="none"/>'
            .'<path d="M22.2 18.6c1.3-7.3 7.6-12.1 15-11.6.4 7.8-5.5 13.4-15 11.6Z" fill="#2f8a4e"/>'
            .'<path d="M22.6 17.9c3.2-2.6 6.6-5.4 10.4-7.6" fill="none" stroke="#ffffff" stroke-width="1.3"/>'
            .'<path d="M20.2 28.4c4.5-1.7 9 .2 10.6 4-4.5 1.6-8.7-.1-10.6-4Z" fill="#9bd29a"/>',

        'peak' => '<path d="M2.8 35 16.4 9.4l7 11.6 3.6-4.8 10.2 18.8Z" fill="#8cc4ee"/>'
            .'<path d="M11.4 18.8 16.4 9.4l5 8.3-2.8 2.6-2.2-2.4-2.5 2.6Z" fill="#ffffff"/>'
            .'<path d="M24.8 18.4 27 16.2l3.2 5.9-2.7-1.2-1.6 1.4Z" fill="#ffffff"/>'
            .'<path d="M16.4 9.4 12.2 35" fill="none" stroke-width="1.2"/>',

        'roadtrip' => '<path d="M3.5 27.8V15.6c0-2.5 2-4.5 4.5-4.5h16.6c1.6 0 3.1.8 3.9 2.2l4.3 6.9h1.7c1.8 0 3.3 1.5 3.3 3.3v4.3c0 1-.8 1.8-1.8 1.8H5.3c-1 0-1.8-.8-1.8-1.8Z" fill="#f2a71b"/>'
            .'<path d="M7.5 14.6h6v5.6h-6ZM16.6 14.6h7.4l3.4 5.6H16.6Z" fill="#dcf0ed"/>'
            .'<path d="M3.5 23.6h33.2" fill="none" stroke="#cf8304" stroke-width="1.4"/>'
            .'<circle cx="11" cy="29.6" r="3.6" fill="#17211f"/><circle cx="29" cy="29.6" r="3.6" fill="#17211f"/>'
            .'<circle cx="11" cy="29.6" r="1.2" fill="#ffffff" stroke="none"/><circle cx="29" cy="29.6" r="1.2" fill="#ffffff" stroke="none"/>',

        'backpack' => '<path d="M13.6 9.4c0-3.4 2.8-6.2 6.4-6.2s6.4 2.8 6.4 6.2" fill="none"/>'
            .'<path d="M8.4 18.4c0-6.1 5.2-10.4 11.6-10.4s11.6 4.3 11.6 10.4v14.4c0 2-1.6 3.6-3.6 3.6H12c-2 0-3.6-1.6-3.6-3.6Z" fill="#0f6f6a"/>'
            .'<path d="M8.4 18.4c0-6.1 5.2-10.4 11.6-10.4s11.6 4.3 11.6 10.4c-3.6 1.6-7.4 2.3-11.6 2.3s-8-.7-11.6-2.3Z" fill="#f2a71b"/>'
            .'<rect x="13.4" y="24.4" width="13.2" height="8" rx="2.4" fill="#7cc4b6"/>'
            .'<path d="M20 20.6v3.4" fill="none"/>',

        'sea' => '<path d="M3 23.6c3.2-9.6 15-13.6 22.6-7.6 4 3.2 3.4 9-1.2 9.6-3 .4-4.6-2.2-3.2-4.2-4.6 0-7.2 3.4-6.6 8.2H3Z" fill="#0f6f6a"/>'
            .'<path d="M24.8 18.2c-2.5-1.7-5.6-1.6-7.6.3" fill="none" stroke="#ffffff" stroke-width="1.3"/>'
            .'<path d="M3 29.6h34.2v4.6c0 1.2-1 2.2-2.2 2.2H5.2c-1.2 0-2.2-1-2.2-2.2Z" fill="#8cc4ee"/>'
            .'<path d="M26.8 29.6c1.6-1.8 3.4-2.8 5.6-2.8 2 0 3.6.8 4.8 2.2" fill="none"/>'
            .'<circle cx="31.2" cy="9" r="3.6" fill="#f2a71b"/>',

        'camp' => '<path d="M3 35.8h34" fill="none"/>'
            .'<path d="M5 35.8 18.4 9.6l13.4 26.2Z" fill="#f2a71b"/>'
            .'<path d="M18.4 9.6 14.6 35.8h7.6Z" fill="#cf8304"/>'
            .'<path d="M16.4 35.8 18.4 26l2 9.8Z" fill="#17211f"/>'
            .'<path d="M15.8 9.4 18.4 9.6l2.4-1.8" fill="none"/>'
            .'<path d="M33.2 22.6 29.6 30h7.2Z" fill="#4caf6a"/><path d="M33.2 17.6 30.4 23.6h5.6Z" fill="#4caf6a"/><path d="M33.2 30v5.8" fill="none"/>',

        'mosque' => '<path d="M11.6 19.4c0-5 3.8-8.6 8.4-10.4 4.6 1.8 8.4 5.4 8.4 10.4Z" fill="#0f6f6a"/>'
            .'<path d="M20 9V5.8" fill="none"/><circle cx="20" cy="4.8" r="1.1" fill="#f2a71b"/>'
            .'<path d="M8 19.4h24v16.4H8Z" fill="#f4dcae"/>'
            .'<path d="M16.6 35.8v-6.4a3.4 3.4 0 0 1 6.8 0v6.4Z" fill="#17211f"/>'
            .'<path d="M3.6 35.8V15.4l1.8-3 1.8 3v20.4ZM32.8 35.8V15.4l1.8-3 1.8 3v20.4Z" fill="#ffffff"/>'
            .'<path d="M11.6 23.6v3.2M28.4 23.6v3.2" fill="none"/>',

        'food' => '<path d="M6.4 33.6 33.6 6.4" fill="none" stroke-width="1.8"/>'
            .'<rect x="8.6" y="21.4" width="8" height="8" rx="2.2" transform="rotate(-45 12.6 25.4)" fill="#ef7b5a"/>'
            .'<rect x="14.8" y="15.2" width="8" height="8" rx="3.6" transform="rotate(-45 18.8 19.2)" fill="#4caf6a"/>'
            .'<rect x="21" y="9" width="8" height="8" rx="2.2" transform="rotate(-45 25 13)" fill="#f2a71b"/>'
            .'<path d="M28.2 30.4c1.6-1.6 1.6-3.2 0-4.8s-1.6-3.2 0-4.8M33.4 30.4c1.6-1.6 1.6-3.2 0-4.8" fill="none" stroke="#cf8304" stroke-width="1.3"/>',

        'camera' => '<path d="M3.6 15.4c0-2.2 1.8-4 4-4h4.2l2.4-3.6h11.6l2.4 3.6h4.2c2.2 0 4 1.8 4 4v15.4c0 2.2-1.8 4-4 4H7.6c-2.2 0-4-1.8-4-4Z" fill="#17211f"/>'
            .'<path d="M3.6 18.4h32.8" fill="none" stroke="#f2a71b" stroke-width="2.2"/>'
            .'<circle cx="20" cy="22.4" r="7.6" fill="#ffffff"/><circle cx="20" cy="22.4" r="4.8" fill="#0f6f6a"/>'
            .'<circle cx="18.2" cy="20.6" r="1.4" fill="#ffffff" stroke="none"/>'
            .'<rect x="29" y="12.6" width="4" height="2.4" rx="1" fill="#ef7b5a" stroke="none"/>',

        'boat' => '<path d="M3 26.4h34l-4.4 6.8c-.6 1-1.8 1.6-3 1.6H10.4c-1.2 0-2.4-.6-3-1.6Z" fill="#c9874a"/>'
            .'<path d="M19.6 26.4V4.6" fill="none"/>'
            .'<path d="M19.6 6.2 32.4 24H19.6Z" fill="#ffffff"/><path d="M18 9.4 8 24h10Z" fill="#ef7b5a"/>'
            .'<path d="M3 37c2.6-1.6 5.4-1.6 8 0s5.4 1.6 8 0 5.4-1.6 8 0 5.4 1.6 8 0" fill="none" stroke="#0f6f6a"/>',

        'forest' => '<path d="M3 35.8h34" fill="none"/>'
            .'<path d="M14 4.8 5.6 18.6h4.2L4.4 28.2h19.2l-5.4-9.6h4.2Z" fill="#2f8a4e"/>'
            .'<path d="M14 28.2v7.6" fill="none"/>'
            .'<path d="M28 13.6 21.8 24h3.4l-4 7.4h13.6l-4-7.4h3.4Z" fill="#4caf6a"/>'
            .'<path d="M28 31.4v4.4" fill="none"/>'
            .'<circle cx="32.2" cy="7.6" r="2.8" fill="#f2a71b"/>',

        'city' => '<path d="M3 35.8h34" fill="none"/>'
            .'<path d="M5 35.8V17.4h9.2v18.4Z" fill="#7cc4b6"/>'
            .'<path d="M14.2 35.8V7.2l6.4-3.4 6.4 3.4v28.6Z" fill="#0f6f6a"/>'
            .'<path d="M27 35.8V14.6h8.6v21.2Z" fill="#f4dcae"/>'
            .'<path d="M18 11.8h1.2M21.8 11.8H23M18 16.6h1.2M21.8 16.6H23M18 21.4h1.2M21.8 21.4H23M18 26.2h1.2M21.8 26.2H23" fill="none" stroke="#f2a71b" stroke-width="1.8"/>'
            .'<path d="M8.4 22.4h2.4M8.4 27.4h2.4M30.2 19.6h2.4M30.2 24.6h2.4M30.2 29.6h2.4" fill="none"/>',

        'plane' => '<path d="M5.4 33.8c3.2-3.4 6.6-6 10.4-8" fill="none" stroke="#8cc4ee" stroke-dasharray="1.6 3"/>'
            .'<path d="M33.8 6.2c1.3 1.3 1 3.2-.7 4.9L28.4 15.8l3 14.6-2.8 2.8-6.2-11.6-5.2 5.2.6 4.2-2.2 2.2-2.6-5.2-5.2-2.6 2.2-2.2 4.2.6 5.2-5.2-11.6-6.2 2.8-2.8 14.6 3 4.7-4.7c1.7-1.7 3.6-2 4.9-.7Z" fill="#ffffff"/>'
            .'<path d="M28.4 15.8 31.4 30.4l-2.8 2.8-6.2-11.6ZM24.2 11.6 9.6 8.6 6.8 11.4l11.6 6.2Z" fill="#0f6f6a"/>',

        // ---- Cost categories ---------------------------------------------
        'stay' => '<path d="M4 33V12.6" fill="none"/><path d="M36 33V23" fill="none"/>'
            .'<rect x="5.6" y="16" width="9.4" height="6.4" rx="2.6" fill="#ffffff"/>'
            .'<path d="M4 23.4h32v5.4H4Z" fill="#0f6f6a"/>'
            .'<path d="M16 16.6h15.4c2.5 0 4.6 2.1 4.6 4.6v2.2H16Z" fill="#7cc4b6"/>'
            .'<path d="M3 28.8h34" fill="none"/>',

        'transport' => '<rect x="7" y="4.6" width="26" height="27" rx="5" fill="#f2a71b"/>'
            .'<path d="M10.4 9.6h19.2v9.2H10.4Z" fill="#dcf0ed"/>'
            .'<path d="M7 22.8h26" fill="none" stroke="#cf8304" stroke-width="1.4"/>'
            .'<circle cx="12.4" cy="26.6" r="1.8" fill="#ffffff"/><circle cx="27.6" cy="26.6" r="1.8" fill="#ffffff"/>'
            .'<path d="M10.6 31.6v3.6h4.2v-3.6M25.2 31.6v3.6h4.2v-3.6" fill="#17211f"/>'
            .'<path d="M4.6 12v5M35.4 12v5" fill="none"/>',

        'meal' => '<circle cx="20" cy="21.6" r="13" fill="#ffffff"/><circle cx="20" cy="21.6" r="8.4" fill="#fdf1d7"/>'
            .'<path d="M15.6 21.6c1.6-3 7.2-3 8.8 0-2.2 1.8-6.6 1.8-8.8 0Z" fill="#ef7b5a"/>'
            .'<path d="M4.4 6.4v8.2c0 1.4 1.1 2.4 2.4 2.4h0c1.4 0 2.4-1 2.4-2.4V6.4M6.8 6.4v28.4" fill="none"/>'
            .'<path d="M33.4 34.8V6.4c-2.6 1.4-3.8 5-3.8 9.4 0 2.2 1.4 3.4 3.8 3.4" fill="none"/>',

        'activity' => '<circle cx="18.4" cy="21.6" r="14" fill="#ef7b5a"/><circle cx="18.4" cy="21.6" r="9.6" fill="#ffffff"/>'
            .'<circle cx="18.4" cy="21.6" r="5.2" fill="#0f6f6a"/>'
            .'<path d="M18.4 21.6 33.2 6.8" fill="none" stroke-width="1.8"/>'
            .'<path d="M30.4 4.6 33.2 6.8l2.2 2.8-3.6.4-1.8-1.8Z" fill="#f2a71b"/>',

        'ticket' => '<path d="M4 12.4c0-1.4 1.1-2.4 2.4-2.4h27.2c1.4 0 2.4 1 2.4 2.4v4a3.6 3.6 0 0 0 0 7.2v4c0 1.4-1 2.4-2.4 2.4H6.4c-1.4 0-2.4-1-2.4-2.4v-4a3.6 3.6 0 0 0 0-7.2Z" fill="#f2a71b"/>'
            .'<path d="M26 10.6v18.8" fill="none" stroke-dasharray="2 2.4"/>'
            .'<path d="m15 15.2 1.4 2.8 3.1.4-2.3 2.2.6 3.1-2.8-1.5-2.8 1.5.6-3.1-2.3-2.2 3.1-.4Z" fill="#ffffff" stroke-width="1.2"/>',

        'shopping' => '<path d="M14 14.4v-3a6 6 0 0 1 12 0v3" fill="none"/>'
            .'<path d="M7.6 13.4h24.8l-1.4 19.4c-.1 1.6-1.4 2.8-3 2.8H12c-1.6 0-2.9-1.2-3-2.8Z" fill="#ef7b5a"/>'
            .'<path d="M8.4 25h23.2" fill="none" stroke="#ffffff" stroke-width="1.6"/>'
            .'<circle cx="14" cy="17.6" r="1.3" fill="#17211f" stroke="none"/><circle cx="26" cy="17.6" r="1.3" fill="#17211f" stroke="none"/>',

        'guide' => '<circle cx="16.4" cy="11" r="5" fill="#f4dcae"/>'
            .'<path d="M6 35.6c.6-7.4 4.8-12 10.4-12s9.8 4.6 10.4 12Z" fill="#0f6f6a"/>'
            .'<path d="M28.6 35.6V9" fill="none"/>'
            .'<path d="M28.6 9.4h8.2l-2.4 3.4 2.4 3.4h-8.2Z" fill="#f2a71b"/>'
            .'<path d="M12.2 9.4c1-2.4 3-3.6 5.4-3.2 1.6.2 2.8 1.2 3.4 2.4" fill="none"/>',

        'parcel' => '<path d="M5 13.4 20 6.2l15 7.2v15.8l-15 7.2-15-7.2Z" fill="#f4dcae"/>'
            .'<path d="M5 13.4 20 20.6l15-7.2M20 20.6v15.8" fill="none"/>'
            .'<path d="M12.4 9.8l15 7.2v5.6l-3.6 1.8V18.8l-15-7.2Z" fill="#c9874a"/>',

        // ---- RSVP ---------------------------------------------------------
        'going' => '<circle cx="20" cy="20" r="15" fill="#4caf6a"/><path d="m13.4 20.4 4.6 4.6 8.8-9.6" fill="none" stroke="#ffffff" stroke-width="2.6"/>'
            .'<path d="M31.6 4.2v3.6M29.8 6h3.6" fill="none" stroke="#f2a71b" stroke-width="1.6"/>',

        'maybe' => '<circle cx="20" cy="20" r="15" fill="#f2a71b"/>'
            .'<path d="M15.6 16.2c0-2.6 2-4.4 4.6-4.4s4.4 1.8 4.4 4.2c0 3.6-4.4 3.6-4.4 7.2" fill="none" stroke="#17211f" stroke-width="2.4"/>'
            .'<circle cx="20.2" cy="27.8" r="1.7" fill="#17211f" stroke="none"/>',

        'not-going' => '<circle cx="20" cy="20" r="15" fill="#ef7b5a"/><path d="m14.6 14.6 10.8 10.8M25.4 14.6 14.6 25.4" fill="none" stroke="#ffffff" stroke-width="2.6"/>',

        // ---- Odds and ends ------------------------------------------------
        'link' => '<path d="M17.4 22.6a6 6 0 0 0 8.5 0l5.2-5.2a6 6 0 0 0-8.5-8.5l-1.8 1.8" fill="none" stroke="#0f6f6a" stroke-width="3"/>'
            .'<path d="M22.6 17.4a6 6 0 0 0-8.5 0l-5.2 5.2a6 6 0 0 0 8.5 8.5l1.8-1.8" fill="none" stroke="#f2a71b" stroke-width="3"/>',

        'settled' => '<path d="M20 3.6 24 6.4l4.8-.4 1.8 4.6 4.4 2.2-.8 4.8 2.4 4.2-3.6 3.2-.6 4.8-4.8.8-3 3.8-4.6-1.8-4.6 1.8-3-3.8-4.8-.8-.6-4.8-3.6-3.2 2.4-4.2-.8-4.8 4.4-2.2L11.2 6l4.8.4Z" fill="#4caf6a"/>'
            .'<path d="m14 20.4 4.2 4.2 8-8.6" fill="none" stroke="#ffffff" stroke-width="2.6"/>',

        'chat' => '<path d="M6 8.4c0-1.8 1.4-3.2 3.2-3.2h21.6c1.8 0 3.2 1.4 3.2 3.2v14.4c0 1.8-1.4 3.2-3.2 3.2H17.4l-6.8 6v-6H9.2c-1.8 0-3.2-1.4-3.2-3.2Z" fill="#dcf0ed"/>'
            .'<path d="M12.4 13.4h15.2M12.4 18.2h9.6" fill="none"/>',
    ];

    /** What a trip picture means, for screen readers and tooltips. */
    public static function label(string $name): string
    {
        return __(match ($name) {
            'beach' => 'Beach',
            'hills' => 'Hills and clouds',
            'tea' => 'Tea garden',
            'peak' => 'Snowy peaks',
            'roadtrip' => 'Road trip',
            'backpack' => 'Backpacking',
            'sea' => 'Sea',
            'camp' => 'Camping',
            'mosque' => 'Heritage',
            'food' => 'Food trip',
            'camera' => 'Photo walk',
            'boat' => 'Boat trip',
            'forest' => 'Forest',
            'city' => 'City break',
            'plane' => 'Flight',
            default => 'Anywhere',
        });
    }

    public static function has(string $name): bool
    {
        return isset(self::SHAPES[$name]);
    }

    /** The picture for a stored trip value - an icon name, or an emoji from before icons. */
    public static function forTrip(?string $value): string
    {
        $value = trim((string) $value);

        if (in_array($value, self::TRIP, true)) {
            return $value;
        }

        return self::FROM_EMOJI[$value] ?? self::FROM_EMOJI[$value."\u{FE0F}"] ?? 'compass';
    }

    /** A complete <svg>, for HTML, Alpine x-html and the recap image alike. */
    public static function svg(string $name, string $class = 'size-6', ?string $label = null): string
    {
        $inner = self::SHAPES[$name] ?? self::SHAPES['compass'];
        $a11y = $label !== null
            ? 'role="img" aria-label="'.e($label).'"'
            : 'aria-hidden="true" focusable="false"';

        return '<svg viewBox="0 0 40 40" class="'.e($class).'" '.$a11y.' xmlns="http://www.w3.org/2000/svg">'
            .self::group($inner).'</svg>';
    }

    /** The shapes in the ink-outlined group, to drop into a larger SVG (the recap card). */
    public static function group(string $inner): string
    {
        return '<g fill="none" stroke="'.self::INK.'" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'.$inner.'</g>';
    }

    public static function inner(string $name): string
    {
        return self::group(self::SHAPES[$name] ?? self::SHAPES['compass']);
    }
}
