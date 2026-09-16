<?php

namespace Crenspire\Whatsapp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a send request fails, or when a webhook reports that delivery failed
 */
class MessageFailed
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string  $recipient  The recipient phone number
     * @param  array  $error  The error from the API response, or the errors list from the webhook
     * @param  string|null  $messageId  The WhatsApp message ID, when the failure was reported by webhook
     * @param  array  $payload  The message payload, when the send request itself failed
     * @param  string|null  $phoneNumberId  The phone number ID the message was sent from
     * @param  string|null  $tenantId  The tenant the message was sent for, if known
     */
    public function __construct(
        public string $recipient,
        public array $error,
        public ?string $messageId = null,
        public array $payload = [],
        public ?string $phoneNumberId = null,
        public ?string $tenantId = null,
    ) {}
}
