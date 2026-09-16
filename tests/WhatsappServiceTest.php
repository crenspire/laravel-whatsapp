<?php

use Crenspire\Whatsapp\Events\MessageFailed;
use Crenspire\Whatsapp\Events\MessageSent;
use Crenspire\Whatsapp\Exceptions\WhatsappException;
use Crenspire\Whatsapp\WhatsappService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

const MESSAGES_URL = 'https://graph.facebook.com/v20.0/123456789/messages';

function fakeMessageSent(): void
{
    Http::fake([
        '*/messages' => Http::response(['messages' => [['id' => 'wamid.123']]]),
    ]);
}

function tenantService(): WhatsappService
{
    return makeService([
        'tenants' => [
            'tenant1' => [
                'phone_number_id' => 'tenant_phone',
                'access_token' => 'tenant_token',
                'business_account_id' => 'tenant_waba',
                'headers' => ['X-Tenant-Header' => 'tenant1-value'],
                'language' => 'fr_FR',
            ],
        ],
    ]);
}

// Configuration and helpers

it('resolves the service from the container', function () {
    expect(app(WhatsappService::class))->toBeInstanceOf(WhatsappService::class);
});

it('creates media storage directory', function () {
    $tempDir = sys_get_temp_dir().'/whatsapp-test-'.uniqid();

    makeService(['media_storage' => $tempDir]);

    expect(is_dir($tempDir))->toBeTrue();
    rmdir($tempDir);
});

it('validates phone number format', function (string $number, bool $valid) {
    $method = new ReflectionMethod(WhatsappService::class, 'validatePhoneNumber');
    $call = fn () => $method->invoke(makeService(), $number);

    $valid
        ? expect($call)->not->toThrow(WhatsappException::class)
        : expect($call)->toThrow(WhatsappException::class, 'Invalid phone number format');
})->with([
    ['1234567890', true],
    ['123456789012345', true],
    ['+1234567890', true],
    ['123-456-7890', true],
    ['123', false],
    ['12345678901234567890', false],
    ['abc123def', false],
]);

it('uses default configuration without a tenant', function () {
    $config = (new ReflectionMethod(WhatsappService::class, 'tenantConfig'))->invoke(makeService(), null);

    expect($config)
        ->toMatchArray([
            'phone_number_id' => '123456789',
            'access_token' => 'test_token',
            'business_account_id' => 'waba_123',
            'headers' => [],
            'language' => 'en_US',
        ]);
});

it('uses tenant configuration when provided', function () {
    $config = (new ReflectionMethod(WhatsappService::class, 'tenantConfig'))->invoke(tenantService(), 'tenant1');

    expect($config)
        ->toMatchArray([
            'phone_number_id' => 'tenant_phone',
            'access_token' => 'tenant_token',
            'business_account_id' => 'tenant_waba',
            'headers' => ['X-Tenant-Header' => 'tenant1-value'],
            'language' => 'fr_FR',
        ]);
});

it('falls back to the global business account ID for tenants without one', function () {
    $service = makeService([
        'tenants' => ['tenant1' => ['phone_number_id' => 'p', 'access_token' => 't']],
    ]);

    $config = (new ReflectionMethod(WhatsappService::class, 'tenantConfig'))->invoke($service, 'tenant1');

    expect($config['business_account_id'])->toBe('waba_123');
});

it('rejects unknown tenants instead of using default credentials', function () {
    Http::fake();

    expect(fn () => makeService()->sendTextMessage('1234567890', 'Hi', false, 'missing'))
        ->toThrow(WhatsappException::class, 'Unknown WhatsApp tenant: missing');

    Http::assertNothingSent();
});

it('merges headers with custom headers taking precedence', function () {
    $headers = (new ReflectionMethod(WhatsappService::class, 'buildHeaders'))->invoke(
        makeService(),
        ['access_token' => 'test_token', 'headers' => ['X-Custom-Header' => 'tenant', 'X-Tenant-ID' => 't1']],
        ['X-Custom-Header' => 'override', 'X-Request-ID' => 'req123']
    );

    expect($headers)->toMatchArray([
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'Authorization' => 'Bearer test_token',
        'X-Custom-Header' => 'override',
        'X-Tenant-ID' => 't1',
        'X-Request-ID' => 'req123',
    ]);
});

it('resolves language from override, tenant, then default', function () {
    $method = new ReflectionMethod(WhatsappService::class, 'getLanguage');
    $service = makeService(['default_language' => 'en_GB']);

    expect($method->invoke($service, ['language' => 'es_ES'], 'de_DE'))->toBe('de_DE');
    expect($method->invoke($service, ['language' => 'es_ES'], null))->toBe('es_ES');
    expect($method->invoke($service, ['language' => null], null))->toBe('en_GB');
});

// Messaging

it('sends a text message', function () {
    Event::fake();
    fakeMessageSent();

    $response = makeService()->sendTextMessage('1234567890', 'Hello', true);

    expect($response['messages'][0]['id'])->toBe('wamid.123');

    Http::assertSent(fn (Request $request) => $request->url() === MESSAGES_URL
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer test_token')
        && $request->data() === [
            'messaging_product' => 'whatsapp',
            'to' => '1234567890',
            'type' => 'text',
            'text' => ['body' => 'Hello', 'preview_url' => true],
        ]);

    Event::assertDispatched(MessageSent::class, fn ($event) => $event->messageId === 'wamid.123' && $event->recipient === '1234567890');
});

it('throws and dispatches MessageFailed when the API rejects a message', function () {
    Event::fake();
    Http::fake([
        '*/messages' => Http::response(['error' => ['message' => 'Invalid parameter']], 400),
    ]);

    expect(fn () => makeService()->sendTextMessage('1234567890', 'Hello'))
        ->toThrow(WhatsappException::class, 'Failed to send WhatsApp message: Invalid parameter');

    Event::assertDispatched(MessageFailed::class);
    Event::assertNotDispatched(MessageSent::class);
});

it('enforces the rate limit per phone number', function () {
    fakeMessageSent();
    $service = makeService(['rate_limit' => 1]);

    $service->sendTextMessage('1234567890', 'First');

    expect(fn () => $service->sendTextMessage('1234567890', 'Second'))
        ->toThrow(WhatsappException::class, 'Rate limit exceeded');
});

it('sends custom headers with a message', function () {
    fakeMessageSent();

    makeService()->sendTextMessage('1234567890', 'Hello', false, null, ['X-Custom' => 'value']);

    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Custom', 'value'));
});

it('sends messages with tenant credentials', function () {
    fakeMessageSent();

    tenantService()->sendTextMessage('1234567890', 'Hello', false, 'tenant1');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.facebook.com/v20.0/tenant_phone/messages'
        && $request->hasHeader('Authorization', 'Bearer tenant_token')
        && $request->hasHeader('X-Tenant-Header', 'tenant1-value'));
});

it('sends a media message with caption', function () {
    fakeMessageSent();

    makeService()->sendMediaMessage('1234567890', 'media_1', 'image', 'A caption');

    Http::assertSent(fn (Request $request) => $request['type'] === 'image'
        && $request['image'] === ['id' => 'media_1', 'caption' => 'A caption']);
});

it('sends a template message using the tenant language', function () {
    fakeMessageSent();

    tenantService()->sendTemplateMessage('1234567890', 'order_update', ['John', '42'], null, 'tenant1');

    Http::assertSent(fn (Request $request) => $request['template'] === [
        'name' => 'order_update',
        'language' => ['code' => 'fr_FR'],
        'components' => [[
            'type' => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => 'John'],
                ['type' => 'text', 'text' => '42'],
            ],
        ]],
    ]);
});

it('sends a button message', function () {
    fakeMessageSent();

    makeService()->sendButtonMessage('1234567890', 'Pick one', [
        ['id' => 'btn1', 'title' => 'Option 1'],
        ['title' => 'Option 2'],
    ], 'Header', 'Footer');

    Http::assertSent(fn (Request $request) => $request['interactive'] === [
        'type' => 'button',
        'body' => ['text' => 'Pick one'],
        'action' => ['buttons' => [
            ['type' => 'reply', 'reply' => ['id' => 'btn1', 'title' => 'Option 1']],
            ['type' => 'reply', 'reply' => ['id' => 'btn_1', 'title' => 'Option 2']],
        ]],
        'header' => ['type' => 'text', 'text' => 'Header'],
        'footer' => ['text' => 'Footer'],
    ]);
});

it('sends a list message', function () {
    fakeMessageSent();
    $sections = [['title' => 'Section 1', 'rows' => [['id' => 'row1', 'title' => 'Row 1']]]];

    makeService()->sendListMessage('1234567890', 'Choose', 'View', $sections);

    Http::assertSent(fn (Request $request) => $request['interactive'] === [
        'type' => 'list',
        'body' => ['text' => 'Choose'],
        'action' => ['button' => 'View', 'sections' => $sections],
    ]);
});

it('sends a contact message', function () {
    fakeMessageSent();
    $contacts = [['name' => ['formatted_name' => 'John Doe'], 'phones' => [['phone' => '+1234567890']]]];

    makeService()->sendContactMessage('1234567890', $contacts);

    Http::assertSent(fn (Request $request) => $request['type'] === 'contacts' && $request['contacts'] === $contacts);
});

it('sends a location message', function () {
    fakeMessageSent();

    makeService()->sendLocationMessage('1234567890', 40.7128, -74.006, 'New York', 'New York, NY');

    Http::assertSent(fn (Request $request) => $request['location'] === [
        'latitude' => 40.7128,
        'longitude' => -74.006,
        'name' => 'New York',
        'address' => 'New York, NY',
    ]);
});

it('sends a sticker message', function () {
    fakeMessageSent();

    makeService()->sendStickerMessage('1234567890', 'sticker_1');

    Http::assertSent(fn (Request $request) => $request['sticker'] === ['id' => 'sticker_1']);
});

it('sends a reaction message', function () {
    fakeMessageSent();

    makeService()->sendReactionMessage('1234567890', 'wamid.1', '👍');

    Http::assertSent(fn (Request $request) => $request['reaction'] === ['message_id' => 'wamid.1', 'emoji' => '👍']);
});

it('sends a flow message', function () {
    fakeMessageSent();

    makeService()->sendFlowMessage('1234567890', 'token', 'flow_1', 'Start', 'navigate', ['screen' => 'WELCOME']);

    Http::assertSent(fn (Request $request) => $request['interactive']['type'] === 'flow'
        && $request['interactive']['action']['parameters'] === [
            'flow_token' => 'token',
            'flow_id' => 'flow_1',
            'flow_cta' => 'Start',
            'flow_action' => 'navigate',
            'flow_action_payload' => ['screen' => 'WELCOME'],
        ]);
});

it('sends a single product message', function () {
    fakeMessageSent();

    makeService()->sendSingleProductMessage('1234567890', 'catalog_1', 'sku_1', 'Check this out');

    Http::assertSent(fn (Request $request) => $request['interactive'] === [
        'type' => 'product',
        'action' => ['catalog_id' => 'catalog_1', 'product_retailer_id' => 'sku_1'],
        'body' => ['text' => 'Check this out'],
    ]);
});

it('marks a message as read', function () {
    Http::fake(['*/messages' => Http::response(['success' => true])]);

    makeService()->markMessageAsRead('wamid.1');

    Http::assertSent(fn (Request $request) => $request->url() === MESSAGES_URL
        && $request->data() === ['messaging_product' => 'whatsapp', 'status' => 'read', 'message_id' => 'wamid.1']);
});

// Media

it('throws when uploading a missing file', function () {
    Http::fake();

    expect(fn () => makeService()->uploadMedia(sys_get_temp_dir().'/non-existent-file.jpg', 'image/jpeg'))
        ->toThrow(WhatsappException::class, 'File not found');

    Http::assertNothingSent();
});

it('uploads media', function () {
    Http::fake(['*/media' => Http::response(['id' => 'media_1'])]);
    $file = tempnam(sys_get_temp_dir(), 'wa').'.jpg';
    file_put_contents($file, 'binary');

    $response = makeService()->uploadMedia($file, 'image/jpeg');

    expect($response['id'])->toBe('media_1');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.facebook.com/v20.0/123456789/media' && $request->isMultipart());
    unlink($file);
});

it('gets media info', function () {
    Http::fake(['*/media_1' => Http::response(['id' => 'media_1', 'mime_type' => 'image/png'])]);

    expect(makeService()->getMediaInfo('media_1'))->toMatchArray(['mime_type' => 'image/png']);
});

it('downloads media using the extension for its mime type', function () {
    $storage = sys_get_temp_dir().'/whatsapp-test-'.uniqid();
    Http::fake([
        'https://graph.facebook.com/v20.0/media_1' => Http::response(['url' => 'https://lookaside.fbsbx.com/file', 'mime_type' => 'application/pdf']),
        'https://lookaside.fbsbx.com/file' => Http::response('pdf-bytes'),
    ]);

    $path = makeService(['media_storage' => $storage])->downloadMedia('media_1');

    expect($path)->toBe("{$storage}/media_1.pdf");
    expect(file_get_contents($path))->toBe('pdf-bytes');
    unlink($path);
    rmdir($storage);
});

it('deletes media', function () {
    Http::fake(['*/media_1' => Http::response(['success' => true])]);

    expect(makeService()->deleteMedia('media_1'))->toBeTrue();
    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE');
});

it('uses the correct tenant credentials for each media call', function () {
    Http::fake(['*' => Http::response(['id' => 'media_1'])]);
    $service = tenantService();

    $service->getMediaInfo('media_1', 'tenant1');
    $service->getMediaInfo('media_1');

    $requests = Http::recorded();
    expect($requests[0][0]->header('Authorization'))->toBe(['Bearer tenant_token']);
    expect($requests[1][0]->header('Authorization'))->toBe(['Bearer test_token']);
});

it('sends custom headers with media requests', function () {
    Http::fake(['*' => Http::response(['id' => 'media_1'])]);

    makeService()->getMediaInfo('media_1', null, ['X-Request-ID' => 'req123']);

    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Request-ID', 'req123'));
});

// Business profile

it('gets the business profile', function () {
    Http::fake(['*/whatsapp_business_profile*' => Http::response(['data' => [['about' => 'Hi']]])]);

    expect(makeService()->getBusinessProfile()['data'][0]['about'])->toBe('Hi');
});

it('updates the business profile', function () {
    Http::fake(['*/whatsapp_business_profile' => Http::response(['success' => true])]);

    makeService()->updateBusinessProfile(['messaging_product' => 'whatsapp', 'about' => 'Test business']);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request['about'] === 'Test business');
});

it('uses the correct tenant credentials for each business profile call', function () {
    Http::fake(['*' => Http::response(['data' => []])]);
    $service = tenantService();

    $service->getBusinessProfile('tenant1');
    $service->getBusinessProfile();

    $requests = Http::recorded();
    expect($requests[0][0]->url())->toStartWith('https://graph.facebook.com/v20.0/tenant_phone/');
    expect($requests[1][0]->url())->toStartWith('https://graph.facebook.com/v20.0/123456789/');
});

it('throws when a media download fails', function () {
    Http::fake([
        'https://graph.facebook.com/v20.0/media_1' => Http::response(['url' => 'https://lookaside.fbsbx.com/file', 'mime_type' => 'image/png']),
        'https://lookaside.fbsbx.com/file' => Http::response('Not found', 404),
    ]);

    expect(fn () => makeService()->downloadMedia('media_1'))
        ->toThrow(WhatsappException::class, 'Failed to download media');
});

// Template and product payloads

it('sends a template message with text and media parameters', function () {
    fakeMessageSent();

    makeService()->sendTemplateMessageWithComponents(
        '1234567890',
        'order_shipped',
        ['John', ['type' => 'currency', 'currency' => ['fallback_value' => '$10', 'code' => 'USD', 'amount_1000' => 10000]]],
        [['type' => 'image', 'image' => ['link' => 'https://example.com/box.jpg']]]
    );

    Http::assertSent(fn (Request $request) => $request['template']['components'] === [
        ['type' => 'header', 'parameters' => [
            ['type' => 'image', 'image' => ['link' => 'https://example.com/box.jpg']],
        ]],
        ['type' => 'body', 'parameters' => [
            ['type' => 'text', 'text' => 'John'],
            ['type' => 'currency', 'currency' => ['fallback_value' => '$10', 'code' => 'USD', 'amount_1000' => 10000]],
        ]],
    ]);
});

it('rejects template footer parameters', function () {
    Http::fake();

    expect(fn () => makeService()->sendTemplateMessageWithComponents('1234567890', 'tpl', [], [], ['Footer']))
        ->toThrow(WhatsappException::class, 'Template footers do not accept parameters');

    Http::assertNothingSent();
});

it('sends a multi-product message with header and body', function () {
    fakeMessageSent();
    $sections = [['title' => 'Products', 'product_items' => [['product_retailer_id' => 'sku_1']]]];

    makeService()->sendMultiProductMessage('1234567890', 'catalog_1', 'Browse our range', $sections, 'Our products');

    Http::assertSent(fn (Request $request) => $request['interactive'] === [
        'type' => 'product_list',
        'header' => ['type' => 'text', 'text' => 'Our products'],
        'body' => ['text' => 'Browse our range'],
        'action' => ['catalog_id' => 'catalog_1', 'sections' => $sections],
    ]);
});

it('requires a header for multi-product messages', function () {
    Http::fake();

    expect(fn () => makeService()->sendMultiProductMessage('1234567890', 'catalog_1', 'Browse', []))
        ->toThrow(WhatsappException::class, 'Multi-product messages require header text');
});

it('builds template payloads with the template builder', function () {
    $payload = makeService()->template('order_update')
        ->header([['type' => 'document', 'document' => ['id' => 'doc_1']]])
        ->body(['John', 42])
        ->button('url', ['track/123'], 1)
        ->build();

    expect($payload['template'])->toBe([
        'name' => 'order_update',
        'language' => ['code' => 'en_US'],
        'components' => [
            ['type' => 'header', 'parameters' => [['type' => 'document', 'document' => ['id' => 'doc_1']]]],
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'John'], ['type' => 'text', 'text' => '42']]],
            ['type' => 'button', 'sub_type' => 'url', 'index' => '1', 'parameters' => [['type' => 'text', 'text' => 'track/123']]],
        ],
    ]);
});

it('detects the MIME type when uploading with a media category', function () {
    Http::fake(['*/media' => Http::response(['id' => 'media_1'])]);
    $file = sys_get_temp_dir().'/wa-'.uniqid().'.png';
    file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAMAASsJTYQAAAAASUVORK5CYII='));

    makeService()->uploadMedia($file, 'image');

    Http::assertSent(fn (Request $request) => collect($request->data())
        ->contains(fn ($part) => $part['name'] === 'type' && $part['contents'] === 'image/png'));
    unlink($file);
});

it('requests business profile fields and sends messaging_product on update', function () {
    Http::fake(['*' => Http::response(['success' => true])]);
    $service = makeService();

    $service->getBusinessProfile();
    $service->updateBusinessProfile(['about' => 'Hello']);

    $requests = Http::recorded();
    expect($requests[0][0]->url())->toContain('fields=about%2Caddress%2Cdescription%2Cemail%2Cprofile_picture_url%2Cwebsites%2Cvertical');
    expect($requests[1][0]->data())->toBe(['messaging_product' => 'whatsapp', 'about' => 'Hello']);
});
