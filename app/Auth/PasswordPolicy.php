<?php

namespace App\Auth;

use Illuminate\Validation\Rules\Password;

/**
 * One statement of what a password has to be. Every path that sets one reads it from
 * here — the two form requests and the cms:user command — so there is no second place
 * for the rules to drift.
 *
 * `resources/js/cms/passwordPolicy.js` mirrors these rules to draw the live checklist.
 * That copy is a courtesy to whoever is typing; this one decides.
 *
 * `uncompromised()` checks the password against the public breach corpus. It fails open —
 * if that service cannot be reached the password is accepted — so it can rule out the
 * passwords that actually get guessed without ever locking anybody out.
 */
class PasswordPolicy
{
    public const MINIMUM = 10;

    public static function rule(): Password
    {
        return Password::min(self::MINIMUM)
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised();
    }

    public static function rules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', self::rule()];
    }
}
