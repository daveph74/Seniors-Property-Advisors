<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * CMS routes all require an account, so the suite signs in a super administrator
     * by default. Tests about permissions sign in as somebody else, or nobody.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ContentSeeder::class);

        $this->actingAs($this->superAdmin());
    }

    protected function superAdmin(array $attributes = []): User
    {
        return User::factory()->superAdmin()->create($attributes);
    }

    protected function clientAdmin(array $attributes = []): User
    {
        return User::factory()->clientAdmin()->create($attributes);
    }
}
