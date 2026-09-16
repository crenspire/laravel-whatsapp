<?php

use Crenspire\Whatsapp\Events\MessageSent;
use Crenspire\Whatsapp\Listeners\LogMessages;
use Crenspire\Whatsapp\Models\Message;
use Crenspire\Whatsapp\WhatsappServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    (include __DIR__.'/../database/migrations/create_whatsapp_messages_table.php.stub')->up();

    config(['whatsapp.message_log.enabled' => true, 'whatsapp.tenants' => [
        'acme' => ['phone_number_id' => 'acme_phone', 'access_token' => 'acme_token'],
    ]]);
    Event::subscribe(LogMessages::class);
});

function postStatus(string $messageId, string $status, int $timestamp, array $extra = []): void
{
    test()->postJson('/whatsapp/webhook', webhookPayload([webhookEntry([
        'metadata' => ['phone_number_id' => '123456789'],
        'statuses' => [array_merge(['id' => $messageId, 'recipient_id' => '15551234567', 'status' => $status, 'timestamp' => (string) $timestamp], $extra)],
    ])]))->assertOk();
}

it('is only enabled when configured', function () {
    Event::forget(MessageSent::class);
    config(['whatsapp.message_log.enabled' => false]);
    (new WhatsappServiceProvider(app()))->packageBooted();
    expect(Event::hasListeners(MessageSent::class))->toBeFalse();

    config(['whatsapp.message_log.enabled' => true]);
    (new WhatsappServiceProvider(app()))->packageBooted();
    expect(Event::hasListeners(MessageSent::class))->toBeTrue();
});

it('logs sent messages and tracks their status', function () {
    Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.out']]])]);

    makeService()->sendTextMessage('+1 555 123 4567', 'Your order has shipped.', replyTo: 'wamid.in');

    $message = Message::query()->sole();
    expect($message->only(['wamid', 'direction', 'phone', 'phone_number_id', 'type', 'body', 'status', 'reply_to']))->toBe([
        'wamid' => 'wamid.out',
        'direction' => 'outbound',
        'phone' => '15551234567',
        'phone_number_id' => '123456789',
        'type' => 'text',
        'body' => 'Your order has shipped.',
        'status' => 'accepted',
        'reply_to' => 'wamid.in',
    ]);
    expect($message->payload['text']['body'])->toBe('Your order has shipped.');

    postStatus('wamid.out', 'sent', 1700000000);
    postStatus('wamid.out', 'read', 1700000200);
    postStatus('wamid.out', 'delivered', 1700000100); // arrives late

    $message->refresh();
    expect($message->status)->toBe('read')
        ->and($message->sent_at->timestamp)->toBe(1700000000)
        ->and($message->delivered_at->timestamp)->toBe(1700000100)
        ->and($message->read_at->timestamp)->toBe(1700000200);
});

it('logs delivery failures reported by webhook', function () {
    Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.out']]])]);
    makeService()->sendTemplateMessage('15551234567', 'order_shipped', ['Sarah']);

    postStatus('wamid.out', 'failed', 1700000300, ['errors' => [['code' => 131026, 'title' => 'Message undeliverable']]]);

    $message = Message::query()->sole();
    expect($message->status)->toBe('failed')
        ->and($message->body)->toBe('order_shipped')
        ->and($message->errors[0]['code'])->toBe(131026)
        ->and($message->failed_at->timestamp)->toBe(1700000300);
});

it('logs send requests the API rejected', function () {
    Http::fake(['*' => Http::response(['error' => ['code' => 131047, 'message' => 'Re-engagement message']], 400)]);

    rescue(fn () => makeService(['tenants' => config('whatsapp.tenants')])->sendTextMessage('15551234567', 'Too late', tenantId: 'acme'), report: false);

    $message = Message::query()->sole();
    expect($message->wamid)->toBeNull()
        ->and($message->status)->toBe('failed')
        ->and($message->tenant)->toBe('acme')
        ->and($message->phone_number_id)->toBe('acme_phone')
        ->and($message->body)->toBe('Too late')
        ->and($message->errors[0]['code'])->toBe(131047);
});

it('logs received messages once, with the tenant that received them', function () {
    $payload = webhookPayload([webhookEntry([
        'metadata' => ['phone_number_id' => 'acme_phone'],
        'messages' => [[
            'id' => 'wamid.in',
            'from' => '15551234567',
            'timestamp' => '1700000000',
            'type' => 'interactive',
            'context' => ['from' => '15550000000', 'id' => 'wamid.out'],
            'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'yes', 'title' => 'Yes']],
        ]],
    ])]);

    $this->postJson('/whatsapp/webhook', $payload);
    config(['whatsapp.webhook.deduplicate' => false]);
    $this->postJson('/whatsapp/webhook', $payload);

    $message = Message::query()->sole();
    expect($message->only(['direction', 'phone', 'tenant', 'type', 'body', 'status', 'reply_to']))->toBe([
        'direction' => 'inbound',
        'phone' => '15551234567',
        'tenant' => 'acme',
        'type' => 'interactive',
        'body' => 'Yes',
        'status' => 'received',
        'reply_to' => 'wamid.out',
    ]);
    expect($message->created_at->timestamp)->toBe(1700000000);
});

it('ignores status updates for messages that were not logged', function () {
    postStatus('wamid.unknown', 'delivered', 1700000000);

    expect(Message::query()->count())->toBe(0);
});

it('returns a conversation in order', function () {
    Http::fakeSequence()
        ->push(['messages' => [['id' => 'wamid.out']]])
        ->push(['messages' => [['id' => 'wamid.other']]]);

    $this->travelTo(now()->subMinute());
    $this->postJson('/whatsapp/webhook', webhookPayload([webhookEntry([
        'messages' => [['id' => 'wamid.in', 'from' => '15551234567', 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => 'Where is my order?']]],
    ])]));
    $this->travelBack();

    makeService()->sendTextMessage('+15551234567', 'It ships today.');
    makeService()->sendTextMessage('15550000000', 'Different customer');

    $conversation = Message::query()->conversation('+1 555 123 4567')->get();

    expect($conversation->pluck('body')->all())->toBe(['Where is my order?', 'It ships today.'])
        ->and($conversation[0]->isInbound())->toBeTrue()
        ->and($conversation[1]->isOutbound())->toBeTrue()
        ->and(Message::query()->outbound()->count())->toBe(2)
        ->and(Message::query()->inbound()->count())->toBe(1);
});

it('never lets a logging failure break sending', function () {
    Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.out']]])]);
    Schema::drop('whatsapp_messages');
    $reported = [];
    app(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$reported) {
        $reported[] = $e;
    });

    $response = makeService()->sendTextMessage('15551234567', 'Still sent');

    expect($response['messages'][0]['id'])->toBe('wamid.out')
        ->and($reported)->not->toBeEmpty();
});
