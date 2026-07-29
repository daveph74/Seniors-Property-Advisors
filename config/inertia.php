<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Overrides the package default of `resources/js/pages`. This project uses a
    | capitalised `Pages` directory, and on a case-sensitive filesystem the
    | lowercase default silently fails to resolve every component — which makes
    | `assertInertia(...)->component(...)` fail in tests.
    |
    | Only the `pages` key is overridden here; every other Inertia setting keeps
    | its packaged default (config is shallow-merged on the top-level key).
    |
    */

    'pages' => [

        'ensure_pages_exist' => false,

        'paths' => [

            resource_path('js/Pages'),

        ],

        'extensions' => [

            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',

        ],

    ],

];
