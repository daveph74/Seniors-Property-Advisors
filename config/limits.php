<?php

/*
 * Every number this application refuses at, in one place.
 *
 * They are here rather than inline in the routes for two reasons. A limit is a product decision —
 * how much use is honest use — and reading them together is the only way to see whether they agree.
 * And a test can lower one and prove a refusal in four requests instead of looping a hundred and
 * twenty-one times, which is what keeps the security suite fast enough to run.
 *
 * The reasoning for each sits beside it in `app/Http/Limits.php`, against the screen it was measured
 * on. A number without that is a number somebody tightens later, breaking editing in a way that looks
 * like a bug rather than a policy.
 */

return [
    /* Signed-in staff. Keyed on the account, never the address: the office shares one. */
    'cms' => ['minute' => 300],
    'cms_write' => ['minute' => 120],
    'cms_upload' => ['minute' => 180],
    'cms_search' => ['minute' => 180],
    'password' => ['hour' => 10],

    /* Visitors. Keyed on the address, which only means anything once TRUSTED_PROXIES is set. */
    'sign_in' => ['minute' => 10, 'hour' => 60],
    'enquiries' => ['minute' => 6, 'hour' => 20],
    'suburbs' => ['minute' => 60, 'hour' => 1200],
    'public' => ['minute' => 120],
    'sitemap' => ['minute' => 10],
    'media' => ['minute' => 600],
];
