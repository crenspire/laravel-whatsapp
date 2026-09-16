<?php

namespace Crenspire\Whatsapp\Events;

use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a message reaches the customer's phone
 */
class MessageDelivered
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string|null  $messageId  The WhatsApp message ID
     * @param  string  $recipient  The recipient phone number
     * @param  string|null  $phoneNumberId  The phone number ID the message was sent from
     * @param  Carbon|null  $timestamp  When the message was delivered
     */
    public function __construct(
        public ?string $messageId,
        public string $recipient,
        public ?string $phoneNumberId = null,
        public ?Carbon $timestamp = null,
    ) {}
}
