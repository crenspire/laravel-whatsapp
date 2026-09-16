<?php

namespace Crenspire\Whatsapp\Http\Controllers;

use Carbon\Carbon;
use Crenspire\Whatsapp\Events\MessageDelivered;
use Crenspire\Whatsapp\Events\MessageFailed;
use Crenspire\Whatsapp\Events\MessageRead;
use Crenspire\Whatsapp\Events\MessageReceived;
use Crenspire\Whatsapp\Events\MessageStatusUpdated;
use Crenspire\Whatsapp\Events\TemplateQualityUpdated;
use Crenspire\Whatsapp\Events\TemplateStatusUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Webhook Controller
 *
 * This controller handles incoming webhooks from WhatsApp Business API,
 * including webhook verification, message status updates, and incoming messages.
 *
 * @author Akshay Joshi <akshay.joshi@crenspire.com>
 *
 * @version 1.0.0
 *
 * @since 1.0.0
 */
class WhatsappWebhookController extends Controller
{
    /**
     * Verify webhook subscription
     *
     * This method handles the initial webhook verification process required by WhatsApp
     * to confirm the webhook endpoint is valid and accessible.
     *
     * @param  Request  $request  The incoming HTTP request
     * @return Response The verification response
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');
        $expectedToken = config('whatsapp.webhook_verify_token');

        if ($mode === 'subscribe'
            && is_string($expectedToken) && $expectedToken !== ''
            && is_string($token) && hash_equals($expectedToken, $token)) {
            Log::info('WhatsApp webhook verified successfully');

            return response($challenge, 200);
        }

        Log::warning('WhatsApp webhook verification failed', [
            'mode' => $mode,
            'token_provided' => $token !== null,
            'token_configured' => ! empty($expectedToken),
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming webhook data
     *
     * This method processes incoming webhook data from WhatsApp, including
     * message status updates and incoming messages.
     *
     * @param  Request  $request  The incoming HTTP request
     * @return Response|JsonResponse The receipt confirmation, or 401 for an invalid signature
     */
    public function handle(Request $request)
    {
        // Verify webhook signature if configured
        if (config('whatsapp.webhook_secret')) {
            if (! $this->verifySignature($request)) {
                Log::warning('WhatsApp webhook signature verification failed');

                return response('Unauthorized', 401);
            }
        } else {
            Log::warning('WhatsApp webhook accepted without signature verification; set WHATSAPP_WEBHOOK_SECRET to your app secret');
        }

        $data = $request->all();

        Log::info('WhatsApp webhook received', [
            'object' => $data['object'] ?? null,
            'entries' => count($data['entry'] ?? []),
        ]);

        // The payload contains user messages, so only log it in debug mode
        if (config('whatsapp.debug')) {
            Log::debug('WhatsApp webhook payload', ['data' => $data]);
        }

        // Meta may batch several entries and changes into one delivery
        foreach ($data['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                if (($change['field'] ?? null) === 'message_template_status_update') {
                    $this->handleTemplateStatusUpdate($value, $entry['id'] ?? null);

                    continue;
                }

                if (($change['field'] ?? null) === 'message_template_quality_update') {
                    $this->handleTemplateQualityUpdate($value, $entry['id'] ?? null);

                    continue;
                }

                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

                if (isset($value['statuses']) && is_array($value['statuses'])) {
                    $this->handleStatusUpdates($value['statuses'], $phoneNumberId);
                }

                if (isset($value['messages']) && is_array($value['messages'])) {
                    $this->handleIncomingMessages($value['messages'], $phoneNumberId, $value['contacts'] ?? []);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Verify webhook signature
     *
     * This method verifies the webhook signature to ensure the request
     * is authentic and comes from WhatsApp.
     *
     * @param  Request  $request  The incoming HTTP request
     * @return bool True if signature is valid, false otherwise
     */
    protected function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Hub-Signature-256');
        $payload = $request->getContent();
        $secret = config('whatsapp.webhook_secret');

        if (! $signature || ! $secret) {
            return false;
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Handle message status updates
     *
     * This method processes message status updates (delivered, read, etc.)
     * and dispatches appropriate events.
     *
     * @param  array  $statuses  Array of status update data
     * @param  string|null  $phoneNumberId  The phone number ID the messages were sent from
     */
    protected function handleStatusUpdates(array $statuses, ?string $phoneNumberId = null): void
    {
        foreach ($statuses as $status) {
            $messageId = $status['id'] ?? null;
            // Users with a username may only be identified by their business-scoped user ID
            $recipient = $status['recipient_id'] ?? $status['recipient_user_id'] ?? null;
            $statusType = $status['status'] ?? null;

            if (! $messageId || ! $recipient || ! $statusType) {
                continue;
            }

            if ($this->isDuplicate("status:{$messageId}:{$statusType}")) {
                continue;
            }

            $timestamp = Carbon::createFromTimestamp($status['timestamp'] ?? time());

            Log::info('WhatsApp message status update', [
                'message_id' => $messageId,
                'recipient' => $recipient,
                'status' => $statusType,
            ]);

            event(new MessageStatusUpdated($messageId, $recipient, $statusType, $timestamp, $status, $phoneNumberId));

            match ($statusType) {
                'delivered' => event(new MessageDelivered($messageId, $recipient, $phoneNumberId, $timestamp)),
                'read' => event(new MessageRead($messageId, $recipient, $timestamp, $phoneNumberId)),
                'failed' => event(new MessageFailed($recipient, $status['errors'] ?? [], $messageId, [], $phoneNumberId)),
                default => null,
            };
        }
    }

    /**
     * Handle incoming messages
     *
     * This method processes incoming messages from WhatsApp users
     * and dispatches the MessageReceived event.
     *
     * @param  array  $messages  Array of incoming message data
     * @param  string|null  $phoneNumberId  The phone number ID that received the messages
     * @param  array  $contacts  The sender contact objects from the webhook
     */
    protected function handleIncomingMessages(array $messages, ?string $phoneNumberId = null, array $contacts = []): void
    {
        foreach ($messages as $message) {
            $messageId = $message['id'] ?? null;
            // Users with a username may only be identified by their business-scoped user ID
            $from = $message['from'] ?? $message['from_user_id'] ?? null;
            $timestamp = $message['timestamp'] ?? time();
            $type = $message['type'] ?? 'unknown';

            if (! $messageId || ! $from) {
                Log::warning('WhatsApp message missing required fields', [
                    'message_id' => $messageId,
                    'type' => $type,
                ]);

                continue;
            }

            if ($this->isDuplicate("message:{$messageId}")) {
                Log::info('Duplicate WhatsApp message skipped', ['message_id' => $messageId]);

                continue;
            }

            Log::info('WhatsApp incoming message', [
                'message_id' => $messageId,
                'from' => $from,
                'type' => $type,
                'timestamp' => $timestamp,
                'context' => $message['context'] ?? null,
            ]);

            // Handle different message types
            $this->processMessageByType($message, $type);

            $contact = collect($contacts)->first(fn ($contact) => ($contact['wa_id'] ?? null) === $from
                || ($contact['user_id'] ?? null) === ($message['from_user_id'] ?? $from)) ?? [];

            event(new MessageReceived(
                $messageId,
                $from,
                $message,
                Carbon::createFromTimestamp($timestamp),
                $phoneNumberId,
                $contact,
                $message['from_user_id'] ?? $contact['user_id'] ?? null,
            ));
        }
    }

    /**
     * Handle a template review or status change
     *
     * @param  array  $value  The change value from the webhook
     * @param  string|null  $businessAccountId  The WhatsApp Business Account ID from the entry
     */
    protected function handleTemplateStatusUpdate(array $value, ?string $businessAccountId): void
    {
        $templateId = isset($value['message_template_id']) ? (string) $value['message_template_id'] : null;
        $status = $value['event'] ?? null;

        if ($templateId === null || $status === null) {
            return;
        }

        if ($this->isDuplicate("template:{$templateId}:{$status}:".md5(json_encode($value)))) {
            return;
        }

        Log::info('WhatsApp template status update', [
            'template_id' => $templateId,
            'name' => $value['message_template_name'] ?? null,
            'status' => $status,
        ]);

        event(new TemplateStatusUpdated(
            $templateId,
            $value['message_template_name'] ?? '',
            $value['message_template_language'] ?? '',
            $status,
            ($value['reason'] ?? 'NONE') === 'NONE' ? null : $value['reason'],
            $value,
            $businessAccountId,
            $value['message_template_category'] ?? null,
        ));
    }

    /**
     * Handle a template quality score change
     *
     * @param  array  $value  The change value from the webhook
     * @param  string|null  $businessAccountId  The WhatsApp Business Account ID from the entry
     */
    protected function handleTemplateQualityUpdate(array $value, ?string $businessAccountId): void
    {
        $templateId = isset($value['message_template_id']) ? (string) $value['message_template_id'] : null;
        $score = $value['new_quality_score'] ?? null;

        if ($templateId === null || $score === null) {
            return;
        }

        if ($this->isDuplicate("template-quality:{$templateId}:".md5(json_encode($value)))) {
            return;
        }

        event(new TemplateQualityUpdated(
            $templateId,
            $value['message_template_name'] ?? '',
            $value['message_template_language'] ?? '',
            $value['previous_quality_score'] ?? null,
            $score,
            $value,
            $businessAccountId,
        ));
    }

    /**
     * Check whether an event was already processed, and remember it if not
     *
     * Meta retries webhooks and can deliver the same event more than once.
     *
     * @param  string  $key  A key identifying the event
     */
    protected function isDuplicate(string $key): bool
    {
        if (! config('whatsapp.webhook.deduplicate', true)) {
            return false;
        }

        $minutes = (int) config('whatsapp.webhook.deduplicate_for', 1440);

        return ! Cache::store(config('whatsapp.webhook.cache_store'))
            ->add("whatsapp:webhook:{$key}", true, now()->addMinutes($minutes));
    }

    /**
     * Process message based on its type
     *
     * @param  array  $message  The message data
     * @param  string  $type  The message type
     */
    protected function processMessageByType(array $message, string $type): void
    {
        switch ($type) {
            case 'text':
                $this->handleTextMessage($message);
                break;
            case 'image':
            case 'video':
            case 'audio':
            case 'document':
            case 'sticker':
                $this->handleMediaMessage($message);
                break;
            case 'location':
                $this->handleLocationMessage($message);
                break;
            case 'contacts':
                $this->handleContactMessage($message);
                break;
            case 'interactive':
                $this->handleInteractiveMessage($message);
                break;
            case 'reaction':
                $this->handleReactionMessage($message);
                break;
            case 'system':
                $this->handleSystemMessage($message);
                break;
            default:
                Log::info('Unknown message type received', [
                    'type' => $type,
                    'message_id' => $message['id'] ?? null,
                ]);
        }
    }

    /**
     * Handle text messages
     *
     * @param  array  $message  The message data
     */
    protected function handleTextMessage(array $message): void
    {
        $text = $message['text']['body'] ?? '';
        Log::info('Text message received', [
            'message_id' => $message['id'] ?? null,
            'text_length' => strlen($text),
            'preview_url' => $message['text']['preview_url'] ?? false,
        ]);
    }

    /**
     * Handle media messages
     *
     * @param  array  $message  The message data
     */
    protected function handleMediaMessage(array $message): void
    {
        $mediaType = $message['type'] ?? 'unknown';
        $mediaId = $message[$mediaType]['id'] ?? null;
        $mimeType = $message[$mediaType]['mime_type'] ?? null;
        $sha256 = $message[$mediaType]['sha256'] ?? null;

        Log::info('Media message received', [
            'message_id' => $message['id'] ?? null,
            'media_type' => $mediaType,
            'media_id' => $mediaId,
            'mime_type' => $mimeType,
            'sha256' => $sha256,
        ]);
    }

    /**
     * Handle location messages
     *
     * @param  array  $message  The message data
     */
    protected function handleLocationMessage(array $message): void
    {
        $location = $message['location'] ?? [];
        $latitude = $location['latitude'] ?? null;
        $longitude = $location['longitude'] ?? null;
        $name = $location['name'] ?? null;
        $address = $location['address'] ?? null;

        Log::info('Location message received', [
            'message_id' => $message['id'] ?? null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'name' => $name,
            'address' => $address,
        ]);
    }

    /**
     * Handle contact messages
     *
     * @param  array  $message  The message data
     */
    protected function handleContactMessage(array $message): void
    {
        $contacts = $message['contacts'] ?? [];

        Log::info('Contact message received', [
            'message_id' => $message['id'] ?? null,
            'contact_count' => count($contacts),
        ]);
    }

    /**
     * Handle interactive messages
     *
     * @param  array  $message  The message data
     */
    protected function handleInteractiveMessage(array $message): void
    {
        $interactive = $message['interactive'] ?? [];
        $type = $interactive['type'] ?? 'unknown';
        $buttonReply = $interactive['button_reply'] ?? null;
        $listReply = $interactive['list_reply'] ?? null;

        Log::info('Interactive message received', [
            'message_id' => $message['id'] ?? null,
            'interactive_type' => $type,
            'button_reply' => $buttonReply,
            'list_reply' => $listReply,
        ]);
    }

    /**
     * Handle reaction messages
     *
     * @param  array  $message  The message data
     */
    protected function handleReactionMessage(array $message): void
    {
        $reaction = $message['reaction'] ?? [];
        $messageId = $reaction['message_id'] ?? null;
        $emoji = $reaction['emoji'] ?? null;

        Log::info('Reaction message received', [
            'message_id' => $message['id'] ?? null,
            'reacted_to_message_id' => $messageId,
            'emoji' => $emoji,
        ]);
    }

    /**
     * Handle system messages
     *
     * @param  array  $message  The message data
     */
    protected function handleSystemMessage(array $message): void
    {
        $system = $message['system'] ?? [];
        $body = $system['body'] ?? null;
        $type = $system['type'] ?? null;

        Log::info('System message received', [
            'message_id' => $message['id'] ?? null,
            'system_type' => $type,
            'body' => $body,
        ]);
    }
}
