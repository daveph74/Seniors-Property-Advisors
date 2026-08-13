/**
 * The socket that tells an open CMS screen something arrived.
 *
 * Loaded on demand rather than imported at the top of the admin bundle: Pusher's client is around
 * 40KB gzipped and the public site has no use for it, the same reasoning the article editor's TipTap
 * chunk follows.
 *
 * Everything here fails quietly. No key configured, no server listening, a socket that drops — the
 * CMS goes on working exactly as it did before any of this existed, which is to say the inbox updates
 * when somebody looks at it. A notification mechanism that can take a screen down with it is worse
 * than no notification mechanism.
 */
let connecting = null;

async function echo(config) {
    if (connecting) {
        return connecting;
    }

    connecting = (async () => {
        const [{ default: Echo }, { default: Pusher }] = await Promise.all([
            import('laravel-echo'),
            import('pusher-js'),
        ]);

        /*
         * The class goes on `window` and the options are given to Echo, rather than handing Echo a
         * client built here. A pre-built client keeps Pusher's own defaults, which means it asks
         * `/pusher/auth` to authorise a private channel instead of Laravel's `/broadcasting/auth` —
         * and this application answers that address with a 405 from the catch-all page route. The
         * socket connects, the subscription is never authorised, and nothing is ever delivered. There
         * is no error worth reading anywhere in that sequence.
         */
        window.Pusher = Pusher;

        return new Echo({
            broadcaster: 'reverb',
            key: config.key,
            wsHost: config.host,
            wsPort: config.port,
            wssPort: config.port,
            forceTLS: config.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    })().catch(() => null);

    return connecting;
}

/**
 * Calls `handler` when an enquiry arrives. Returns a function that stops listening, which callers
 * must use on unmount — a listener left behind on a screen somebody has navigated away from refetches
 * props for a page that is no longer there.
 */
export function onEnquiryReceived(config, handler) {
    /* No server configured, so nothing is attempted. The `config` comes from the page's own props
       rather than from the build, which is what keeps this and the content policy telling the same
       story about whether a socket exists. */
    if (! config?.key) {
        return () => {};
    }

    let channel = null;
    let stopped = false;

    echo(config).then((instance) => {
        if (! instance || stopped) {
            return;
        }

        channel = instance.private('cms');
        channel.listen('.enquiry.received', handler);
    });

    return () => {
        stopped = true;
        channel?.stopListening('.enquiry.received', handler);
    };
}
