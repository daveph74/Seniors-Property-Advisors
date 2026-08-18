<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * A record that somebody tried and failed to sign in.
 *
 * There was none at all: the limiter turned attackers away and left nothing behind, so a password
 * spray was invisible the day after. Without this you cannot tell a colleague's typo from a campaign,
 * which is the only question worth asking of a failed sign-in.
 *
 * **To the log file, never to `activity_log`.** The email in a failed attempt is unverified — anyone
 * can type somebody else's — and belongs to a person who is not a user here and has agreed to
 * nothing. `activity_log` has no delete path and keeps `by_name` as text on purpose, so writing a
 * stranger's address into it is the mistake `OwaspTest::test_a09_the_log_does_not_keep_what_a_deletion_was_meant_to_remove`
 * exists to prevent. It would also drown the editors' change history, which is what that screen is for.
 *
 * So: the account id when the address matches somebody real — an identifier already held lawfully and
 * deletable with them — and a hash when it does not. The hash still answers "was this five hundred
 * attempts against one account, or against five hundred", which is the shape of an attack, without
 * holding the address itself.
 */
class RecordSignInTrouble
{
    public function handleFailed(Failed $event): void
    {
        Log::info('Sign-in failed', $this->about($event->credentials['email'] ?? null));
    }

    public function handleLockout(Lockout $event): void
    {
        Log::warning('Sign-in locked out', $this->about($event->request->input('email')));
    }

    /** @return array<string, mixed> */
    private function about(?string $email): array
    {
        $address = Str::lower(trim((string) $email));

        return [
            'ip' => request()->ip(),
            /* Null when nobody by that address exists here, which is itself worth seeing: a run of
               them is somebody guessing addresses rather than passwords. */
            'user_id' => $address === '' ? null : User::where('email', $address)->value('id'),
            'email_hash' => $address === '' ? null : substr(hash('sha256', $address), 0, 16),
        ];
    }
}
