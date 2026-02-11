<?php

namespace Crenspire\Whatsapp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeliveryFailed extends MessageFailed
{
    public function __construct(
        public string $messageId,
        string $recipient,
        array $error
    )
    {
        parent::__construct($recipient, $error);
    }
}
