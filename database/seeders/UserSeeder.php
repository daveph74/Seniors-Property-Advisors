<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local development accounts only — they share one well-known password. Real accounts
 * are made with `php artisan cms:user`, which generates a password per account.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['Site Administrator', 'superadmin@seniorspropertyadvisors.com.au', User::SUPER_ADMIN],
            ['Helen Marsh', 'helen@seniorspropertyadvisors.com.au', User::CLIENT_ADMIN],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'role' => $role, 'is_active' => true, 'password' => Hash::make('password')],
            );
        }
    }
}
