<?php

use Crenspire\Whatsapp\WhatsappService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'whatsapp.phone_number_id' => '123456789',
        'whatsapp.access_token' => 'test_token',
        'whatsapp.business_account_id' => 'waba_123',
        'whatsapp.app_id' => 'app_123',
        'whatsapp.webhook_verify_token' => 'verify',
        'whatsapp.webhook_secret' => 'secret',
        'whatsapp.retry' => ['times' => 1, 'sleep' => 0],
        'app.url' => 'https://example.com',
    ]);
    url()->forceRootUrl('https://example.com');
    url()->forceScheme('https');
    app()->forgetInstance(WhatsappService::class);
});

// whatsapp:test

it('sends the hello_world template by default', function () {
    Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.test']]])]);

    $this->artisan('whatsapp:test', ['phone' => '15551234567'])
        ->expectsOutputToContain('Message sent to 15551234567')
        ->expectsOutputToContain('wamid.test')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request['template']['name'] === 'hello_world'
        && $request['template']['language']['code'] === 'en_US');
});

it('sends a text message when asked', function () {
    Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.test']]])]);

    $this->artisan('whatsapp:test', ['phone' => '15551234567', '--text' => 'Ping'])->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request['text']['body'] === 'Ping');
});

it('reports send failures with the Meta error code', function () {
    Http::fake(['*' => Http::response(['error' => ['code' => 131030, 'message' => 'Recipient phone number not in allowed list']], 400)]);

    $this->artisan('whatsapp:test', ['phone' => '15551234567'])
        ->expectsOutputToContain('Recipient phone number not in allowed list')
        ->expectsOutputToContain('131030')
        ->assertFailed();
});

// whatsapp:templates

it('lists templates across pages', function () {
    Http::fake(function (Request $request) {
        return str_contains($request->url(), 'after=cursor_1')
            ? Http::response(['data' => [['name' => 'welcome', 'language' => 'en_US', 'category' => 'MARKETING', 'status' => 'APPROVED', 'id' => '2']]])
            : Http::response([
                'data' => [['name' => 'order_shipped', 'language' => 'en_US', 'category' => 'UTILITY', 'status' => 'REJECTED', 'id' => '1']],
                'paging' => ['cursors' => ['after' => 'cursor_1'], 'next' => 'https://graph.facebook.com/next'],
            ]);
    });

    $this->artisan('whatsapp:templates', ['--status' => 'rejected'])
        ->expectsTable(['Name', 'Language', 'Category', 'Status', 'ID'], [
            ['order_shipped', 'en_US', 'UTILITY', 'REJECTED', '1'],
            ['welcome', 'en_US', 'MARKETING', 'APPROVED', '2'],
        ])
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'status=REJECTED'));
});

it('outputs templates as JSON', function () {
    Http::fake(['*' => Http::response(['data' => [['name' => 'welcome', 'status' => 'APPROVED']]])]);

    $this->artisan('whatsapp:templates', ['--json' => true])
        ->expectsOutputToContain('"name": "welcome"')
        ->assertSuccessful();
});

// whatsapp:check

it('passes when everything is configured', function () {
    Http::fake([
        'https://graph.facebook.com/v20.0/123456789?*' => Http::response(['display_phone_number' => '+1 555-000-0000', 'verified_name' => 'Acme', 'quality_rating' => 'GREEN']),
        'https://graph.facebook.com/v20.0/waba_123/subscribed_apps' => Http::response(['data' => [['whatsapp_business_api_data' => ['id' => 'app_123', 'name' => 'Acme App']]]]),
    ]);

    $this->artisan('whatsapp:check')
        ->expectsOutputToContain('+1 555-000-0000 Acme')
        ->expectsOutputToContain('1 app(s) subscribed')
        ->expectsOutputToContain('https://example.com/whatsapp/webhook')
        ->expectsOutputToContain('Everything looks good.')
        ->assertSuccessful();
});

it('fails when credentials are missing, without calling the API', function () {
    Http::fake();
    config(['whatsapp.access_token' => null]);

    $this->artisan('whatsapp:check')
        ->expectsOutputToContain('not set (WHATSAPP_ACCESS_TOKEN)')
        ->expectsOutputToContain('Some checks failed.')
        ->assertFailed();

    Http::assertNothingSent();
});

it('fails when the token is rejected or no app is subscribed', function () {
    Http::fake([
        'https://graph.facebook.com/v20.0/123456789?*' => Http::response(['error' => ['code' => 190, 'message' => 'Error validating access token']], 401),
        'https://graph.facebook.com/v20.0/waba_123/subscribed_apps' => Http::response(['data' => []]),
    ]);

    $this->artisan('whatsapp:check')
        ->expectsOutputToContain('Error validating access token')
        ->expectsOutputToContain('no app is subscribed')
        ->assertFailed();
});

it('warns about local callback URLs and a missing message log table', function () {
    Http::fake([
        'https://graph.facebook.com/v20.0/123456789?*' => Http::response(['display_phone_number' => '+1 555', 'quality_rating' => 'GREEN']),
        'https://graph.facebook.com/v20.0/waba_123/subscribed_apps' => Http::response(['data' => [['whatsapp_business_api_data' => ['id' => 'app_123']]]]),
    ]);
    url()->forceRootUrl('http://localhost');
    url()->forceScheme('http');
    config(['whatsapp.message_log.enabled' => true]);

    $this->artisan('whatsapp:check')
        ->expectsOutputToContain('Meta requires HTTPS')
        ->expectsOutputToContain('whatsapp_messages is missing')
        ->assertFailed();
});

it('rejects unknown tenants', function () {
    $this->artisan('whatsapp:check', ['--tenant' => 'missing'])
        ->expectsOutputToContain('Tenant [missing] is not configured')
        ->assertFailed();
});

it('reports warnings separately from failures', function () {
    Http::fake([
        'https://graph.facebook.com/v20.0/123456789?*' => Http::response(['display_phone_number' => '+1 555', 'quality_rating' => 'YELLOW']),
        'https://graph.facebook.com/v20.0/waba_123/subscribed_apps' => Http::response(['data' => [['whatsapp_business_api_data' => ['id' => 'app_123']]]]),
    ]);
    config(['whatsapp.webhook_secret' => null]);

    $this->artisan('whatsapp:check')
        ->expectsOutputToContain('webhook signatures are not verified')
        ->expectsOutputToContain('messaging limits may be lowered')
        ->expectsOutputToContain('Checks passed with warnings.')
        ->assertSuccessful();
});
