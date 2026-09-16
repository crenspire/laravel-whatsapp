<?php

use Crenspire\Whatsapp\Exceptions\CustomerServiceWindowException;
use Crenspire\Whatsapp\Exceptions\WhatsappException;
use Crenspire\Whatsapp\Facades\Whatsapp;
use Crenspire\Whatsapp\Notifications\WhatsappChannel;
use Crenspire\Whatsapp\Notifications\WhatsappMessage;
use Crenspire\Whatsapp\Testing\SentMessage;
use Crenspire\Whatsapp\Testing\WhatsappFake;
use Crenspire\Whatsapp\WhatsappService;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\ExpectationFailedException;

class OrderShipped extends BaseNotification
{
    public function __construct(public mixed $message) {}

    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsapp(object $notifiable): mixed
    {
        return $this->message;
    }
}

class WhatsappUser
{
    use Notifiable;

    public function __construct(public mixed $route) {}

    public function routeNotificationForWhatsapp(): mixed
    {
        return $this->route;
    }

    public function getKey(): string
    {
        return 'user-1';
    }
}

// The fake

it('swaps the service for a fake in the container', function () {
    $fake = Whatsapp::fake();

    expect(app(WhatsappService::class))->toBe($fake)->toBeInstanceOf(WhatsappFake::class);
});

it('records messages instead of sending them', function () {
    Http::fake();
    $fake = Whatsapp::fake();

    $response = Whatsapp::sendTextMessage('+1 555 123 4567', 'Your order has shipped.');
    Whatsapp::sendTemplateMessage('15551234567', 'order_shipped', ['Sarah'], language: 'es_ES');
    Whatsapp::sendButtonMessage('15550000000', 'Deliver tomorrow?', [['id' => 'yes', 'title' => 'Yes']], replyTo: 'wamid.in');

    expect($response['messages'][0]['id'])->toStartWith('wamid.fake.');
    Http::assertNothingSent();

    $fake->assertSentCount(3);
    $fake->assertSentText('15551234567', 'Your order has shipped.');
    $fake->assertSentText('15551234567');
    $fake->assertSentTemplate('15551234567', 'order_shipped');
    $fake->assertSentTemplate('15551234567', 'order_shipped', 'es_ES');
    $fake->assertSentTo('15550000000', fn (SentMessage $m) => $m->type() === 'interactive' && $m->replyTo() === 'wamid.in');
    $fake->assertSent(fn (SentMessage $m) => $m->text() === 'Deliver tomorrow?');
    $fake->assertNotSentTo('15559999999');
    $fake->assertNotSent(fn (SentMessage $m) => $m->type() === 'image');

    expect($fake->sent(fn (SentMessage $m) => $m->isTo('15551234567')))->toHaveCount(2);
});

it('fails assertions with a useful message', function () {
    $fake = Whatsapp::fake();
    Whatsapp::sendTextMessage('15551234567', 'Hello');

    expect(fn () => $fake->assertSentText('15551234567', 'Goodbye'))
        ->toThrow(ExpectationFailedException::class, 'No text message [Goodbye] was sent to [15551234567].');
    expect(fn () => $fake->assertSentTemplate('15551234567', 'welcome'))
        ->toThrow(ExpectationFailedException::class, 'text to 15551234567: Hello');
    expect(fn () => $fake->assertNothingSent())->toThrow(ExpectationFailedException::class);
    expect(fn () => $fake->assertSentCount(2))->toThrow(ExpectationFailedException::class, 'Expected 2 WhatsApp messages, but 1 were sent.');
    expect(fn () => $fake->assertNotSentTo('15551234567'))->toThrow(ExpectationFailedException::class);
});

it('still validates phone numbers and tenants', function () {
    Whatsapp::fake(['tenants' => ['acme' => ['phone_number_id' => 'p', 'access_token' => 't']]]);

    expect(fn () => Whatsapp::sendTextMessage('123', 'Hi'))->toThrow(WhatsappException::class, 'Invalid phone number');
    expect(fn () => Whatsapp::sendTextMessage('15551234567', 'Hi', tenantId: 'missing'))->toThrow(WhatsappException::class, 'Unknown WhatsApp tenant');

    Whatsapp::sendTextMessage('15551234567', 'Hi', tenantId: 'acme');
    Whatsapp::assertSentTo('15551234567', fn (SentMessage $m) => $m->tenantId === 'acme');
});

it('simulates failures', function () {
    $fake = Whatsapp::fake()->failSending(fn (SentMessage $m) => $m->isTo('15550000000')
        ? new CustomerServiceWindowException('Failed to send WhatsApp message: Re-engagement message', 400)
        : null);

    Whatsapp::sendTextMessage('15551234567', 'Delivered');

    expect(fn () => Whatsapp::sendTextMessage('15550000000', 'Blocked'))->toThrow(CustomerServiceWindowException::class);
    $fake->assertSentCount(1);
    $fake->assertNotSentTo('15550000000');
});

it('records read receipts, typing indicators, uploads and templates', function () {
    $fake = Whatsapp::fake()->withTemplates([
        ['name' => 'welcome', 'language' => 'en_US', 'status' => 'APPROVED', 'category' => 'MARKETING'],
        ['name' => 'order_shipped', 'language' => 'en_US', 'status' => 'PENDING', 'category' => 'UTILITY'],
    ]);

    Whatsapp::markMessageAsRead('wamid.1');
    Whatsapp::showTypingIndicator('wamid.2');
    Whatsapp::uploadMedia('/tmp/photo.jpg', 'image/jpeg');
    Whatsapp::createTemplate('promo', 'en_US', 'MARKETING', [['type' => 'BODY', 'text' => 'Hi']]);

    $fake->assertMarkedAsRead('wamid.1');
    $fake->assertTypingIndicatorShown('wamid.2');
    $fake->assertMediaUploaded(fn ($upload) => $upload['type'] === 'image/jpeg');
    $fake->assertTemplateCreated('promo', fn ($t) => $t['category'] === 'MARKETING');
    expect(fn () => $fake->assertTypingIndicatorShown('wamid.1'))->toThrow(ExpectationFailedException::class);

    expect(Whatsapp::isTemplateApproved('welcome'))->toBeTrue()
        ->and(Whatsapp::isTemplatePending('order_shipped'))->toBeTrue()
        ->and(Whatsapp::getTemplatesByCategory('UTILITY')['data'])->toHaveCount(1)
        ->and(Whatsapp::getPhoneNumber()['quality_rating'])->toBe('GREEN');
});

// Notification channel

it('sends a notification as a text message', function () {
    $fake = Whatsapp::fake();

    (new WhatsappUser('15551234567'))->notify(new OrderShipped('Your order has shipped.'));

    $fake->assertSentText('15551234567', 'Your order has shipped.');
});

it('sends notification messages built with WhatsappMessage', function (WhatsappMessage $message, Closure $check) {
    $fake = Whatsapp::fake();

    (new WhatsappUser('15551234567'))->notify(new OrderShipped($message));

    $fake->assertSentTo('15551234567', $check);
})->with([
    'template' => [
        fn () => WhatsappMessage::template('order_shipped', ['Sarah', '#1042']),
        fn () => fn (SentMessage $m) => $m->template() === 'order_shipped' && $m->language() === 'en_US'
            && $m->payload['template']['components'][0]['parameters'][1] === ['type' => 'text', 'text' => '#1042'],
    ],
    'template builder' => [
        fn () => WhatsappMessage::fromTemplate(Whatsapp::template('delivery')->button('url', ['t/1'])),
        fn () => fn (SentMessage $m) => $m->template() === 'delivery' && $m->payload['template']['components'][0]['index'] === '0',
    ],
    'media' => [
        fn () => WhatsappMessage::media('document', 'media_1', 'Invoice'),
        fn () => fn (SentMessage $m) => $m->payload['document'] === ['id' => 'media_1', 'caption' => 'Invoice'],
    ],
    'buttons' => [
        fn () => WhatsappMessage::buttons('Deliver tomorrow?', [['id' => 'yes', 'title' => 'Yes']]),
        fn () => fn (SentMessage $m) => $m->payload['interactive']['action']['buttons'][0]['reply']['id'] === 'yes',
    ],
    'list' => [
        fn () => WhatsappMessage::list('Pick a slot', 'View', [['title' => 'Thu', 'rows' => [['id' => 'am', 'title' => 'AM']]]]),
        fn () => fn (SentMessage $m) => $m->payload['interactive']['type'] === 'list',
    ],
    'location' => [
        fn () => WhatsappMessage::location(51.5, -0.12, 'Shop'),
        fn () => fn (SentMessage $m) => $m->payload['location']['name'] === 'Shop',
    ],
    'payload with reply' => [
        fn () => WhatsappMessage::payload(['type' => 'text', 'text' => ['body' => 'Raw']])->replyTo('wamid.in'),
        fn () => fn (SentMessage $m) => $m->text() === 'Raw' && $m->replyTo() === 'wamid.in',
    ],
]);

it('uses the tenant from the route or the message', function () {
    $fake = Whatsapp::fake(['tenants' => [
        'acme' => ['phone_number_id' => 'acme_phone', 'access_token' => 't'],
        'globex' => ['phone_number_id' => 'globex_phone', 'access_token' => 't'],
    ]]);

    (new WhatsappUser(['to' => '15551234567', 'tenant' => 'acme']))->notify(new OrderShipped('From route'));
    (new WhatsappUser(['to' => '15551234567', 'tenant' => 'acme']))->notify(new OrderShipped(WhatsappMessage::text('From message')->tenant('globex')));

    $fake->assertSentTo('15551234567', fn (SentMessage $m) => $m->text() === 'From route' && $m->tenantId === 'acme');
    $fake->assertSentTo('15551234567', fn (SentMessage $m) => $m->text() === 'From message' && $m->tenantId === 'globex');
});

it('supports on-demand notifications and message recipients', function () {
    $fake = Whatsapp::fake();

    Notification::route('whatsapp', '15551234567')->notify(new OrderShipped('On demand'));
    (new WhatsappUser(null))->notify(new OrderShipped(WhatsappMessage::text('Explicit')->to('15550000000')));

    $fake->assertSentText('15551234567', 'On demand');
    $fake->assertSentText('15550000000', 'Explicit');
});

it('skips notifiables without a WhatsApp route', function () {
    $fake = Whatsapp::fake();

    (new WhatsappUser(null))->notify(new OrderShipped('Nobody'));

    $fake->assertNothingSent();
});

it('rejects invalid toWhatsapp return values', function () {
    Whatsapp::fake();

    expect(fn () => (new WhatsappChannel)->send(new WhatsappUser('15551234567'), new OrderShipped(['not' => 'a message'])))
        ->toThrow(WhatsappException::class, 'must return a string or');
});

it('uses a fake created after the channel was first used', function () {
    Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.real']]])]);
    Notification::route('whatsapp', '15551234567')->notify(new OrderShipped('Real send'));
    Http::assertSent(fn (Request $request) => $request['text']['body'] === 'Real send');

    $fake = Whatsapp::fake();
    Notification::route('whatsapp', '15551234567')->notify(new OrderShipped('Faked send'));

    $fake->assertSentText('15551234567', 'Faked send');
    Http::assertSentCount(1);
});

it('sends notifications through the real API', function () {
    Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.real']]])]);

    Notification::route('whatsapp', '15551234567')->notify(new OrderShipped(WhatsappMessage::template('order_shipped', ['Sarah'])));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.facebook.com/v20.0/123456789/messages'
        && $request['template']['name'] === 'order_shipped'
        && $request['template']['language']['code'] === 'en_US');
});

it('works with Notification::fake()', function () {
    Notification::fake();
    $user = new WhatsappUser('15551234567');

    $user->notify(new OrderShipped('Hi'));

    Notification::assertSentTo($user, OrderShipped::class, fn ($notification, $channels) => $channels === ['whatsapp']);
});
