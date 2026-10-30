@props([
    'title' => null,
    'description' => null,
    'noindex' => false,
    'manifest' => true,
    // The one address search engines should credit; defaults to this page without its query string.
    'canonical' => null,
    // Shown when the page is shared; defaults to the Sofor card.
    'image' => null,
    'imageAlt' => null,
    'type' => 'website',
    // JSON-LD structured data: one schema.org object, or a list of them.
    'schema' => null,
    // [[name, url], ...] from the home page down to this one, given to search engines as a BreadcrumbList.
    'breadcrumbs' => [],
])

@php
    use App\Support\Seo;

    $fullTitle = Seo::text($title ? $title.' - Sofor' : __('Sofor - Split trip costs, settle up and map your travels'));
    $description = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($description ?? ''))) ?? '');
    $description = Seo::text($description !== ''
        ? \Illuminate\Support\Str::limit($description, 160, '...')
        : __('Sofor is the free trip app for groups: split costs fairly, settle up over bKash, and colour in every district you have been to.'));

    // One address per language: the canonical names this language's version,
    // and hreflang links name the others (see App\Support\Seo).
    $address = $canonical ?? url()->current();
    $canonical = Seo::url($address, app()->getLocale());
    $image ??= asset('images/og/sofor.png');
    $imageAlt ??= __('Sofor - Travel more. Argue less.');

    if (! $noindex && count($breadcrumbs) > 1) {
        $trail = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $crumb, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb[0],
                'item' => Seo::url($crumb[1], app()->getLocale()),
            ], $breadcrumbs, array_keys($breadcrumbs)),
        ];

        $schema = match (true) {
            $schema === null => ['@context' => 'https://schema.org'] + $trail,
            isset($schema['@graph']) => ['@context' => 'https://schema.org', '@graph' => [...$schema['@graph'], $trail]],
            array_is_list($schema) => ['@context' => 'https://schema.org', '@graph' => [...$schema, $trail]],
            default => ['@context' => 'https://schema.org', '@graph' => [array_diff_key($schema, ['@context' => true]), $trail]],
        };
    }
@endphp

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=5">
    <meta name="theme-color" content="#fbf8f2">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (request()->hasSession() && request()->isMethod('GET'))
        {{-- Who this page was made for, in which language and when, so the other
             open tabs follow a sign in, a sign out or a new language (resources/js/tab-sync.js). --}}
        <meta name="sofor-session" data-user="{{ auth()->id() }}" data-locale="{{ \App\Http\Middleware\SetLocale::remembered(request()) }}"
              data-at="{{ (int) round(request()->server('REQUEST_TIME_FLOAT', microtime(true)) * 1000) }}">
    @endif

    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $description }}">

    @if ($noindex === 'follow')
        {{-- Not a page to show in results, but its links still lead somewhere worth crawling. --}}
        <meta name="robots" content="noindex, follow">
    @elseif ($noindex)
        <meta name="robots" content="noindex, nofollow">
    @else
        <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
        <link rel="canonical" href="{{ $canonical }}">
        @foreach (Seo::alternates($address) as $language => $href)
            <link rel="alternate" hreflang="{{ $language }}" href="{{ $href }}">
        @endforeach
    @endif

    {{-- Ownership checks for Google Search Console and Bing Webmaster Tools, set in .env. --}}
    @if ($google = config('services.search_console.google'))
        <meta name="google-site-verification" content="{{ $google }}">
    @endif
    @if ($bing = config('services.search_console.bing'))
        <meta name="msvalidate.01" content="{{ $bing }}">
    @endif

    {{-- How the page looks when shared on Facebook, WhatsApp, X, LinkedIn... --}}
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:site_name" content="Sofor">
    <meta property="og:locale" content="{{ app()->getLocale() === 'bn' ? 'bn_BD' : 'en_GB' }}">
    <meta property="og:locale:alternate" content="{{ app()->getLocale() === 'bn' ? 'en_GB' : 'bn_BD' }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:image:alt" content="{{ Seo::text($imageAlt) }}">
    @if ($image === asset('images/og/sofor.png'))
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:type" content="image/png">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $fullTitle }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image }}">
    <meta name="twitter:image:alt" content="{{ Seo::text($imageAlt) }}">

    @if ($manifest)
        {{-- Served by BrandingController so its icons follow the uploaded favicon. --}}
        <link rel="manifest" href="{{ route('manifest') }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Sofor">
    @endif

    @php $branding = app(\App\Services\BrandingService::class); @endphp

    @if ($branding->hasFavicon())
        <link rel="icon" href="{{ $branding->faviconUrl(32) }}" type="image/png" sizes="32x32">
        <link rel="icon" href="{{ $branding->faviconUrl(192) }}" type="image/png" sizes="192x192">
        <link rel="apple-touch-icon" href="{{ $branding->faviconUrl(180) }}" sizes="180x180">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}" sizes="180x180">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400..700&family=JetBrains+Mono:wght@400;600{{ app()->getLocale() === 'bn' ? '&family=Hind+Siliguri:wght@400;500;600;700' : '' }}&display=swap" rel="stylesheet">

    @if ($schema)
        {{-- JSON_HEX_TAG turns < and > into < / >, so no value can close this script tag. --}}
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endif

    @if (($jsStrings = \App\Support\JsStrings::dictionary()) !== [])
        {{-- Sentences the scripts write themselves, in this page's language. --}}
        <script>window.SoforI18n = @json($jsStrings);</script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
