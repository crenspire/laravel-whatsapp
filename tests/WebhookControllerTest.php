<?php

use Crenspire\Whatsapp\Events\MessageDelivered;
use Crenspire\Whatsapp\Events\MessageFailed;
use Crenspire\Whatsapp\Events\MessageRead;
use Crenspire\Whatsapp\Events\MessageReceived;
use Crenspire\Whatsapp\Events\MessageStatusUpdated;
use Crenspire\Whatsapp\Events\TemplateQualityUpdated;
use Crenspire\Whatsapp\Events\TemplateStatusUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

// Verification

it('returns the challenge for a valid verification request', function () {
    $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=test_verify_token&hub.challenge=abc123')
        ->assertOk()
        ->assertSee('abc123');
});

it('rejects verification with the wrong token', function () {
    $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=abc123')
        ->assertForbidden();
});

it('rejects verification when no token is configured', function () {
    config(['whatsapp.webhook_verify_token' => null]);

    $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.challenge=abc123')
        ->assertForbidden();
});

it('does not log the expected verify token', function () {
    Log::spy();

    $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=wrong');

    Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => ! str_contains(json_encode($context), 'test_verify_token'));
});

// Signature

it('rejects payloads with an invalid signature when a secret is configured', function () {
    Event::fake();
    config(['whatsapp.webhook_secret' => 'app_secret']);

    $this->postJson('/whatsapp/webhook', webhookPayload([]), ['X-Hub-Signature-256' => 'sha256=invalid'])
        ->assertUnauthorized();
});

it('accepts payloads with a valid signature', function () {
    Event::fake();
    config(['whatsapp.webhook_secret' => 'app_secret']);
    $body = json_encode(webhookPayload([]));

    $this->call('POST', '/whatsapp/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'app_secret'),
    ], $body)->assertOk();
});

// Events

it('dispatches events for incoming messages and status updates', function () {
    Event::fake();

    $this->postJson('/whatsapp/webhook', webhookPayload([webhookEntry([
        'messages' => [[
            'id' => 'wamid.in',
            'from' => '15551234567',
            'timestamp' => '1700000000',
            'type' => 'text',
            'text' => ['body' => 'Hello'],
        ]],
        'statuses' => [
            ['id' => 'wamid.1', 'recipient_id' => '15550000001', 'status' => 'delivered', 'timestamp' => '1700000000'],
            ['id' => 'wamid.2', 'recipient_id' => '15550000002', 'status' => 'read', 'timestamp' => '1700000100'],
            ['id' => 'wamid.3', 'recipient_id' => '15550000003', 'status' => 'failed', 'timestamp' => '1700000200',
                'errors' => [['code' => 131026, 'title' => 'Message undeliverable']]],
        ],
    ])]))->assertOk()->assertJson(['status' => 'ok']);

    Event::assertDispatched(MessageReceived::class, fn ($event) => $event->messageId === 'wamid.in'
        && $event->from === '15551234567'
        && $event->timestamp->timestamp === 1700000000);
    Event::assertDispatched(MessageDelivered::class, fn ($event) => $event->messageId === 'wamid.1');
    Event::assertDispatched(MessageRead::class, fn ($event) => $event->messageId === 'wamid.2' && $event->timestamp->timestamp === 1700000100);
    Event::assertDispatched(MessageFailed::class, fn ($event) => $event->messageId === 'wamid.3'
        && $event->recipient === '15550000003'
        && $event->error[0]['code'] === 131026);
});

it('processes every entry and change in a batched payload', function () {
    Event::fake();
    $message = fn (string $id) => ['id' => $id, 'from' => '15551234567', 'timestamp' => '1700000000', 'type' => 'text', 'text' => ['body' => 'Hi']];

    $this->postJson('/whatsapp/webhook', webhookPayload([
        [
            'id' => 'waba_1',
            'changes' => [
                ['field' => 'messages', 'value' => ['messages' => [$message('wamid.a')]]],
                ['field' => 'messages', 'value' => ['messages' => [$message('wamid.b')]]],
            ],
        ],
        webhookEntry(['messages' => [$message('wamid.c')]]),
    ]))->assertOk();

    Event::assertDispatchedTimes(MessageReceived::class, 3);
});

it('skips incomplete messages and statuses', function () {
    Event::fake();

    $this->postJson('/whatsapp/webhook', webhookPayload([webhookEntry([
        'messages' => [['type' => 'text']],
        'statuses' => [['status' => 'delivered']],
    ])]))->assertOk();

    Event::assertNotDispatched(MessageReceived::class);
    Event::assertNotDispatched(MessageDelivered::class);
});

it('only logs the full payload in debug mode', function () {
    Event::fake();
    Log::spy();

    $this->postJson('/whatsapp/webhook', webhookPayload([webhookEntry([
        'messages' => [['id' => 'wamid.in', 'from' => '15551234567', 'type' => 'text', 'text' => ['body' => 'secret text']]],
    ])]));

    Log::shouldNotHaveReceived('debug');
    Log::shouldNotHaveReceived('info', [Mockery::any(), Mockery::on(fn ($context) => str_contains(json_encode($context), 'secret text'))]);
});

it('warns when accepting payloads without a configured secret', function () {
    Event::fake();
    Log::spy();

    $this->postJson('/whatsapp/webhook', webhookPayload([]))->assertOk();

    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'without signature verification'));
});

it('does not warn about signatures when a secret is configured', function () {
    Event::fake();
    Log::spy();
    config(['whatsapp.webhook_secret' => 'app_secret']);
    $body = json_encode(webhookPayload([]));

    $this->call('POST', '/whatsapp/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'app_secret'),
    ], $body)->assertOk();

    Log::shouldNotHaveReceived('warning');
});

// Duplicates, metadata and template events

function textMessage(string $id = 'wamid.in', array $extra = []): array
{
    return array_merge(['id' => $id, 'from' => '15551234567', 'timestamp' => '1700000000', 'type' => 'text', 'text' => ['body' => 'Hi']], $extra);
}

it('skips duplicate messages and statuses delivered again', function () {
    Event::fake();
    $payload = webhookPayload([webhookEntry([
        'messages' => [textMessage()],
        'statuses' => [['id' => 'wamid.1', 'recipient_id' => '15550000001', 'status' => 'read', 'timestamp' => '1700000000']],
    ])]);

    $this->postJson('/whatsapp/webhook', $payload)->assertOk();
    $this->postJson('/whatsapp/webhook', $payload)->assertOk();

    Event::assertDispatchedTimes(MessageReceived::class, 1);
    Event::assertDispatchedTimes(MessageRead::class, 1);
});

it('still dispatches each new status for the same message', function () {
    Event::fake();

    foreach (['sent', 'delivered', 'read'] as $status) {
        $this->postJson('/whatsapp/webhook', webhookPayload([webhookEntry([
            'statuses' => [['id' => 'wamid.1', 'recipient_id' => '15550000001', 'status' => $status, 'timestamp' => '1700000000']],
        ])]));
    }

    Event::assertDispatchedTimes(MessageStatusUpdated::class, 3);
    Event::assertDispatched(MessageStatusUpdated::class, fn ($e) => $e->status === 'sent');
});

it('processes duplicates when deduplication is disabled', function () {
    Event::fake();
    config(['whatsapp.webhook.deduplicate' => false]);
    $payload = webhookPayload([webhookEntry(['messages' => [textMessage()]])]);

    $this->postJson('/whatsapp/webhook', $payload);
    $this->postJson('/whatsapp/webhook', $payload);

    Event::assertDispatchedTimes(MessageReceived::class, 2);
});

it('passes the receiving phone number ID and sender contact to events', function () {
    Event::fake();

    $this->postJson('/whatsapp/webhook', webhookPayload([webhookEntry([
        'metadata' => ['display_phone_number' => '15550009999', 'phone_number_id' => 'pn_1'],
        'contacts' => [['profile' => ['name' => 'Sarah'], 'wa_id' => '15551234567']],
        'messages' => [textMessage()],
        'statuses' => [['id' => 'wamid.out', 'recipient_id' => '15550000001', 'status' => 'delivered', 'timestamp' => '1700000000']],
    ])]));

    Event::assertDispatched(MessageReceived::class, fn ($e) => $e->phoneNumberId === 'pn_1' && $e->senderName() === 'Sarah');
    Event::assertDispatched(MessageDelivered::class, fn ($e) => $e->phoneNumberId === 'pn_1' && $e->timestamp?->timestamp === 1700000000);
});

it('accepts messages identified only by a business-scoped user ID', function () {
    Event::fake();

    $this->postJson('/whatsapp/webhook', webhookPayload([webhookEntry([
        'contacts' => [['profile' => ['name' => 'Sam', 'username' => 'sam'], 'user_id' => 'US.123']],
        'messages' => [['id' => 'wamid.bsuid', 'from_user_id' => 'US.123', 'timestamp' => '1700000000', 'type' => 'text', 'text' => ['body' => 'Hi']]],
        'statuses' => [['id' => 'wamid.out', 'recipient_user_id' => 'US.456', 'status' => 'read', 'timestamp' => '1700000000']],
    ])]));

    Event::assertDispatched(MessageReceived::class, fn ($e) => $e->from === 'US.123'
        && $e->userId === 'US.123'
        && ! $e->hasPhoneNumber()
        && $e->senderUsername() === 'sam');
    Event::assertDispatched(MessageRead::class, fn ($e) => $e->recipient === 'US.456');
});

it('dispatches template status and quality events', function () {
    Event::fake();

    $this->postJson('/whatsapp/webhook', webhookPayload([
        ['id' => 'waba_123', 'time' => 1700000000, 'changes' => [
            ['field' => 'message_template_status_update', 'value' => [
                'event' => 'REJECTED',
                'message_template_id' => 987,
                'message_template_name' => 'order_shipped',
                'message_template_language' => 'en_US',
                'message_template_category' => 'UTILITY',
                'reason' => 'INCORRECT_CATEGORY',
            ]],
            ['field' => 'message_template_status_update', 'value' => [
                'event' => 'APPROVED',
                'message_template_id' => 988,
                'message_template_name' => 'welcome',
                'message_template_language' => 'en_US',
                'reason' => 'NONE',
            ]],
            ['field' => 'message_template_quality_update', 'value' => [
                'previous_quality_score' => 'GREEN',
                'new_quality_score' => 'RED',
                'message_template_id' => 987,
                'message_template_name' => 'order_shipped',
                'message_template_language' => 'en_US',
            ]],
        ]],
    ]))->assertOk();

    Event::assertDispatched(TemplateStatusUpdated::class, fn ($e) => $e->templateId === '987'
        && $e->isRejected()
        && $e->reason === 'INCORRECT_CATEGORY'
        && $e->category === 'UTILITY'
        && $e->businessAccountId === 'waba_123');
    Event::assertDispatched(TemplateStatusUpdated::class, fn ($e) => $e->isApproved() && $e->reason === null);
    Event::assertDispatched(TemplateQualityUpdated::class, fn ($e) => $e->previousScore === 'GREEN' && $e->newScore === 'RED');
    Event::assertNotDispatched(MessageReceived::class);
});

// MessageReceived helpers

function received(array $message, array $contact = []): MessageReceived
{
    return new MessageReceived($message['id'] ?? 'wamid.1', '15551234567', $message, now(), 'pn_1', $contact);
}

it('reads common fields from received messages', function () {
    $text = received(['type' => 'text', 'text' => ['body' => 'Hello']]);
    $button = received(['type' => 'interactive', 'context' => ['from' => '1555', 'id' => 'wamid.q'], 'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'yes', 'title' => 'Yes']]]);
    $list = received(['type' => 'interactive', 'interactive' => ['type' => 'list_reply', 'list_reply' => ['id' => 'thu_am', 'title' => 'Morning']]]);
    $quickReply = received(['type' => 'button', 'button' => ['payload' => 'STOP', 'text' => 'Stop promotions']]);
    $flow = received(['type' => 'interactive', 'interactive' => ['type' => 'nfm_reply', 'nfm_reply' => ['name' => 'flow', 'body' => 'Sent', 'response_json' => '{"flow_token":"t","date":"2026-10-01"}']]]);
    $image = received(['type' => 'image', 'image' => ['id' => 'media_1', 'caption' => 'Receipt']], ['profile' => ['name' => 'Sarah']]);

    expect($text->text())->toBe('Hello')
        ->and($text->isType('text'))->toBeTrue()
        ->and($button->buttonReplyId())->toBe('yes')
        ->and($button->text())->toBe('Yes')
        ->and($button->replyToMessageId())->toBe('wamid.q')
        ->and($list->listReplyId())->toBe('thu_am')
        ->and($list->buttonReplyId())->toBeNull()
        ->and($quickReply->buttonPayload())->toBe('STOP')
        ->and($quickReply->text())->toBe('Stop promotions')
        ->and($flow->flowResponse())->toBe(['flow_token' => 't', 'date' => '2026-10-01'])
        ->and($flow->text())->toBeNull()
        ->and($image->mediaId())->toBe('media_1')
        ->and($image->text())->toBe('Receipt')
        ->and($image->senderName())->toBe('Sarah')
        ->and($text->mediaId())->toBeNull();
});
