<?php

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** How long a run of wrong guesses is made to wait, in seconds. */
    private const LOCKOUT = 300;

    /** How many wrong guesses that run is. */
    private const ATTEMPTS = 5;

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            /*
             * Five minutes, not the default minute. At sixty seconds five wrong guesses buy a pause
             * and then five more — three hundred an hour against one address, for ever, which is a
             * word list rather than a lockout. Five minutes is meant to be intolerable to a script
             * and survivable for somebody who has genuinely forgotten: there is no self-serve reset
             * here, so the alternative to waiting is telephoning an administrator.
             */
            RateLimiter::hit($this->throttleKey(), self::LOCKOUT);

            throw ValidationException::withMessages([
                'email' => 'Those details do not match our records.',
            ]);
        }

        if (! $this->user()->is_active) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    private function ensureIsNotRateLimited(): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), self::ATTEMPTS)) {
            Event::dispatch(new Lockout($this));

            $seconds = RateLimiter::availableIn($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => "Too many sign-in attempts. Try again in {$seconds} seconds.",
            ]);
        }
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('email')).'|'.$this->ip());
    }
}
