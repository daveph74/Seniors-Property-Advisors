<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

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

        /* Setting a password now checks it against the public breach corpus, which is a real HTTP
           call. Answered here with an empty body — the response lists the hashes that *are*
           breached, so nothing listed means not breached. Without this the suite would reach the
           internet to set a password: slow, flaky, and broken on any machine that is offline.
           Scoped to that one host so a test's own `Http::fake()` still governs its own calls. */
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);

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
