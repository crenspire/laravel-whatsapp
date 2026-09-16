<?php

namespace Crenspire\Whatsapp\Testing;

use Closure;
use Crenspire\Whatsapp\Exceptions\WhatsappException;
use Crenspire\Whatsapp\WhatsappService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * A stand-in for the WhatsApp service that records messages instead of sending them
 *
 * Swap it in with Whatsapp::fake(). Messages are built exactly as the real
 * service builds them, so assertions see the payload the API would receive.
 */
class WhatsappFake extends WhatsappService
{
    /**
     * @var SentMessage[]
     */
    protected array $sent = [];

    /**
     * @var array<int, array{messageId: string, typing: bool, tenantId: string|null}>
     */
    protected array $readReceipts = [];

    /**
     * @var array<int, array{path: string, type: string, tenantId: string|null}>
     */
    protected array $uploads = [];

    /**
     * @var array<int, array{name: string, language: string, category: string, components: array, tenantId: string|null}>
     */
    protected array $createdTemplates = [];

    /**
     * Templates returned by the template lookup methods
     */
    protected array $templates = [];

    /**
     * Exception thrown for sends, or a closure deciding per message
     */
    protected WhatsappException|Closure|null $failure = null;

    protected int $counter = 0;

    public function __construct(array $config)
    {
        parent::__construct($config);
    }

    /**
     * Make every send throw the given exception
     *
     * Pass a closure to decide per message: it receives the SentMessage and
     * returns an exception to throw, or null to send normally.
     */
    public function failSending(WhatsappException|Closure|null $failure = null): self
    {
        $this->failure = $failure ?? new WhatsappException('Failed to send WhatsApp message: fake failure', 500);

        return $this;
    }

    /**
     * Set the templates returned by getTemplates(), getTemplate() and the status helpers
     *
     * @param  array  $templates  Template arrays with at least name and status
     */
    public function withTemplates(array $templates): self
    {
        $this->templates = array_values($templates);

        return $this;
    }

    public function sendMessage(string $to, array $message, ?string $tenantId = null, array $customHeaders = [], ?string $language = null, ?string $replyTo = null): array
    {
        $this->validatePhoneNumber($to);
        $this->tenantConfig($tenantId);

        $sent = new SentMessage($to, $this->buildMessagePayload($to, $message, $replyTo), $tenantId, $customHeaders);

        $failure = $this->failure instanceof Closure ? ($this->failure)($sent) : $this->failure;

        if ($failure instanceof WhatsappException) {
            throw $failure;
        }

        $this->sent[] = $sent;

        return [
            'messaging_product' => 'whatsapp',
            'contacts' => [['input' => $to, 'wa_id' => SentMessage::digits($to)]],
            'messages' => [['id' => $this->fakeId('wamid')]],
        ];
    }

    public function markMessageAsRead(string $messageId, ?string $tenantId = null, array $customHeaders = []): array
    {
        $this->tenantConfig($tenantId);
        $this->readReceipts[] = ['messageId' => $messageId, 'typing' => false, 'tenantId' => $tenantId];

        return ['success' => true];
    }

    public function showTypingIndicator(string $messageId, ?string $tenantId = null, array $customHeaders = []): array
    {
        $this->tenantConfig($tenantId);
        $this->readReceipts[] = ['messageId' => $messageId, 'typing' => true, 'tenantId' => $tenantId];

        return ['success' => true];
    }

    public function uploadMedia(string $filePath, string $type, ?string $tenantId = null, array $customHeaders = []): array
    {
        $this->tenantConfig($tenantId);
        $this->uploads[] = ['path' => $filePath, 'type' => $type, 'tenantId' => $tenantId];

        return ['id' => $this->fakeId('media')];
    }

    public function downloadMedia(string $mediaId, ?string $tenantId = null, array $customHeaders = []): string
    {
        $path = rtrim($this->config['media_storage'], '/')."/{$mediaId}.bin";
        file_put_contents($path, '');

        return $path;
    }

    public function getMediaInfo(string $mediaId, ?string $tenantId = null, array $customHeaders = []): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'id' => $mediaId,
            'url' => "https://example.com/whatsapp-fake/{$mediaId}",
            'mime_type' => 'application/octet-stream',
            'file_size' => 0,
            'sha256' => hash('sha256', ''),
        ];
    }

    public function deleteMedia(string $mediaId, ?string $tenantId = null, array $customHeaders = []): bool
    {
        return true;
    }

    public function uploadTemplateMedia(string $filePath, ?string $tenantId = null): string
    {
        $this->tenantConfig($tenantId);
        $this->uploads[] = ['path' => $filePath, 'type' => 'template', 'tenantId' => $tenantId];

        return '4::'.$this->fakeId('handle');
    }

    public function getPhoneNumber(?string $tenantId = null, array $fields = ['display_phone_number', 'verified_name', 'quality_rating']): array
    {
        $tenant = $this->tenantConfig($tenantId);

        return [
            'id' => (string) $tenant['phone_number_id'],
            'display_phone_number' => '+1 555-000-0000',
            'verified_name' => 'Test Business',
            'quality_rating' => 'GREEN',
        ];
    }

    public function getWebhookSubscriptions(?string $tenantId = null): array
    {
        return [['whatsapp_business_api_data' => ['id' => (string) ($this->config['app_id'] ?? 'app'), 'name' => 'Test App']]];
    }

    public function getBusinessProfile(?string $tenantId = null, array $customHeaders = []): array
    {
        return ['data' => [['messaging_product' => 'whatsapp']]];
    }

    public function updateBusinessProfile(array $profileData, ?string $tenantId = null, array $customHeaders = []): array
    {
        return ['success' => true];
    }

    public function createTemplate(string $name, string $language, string $category, array $components, ?string $tenantId = null): array
    {
        $this->createdTemplates[] = compact('name', 'language', 'category', 'components', 'tenantId');

        return ['id' => $this->fakeId('template'), 'status' => 'PENDING', 'category' => $category];
    }

    public function updateTemplate(string $name, string $language, string $category, array $components, ?string $tenantId = null): array
    {
        return ['success' => true];
    }

    public function deleteTemplate(string $name, ?string $tenantId = null): bool
    {
        $this->templates = array_values(array_filter($this->templates, fn ($t) => ($t['name'] ?? null) !== $name));

        return true;
    }

    public function getTemplates(array $filters = [], ?string $tenantId = null): array
    {
        $templates = array_filter($this->templates, function ($template) use ($filters) {
            foreach (['name', 'status', 'category', 'language'] as $key) {
                if (isset($filters[$key]) && ($template[$key] ?? null) !== $filters[$key]) {
                    return false;
                }
            }

            return true;
        });

        return ['data' => array_values($templates)];
    }

    public function getTemplate(string $name, ?string $tenantId = null): array
    {
        foreach ($this->templates as $template) {
            if (($template['name'] ?? null) === $name) {
                return $template;
            }
        }

        throw new WhatsappException("Template '{$name}' not found");
    }

    public function getTemplatesByStatus(string $status, ?string $tenantId = null): array
    {
        return $this->getTemplates(['status' => $status]);
    }

    public function getTemplatesByCategory(string $category, ?string $tenantId = null): array
    {
        return $this->getTemplates(['category' => $category]);
    }

    public function getTemplatesByLanguage(string $language, ?string $tenantId = null): array
    {
        return $this->getTemplates(['language' => $language]);
    }

    public function getTemplateStatus(string $name, ?string $tenantId = null): string
    {
        return $this->getTemplate($name)['status'] ?? 'UNKNOWN';
    }

    public function isTemplateApproved(string $name, ?string $tenantId = null): bool
    {
        return $this->getTemplateStatus($name) === 'APPROVED';
    }

    public function isTemplatePending(string $name, ?string $tenantId = null): bool
    {
        return $this->getTemplateStatus($name) === 'PENDING';
    }

    /**
     * Get the messages that were sent, optionally filtered by a callback
     *
     * @param  callable(SentMessage): bool|null  $callback
     * @return Collection<int, SentMessage>
     */
    public function sent(?callable $callback = null): Collection
    {
        $messages = collect($this->sent);

        return $callback ? $messages->filter(fn (SentMessage $message) => $callback($message))->values() : $messages;
    }

    /**
     * Assert a message was sent, optionally matching a callback
     *
     * @param  callable(SentMessage): bool|null  $callback
     */
    public function assertSent(?callable $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->sent($callback)->isNotEmpty(),
            $callback ? 'The expected WhatsApp message was not sent.' : 'No WhatsApp messages were sent.'
        );
    }

    /**
     * Assert a message was sent to a phone number, optionally matching a callback
     *
     * @param  callable(SentMessage): bool|null  $callback
     */
    public function assertSentTo(string $to, ?callable $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->sent(fn (SentMessage $message) => $message->isTo($to) && (! $callback || $callback($message)))->isNotEmpty(),
            "The expected WhatsApp message was not sent to [{$to}].".$this->sentSummary()
        );
    }

    /**
     * Assert a text message was sent to a phone number, optionally with the exact text
     */
    public function assertSentText(string $to, ?string $text = null): void
    {
        $matched = $this->sent(fn (SentMessage $message) => $message->isTo($to)
            && $message->type() === 'text'
            && ($text === null || $message->text() === $text));

        PHPUnit::assertTrue(
            $matched->isNotEmpty(),
            ($text === null
                ? "No text message was sent to [{$to}]."
                : "No text message [{$text}] was sent to [{$to}].").$this->sentSummary()
        );
    }

    /**
     * Assert a template message was sent to a phone number
     */
    public function assertSentTemplate(string $to, string $template, ?string $language = null): void
    {
        $matched = $this->sent(fn (SentMessage $message) => $message->isTo($to)
            && $message->template() === $template
            && ($language === null || $message->language() === $language));

        PHPUnit::assertTrue(
            $matched->isNotEmpty(),
            "Template [{$template}] was not sent to [{$to}].".$this->sentSummary()
        );
    }

    /**
     * Assert no message matching the callback was sent
     *
     * @param  callable(SentMessage): bool  $callback
     */
    public function assertNotSent(callable $callback): void
    {
        PHPUnit::assertTrue(
            $this->sent($callback)->isEmpty(),
            'An unexpected WhatsApp message was sent.'
        );
    }

    /**
     * Assert no message was sent to a phone number
     */
    public function assertNotSentTo(string $to): void
    {
        PHPUnit::assertTrue(
            $this->sent(fn (SentMessage $message) => $message->isTo($to))->isEmpty(),
            "An unexpected WhatsApp message was sent to [{$to}]."
        );
    }

    /**
     * Assert no messages were sent
     */
    public function assertNothingSent(): void
    {
        PHPUnit::assertEmpty($this->sent, 'WhatsApp messages were sent unexpectedly.'.$this->sentSummary());
    }

    /**
     * Assert how many messages were sent
     */
    public function assertSentCount(int $count): void
    {
        PHPUnit::assertCount($count, $this->sent, "Expected {$count} WhatsApp messages, but ".count($this->sent).' were sent.');
    }

    /**
     * Assert a message was marked as read
     */
    public function assertMarkedAsRead(string $messageId): void
    {
        PHPUnit::assertTrue(
            collect($this->readReceipts)->contains('messageId', $messageId),
            "Message [{$messageId}] was not marked as read."
        );
    }

    /**
     * Assert a typing indicator was shown for a message
     */
    public function assertTypingIndicatorShown(string $messageId): void
    {
        PHPUnit::assertTrue(
            collect($this->readReceipts)->contains(fn ($receipt) => $receipt['messageId'] === $messageId && $receipt['typing']),
            "No typing indicator was shown for message [{$messageId}]."
        );
    }

    /**
     * Assert media was uploaded, optionally matching a callback
     *
     * @param  callable(array{path: string, type: string, tenantId: string|null}): bool|null  $callback
     */
    public function assertMediaUploaded(?callable $callback = null): void
    {
        $uploads = collect($this->uploads);

        PHPUnit::assertTrue(
            ($callback ? $uploads->filter($callback) : $uploads)->isNotEmpty(),
            'The expected media was not uploaded.'
        );
    }

    /**
     * Assert a template was created, optionally matching a callback
     *
     * @param  callable(array): bool|null  $callback
     */
    public function assertTemplateCreated(string $name, ?callable $callback = null): void
    {
        PHPUnit::assertTrue(
            collect($this->createdTemplates)
                ->filter(fn ($template) => $template['name'] === $name && (! $callback || $callback($template)))
                ->isNotEmpty(),
            "Template [{$name}] was not created."
        );
    }

    /**
     * Generate a unique fake ID
     */
    protected function fakeId(string $prefix): string
    {
        return "{$prefix}.fake.".++$this->counter;
    }

    /**
     * Describe the sent messages for assertion failure output
     */
    protected function sentSummary(): string
    {
        if ($this->sent === []) {
            return ' No messages were sent.';
        }

        $lines = array_map(
            fn (SentMessage $message) => "  - {$message->type()} to {$message->to}".
                ($message->template() ? " ({$message->template()})" : '').
                ($message->text() !== null ? ': '.mb_strimwidth($message->text(), 0, 60, '...') : ''),
            $this->sent
        );

        return "\nSent messages:\n".implode("\n", $lines);
    }
}
