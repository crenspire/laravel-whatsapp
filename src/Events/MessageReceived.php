<?php

namespace Crenspire\Whatsapp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

/**
 * Message Received Event
 * 
 * This event is dispatched when a WhatsApp message is received from a user.
 * 
 * @package Crenspire\Whatsapp\Events
 * @author Akshay Joshi <akshay.joshi@crenspire.com>
 * @version 1.0.0
 * @since 1.0.0
 */
class MessageReceived
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance
     * 
     * @param string $messageId The WhatsApp message ID
     * @param string $from The sender phone number
     * @param array $message The complete message data
     * @param Carbon $timestamp When the message was received
     */
    public function __construct(
        public string $messageId,
        public string $from,
        public array $message,
        public Carbon $timestamp
    ) {
    }
}
