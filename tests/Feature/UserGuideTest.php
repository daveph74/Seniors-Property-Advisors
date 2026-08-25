<?php

namespace Tests\Feature;

use App\Docs\UserGuide;
use App\Models\User;
use Tests\TestCase;

/**
 * The guide exists twice — as markdown, which is what anybody edits, and as the page staff are
 * actually given. Two copies of anything drift, and this pair would drift in the worst direction:
 * silently, and towards the copy nobody reads being the correct one. A sentence fixed in the markdown
 * would go on being wrong on the page for as long as nobody happened to rebuild it.
 *
 * So the first test is the same shape as FindMyAgentOptionsParityTest, and for the same reason. The
 * rest are about `/cms/help`, where the failures are all quiet ones.
 */
class UserGuideTest extends TestCase
{
    public function test_the_built_page_is_what_the_markdown_currently_says(): void
    {
        $this->artisan('docs:guide --check')
            ->assertSuccessful();
    }

    public function test_both_files_are_where_the_command_thinks_they_are(): void
    {
        $this->assertFileExists(base_path(UserGuide::SOURCE));
        $this->assertFileExists(base_path(UserGuide::OUTPUT));
    }

    public function test_the_guide_is_served_to_somebody_signed_in(): void
    {
        $this->get('/cms/help')
            ->assertOk()
            ->assertSee('Using the CMS', false)
            ->assertSee('Saving is not publishing.', false);
    }

    public function test_a_client_administrator_gets_it_too(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::CLIENT_ADMIN]));

        $this->get('/cms/help')->assertOk();
    }

    public function test_a_signed_out_visitor_does_not(): void
    {
        auth()->logout();

        $this->get('/cms/help')->assertRedirect('/login');
    }

    /**
     * The quiet one. `script-src` is `'self'` plus a per-request nonce and no `'unsafe-inline'`, so
     * the guide's own inline script — the contents list that follows the reader down the page — is
     * refused unless it carries the nonce this very response declares. Nothing looks broken when it
     * is missing: the page renders perfectly and the contents simply stop tracking.
     */
    public function test_the_inline_script_carries_the_nonce_the_policy_declares(): void
    {
        $response = $this->get('/cms/help')->assertOk();

        $policy = $response->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src [^;]*'nonce-([A-Za-z0-9+\/=]+)'/", $policy);
        preg_match("/script-src [^;]*'nonce-([A-Za-z0-9+\/=]+)'/", $policy, $found);

        $this->assertStringContainsString(
            '<script nonce="'.$found[1].'">',
            $response->getContent(),
            'The guide\'s inline script is not carrying the nonce, so the policy will refuse it.',
        );
    }

    /**
     * The guide describes roles. Who currently holds one is the Users screen's business — a document
     * naming colleagues is out of date the first time somebody leaves, and it is handed out as a file.
     */
    public function test_it_names_no_accounts(): void
    {
        $user = User::factory()->create(['name' => 'Dana Reeves']);

        $this->get('/cms/help')
            ->assertOk()
            ->assertDontSee($user->name, false)
            ->assertDontSee($user->email, false);

        $this->assertStringNotContainsString(
            $user->email,
            file_get_contents(base_path(UserGuide::OUTPUT)),
        );
    }
}
