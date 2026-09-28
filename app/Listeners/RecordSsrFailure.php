<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Inertia\Ssr\SsrRenderFailed;

/**
 * A record that the server-side render did not happen.
 *
 * This exists because the failure is otherwise completely silent, and silence here is expensive.
 * `HttpGateway` answers `null` whenever the renderer is unreachable, the bundle is missing, or the
 * render throws — and Inertia then serves the page the old way, from the browser. The site works. Every
 * screen looks right. The only thing lost is the reason SSR was added: the delivered document goes back
 * to having no heading, no copy and no links, so crawlers that do not run JavaScript see an empty page
 * again. Nobody notices until somebody thinks to read the source months later.
 *
 * So it is logged as a warning with what the event knows — the component, the error, and the browser API
 * that was reached for, which is the usual cause: something touching `window` or `document` while
 * rendering, where the server has neither.
 *
 * Deliberately not `activity_log`. That screen is a history of what editors did to content, and a
 * rendering fault is neither. It is also not thrown: turning a degraded render into a 500 would take the
 * site down to protect its search ranking, which is the wrong way round. `INERTIA_SSR_THROW_ON_ERROR`
 * exists for the browser suite, where a red test is exactly what is wanted.
 */
class RecordSsrFailure
{
    public function handle(SsrRenderFailed $event): void
    {
        Log::warning('The server-side render failed; this page fell back to the browser.', [
            'component' => $event->page['component'] ?? null,
            'url' => $event->page['url'] ?? null,
            'type' => $event->type->value,
            'error' => $event->error,
            'browser_api' => $event->browserApi,
            'source' => $event->sourceLocation,
            'hint' => $event->hint,
        ]);
    }
}
