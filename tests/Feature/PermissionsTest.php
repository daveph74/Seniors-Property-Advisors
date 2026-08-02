<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    private function asClient(array $attributes = []): User
    {
        $user = $this->clientAdmin($attributes);

        $this->actingAs($user);

        return $user;
    }

    private function home(): Page
    {
        return Page::where('slug', 'home')->firstOrFail();
    }

    public function test_a_client_administrator_reaches_the_content_modules(): void
    {
        $this->asClient();

        foreach (['/cms', '/cms/pages', '/cms/blog', '/cms/faqs', '/cms/testimonials', '/cms/media', '/cms/navigation', '/cms/global-content'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_a_client_administrator_cannot_reach_users_or_settings(): void
    {
        $this->asClient();

        $this->get('/cms/users')->assertForbidden();
        $this->get('/cms/settings')->assertForbidden();
    }

    public function test_a_super_administrator_reaches_users_and_settings(): void
    {
        $this->get('/cms/users')->assertOk();
        $this->get('/cms/settings')->assertOk();
    }

    public function test_a_client_administrator_can_edit_and_publish_a_page(): void
    {
        $this->asClient();
        $page = $this->home();

        $this->post("/cms/pages/{$page->cms_id}/draft", ['sections' => []])->assertRedirect();
        $this->post("/cms/pages/{$page->cms_id}/publish", ['sections' => []])->assertRedirect();
        $this->post("/cms/pages/{$page->cms_id}/unpublish")->assertRedirect();
        $this->post("/cms/pages/{$page->cms_id}/archive")->assertRedirect();
    }

    public function test_only_a_super_administrator_restores_archived_content(): void
    {
        $page = $this->home();

        $this->asClient();
        $this->post("/cms/pages/{$page->cms_id}/archive")->assertRedirect();
        $this->post("/cms/pages/{$page->cms_id}/unarchive")->assertForbidden();

        $this->assertSame('archived', $page->refresh()->status);

        $this->actingAs($this->superAdmin());
        $this->post("/cms/pages/{$page->cms_id}/unarchive")->assertRedirect();

        $this->assertNotSame('archived', $page->refresh()->status);
    }

    public function test_only_a_super_administrator_deletes_media(): void
    {
        $medium = Media::create([
            'key' => '2026/07/example.png',
            'name' => 'example.png',
            'mime' => 'image/png',
            'size' => 2048,
            'disk' => 's3',
        ]);

        $this->asClient();
        $this->delete("/cms/media/{$medium->id}")->assertForbidden();

        $this->assertDatabaseHas('media', ['id' => $medium->id]);
    }

    public function test_only_a_super_administrator_deletes_a_question(): void
    {
        $faq = Faq::create(['question' => 'Why?', 'answer' => 'Because.', 'position' => 1, 'active' => true]);

        $this->asClient();
        $this->delete("/cms/faqs/{$faq->id}")->assertForbidden();

        $this->assertDatabaseHas('faqs', ['id' => $faq->id]);

        $this->actingAs($this->superAdmin());
        $this->delete("/cms/faqs/{$faq->id}")->assertRedirect();

        /* Off the website and out of the lists, but recoverable — see DeletedContentTest. */
        $this->assertSoftDeleted('faqs', ['id' => $faq->id]);
        $this->assertSame(0, Faq::count());
    }

    public function test_a_client_administrator_still_creates_and_hides_questions(): void
    {
        $this->asClient();

        $this->post('/cms/faqs', ['question' => 'Can I?', 'answer' => 'Yes.'])->assertRedirect();

        $faq = Faq::where('question', 'Can I?')->firstOrFail();

        $this->patch("/cms/faqs/{$faq->id}", [
            'question' => 'Can I?',
            'answer' => 'Yes.',
            'active' => false,
        ])->assertRedirect();

        $this->assertFalse($faq->refresh()->active);
    }

    public function test_the_sidebar_only_offers_modules_the_role_can_open(): void
    {
        $this->asClient();

        $this->get('/cms/pages')->assertInertia(function ($props) {
            $modules = $props->toArray()['props']['auth']['modules'];

            $this->assertTrue($modules['pages']);
            $this->assertFalse($modules['users']);
            $this->assertFalse($modules['settings']);
        });

        $this->actingAs($this->superAdmin())->get('/cms/pages')->assertInertia(function ($props) {
            $modules = $props->toArray()['props']['auth']['modules'];

            $this->assertTrue($modules['users']);
            $this->assertTrue($modules['settings']);
        });
    }

    public function test_a_change_is_recorded_against_the_signed_in_user(): void
    {
        $user = $this->asClient(['name' => 'Daniel Ruiz']);
        $page = $this->home();

        $this->post("/cms/pages/{$page->cms_id}/publish", ['sections' => []])->assertRedirect();

        $page->refresh();

        $this->assertSame($user->name, $page->published_by);
        $this->assertSame($user->name, $page->revisions()->latest('n')->first()->by);
    }

    public function test_the_last_active_super_administrator_cannot_be_demoted(): void
    {
        $me = User::where('role', User::SUPER_ADMIN)->sole();

        $this->patch("/cms/users/{$me->id}", [
            'name' => $me->name,
            'email' => $me->email,
            'role' => User::CLIENT_ADMIN,
        ])->assertStatus(422);

        $this->assertTrue($me->refresh()->isSuperAdmin());
    }

    public function test_a_super_administrator_cannot_delete_their_own_account(): void
    {
        $me = User::where('role', User::SUPER_ADMIN)->sole();

        $this->delete("/cms/users/{$me->id}")->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $me->id]);
    }

    public function test_a_super_administrator_creates_and_promotes_accounts(): void
    {
        $this->post('/cms/users', [
            'name' => 'Anna Kelly',
            'email' => 'anna@example.com',
            'role' => User::CLIENT_ADMIN,
            'password' => 'a-long-enough-password',
        ])->assertRedirect();

        $anna = User::where('email', 'anna@example.com')->firstOrFail();

        $this->assertFalse($anna->isSuperAdmin());

        $this->patch("/cms/users/{$anna->id}", [
            'name' => 'Anna Kelly',
            'email' => 'anna@example.com',
            'role' => User::SUPER_ADMIN,
        ])->assertRedirect();

        $this->assertTrue($anna->refresh()->isSuperAdmin());
    }

    public function test_a_short_password_is_refused(): void
    {
        $this->post('/cms/users', [
            'name' => 'Weak',
            'email' => 'weak@example.com',
            'role' => User::CLIENT_ADMIN,
            'password' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'weak@example.com']);
    }

    public function test_the_command_creates_and_promotes_an_account(): void
    {
        $this->artisan('cms:user', [
            'email' => 'support@example.com',
            '--name' => 'RedHQ Support',
            '--role' => User::SUPER_ADMIN,
            '--password' => 'a-long-enough-password',
        ])->assertSuccessful();

        $user = User::where('email', 'support@example.com')->firstOrFail();

        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->is_active);

        $this->artisan('cms:user', [
            'email' => 'support@example.com',
            '--role' => User::CLIENT_ADMIN,
            '--deactivate' => true,
        ])->assertSuccessful();

        $user->refresh();

        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->is_active);
    }
}
