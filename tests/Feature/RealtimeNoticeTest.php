<?php

namespace Tests\Feature;

use App\Broadcasting\CmsChannel;
use App\Events\EnquiryReceived;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The nudge that tells an open CMS screen the inbox has changed.
 *
 * What is worth pinning here is not that a socket works — that is Reverb's business — but the two
 * decisions around it: that the message carries nothing about the person who sent the enquiry, and
 * that nothing about notifying anybody can cost a visitor their enquiry.
 */
class RealtimeNoticeTest extends TestCase
{
    private function send(): void
    {
        $this->post('/enquiries', [
            'name' => 'Janet Reid',
            'email' => 'janet@example.com',
            'message' => 'Thinking about downsizing.',
            'consent' => true,
            'page' => '/contact',
        ]);
    }

    public function test_an_arrival_is_announced(): void
    {
        Event::fake([EnquiryReceived::class]);

        $this->send();

        Event::assertDispatched(EnquiryReceived::class);
    }

    public function test_it_says_that_something_arrived_and_nothing_about_who(): void
    {
        /*
         * The whole design of this event. A payload carrying the name, suburb or first line of the
         * message would put somebody's account of their own circumstances into a queue record and a
         * socket frame, delivered to every signed-in browser whether or not anyone is looking at the
         * inbox. The screen refetches through `/cms/enquiries` instead, which is authorised, filtered
         * and paged exactly as it is when somebody presses reload.
         */
        $event = new EnquiryReceived;

        $this->assertSame([], $event->broadcastWith());
        $this->assertSame('enquiry.received', $event->broadcastAs());

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        /* Private, so it is subject to the authorisation below rather than readable by anybody who
           knows the channel name. */
        $this->assertSame('private-cms', (string) $channels[0]);
    }

    public function test_the_enquiry_survives_a_broadcaster_that_is_not_there(): void
    {
        /* Reverb down, and the queue synchronous, so the failure happens inside the request that just
           saved somebody's enquiry. They must not be told it failed — it did not. */
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'k',
            'broadcasting.connections.reverb.secret' => 's',
            'broadcasting.connections.reverb.app_id' => '1',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 1,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);

        $this->send();

        $this->assertSame(1, Enquiry::count());
        $this->assertSame('Janet Reid', Enquiry::sole()->name);
    }

    /*
     * The rule these three check is `CmsChannel`, not the `/broadcasting/auth` endpoint. Going through
     * the endpoint was tried first and had to be abandoned: under the `null` broadcaster this suite
     * runs with, an authorisation request is answered without the channel callback being consulted at
     * all — every channel refused every caller, admins included, so the two denial tests were passing
     * without testing anything. A test that cannot tell a right answer from a broken harness is worse
     * than no test. The endpoint itself was checked by hand against a running Reverb.
     */
    public function test_the_admin_channel_is_open_to_the_admin(): void
    {
        $this->assertTrue(CmsChannel::allows(User::factory()->clientAdmin()->create()));
        $this->assertTrue(CmsChannel::allows(User::factory()->superAdmin()->create()));
    }

    public function test_a_deactivated_account_cannot_keep_listening(): void
    {
        /* `Permit` locks a deactivated account out of `/cms` on its next request. A socket it could
           still listen on would be a way around the screen it was just locked out of. */
        $this->assertFalse(CmsChannel::allows(User::factory()->clientAdmin()->deactivated()->create()));
    }

    public function test_a_visitor_cannot_listen_at_all(): void
    {
        $this->assertFalse(CmsChannel::allows(null));
    }

    public function test_the_channel_is_registered_under_the_name_the_browser_subscribes_to(): void
    {
        /* The rule above is only reached if the name matches what `realtime.js` asks for, and a
           private channel arrives prefixed. */
        $this->assertStringContainsString(
            "Broadcast::channel('cms'",
            file_get_contents(base_path('routes/channels.php')),
        );
        $this->assertSame('private-cms', (string) (new EnquiryReceived)->broadcastOn()[0]);
    }
}
