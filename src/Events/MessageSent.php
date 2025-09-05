<?php

namespace Crenspire\Whatsapp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Message Sent Event
 * 
 * This event is dispatched when a WhatsApp message is successfully sent.
 * 
 * @package Crenspire\Whatsapp\Events
 * @author Akshay Joshi <akshay.joshi@crenspire.com>
 * @version 1.0.0
 * @since 1.0.0
 */
class MessageSent
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance
     * 
     * @param string|null $messageId The WhatsApp message ID
     * @param string $recipient The recipient phone number
     * @param array $response The complete API response
     */
    public function __construct(
        public ?string $messageId,
        public string $recipient,
        public array $response
    ) {
    }
}
