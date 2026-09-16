<?php

namespace Crenspire\Whatsapp\Testing;

/**
 * A message recorded by the WhatsApp fake
 */
class SentMessage
{
    /**
     * @param  string  $to  The recipient phone number, as passed to the send method
     * @param  array  $payload  The full request payload that would have been sent
     * @param  string|null  $tenantId  The tenant the message was sent for
     * @param  array  $customHeaders  Custom headers passed with the message
     */
    public function __construct(
        public string $to,
        public array $payload,
        public ?string $tenantId = null,
        public array $customHeaders = [],
    ) {}

    /**
     * The message type, e.g. "text", "template" or "interactive"
     */
    public function type(): ?string
    {
        return $this->payload['type'] ?? null;
    }

    /**
     * The text body, for text messages, or the body text of interactive messages
     */
    public function text(): ?string
    {
        return $this->payload['text']['body']
            ?? $this->payload['interactive']['body']['text']
            ?? null;
    }

    /**
     * The template name, for template messages
     */
    public function template(): ?string
    {
        return $this->payload['template']['name'] ?? null;
    }

    /**
     * The template language code, for template messages
     */
    public function language(): ?string
    {
        return $this->payload['template']['language']['code'] ?? null;
    }

    /**
     * The ID of the message this one replies to, if any
     */
    public function replyTo(): ?string
    {
        return $this->payload['context']['message_id'] ?? null;
    }

    /**
     * Whether the message was sent to the given phone number, ignoring formatting
     */
    public function isTo(string $phone): bool
    {
        return self::digits($this->to) === self::digits($phone);
    }

    /**
     * Strip everything except digits from a phone number
     */
    public static function digits(string $phone): string
    {
        return preg_replace('/\D/', '', $phone) ?? '';
    }
}
