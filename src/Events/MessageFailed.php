<?php

namespace Crenspire\Whatsapp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Message Failed Event
 * 
 * This event is dispatched when a WhatsApp message fails to send.
 * 
 * @package Crenspire\Whatsapp\Events
 * @author Akshay Joshi <akshay.joshi@crenspire.com>
 * @version 1.0.0
 * @since 1.0.0
 */
class MessageFailed
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance
     * 
     * @param string $recipient The recipient phone number
     * @param array $error The error details from the API
     */
    public function __construct(
        public string $recipient,
        public array $error
    ) {
    }
}
