<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthTest extends TestCase
{
    private function account(array $attributes = []): User
    {
        return $this->clientAdmin(array_merge([
            'email' => 'helen@example.com',
            'password' => Hash::make('a-long-enough-password'),
        ], $attributes));
    }

    private function signIn(array $body = []): TestResponse
    {
        Auth::logout();

        return $this->post('/login', array_merge([
            'email' => 'helen@example.com',
            'password' => 'a-long-enough-password',
        ], $body));
    }

    public function test_a_guest_is_sent_to_the_sign_in_page(): void
    {
        Auth::logout();

        $this->get('/cms')->assertRedirect('/login');
        $this->get('/cms/pages')->assertRedirect('/login');
    }

    public function test_the_public_website_stays_open_to_guests(): void
    {
        Auth::logout();

        $this->get('/')->assertOk();
    }

    public function test_correct_details_sign_a_user_in(): void
    {
        $user = $this->account();

        $this->signIn()->assertRedirect(route('cms.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $this->account();

        $this->signIn(['password' => 'not-the-password'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_signing_in_records_the_time(): void
    {
        $user = $this->account();

        $this->assertNull($user->last_login_at);

        $this->signIn();

        $this->assertNotNull($user->refresh()->last_login_at);
    }

    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        $this->account(['is_active' => false]);

        $this->signIn()->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_deactivated_account_loses_access_mid_session(): void
    {
        $user = $this->account();

        $this->actingAs($user)->get('/cms/pages')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user->refresh())->get('/cms/pages')->assertForbidden();
    }

    public function test_repeated_failures_are_rate_limited(): void
    {
        $this->account();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->signIn(['password' => 'wrong'])->assertSessionHasErrors('email');
        }

        $this->signIn()->assertSessionHasErrors('email');
        $this->assertGuest();

        RateLimiter::clear(mb_strtolower('helen@example.com').'|127.0.0.1');
    }

    public function test_signing_out_ends_the_session(): void
    {
        $this->actingAs($this->account());

        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_a_signed_in_user_is_kept_off_the_sign_in_page(): void
    {
        $this->get('/login')->assertRedirect(route('cms.dashboard'));
    }

    public function test_a_guest_is_returned_to_where_they_were_headed(): void
    {
        Auth::logout();

        $this->get('/cms/media')->assertRedirect('/login');

        $this->account();

        $this->signIn()->assertRedirect('/cms/media');
    }
}
