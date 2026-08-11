<?php

namespace Crenspire\Whatsapp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TemplateStatusUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $templateId,
        public string $status,
        public array $changes,
    )
    {
    }
}
