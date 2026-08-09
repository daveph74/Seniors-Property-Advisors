<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            /* `uncompromised()` checks the new password against the public breach corpus. It fails
               open — if that service cannot be reached the password is accepted — so it can rule
               out the passwords that actually get guessed without ever locking anybody out. */
            'password' => ['required', 'string', 'confirmed', Password::min(10)->uncompromised()],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Your current password is not correct.',
            'password.confirmed' => 'The two new passwords do not match.',
        ];
    }

    public function password(): string
    {
        return $this->validated()['password'];
    }
}
