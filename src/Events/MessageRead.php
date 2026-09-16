<?php

namespace Crenspire\Whatsapp\Events;

use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when the customer reads a message
 */
class MessageRead
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string|null  $messageId  The WhatsApp message ID
     * @param  string  $recipient  The recipient phone number
     * @param  Carbon  $timestamp  When the message was read
     * @param  string|null  $phoneNumberId  The phone number ID the message was sent from
     */
    public function __construct(
        public ?string $messageId,
        public string $recipient,
        public Carbon $timestamp,
        public ?string $phoneNumberId = null,
    ) {}
}
