<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Says that something arrived. Says nothing about who, or what they wrote.
 *
 * The tempting version of this event carries the enquiry — name, suburb, the first line of the
 * message — so an open screen can draw the new row without asking. It is not worth it. A broadcast
 * is a second copy of somebody's account of their own circumstances, travelling to every signed-in
 * browser whether or not anyone is looking at the inbox, and living in a queue payload and a socket
 * frame on the way. The CMS search palette already refuses to index that message for the same
 * reason.
 *
 * So this is a nudge. The browser hears it and refetches through `/cms/enquiries`, which is
 * authorised, filtered and paged exactly as it is when somebody presses reload — one path to the
 * data, one place where the rules about it live, and nothing here to keep in step with the shape of
 * the props.
 *
 * Queued rather than sent inline, which is the difference between a visitor's enquiry being saved
 * and a visitor seeing a 500 because a socket server is down. If nothing is draining the queue, or
 * no Reverb server is running, the CMS keeps behaving exactly as it did before this existed: the
 * inbox updates when somebody looks at it.
 */
class EnquiryReceived implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        /* One channel for the whole admin, because this is a shared inbox — the same reason
           `read_at` lives on the enquiry rather than per account. */
        return [new PrivateChannel('cms')];
    }

    public function broadcastAs(): string
    {
        return 'enquiry.received';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [];
    }
}
