<?php

namespace Crenspire\Whatsapp\Notifications;

use Crenspire\Whatsapp\Builders\MessageBuilder;
use Crenspire\Whatsapp\Builders\TemplateBuilder;
use Crenspire\Whatsapp\WhatsappService;

/**
 * A WhatsApp message returned from a notification's toWhatsapp() method
 */
class WhatsappMessage
{
    /**
     * The recipient, overriding the notifiable's route
     */
    public ?string $to = null;

    /**
     * The tenant to send from, overriding the notifiable's route
     */
    public ?string $tenantId = null;

    /**
     * The ID of a message to quote
     */
    public ?string $replyTo = null;

    /**
     * Custom headers for the request
     */
    public array $customHeaders = [];

    /**
     * @param  array  $payload  The message payload (without messaging_product and to)
     * @param  array|null  $template  Template name, parameters and language, resolved when sending
     */
    public function __construct(
        public array $payload = [],
        public ?array $template = null,
    ) {}

    /**
     * A text message
     */
    public static function text(string $body, bool $previewUrl = false): self
    {
        return new self(MessageBuilder::text($body, $previewUrl));
    }

    /**
     * A template message with body parameters
     *
     * The language defaults to the tenant's language, then the configured default.
     */
    public static function template(string $name, array $parameters = [], ?string $language = null): self
    {
        return new self(template: compact('name', 'parameters', 'language'));
    }

    /**
     * A template message built with the template builder, for headers and buttons
     */
    public static function fromTemplate(TemplateBuilder $template): self
    {
        return new self($template->build());
    }

    /**
     * An image, video, audio, document or sticker message
     */
    public static function media(string $type, string $mediaId, ?string $caption = null): self
    {
        return new self(MessageBuilder::media($mediaId, $type, $caption));
    }

    /**
     * A message with reply buttons
     *
     * @param  array  $buttons  [['id' => 'yes', 'title' => 'Yes'], ...]
     */
    public static function buttons(string $body, array $buttons, ?string $header = null, ?string $footer = null): self
    {
        return new self(MessageBuilder::interactive(MessageBuilder::buttonInteractive($body, $buttons, $header, $footer)));
    }

    /**
     * A list message
     */
    public static function list(string $body, string $buttonText, array $sections, ?string $header = null, ?string $footer = null): self
    {
        return new self(MessageBuilder::interactive(MessageBuilder::listInteractive($body, $buttonText, $sections, $header, $footer)));
    }

    /**
     * A location message
     */
    public static function location(float $latitude, float $longitude, ?string $name = null, ?string $address = null): self
    {
        return new self(MessageBuilder::location($latitude, $longitude, $name, $address));
    }

    /**
     * A message from a raw payload, for types the helpers don't cover
     */
    public static function payload(array $payload): self
    {
        return new self($payload);
    }

    /**
     * Send to this phone number instead of the notifiable's route
     */
    public function to(string $phone): self
    {
        $this->to = $phone;

        return $this;
    }

    /**
     * Send from this tenant's phone number
     */
    public function tenant(?string $tenantId): self
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    /**
     * Quote a message the customer sent
     */
    public function replyTo(string $messageId): self
    {
        $this->replyTo = $messageId;

        return $this;
    }

    /**
     * Add custom headers to the request
     */
    public function withHeaders(array $headers): self
    {
        $this->customHeaders = array_merge($this->customHeaders, $headers);

        return $this;
    }

    /**
     * Send the message
     *
     * @return array The API response
     */
    public function send(WhatsappService $whatsapp, string $to): array
    {
        if ($this->template !== null) {
            return $whatsapp->sendTemplateMessage(
                $to,
                $this->template['name'],
                $this->template['parameters'],
                $this->template['language'],
                $this->tenantId,
                $this->customHeaders,
                $this->replyTo,
            );
        }

        return $whatsapp->sendMessage($to, $this->payload, $this->tenantId, $this->customHeaders, null, $this->replyTo);
    }
}
