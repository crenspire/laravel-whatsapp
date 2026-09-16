<?php

namespace Crenspire\Whatsapp\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A sent or received WhatsApp message, stored when the message log is enabled
 *
 * @property int $id
 * @property string|null $wamid
 * @property string $direction inbound or outbound
 * @property string $phone The customer's phone number, digits only
 * @property string|null $phone_number_id
 * @property string|null $tenant
 * @property string $type
 * @property string|null $body
 * @property array|null $payload
 * @property string $status accepted, sent, delivered, read, failed or received
 * @property array|null $errors
 * @property string|null $reply_to
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Message extends Model
{
    public const INBOUND = 'inbound';

    public const OUTBOUND = 'outbound';

    protected $table = 'whatsapp_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'errors' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('whatsapp.message_log.connection') ?? parent::getConnectionName();
    }

    /**
     * Messages you received
     */
    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', self::INBOUND);
    }

    /**
     * Messages you sent
     */
    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('direction', self::OUTBOUND);
    }

    /**
     * The conversation with a customer, oldest message first
     */
    public function scopeConversation(Builder $query, string $phone): Builder
    {
        $query->where('phone', preg_replace('/\D/', '', $phone))
            ->orderBy('created_at')
            ->orderBy('id');

        return $query;
    }

    public function isInbound(): bool
    {
        return $this->direction === self::INBOUND;
    }

    public function isOutbound(): bool
    {
        return $this->direction === self::OUTBOUND;
    }
}
