<?php

namespace App\Logging;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Where an enquiry and the settings that route it leave their trail.
 *
 * Production runs at `LOG_LEVEL=error`, which drops every "skipped", "sending" and "saved" line from
 * `laravel.log` — so a server that was quietly doing nothing said nothing at all. Everything logged
 * here also goes to `storage/logs/delivery.log`, which keeps every level regardless.
 */
class Delivery
{
    public static function log(): LoggerInterface
    {
        return Log::stack([config('logging.default'), 'delivery']);
    }
}
