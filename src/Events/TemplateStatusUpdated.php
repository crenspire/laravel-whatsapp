<?php

namespace Crenspire\Whatsapp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when Meta approves, rejects, pauses or disables a message template
 *
 * Requires a webhook subscription to the message_template_status_update field.
 */
class TemplateStatusUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string  $templateId  The template ID
     * @param  string  $name  The template name
     * @param  string  $language  The template language code
     * @param  string  $status  The new status, e.g. "APPROVED", "REJECTED", "PAUSED" or "DISABLED"
     * @param  string|null  $reason  Why the template was rejected, e.g. "INCORRECT_CATEGORY" (null when there's no reason)
     * @param  array  $data  The raw change value from the webhook, including rejection_info and other_info
     * @param  string|null  $businessAccountId  The WhatsApp Business Account ID
     * @param  string|null  $category  The template category
     */
    public function __construct(
        public string $templateId,
        public string $name,
        public string $language,
        public string $status,
        public ?string $reason = null,
        public array $data = [],
        public ?string $businessAccountId = null,
        public ?string $category = null,
    ) {}

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED';
    }
}
