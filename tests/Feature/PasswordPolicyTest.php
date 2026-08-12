<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * One statement of the policy lives in App\Auth\PasswordPolicy, and two paths set a
 * password — your own at /cms/account, somebody else's at /cms/users. Both are checked
 * against the same refusals here, because a rule enforced on one screen and not the
 * other is the same as no rule.
 */
class PasswordPolicyTest extends TestCase
{
    private const CURRENT = 'the-current-password';

    private const ACCEPTABLE = 'Windmill-Harbour-4';

    public static function refusals(): array
    {
        return [
            'too short' => ['Short-9!'],
            'no uppercase' => ['windmill-harbour-4'],
            'no lowercase' => ['WINDMILL-HARBOUR-4'],
            'no number' => ['Windmill-Harbour-Lane'],
            'no symbol' => ['WindmillHarbour4'],
        ];
    }

    private function helen(): User
    {
        return $this->clientAdmin([
            'email' => 'helen@example.com',
            'password' => Hash::make(self::CURRENT),
        ]);
    }

    #[DataProvider('refusals')]
    public function test_your_own_password_has_to_meet_the_policy(string $password): void
    {
        $user = $this->helen();
        $before = $user->password;

        $this->actingAs($user)->patch('/cms/account/password', [
            'current_password' => self::CURRENT,
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrors('password');

        $this->assertSame($before, $user->refresh()->password);
    }

    #[DataProvider('refusals')]
    public function test_a_password_set_for_somebody_else_has_to_meet_the_policy(string $password): void
    {
        $this->post('/cms/users', [
            'name' => 'Anna Kelly',
            'email' => 'anna@example.com',
            'role' => User::CLIENT_ADMIN,
            'password' => $password,
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'anna@example.com']);
    }

    #[DataProvider('refusals')]
    public function test_the_command_refuses_one_too(string $password): void
    {
        $this->artisan('cms:user', [
            'email' => 'support@example.com',
            '--password' => $password,
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'support@example.com']);
    }

    public function test_a_password_meeting_every_rule_is_accepted(): void
    {
        $user = $this->helen();

        $this->actingAs($user)->patch('/cms/account/password', [
            'current_password' => self::CURRENT,
            'password' => self::ACCEPTABLE,
            'password_confirmation' => self::ACCEPTABLE,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::ACCEPTABLE, $user->refresh()->password));
    }

    /**
     * The suite answers the breach service with an empty body, so nothing is ever listed as
     * breached and no other test can tell whether that check runs at all. This one lists it.
     *
     * Stubs are matched in the order they were registered, so the empty answer set up in
     * TestCase would win — the client has to be thrown away before this one is registered.
     */
    public function test_a_breached_password_is_refused(): void
    {
        $suffix = strtoupper(substr(sha1(self::ACCEPTABLE), 5));

        $this->app->forgetInstance(Factory::class);
        Http::clearResolvedInstances();
        Http::fake(['api.pwnedpasswords.com/*' => Http::response($suffix.':417', 200)]);

        $user = $this->helen();
        $before = $user->password;

        $this->actingAs($user)->patch('/cms/account/password', [
            'current_password' => self::CURRENT,
            'password' => self::ACCEPTABLE,
            'password_confirmation' => self::ACCEPTABLE,
        ])->assertSessionHasErrors('password');

        $this->assertSame($before, $user->refresh()->password);
    }

    public function test_choosing_your_own_password_stamps_it_and_clears_the_warning(): void
    {
        $user = $this->helen();
        $user->forceFill(['password_changed_at' => null])->save();

        $this->assertTrue($user->mustChangePassword());
        $this->assertTrue($this->actingAs($user)->get('/cms')->viewData('page')['props']['auth']['mustChangePassword']);

        $this->patch('/cms/account/password', [
            'current_password' => self::CURRENT,
            'password' => self::ACCEPTABLE,
            'password_confirmation' => self::ACCEPTABLE,
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($user->refresh()->password_changed_at);
        $this->assertFalse($this->get('/cms')->viewData('page')['props']['auth']['mustChangePassword']);
    }

    public function test_a_password_issued_by_somebody_else_is_left_unstamped(): void
    {
        $this->post('/cms/users', [
            'name' => 'Anna Kelly',
            'email' => 'anna@example.com',
            'role' => User::CLIENT_ADMIN,
            'password' => self::ACCEPTABLE,
        ])->assertRedirect();

        $anna = User::where('email', 'anna@example.com')->firstOrFail();

        $this->assertTrue($anna->mustChangePassword());

        $anna->forceFill(['password_changed_at' => now()])->save();

        $this->patch("/cms/users/{$anna->id}", [
            'name' => 'Anna Kelly',
            'email' => 'anna@example.com',
            'role' => User::CLIENT_ADMIN,
            'password' => 'Different-Harbour-8',
        ])->assertRedirect();

        $this->assertTrue($anna->refresh()->mustChangePassword());
    }

    public function test_the_command_leaves_a_password_unstamped_but_a_role_change_alone(): void
    {
        $this->artisan('cms:user', [
            'email' => 'support@example.com',
            '--password' => self::ACCEPTABLE,
        ])->assertSuccessful();

        $user = User::where('email', 'support@example.com')->firstOrFail();

        $this->assertTrue($user->mustChangePassword());

        $user->forceFill(['password_changed_at' => now()])->save();

        $this->artisan('cms:user', [
            'email' => 'support@example.com',
            '--role' => User::SUPER_ADMIN,
        ])->assertSuccessful();

        $this->assertFalse($user->refresh()->mustChangePassword());
    }

    public function test_signing_in_is_not_held_to_the_policy(): void
    {
        $this->helen();

        $this->post('/logout');

        $this->post('/login', ['email' => 'helen@example.com', 'password' => self::CURRENT])
            ->assertRedirect('/cms');
    }
}
