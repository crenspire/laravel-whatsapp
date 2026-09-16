<?php

namespace Crenspire\Whatsapp\Listeners;

use Crenspire\Whatsapp\Events\MessageFailed;
use Crenspire\Whatsapp\Events\MessageReceived;
use Crenspire\Whatsapp\Events\MessageSent;
use Crenspire\Whatsapp\Events\MessageStatusUpdated;
use Crenspire\Whatsapp\Models\Message;
use Illuminate\Events\Dispatcher;

/**
 * Stores sent and received messages, and keeps their status up to date
 */
class LogMessages
{
    /**
     * Status order, so a late webhook can't move a message backwards
     */
    private const STATUS_RANK = [
        'accepted' => 0,
        'sent' => 1,
        'delivered' => 2,
        'read' => 3,
        'played' => 3,
    ];

    public function subscribe(Dispatcher $events): array
    {
        return [
            MessageSent::class => 'handleSent',
            MessageFailed::class => 'handleFailed',
            MessageReceived::class => 'handleReceived',
            MessageStatusUpdated::class => 'handleStatusUpdated',
        ];
    }

    public function handleSent(MessageSent $event): void
    {
        $this->safely(function () use ($event) {
            Message::query()->create([
                'wamid' => $event->messageId,
                'direction' => Message::OUTBOUND,
                'phone' => $this->digits($event->recipient),
                'phone_number_id' => $event->phoneNumberId,
                'tenant' => $event->tenantId,
                'type' => $event->payload['type'] ?? 'unknown',
                'body' => $this->outboundBody($event->payload),
                'payload' => $event->payload,
                'status' => 'accepted',
                'reply_to' => $event->payload['context']['message_id'] ?? null,
            ]);
        });
    }

    public function handleFailed(MessageFailed $event): void
    {
        $this->safely(function () use ($event) {
            // Delivery failures reported by webhook update the existing record
            if ($event->messageId !== null) {
                return;
            }

            Message::query()->create([
                'direction' => Message::OUTBOUND,
                'phone' => $this->digits($event->recipient),
                'phone_number_id' => $event->phoneNumberId,
                'tenant' => $event->tenantId,
                'type' => $event->payload['type'] ?? 'unknown',
                'body' => $this->outboundBody($event->payload),
                'payload' => $event->payload ?: null,
                'status' => 'failed',
                'errors' => $event->error ? [$event->error] : null,
                'reply_to' => $event->payload['context']['message_id'] ?? null,
                'failed_at' => now(),
            ]);
        });
    }

    public function handleReceived(MessageReceived $event): void
    {
        $this->safely(function () use ($event) {
            Message::query()->firstOrCreate(['wamid' => $event->messageId], [
                'direction' => Message::INBOUND,
                'phone' => $this->digits($event->from),
                'phone_number_id' => $event->phoneNumberId,
                'tenant' => $this->tenantFor($event->phoneNumberId),
                'type' => $event->type(),
                'body' => $event->text(),
                'payload' => $event->message,
                'status' => 'received',
                'reply_to' => $event->replyToMessageId(),
                'created_at' => $event->timestamp,
            ]);
        });
    }

    public function handleStatusUpdated(MessageStatusUpdated $event): void
    {
        $this->safely(function () use ($event) {
            $message = Message::query()->where('wamid', $event->messageId)->first();

            if ($message === null) {
                return;
            }

            $column = match ($event->status) {
                'sent' => 'sent_at',
                'delivered' => 'delivered_at',
                'read', 'played' => 'read_at',
                'failed' => 'failed_at',
                default => null,
            };

            if ($column !== null && $message->getAttribute($column) === null) {
                $message->setAttribute($column, $event->timestamp);
            }

            if ($event->status === 'failed') {
                $message->status = 'failed';
                $message->errors = $event->errors() ?: null;
            } elseif ($message->status !== 'failed'
                && (self::STATUS_RANK[$event->status] ?? -1) > (self::STATUS_RANK[$message->status] ?? -1)) {
                $message->status = $event->status;
            }

            $message->save();
        });
    }

    /**
     * Run a database write without letting failures break sending or webhook handling
     *
     * The message may already have been sent, so an exception here could make
     * the caller retry and send it twice.
     */
    private function safely(callable $callback): void
    {
        rescue($callback, report: true);
    }

    /**
     * A readable summary of an outbound message
     */
    private function outboundBody(array $payload): ?string
    {
        $type = $payload['type'] ?? null;

        return match ($type) {
            'text' => $payload['text']['body'] ?? null,
            'template' => $payload['template']['name'] ?? null,
            'interactive' => $payload['interactive']['body']['text'] ?? null,
            'reaction' => $payload['reaction']['emoji'] ?? null,
            'location' => $payload['location']['name'] ?? null,
            default => $type !== null ? ($payload[$type]['caption'] ?? null) : null,
        };
    }

    /**
     * Find the configured tenant for a phone number ID
     */
    private function tenantFor(?string $phoneNumberId): ?string
    {
        if ($phoneNumberId === null) {
            return null;
        }

        foreach (config('whatsapp.tenants', []) as $tenantId => $tenant) {
            if ((string) ($tenant['phone_number_id'] ?? '') === $phoneNumberId) {
                return (string) $tenantId;
            }
        }

        return null;
    }

    private function digits(string $phone): string
    {
        return preg_replace('/\D/', '', $phone) ?? $phone;
    }
}
