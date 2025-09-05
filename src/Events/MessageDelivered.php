<?php

namespace Crenspire\Whatsapp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Message Delivered Event
 * 
 * This event is dispatched when a WhatsApp message is delivered to the recipient.
 * 
 * @package Crenspire\Whatsapp\Events
 * @author Akshay Joshi <akshay.joshi@crenspire.com>
 * @version 1.0.0
 * @since 1.0.0
 */
class MessageDelivered
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance
     * 
     * @param string|null $messageId The WhatsApp message ID
     * @param string $recipient The recipient phone number
     */
    public function __construct(
        public ?string $messageId,
        public string $recipient
    ) {
    }
}
