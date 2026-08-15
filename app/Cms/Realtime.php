<?php

namespace App\Cms;

/**
 * Whether there is a socket to listen to, and where.
 *
 * One place, because three things have to agree about it: the content policy, which must name the
 * origin or the browser refuses the connection; the admin, which must not attempt one that is not
 * there; and whatever is actually running.
 *
 * They did not agree at first. The policy read this configuration while the browser read
 * `VITE_REVERB_*`, which is baked in when the assets are built — so a build carrying development
 * credentials, served by an environment with none, tried to open a socket its own policy forbade. The
 * end-to-end policy sweep is what noticed. Nothing here is a build-time value now: the admin is told
 * what to connect to by the server that drew the page.
 */
class Realtime
{
    /**
     * Null when no socket server is configured, which is the signal to the admin not to try — and to
     * the policy not to grant a permission for a server that does not exist.
     *
     * @return array{key: string, host: string, port: int, scheme: string}|null
     */
    public static function config(): ?array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return null;
        }

        $key = config('broadcasting.connections.reverb.key');
        $host = config('broadcasting.connections.reverb.options.host');

        if (! is_string($key) || $key === '' || ! is_string($host) || $host === '') {
            return null;
        }

        return [
            'key' => $key,
            'host' => $host,
            'port' => (int) (config('broadcasting.connections.reverb.options.port') ?: 8080),
            'scheme' => config('broadcasting.connections.reverb.options.scheme') === 'https' ? 'https' : 'http',
        ];
    }

    /** The same thing as `connect-src` needs it: a socket origin, not the address it upgrades from. */
    public static function socketOrigin(): ?string
    {
        $config = self::config();

        if ($config === null) {
            return null;
        }

        return ($config['scheme'] === 'https' ? 'wss' : 'ws').'://'.$config['host'].':'.$config['port'];
    }
}
