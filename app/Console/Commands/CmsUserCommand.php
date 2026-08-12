<?php

namespace App\Console\Commands;

use App\Auth\PasswordPolicy;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CmsUserCommand extends Command
{
    protected $signature = 'cms:user
        {email : The account email address}
        {--name= : Display name, used when creating the account}
        {--role=client_admin : super_admin or client_admin}
        {--password= : Leave empty to generate one}
        {--deactivate : Turn the account off instead of on}';

    protected $description = 'Create a CMS account, or change an existing one\'s role, password or status';

    public function handle(): int
    {
        $role = (string) $this->option('role');

        if (! array_key_exists($role, User::ROLES)) {
            $this->error('Role must be one of: '.implode(', ', array_keys(User::ROLES)));

            return self::FAILURE;
        }

        $email = Str::lower(trim((string) $this->argument('email')));
        $user = User::where('email', $email)->first();
        $password = (string) ($this->option('password') ?: Str::password(16));

        if ($this->option('password')) {
            $validator = Validator::make(['password' => $password], ['password' => PasswordPolicy::rules()]);

            if ($validator->fails()) {
                foreach ($validator->errors()->get('password') as $message) {
                    $this->error($message);
                }

                return self::FAILURE;
            }
        }

        $attributes = [
            'role' => $role,
            'is_active' => ! $this->option('deactivate'),
            'password' => $password,
            'password_changed_at' => null,
        ];

        if ($user === null) {
            $user = User::create($attributes + [
                'name' => (string) ($this->option('name') ?: Str::headline(Str::before($email, '@'))),
                'email' => $email,
            ]);

            $this->info("Created {$user->name} <{$user->email}> as {$user->roleLabel()}.");
        } else {
            if ($this->option('name')) {
                $attributes['name'] = (string) $this->option('name');
            }

            if (! $this->option('password')) {
                unset($attributes['password'], $attributes['password_changed_at']);
                $password = null;
            }

            $user->forceFill($attributes)->save();

            $this->info("Updated {$user->name} <{$user->email}> to {$user->roleLabel()}.");
        }

        if ($password !== null && ! $this->option('password')) {
            $this->line("Password: {$password}");
        }

        return self::SUCCESS;
    }
}
