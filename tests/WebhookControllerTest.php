<?php

use Crenspire\Whatsapp\Events\MessageDelivered;
use Crenspire\Whatsapp\Events\MessageFailed;
use Crenspire\Whatsapp\Events\MessageRead;
use Crenspire\Whatsapp\Events\MessageReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

function webhookPayload(array $entries): array
{
    return ['object' => 'whatsapp_business_account', 'entry' => $entries];
}

function webhookEntry(array $value): array
{
    return ['id' => 'waba_123', 'changes' => [['field' => 'messages', 'value' => $value]]];
}

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

    Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) =>
        !str_contains(json_encode($context), 'test_verify_token'));
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
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 'app_secret'),
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

    Event::assertDispatched(MessageReceived::class, fn ($event) =>
        $event->messageId === 'wamid.in'
        && $event->from === '15551234567'
        && $event->timestamp->timestamp === 1700000000);
    Event::assertDispatched(MessageDelivered::class, fn ($event) => $event->messageId === 'wamid.1');
    Event::assertDispatched(MessageRead::class, fn ($event) =>
        $event->messageId === 'wamid.2' && $event->timestamp->timestamp === 1700000100);
    Event::assertDispatched(MessageFailed::class, fn ($event) =>
        $event->messageId === 'wamid.3'
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
    Log::shouldNotHaveReceived('info', [Mockery::any(), Mockery::on(fn ($context) =>
        str_contains(json_encode($context), 'secret text'))]);
});

it('warns when accepting payloads without a configured secret', function () {
    Event::fake();
    Log::spy();

    $this->postJson('/whatsapp/webhook', webhookPayload([]))->assertOk();

    Log::shouldHaveReceived('warning')->withArgs(fn ($message) =>
        str_contains($message, 'without signature verification'));
});

it('does not warn about signatures when a secret is configured', function () {
    Event::fake();
    Log::spy();
    config(['whatsapp.webhook_secret' => 'app_secret']);
    $body = json_encode(webhookPayload([]));

    $this->call('POST', '/whatsapp/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 'app_secret'),
    ], $body)->assertOk();

    Log::shouldNotHaveReceived('warning');
});
