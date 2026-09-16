<?php

namespace Crenspire\Whatsapp\Events;

use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a customer sends you a message
 *
 * The raw message is in $message. The helper methods cover the common fields
 * so you don't have to dig through the array.
 */
class MessageReceived
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string  $messageId  The WhatsApp message ID
     * @param  string  $from  The sender's phone number, or their user ID if WhatsApp didn't share the number
     * @param  array  $message  The raw message object from the webhook
     * @param  Carbon  $timestamp  When the message was sent
     * @param  string|null  $phoneNumberId  The phone number ID that received the message
     * @param  array  $contact  The sender's contact object from the webhook, including their profile name
     * @param  string|null  $userId  The sender's business-scoped user ID (BSUID), if provided
     */
    public function __construct(
        public string $messageId,
        public string $from,
        public array $message,
        public Carbon $timestamp,
        public ?string $phoneNumberId = null,
        public array $contact = [],
        public ?string $userId = null,
    ) {}

    /**
     * The message type, e.g. "text", "image", "interactive" or "button"
     */
    public function type(): string
    {
        return $this->message['type'] ?? 'unknown';
    }

    /**
     * The text of the message
     *
     * Returns the body of text messages, the caption of media messages, and the
     * title of a tapped button or list item.
     */
    public function text(): ?string
    {
        return match ($this->type()) {
            'text' => $this->message['text']['body'] ?? null,
            'button' => $this->message['button']['text'] ?? null,
            'interactive' => $this->message['interactive']['button_reply']['title']
                ?? $this->message['interactive']['list_reply']['title']
                ?? null,
            default => $this->message[$this->type()]['caption'] ?? null,
        };
    }

    /**
     * The ID of the reply button the customer tapped, if any
     */
    public function buttonReplyId(): ?string
    {
        return $this->message['interactive']['button_reply']['id'] ?? null;
    }

    /**
     * The ID of the list item the customer picked, if any
     */
    public function listReplyId(): ?string
    {
        return $this->message['interactive']['list_reply']['id'] ?? null;
    }

    /**
     * The payload of a template quick-reply button the customer tapped, if any
     */
    public function buttonPayload(): ?string
    {
        return $this->message['button']['payload'] ?? null;
    }

    /**
     * The decoded response of a completed WhatsApp Flow, if any
     */
    public function flowResponse(): ?array
    {
        $json = $this->message['interactive']['nfm_reply']['response_json'] ?? null;

        if (! is_string($json)) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * The media ID of an image, video, audio, document or sticker message
     */
    public function mediaId(): ?string
    {
        return $this->message[$this->type()]['id'] ?? null;
    }

    /**
     * The ID of the message the customer replied to
     *
     * Set for button and list replies (the message containing the button), and
     * for replies that quote a message, when WhatsApp includes it.
     */
    public function replyToMessageId(): ?string
    {
        return $this->message['context']['id'] ?? null;
    }

    /**
     * The sender's WhatsApp profile name, if provided
     */
    public function senderName(): ?string
    {
        return $this->contact['profile']['name'] ?? null;
    }

    /**
     * The sender's WhatsApp username, if they have one
     */
    public function senderUsername(): ?string
    {
        return $this->contact['profile']['username'] ?? null;
    }

    /**
     * Whether WhatsApp shared the sender's phone number
     *
     * Users who adopt a username may be identified only by their user ID.
     */
    public function hasPhoneNumber(): bool
    {
        return isset($this->message['from']);
    }

    /**
     * Whether the message is of the given type
     */
    public function isType(string $type): bool
    {
        return $this->type() === $type;
    }
}
