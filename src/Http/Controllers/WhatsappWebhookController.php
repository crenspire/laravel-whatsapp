<?php

namespace Crenspire\Whatsapp\Http\Controllers;

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
            Log::info('WhatsApp webhook verified successfully');
            return response($challenge, 200);
        }

        Log::warning('WhatsApp webhook verification failed', [
            'mode' => $mode,
            'token_provided' => $token !== null,
            'expected_token' => config('whatsapp.webhook_verify_token')
        ]);

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
                Log::warning('WhatsApp webhook signature verification failed');
                return response('Unauthorized', 401);
            }
        }

        $data = $request->all();
        
        Log::info('WhatsApp webhook received', [
            'data' => $data
        ]);

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

            Log::info('WhatsApp message status update', [
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
                continue;
            }

            Log::info('WhatsApp incoming message', [
                'message_id' => $messageId,
                'from' => $from,
                'type' => $type,
                'timestamp' => $timestamp
            ]);

            event(new MessageReceived($messageId, $from, $message, \Carbon\Carbon::createFromTimestamp($timestamp)));
        }
    }
}
