<?php

namespace App\Http\Requests;

use App\Auth\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', PasswordPolicy::rule()],
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
