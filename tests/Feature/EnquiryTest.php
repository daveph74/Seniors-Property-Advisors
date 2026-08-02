<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EnquiryTest extends TestCase
{
    private function send(array $overrides = []): TestResponse
    {
        return $this->post('/enquiries', array_merge([
            'name' => 'Janet Reid',
            'email' => 'janet@example.com',
            'phone' => '0400 000 000',
            'suburb' => 'Glen Iris',
            'message' => 'We are thinking about downsizing next year.',
            'consent' => true,
            'page' => '/contact',
        ], $overrides));
    }

    public function test_an_enquiry_is_kept(): void
    {
        $this->send()->assertRedirect()->assertSessionHas('enquiry', 'sent');

        $enquiry = Enquiry::sole();

        $this->assertSame('Janet Reid', $enquiry->name);
        $this->assertSame('janet@example.com', $enquiry->email);
        $this->assertSame('Glen Iris', $enquiry->suburb);
        $this->assertTrue($enquiry->consented);
        $this->assertSame('/contact', $enquiry->page_slug);
        $this->assertNull($enquiry->handled_at);
    }

    public function test_a_visitor_who_is_not_signed_in_can_send_one(): void
    {
        /* Every other test signs in as an administrator; the people using this form never are. */
        auth()->logout();

        $this->send()->assertRedirect();

        $this->assertSame(1, Enquiry::count());
    }

    public function test_a_name_and_a_working_email_are_required(): void
    {
        $this->send(['name' => '', 'email' => 'not-an-email'])
            ->assertSessionHasErrors(['name', 'email']);

        $this->assertSame(0, Enquiry::count());
    }

    public function test_nothing_is_kept_without_consent(): void
    {
        $this->send(['consent' => false])->assertSessionHasErrors('consent');

        $this->assertSame(0, Enquiry::count());
    }

    public function test_markup_is_stripped_from_what_is_kept(): void
    {
        $this->send(['message' => 'Call me <script>alert(1)</script>tomorrow']);

        $this->assertStringNotContainsString('<', Enquiry::sole()->message);
    }

    public function test_the_optional_details_can_be_left_out(): void
    {
        $this->send(['phone' => '', 'suburb' => '', 'message' => ''])->assertRedirect();

        $enquiry = Enquiry::sole();

        $this->assertNull($enquiry->phone);
        $this->assertNull($enquiry->message);
    }

    public function test_a_script_cannot_hammer_the_endpoint(): void
    {
        auth()->logout();

        for ($i = 0; $i < 6; $i++) {
            $this->send(['email' => "sender{$i}@example.com"])->assertRedirect();
        }

        /* Public, and it writes a row on every call. */
        $this->send(['email' => 'seventh@example.com'])->assertStatus(429);

        $this->assertSame(6, Enquiry::count());
    }
}
