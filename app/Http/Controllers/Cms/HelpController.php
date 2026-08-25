<?php

namespace App\Http\Controllers\Cms;

use App\Docs\UserGuide;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Vite;

/**
 * The staff guide, at `/cms/help`.
 *
 * A full-page document rather than a screen inside the admin shell: it is a reference somebody reads
 * with the CMS open in another tab, and wrapping it in the sidebar it is describing would leave two
 * navigations on screen. The sidebar link opens it in a new tab for the same reason, which is also
 * why it is a plain anchor — every other sidebar entry is an Inertia link, and this route answers
 * with HTML rather than an Inertia payload.
 *
 * Two things a request supplies that the committed file cannot:
 *
 * - **The policy nonce.** `script-src` is `'self'` plus a per-request nonce with no `'unsafe-inline'`,
 *   so the guide's own inline script — the contents list that follows the reader down the page — is
 *   refused without one. Nothing breaks visibly: the page renders perfectly and the contents simply
 *   stop tracking, which is the kind of fault nobody reports.
 * - **A way back**, since this page is outside the admin shell.
 *
 * It reads nothing from the database on purpose. This page names no accounts and no people — the
 * guide describes roles, and who currently holds one is the Users screen's business, not a document's.
 */
class HelpController extends Controller
{
    public function __invoke(UserGuide $guide): Response
    {
        return response($guide->html([
            'nonce' => Vite::cspNonce(),
            'home' => '/cms',
        ]))->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
