<!doctype html>
<html lang="en-AU">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    {{-- Printed here, not only by the page component, because a link scraper reads the document as
         delivered and never runs the JavaScript that would otherwise add these. Each carries the
         `inertia` attribute, so the client head manager takes ownership on hydration and keeps them
         current as somebody moves around the site rather than leaving a second copy behind. --}}
    @php($head = $page['props']['head'] ?? [])
    @php($site = App\Content\Site::all())
    @isset($site['favicon'])
        <link rel="icon" href="{{ $site['favicon'] }}" />
    @endisset
    <title inertia>{{ $head['title'] ?? 'Agent Finder — Seniors Property Advisors' }}</title>
    @isset($head['description'])
        <meta inertia name="description" content="{{ $head['description'] }}" />
        <meta inertia="og:description" property="og:description" content="{{ $head['description'] }}" />
    @endisset
    @isset($head['robots'])
        <meta inertia name="robots" content="{{ $head['robots'] }}" />
    @endisset
    @isset($head['canonical'])
        <link inertia rel="canonical" href="{{ $head['canonical'] }}" />
    @endisset
    @isset($head['ogType'])
        <meta inertia="og:type" property="og:type" content="{{ $head['ogType'] }}" />
        <meta inertia="og:title" property="og:title" content="{{ $head['title'] }}" />
    @endisset
    @isset($head['ogUrl'])
        <meta inertia="og:url" property="og:url" content="{{ $head['ogUrl'] }}" />
    @endisset
    @isset($head['image'])
        <meta inertia="og:image" property="og:image" content="{{ $head['image'] }}" />
    @endisset
    @isset($head['imageWidth'])
        <meta inertia="og:image:width" property="og:image:width" content="{{ $head['imageWidth'] }}" />
        <meta inertia="og:image:height" property="og:image:height" content="{{ $head['imageHeight'] }}" />
    @endisset
    @isset($head['twitterCard'])
        <meta inertia name="twitter:card" content="{{ $head['twitterCard'] }}" />
    @endisset
    {{-- One block or several. Separate scripts rather than an @graph: Google reads them the same
         way, each validates on its own in the Rich Results Test, and a page that needs only one
         keeps passing a bare array. JSON_HEX_TAG is what stops a `</script>` inside an editor's
         FAQ answer from closing this one. --}}
    @isset($head['schema'])
        @foreach (array_is_list($head['schema']) ? $head['schema'] : [$head['schema']] as $schema)
            <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endforeach
    @endisset
    {{-- DM Sans is bundled with the stylesheet now (see resources/css/app.css), so the public
         site makes no font request off this origin at all. Only the CMS still reaches out, for
         Instrument Sans/Serif, and only on CMS routes. --}}
    {{-- /login too: it renders the admin stylesheet, so it asks for Instrument Sans and was
         quietly falling back to system-ui without it. --}}
    @if (request()->is('cms', 'cms/*', 'login'))
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;0,700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet" />
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
    {{-- Readers only. Measuring the admin would count the people editing the site as visitors, and
         load a third-party script for staff who never asked for one. Ids are format-checked twice,
         at the save and again in `Site::tracking()`, because this prints inside a <script> where
         Blade's escaping would not help. --}}
    @if (! request()->is('cms', 'cms/*', 'login'))
        @php($tracking = App\Content\Site::tracking())
        @isset($tracking['gtm'])
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $tracking['gtm'] }}');</script>
        @endisset
        @isset($tracking['ga4'])
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ $tracking['ga4'] }}"></script>
            <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $tracking['ga4'] }}');</script>
        @endisset
    @endif
</head>
<body>
    @inertia
</body>
</html>
