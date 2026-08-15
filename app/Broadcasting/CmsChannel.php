<?php

namespace App\Broadcasting;

use App\Auth\Permissions;
use App\Models\User;

/**
 * Who may listen to the admin's channel.
 *
 * The rule lives here rather than as a closure in `routes/channels.php` so it can be tested for what
 * it decides. Authorising a channel over HTTP turned out to depend on which broadcaster the
 * environment has configured — under the `null` driver the callback is never consulted and the
 * endpoint answers everybody the same way, which makes a test against it prove nothing either way.
 *
 * It mirrors `Permit`, the middleware guarding `/cms`: an active account with `content.manage`. A
 * socket that outlived a deactivation would be a way back into the thing the account was locked out
 * of, and nothing on this channel is worth less protection than the screens it belongs to.
 */
class CmsChannel
{
    public static function allows(?User $user): bool
    {
        return $user !== null
            && $user->is_active
            && Permissions::allows($user, 'content.manage');
    }
}
