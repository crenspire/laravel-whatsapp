<?php

namespace Crenspire\Whatsapp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when the API accepts a message you sent
 */
class MessageSent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string|null  $messageId  The WhatsApp message ID (wamid)
     * @param  string  $recipient  The recipient phone number
     * @param  array  $response  The API response
     * @param  array  $payload  The message payload that was sent
     * @param  string|null  $phoneNumberId  The phone number ID the message was sent from
     * @param  string|null  $tenantId  The tenant the message was sent for, if any
     */
    public function __construct(
        public ?string $messageId,
        public string $recipient,
        public array $response,
        public array $payload = [],
        public ?string $phoneNumberId = null,
        public ?string $tenantId = null,
    ) {}
}
