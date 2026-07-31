<!doctype html>
<html lang="en-AU">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title inertia>Agent Finder — Seniors Property Advisors</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    {{-- Instrument Sans/Serif are the CMS admin typefaces (--cms-font-*); the public
         site only needs DM Sans, so it does not pay for a font stack it never renders. --}}
    @if (request()->is('cms', 'cms/*'))
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Instrument+Sans:ital,wght@0,400;0,500;0,600;0,700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet" />
    @else
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet" />
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
