<?php

namespace Crenspire\Whatsapp\Http\Controllers;

use Crenspire\Whatsapp\Events\MessageDeliveryFailed;
use Crenspire\Whatsapp\Events\MessageFailed;
use Crenspire\Whatsapp\Facades\Whatsapp;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Crenspire\Whatsapp\Events\MessageDelivered;
use Crenspire\Whatsapp\Events\MessageRead;
use Crenspire\Whatsapp\Events\MessageReceived;

/**
 * WhatsApp Webhook Controller
 * 
 * This controller handles incoming webhooks from WhatsApp Business API,
 * including webhook verification, message status updates, and incoming messages.
 * 
 * @package Crenspire\Whatsapp\Http\Controllers
 * @author Akshay Joshi <akshay.joshi@crenspire.com>
 * @version 1.0.0
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
     * @param Request $request The incoming HTTP request
     * @return \Illuminate\Http\Response The verification response
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('whatsapp.webhook_verify_token')) {
            Whatsapp::createDebugLog('WhatsApp webhook verified successfully');
            return response($challenge, 200);
        }

        Whatsapp::createDebugLog('WhatsApp webhook verification failed', [
            'mode' => $mode,
            'token_provided' => $token !== null,
            'expected_token' => config('whatsapp.webhook_verify_token')
        ], 'warning');

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming webhook data
     * 
     * This method processes incoming webhook data from WhatsApp, including
     * message status updates and incoming messages.
     * 
     * @param Request $request The incoming HTTP request
     * @return \Illuminate\Http\JsonResponse The response confirming receipt
     */
    public function handle(Request $request)
    {
        // Verify webhook signature if configured
        if (config('whatsapp.webhook_secret')) {
            if (!$this->verifySignature($request)) {
                Whatsapp::createDebugLog('WhatsApp webhook signature verification failed', level: 'warning');
                return response('Unauthorized', 401);
            }
        }

        $data = $request->all();

        Whatsapp::createDebugLog('WhatsApp webhook received', ['data' => $data]);

        // Handle message status updates
        if (isset($data['entry'][0]['changes'][0]['value']['statuses'])) {
            $this->handleStatusUpdates($data['entry'][0]['changes'][0]['value']['statuses']);
        }

        // Handle incoming messages
        if (isset($data['entry'][0]['changes'][0]['value']['messages'])) {
            $this->handleIncomingMessages($data['entry'][0]['changes'][0]['value']['messages']);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Verify webhook signature
     * 
     * This method verifies the webhook signature to ensure the request
     * is authentic and comes from WhatsApp.
     * 
     * @param Request $request The incoming HTTP request
     * @return bool True if signature is valid, false otherwise
     */
    protected function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Hub-Signature-256');
        $payload = $request->getContent();
        $secret = config('whatsapp.webhook_secret');

        if (!$signature || !$secret) {
            return false;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Handle message status updates
     * 
     * This method processes message status updates (delivered, read, etc.)
     * and dispatches appropriate events.
     * 
     * @param array $statuses Array of status update data
     * @return void
     */
    protected function handleStatusUpdates(array $statuses): void
    {
        foreach ($statuses as $status) {
            $messageId = $status['id'] ?? null;
            $recipient = $status['recipient_id'] ?? null;
            $statusType = $status['status'] ?? null;

            if (!$messageId || !$recipient || !$statusType) {
                continue;
            }

            Whatsapp::createDebugLog('WhatsApp message status update', [
                'message_id' => $messageId,
                'recipient' => $recipient,
                'status' => $statusType
            ]);

            switch ($statusType) {
                case 'delivered':
                    event(new MessageDelivered($messageId, $recipient));
                    break;
                case 'read':
                    $timestamp = $status['timestamp'] ?? time();
                    event(new MessageRead(
                        $messageId,
                        $recipient,
                        \Carbon\Carbon::createFromTimestamp($timestamp)
                    ));
                    break;
                case 'failed':
                    event(new MessageDeliveryFailed($messageId, $recipient, $status['errors'] ?? []));
                    break;
            }
        }
    }

    /**
     * Handle incoming messages
     * 
     * This method processes incoming messages from WhatsApp users
     * and dispatches the MessageReceived event.
     * 
     * @param array $messages Array of incoming message data
     * @return void
     */
    protected function handleIncomingMessages(array $messages): void
    {
        foreach ($messages as $message) {
            $messageId = $message['id'] ?? null;
            $from = $message['from'] ?? null;
            $timestamp = $message['timestamp'] ?? time();
            $type = $message['type'] ?? 'unknown';

            if (!$messageId || !$from) {
                Whatsapp::createDebugLog('WhatsApp message missing required fields', [
                    'message_id' => $messageId,
                    'from' => $from,
                    'message' => $message
                ], 'warning');
                continue;
            }

            Whatsapp::createDebugLog('WhatsApp incoming message', [
                'message_id' => $messageId,
                'from' => $from,
                'type' => $type,
                'timestamp' => $timestamp,
                'context' => $message['context'] ?? null
            ]);

            // Handle different message types
            $this->processMessageByType($message, $type);

            event(new MessageReceived($messageId, $from, $message, \Carbon\Carbon::createFromTimestamp($timestamp)));
        }
    }

    /**
     * Process message based on its type
     * 
     * @param array $message The message data
     * @param string $type The message type
     * @return void
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
                Whatsapp::createDebugLog('Unknown message type received', [
                    'type' => $type,
                    'message_id' => $message['id'] ?? null
                ]);
        }
    }

    /**
     * Handle text messages
     * 
     * @param array $message The message data
     * @return void
     */
    protected function handleTextMessage(array $message): void
    {
        $text = $message['text']['body'] ?? '';
        Whatsapp::createDebugLog('Text message received', [
            'message_id' => $message['id'] ?? null,
            'text_length' => strlen($text),
            'preview_url' => $message['text']['preview_url'] ?? false
        ]);
    }

    /**
     * Handle media messages
     * 
     * @param array $message The message data
     * @return void
     */
    protected function handleMediaMessage(array $message): void
    {
        $mediaType = $message['type'] ?? 'unknown';
        $mediaId = $message[$mediaType]['id'] ?? null;
        $mimeType = $message[$mediaType]['mime_type'] ?? null;
        $sha256 = $message[$mediaType]['sha256'] ?? null;

        Whatsapp::createDebugLog('Media message received', [
            'message_id' => $message['id'] ?? null,
            'media_type' => $mediaType,
            'media_id' => $mediaId,
            'mime_type' => $mimeType,
            'sha256' => $sha256
        ]);
    }

    /**
     * Handle location messages
     * 
     * @param array $message The message data
     * @return void
     */
    protected function handleLocationMessage(array $message): void
    {
        $location = $message['location'] ?? [];
        $latitude = $location['latitude'] ?? null;
        $longitude = $location['longitude'] ?? null;
        $name = $location['name'] ?? null;
        $address = $location['address'] ?? null;

        Whatsapp::createDebugLog('Location message received', [
            'message_id' => $message['id'] ?? null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'name' => $name,
            'address' => $address
        ]);
    }

    /**
     * Handle contact messages
     * 
     * @param array $message The message data
     * @return void
     */
    protected function handleContactMessage(array $message): void
    {
        $contacts = $message['contacts'] ?? [];
        
        Whatsapp::createDebugLog('Contact message received', [
            'message_id' => $message['id'] ?? null,
            'contact_count' => count($contacts)
        ]);
    }

    /**
     * Handle interactive messages
     * 
     * @param array $message The message data
     * @return void
     */
    protected function handleInteractiveMessage(array $message): void
    {
        $interactive = $message['interactive'] ?? [];
        $type = $interactive['type'] ?? 'unknown';
        $buttonReply = $interactive['button_reply'] ?? null;
        $listReply = $interactive['list_reply'] ?? null;

        Whatsapp::createDebugLog('Interactive message received', [
            'message_id' => $message['id'] ?? null,
            'interactive_type' => $type,
            'button_reply' => $buttonReply,
            'list_reply' => $listReply
        ]);
    }

    /**
     * Handle reaction messages
     * 
     * @param array $message The message data
     * @return void
     */
    protected function handleReactionMessage(array $message): void
    {
        $reaction = $message['reaction'] ?? [];
        $messageId = $reaction['message_id'] ?? null;
        $emoji = $reaction['emoji'] ?? null;

        Whatsapp::createDebugLog('Reaction message received', [
            'message_id' => $message['id'] ?? null,
            'reacted_to_message_id' => $messageId,
            'emoji' => $emoji
        ]);
    }

    /**
     * Handle system messages
     * 
     * @param array $message The message data
     * @return void
     */
    protected function handleSystemMessage(array $message): void
    {
        $system = $message['system'] ?? [];
        $body = $system['body'] ?? null;
        $type = $system['type'] ?? null;

        Whatsapp::createDebugLog('System message received', [
            'message_id' => $message['id'] ?? null,
            'system_type' => $type,
            'body' => $body
        ]);
    }
}
