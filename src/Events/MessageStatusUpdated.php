<?php

namespace Crenspire\Whatsapp\Events;

use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched for every message status webhook: sent, delivered, read, failed, and any others Meta adds
 *
 * MessageDelivered, MessageRead and MessageFailed are dispatched as well for those statuses.
 */
class MessageStatusUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string  $messageId  The WhatsApp message ID
     * @param  string  $recipient  The recipient phone number
     * @param  string  $status  The new status, e.g. "sent", "delivered", "read" or "failed"
     * @param  Carbon  $timestamp  When the status changed
     * @param  array  $data  The raw status object from the webhook, including errors, conversation and pricing
     * @param  string|null  $phoneNumberId  The phone number ID the message was sent from
     */
    public function __construct(
        public string $messageId,
        public string $recipient,
        public string $status,
        public Carbon $timestamp,
        public array $data = [],
        public ?string $phoneNumberId = null,
    ) {}

    /**
     * The errors reported for a failed message
     */
    public function errors(): array
    {
        return $this->data['errors'] ?? [];
    }
}
