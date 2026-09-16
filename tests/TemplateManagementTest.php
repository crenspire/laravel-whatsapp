<?php

use Crenspire\Whatsapp\Exceptions\WhatsappException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const TEMPLATES_URL = 'https://graph.facebook.com/v20.0/waba_123/message_templates';

function bodyComponent(): array
{
    return [['type' => 'BODY', 'text' => 'Hi {{1}}']];
}

function templatesPage(array $templates, ?string $after = null): array
{
    $page = ['data' => $templates];

    if ($after !== null) {
        $page['paging'] = ['cursors' => ['after' => $after], 'next' => "https://graph.facebook.com/next?after={$after}"];
    }

    return $page;
}

// Creating

it('creates a template', function () {
    Http::fake(['*' => Http::response(['id' => 'tpl_1', 'status' => 'PENDING', 'category' => 'UTILITY'])]);
    $components = [
        ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Order Confirmation'],
        ['type' => 'BODY', 'text' => 'Hi {{1}}, your order {{2}} has been confirmed.'],
        ['type' => 'FOOTER', 'text' => 'Thank you for shopping with us.'],
    ];

    $response = makeService()->createTemplate('order_confirmation', 'en_US', 'UTILITY', $components);

    expect($response)->toBe(['id' => 'tpl_1', 'status' => 'PENDING', 'category' => 'UTILITY']);
    Http::assertSent(fn (Request $request) => $request->url() === TEMPLATES_URL
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer test_token')
        && $request->data() === [
            'name' => 'order_confirmation',
            'language' => 'en_US',
            'category' => 'UTILITY',
            'components' => $components,
        ]);
});

it('accepts every supported category', function (string $category) {
    Http::fake(['*' => Http::response(['id' => 'tpl_1'])]);

    makeService()->createTemplate('test', 'en_US', $category, bodyComponent());

    Http::assertSent(fn (Request $request) => $request['category'] === $category);
})->with(['MARKETING', 'UTILITY', 'AUTHENTICATION']);

it('rejects invalid categories without calling the API', function (string $category) {
    Http::fake();

    expect(fn () => makeService()->createTemplate('test', 'en_US', $category, bodyComponent()))
        ->toThrow(WhatsappException::class, 'Invalid template category');

    Http::assertNothingSent();
})->with(['TRANSACTIONAL', 'INVALID', 'utility']);

it('validates template components', function (array $components, string $message) {
    Http::fake();

    expect(fn () => makeService()->createTemplate('test', 'en_US', 'UTILITY', $components))
        ->toThrow(WhatsappException::class, $message);

    Http::assertNothingSent();
})->with([
    'empty' => [[], 'Template components cannot be empty'],
    'bad type' => [[['type' => 'INVALID']], 'Invalid component type'],
    'bad format' => [[['type' => 'HEADER', 'format' => 'GIF'], ['type' => 'BODY', 'text' => 'x']], 'Invalid component format'],
    'no body' => [[['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'x']], 'must include a BODY component'],
]);

it('accepts every header format', function (string $format) {
    Http::fake(['*' => Http::response(['id' => 'tpl_1'])]);

    makeService()->createTemplate('test', 'en_US', 'MARKETING', [
        ['type' => 'HEADER', 'format' => $format],
        ['type' => 'BODY', 'text' => 'Body'],
    ]);

    Http::assertSentCount(1);
})->with(['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT', 'LOCATION']);

// Editing and deleting

it('edits a template by looking up its ID', function () {
    Http::fake([
        TEMPLATES_URL.'*' => Http::response(templatesPage([
            ['id' => 'tpl_es', 'name' => 'order_update', 'language' => 'es_ES'],
            ['id' => 'tpl_en', 'name' => 'order_update', 'language' => 'en_US'],
        ])),
        'https://graph.facebook.com/v20.0/tpl_en' => Http::response(['success' => true]),
    ]);

    makeService()->updateTemplate('order_update', 'en_US', 'UTILITY', bodyComponent());

    Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.facebook.com/v20.0/tpl_en'
        && $request->method() === 'POST'
        && $request->data() === ['components' => bodyComponent(), 'category' => 'UTILITY']);
});

it('throws when editing a template that does not exist', function () {
    Http::fake(['*' => Http::response(templatesPage([]))]);

    expect(fn () => makeService()->updateTemplate('missing', 'en_US', 'UTILITY', bodyComponent()))
        ->toThrow(WhatsappException::class, "Template 'missing (en_US)' not found");
});

it('deletes a template using the name query parameter', function () {
    Http::fake(['*' => Http::response(['success' => true])]);

    expect(makeService()->deleteTemplate('old_template'))->toBeTrue();

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === TEMPLATES_URL.'?name=old_template');
});

it('surfaces API errors', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Template name already exists']], 400)]);

    expect(fn () => makeService()->createTemplate('dup', 'en_US', 'UTILITY', bodyComponent()))
        ->toThrow(WhatsappException::class, 'Failed to create template: Template name already exists');
});

// Retrieval

it('gets templates with filters', function (string $method, string $value, string $query) {
    Http::fake(['*' => Http::response(templatesPage([]))]);

    makeService()->{$method}($value);

    Http::assertSent(fn (Request $request) => $request->url() === TEMPLATES_URL.'?'.$query);
})->with([
    ['getTemplatesByStatus', 'APPROVED', 'status=APPROVED'],
    ['getTemplatesByStatus', 'PAUSED', 'status=PAUSED'],
    ['getTemplatesByCategory', 'MARKETING', 'category=MARKETING'],
    ['getTemplatesByLanguage', 'en_US', 'language=en_US'],
]);

it('gets all templates', function () {
    Http::fake(['*' => Http::response(templatesPage([['name' => 'a']]))]);

    expect(makeService()->getTemplates()['data'])->toHaveCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === TEMPLATES_URL);
});

it('rejects invalid statuses', function () {
    Http::fake();

    expect(fn () => makeService()->getTemplatesByStatus('INVALID_STATUS'))
        ->toThrow(WhatsappException::class, 'Invalid template status');
});

it('finds a template by exact name across pages', function () {
    Http::fake(function (Request $request) {
        return str_contains($request->url(), 'after=cursor_1')
            ? Http::response(templatesPage([['id' => 'tpl_2', 'name' => 'welcome', 'status' => 'APPROVED']]))
            : Http::response(templatesPage([['id' => 'tpl_1', 'name' => 'welcome_back', 'status' => 'PENDING']], 'cursor_1'));
    });

    $template = makeService()->getTemplate('welcome');

    expect($template['id'])->toBe('tpl_2');
    Http::assertSentCount(2);
});

it('reports template status', function () {
    Http::fake(['*' => Http::response(templatesPage([
        ['name' => 'approved_tpl', 'status' => 'APPROVED'],
        ['name' => 'pending_tpl', 'status' => 'PENDING'],
    ]))]);
    $service = makeService();

    expect($service->getTemplateStatus('approved_tpl'))->toBe('APPROVED');
    expect($service->isTemplateApproved('approved_tpl'))->toBeTrue();
    expect($service->isTemplatePending('approved_tpl'))->toBeFalse();
    expect($service->isTemplatePending('pending_tpl'))->toBeTrue();
});

// Configuration

it('requires business account ID for template management', function () {
    Http::fake();

    expect(fn () => makeService(['business_account_id' => null])->getTemplates())
        ->toThrow(WhatsappException::class, 'Business Account ID is required for template management');

    Http::assertNothingSent();
});

it('uses the tenant business account ID and credentials', function () {
    Http::fake(['*' => Http::response(templatesPage([]))]);
    $service = makeService([
        'business_account_id' => null,
        'tenants' => [
            'tenant1' => [
                'phone_number_id' => 'tenant_phone',
                'access_token' => 'tenant_token',
                'business_account_id' => 'tenant_waba',
            ],
        ],
    ]);

    $service->getTemplates([], 'tenant1');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.facebook.com/v20.0/tenant_waba/message_templates'
        && $request->hasHeader('Authorization', 'Bearer tenant_token'));
});
