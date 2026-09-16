<?php

namespace Crenspire\Whatsapp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a message template's quality score changes
 *
 * Requires a webhook subscription to the message_template_quality_update field.
 * A RED score can lead to the template being paused.
 */
class TemplateQualityUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string  $templateId  The template ID
     * @param  string  $name  The template name
     * @param  string  $language  The template language code
     * @param  string|null  $previousScore  The previous score: GREEN, YELLOW, RED or UNKNOWN
     * @param  string  $newScore  The new score: GREEN, YELLOW, RED or UNKNOWN
     * @param  array  $data  The raw change value from the webhook
     * @param  string|null  $businessAccountId  The WhatsApp Business Account ID
     */
    public function __construct(
        public string $templateId,
        public string $name,
        public string $language,
        public ?string $previousScore,
        public string $newScore,
        public array $data = [],
        public ?string $businessAccountId = null,
    ) {}
}
