<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AccountTest extends TestCase
{
    private const CURRENT = 'the-current-password';

    private const REPLACEMENT = 'the-replacement-password';

    private function helen(): User
    {
        return $this->clientAdmin([
            'email' => 'helen@example.com',
            'password' => Hash::make(self::CURRENT),
        ]);
    }

    private function change(array $body = []): TestResponse
    {
        return $this->patch('/cms/account/password', array_merge([
            'current_password' => self::CURRENT,
            'password' => self::REPLACEMENT,
            'password_confirmation' => self::REPLACEMENT,
        ], $body));
    }

    public function test_a_client_administrator_can_open_their_account(): void
    {
        $this->actingAs($this->helen())->get('/cms/account')->assertOk();
    }

    public function test_a_guest_cannot(): void
    {
        Auth::logout();

        $this->get('/cms/account')->assertRedirect('/login');
    }

    public function test_a_password_can_be_changed_and_the_old_one_stops_working(): void
    {
        $user = $this->helen();

        $this->actingAs($user)->change()->assertRedirect();

        Auth::logout();

        $this->post('/login', ['email' => 'helen@example.com', 'password' => self::CURRENT])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'helen@example.com', 'password' => self::REPLACEMENT])
            ->assertRedirect(route('cms.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_wrong_current_password_changes_nothing(): void
    {
        $user = $this->helen();
        $before = $user->password;

        $this->actingAs($user)->change(['current_password' => 'not-my-password'])
            ->assertSessionHasErrors('current_password');

        $this->assertSame($before, $user->refresh()->password);
    }

    public function test_a_short_password_is_refused(): void
    {
        $user = $this->helen();
        $before = $user->password;

        $this->actingAs($user)->change(['password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');

        $this->assertSame($before, $user->refresh()->password);
    }

    public function test_a_mismatched_confirmation_is_refused(): void
    {
        $user = $this->helen();
        $before = $user->password;

        $this->actingAs($user)->change(['password_confirmation' => 'something-else-entirely'])
            ->assertSessionHasErrors('password');

        $this->assertSame($before, $user->refresh()->password);
    }

    /**
     * The ordering trap: logoutOtherDevices must run after the save, and must refresh the
     * acting session. Getting it wrong signs the user out of the request they just made.
     */
    public function test_changing_your_own_password_keeps_you_signed_in(): void
    {
        $user = $this->helen();

        $this->actingAs($user)->change()->assertRedirect();

        $this->get('/cms/pages')->assertOk();
        $this->assertAuthenticatedAs($user->refresh());
    }

    /**
     * The other device is represented by a session carrying the hash that was really stored
     * before the change. Seeding it with a fresh Hash::make would never match either way, and
     * would pass with the feature removed.
     */
    private function sessionHolding(string $hash, User $user): TestResponse
    {
        $this->flushSession();

        return $this->withSession(['password_hash_web' => $hash])
            ->actingAs($user->refresh())
            ->get('/cms/pages');
    }

    public function test_a_session_on_another_device_is_signed_out(): void
    {
        $user = $this->helen();
        $original = $user->password;

        $this->sessionHolding($original, $user)->assertOk();

        $this->flushSession();
        $this->actingAs($user->refresh())->change()->assertRedirect();

        $this->sessionHolding($original, $user)->assertRedirect('/login');
    }

    public function test_a_super_administrator_resetting_a_password_signs_that_user_out(): void
    {
        $helen = $this->helen();
        $original = $helen->password;
        $me = User::where('role', User::SUPER_ADMIN)->sole();

        $this->sessionHolding($original, $helen)->assertOk();

        $this->flushSession();
        $this->actingAs($me)->patch("/cms/users/{$helen->id}", [
            'name' => $helen->name,
            'email' => $helen->email,
            'role' => User::CLIENT_ADMIN,
            'password' => self::REPLACEMENT,
        ])->assertRedirect();

        $this->sessionHolding($original, $helen)->assertRedirect('/login');
    }

    public function test_a_super_administrator_resetting_their_own_password_stays_signed_in(): void
    {
        $me = User::where('role', User::SUPER_ADMIN)->sole();

        $this->patch("/cms/users/{$me->id}", [
            'name' => $me->name,
            'email' => $me->email,
            'role' => User::SUPER_ADMIN,
            'password' => self::REPLACEMENT,
        ])->assertRedirect();

        $this->get('/cms/users')->assertOk();
        $this->assertAuthenticatedAs($me->refresh());
    }
}
