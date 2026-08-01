<!doctype html>
<html lang="en-AU">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title inertia>Agent Finder — Seniors Property Advisors</title>
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
</head>
<body>
    @inertia
</body>
</html>
