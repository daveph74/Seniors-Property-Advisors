<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveUserRequest extends FormRequest
{
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'string', 'email', 'max:190',
                Rule::unique('users', 'email')->ignore($user?->getKey()),
            ],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'active' => ['sometimes', 'boolean'],
            'password' => [
                $user === null ? 'required' : 'nullable',
                'string', Password::min(10),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Another account already uses that email address.',
            'password.required' => 'A new account needs a password.',
        ];
    }

    public function details(): array
    {
        return [
            'name' => trim(strip_tags($this->validated()['name'])),
            'email' => $this->validated()['email'],
            'role' => $this->validated()['role'],
            'is_active' => $this->boolean('active', true),
        ];
    }

    public function password(): string
    {
        return $this->validated()['password'];
    }
}
