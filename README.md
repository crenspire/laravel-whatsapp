# Laravel WhatsApp

[![Latest Version on Packagist](https://img.shields.io/packagist/v/crenspire/laravel-whatsapp.svg?style=flat-square)](https://packagist.org/packages/crenspire/laravel-whatsapp)
[![Tests](https://img.shields.io/github/actions/workflow/status/crenspire/laravel-whatsapp/tests.yml?branch=develop&label=tests&style=flat-square)](https://github.com/crenspire/laravel-whatsapp/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/crenspire/laravel-whatsapp.svg?style=flat-square)](https://packagist.org/packages/crenspire/laravel-whatsapp)

Send and receive WhatsApp messages from Laravel using Meta's [WhatsApp Business Cloud API](https://developers.facebook.com/docs/whatsapp/cloud-api).

```php
use Crenspire\Whatsapp\Facades\Whatsapp;

Whatsapp::sendTextMessage('15551234567', 'Your order has shipped.');
```

Incoming messages and delivery receipts arrive as regular Laravel events, so you handle them with listeners like anything else in your app.

## Features

| | |
|---|---|
| **Messages** | Text, image, video, audio, document, sticker, location, contacts, reactions |
| **Interactive messages** | Reply buttons, lists, WhatsApp Flows, single and multi-product catalog messages |
| **Templates** | Send templates with text or media parameters, and create, edit, delete and check the approval status of templates |
| **Media** | Upload, download to local storage, look up and delete media |
| **Webhooks** | Verification endpoint, signature checking, and events for received, delivered, read and failed messages |
| **Multiple numbers** | Send from several WhatsApp numbers or business accounts, each with its own token |
| **Business profile** | Read and update the profile shown to your customers |
| **Extras** | Read receipts, per-number rate limiting, custom request headers |

## Requirements

- PHP 8.2+
- Laravel 10, 11 or 12
- A Meta app with the WhatsApp product added ([getting started guide](https://developers.facebook.com/docs/whatsapp/cloud-api/get-started))

## Installation

```bash
composer require crenspire/laravel-whatsapp
```

The service provider and `Whatsapp` facade are registered automatically. Publish the config file if you want to change the defaults:

```bash
php artisan vendor:publish --tag=whatsapp-config
```

## Configuration

Add your credentials to `.env`:

```env
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_BUSINESS_ACCOUNT_ID=
WHATSAPP_WEBHOOK_VERIFY_TOKEN=
WHATSAPP_WEBHOOK_SECRET=
```

Where to find them in the [Meta App Dashboard](https://developers.facebook.com/apps):

- **Phone number ID** and **WhatsApp Business Account ID**: WhatsApp → API Setup.
- **Access token**: the token on the API Setup page expires after 24 hours. For production, create a System User in Business Settings and generate a permanent token with the `whatsapp_business_messaging` and `whatsapp_business_management` permissions.
- **Webhook verify token**: any random string you choose. You'll enter the same value when configuring the webhook.
- **Webhook secret**: your app's **App Secret** (App settings → Basic). It's used to check that webhook requests really come from Meta.

The business account ID is only needed for template management.

Other options:

| Variable | Default | |
|---|---|---|
| `WHATSAPP_BASE_URI` | `https://graph.facebook.com/v20.0` | Graph API version to call |
| `WHATSAPP_DEFAULT_LANGUAGE` | `en_US` | Language used for template messages when none is given |
| `WHATSAPP_RATE_LIMIT` | `30` | Maximum messages sent per minute, per phone number |
| `WHATSAPP_DEBUG` | `false` | Log full webhook payloads (they contain message contents) |

## Sending messages

Pass phone numbers with the country code, like `15551234567`. A leading `+` and formatting characters are accepted too.

Every send method returns the decoded API response. The message ID is in `$response['messages'][0]['id']`.

### Text

```php
Whatsapp::sendTextMessage('15551234567', 'Hello!');

// Show a preview for the first link in the message
Whatsapp::sendTextMessage('15551234567', 'Track it here: https://example.com/t/123', previewUrl: true);
```

### Media

Media is sent by ID, so upload the file first. The ID can be reused for 30 days.

```php
$media = Whatsapp::uploadMedia(storage_path('app/invoices/1042.pdf'), 'application/pdf');

Whatsapp::sendMediaMessage('15551234567', $media['id'], 'document', caption: 'Invoice #1042');
```

The type is `image`, `video`, `audio`, `document` or `sticker`. Captions work on images, videos and documents.

### Templates

Outside the 24-hour window after a customer last messaged you, WhatsApp only lets you send approved templates.

```php
// Fill the body placeholders {{1}} and {{2}}
Whatsapp::sendTemplateMessage('15551234567', 'order_shipped', ['Sarah', '#1042']);

// Use a different language version of the template
Whatsapp::sendTemplateMessage('15551234567', 'order_shipped', ['Sara', '#1042'], language: 'es_ES');
```

For templates with a media header, pass header parameters in the shape the API expects:

```php
Whatsapp::sendTemplateMessageWithComponents(
    to: '15551234567',
    templateName: 'order_shipped_with_photo',
    bodyParameters: ['Sarah', '#1042'],
    headerParameters: [
        ['type' => 'image', 'image' => ['link' => 'https://example.com/parcel.jpg']],
    ],
);
```

Templates with buttons are easier to build with the template builder:

```php
$template = Whatsapp::template('delivery_update')
    ->body(['Sarah', 'Thursday'])
    ->button('url', ['tracking/1042'], index: 0)
    ->build();

Whatsapp::sendMessage('15551234567', $template);
```

### Buttons and lists

```php
Whatsapp::sendButtonMessage('15551234567', 'Can we deliver tomorrow?', [
    ['id' => 'delivery_yes', 'title' => 'Yes'],
    ['id' => 'delivery_no', 'title' => 'Pick another day'],
]);

Whatsapp::sendListMessage(
    to: '15551234567',
    bodyText: 'Choose a delivery slot',
    buttonText: 'View slots',
    sections: [
        [
            'title' => 'Thursday',
            'rows' => [
                ['id' => 'thu_am', 'title' => 'Morning', 'description' => '8am to 12pm'],
                ['id' => 'thu_pm', 'title' => 'Afternoon', 'description' => '12pm to 5pm'],
            ],
        ],
    ],
);
```

WhatsApp allows up to three reply buttons. When the customer taps one, you receive a `MessageReceived` event containing the button's `id` (see [Webhooks](#webhooks)).

### Location, contacts, stickers and reactions

```php
Whatsapp::sendLocationMessage('15551234567', 51.5072, -0.1276, name: 'Our shop', address: '1 High Street, London');

Whatsapp::sendContactMessage('15551234567', [
    [
        'name' => ['formatted_name' => 'Support Team', 'first_name' => 'Support'],
        'phones' => [['phone' => '+15550001111', 'type' => 'WORK']],
    ],
]);

Whatsapp::sendStickerMessage('15551234567', $stickerMediaId);

Whatsapp::sendReactionMessage('15551234567', $messageId, '👍');
```

### Flows and product messages

```php
Whatsapp::sendFlowMessage(
    to: '15551234567',
    flowToken: 'booking-1042',
    flowId: '1234567890',
    flowCta: 'Book a table',
    flowAction: 'navigate',
    flowActionPayload: ['screen' => 'BOOKING'],
);

Whatsapp::sendSingleProductMessage('15551234567', $catalogId, 'SKU-123', bodyText: 'Back in stock');

Whatsapp::sendMultiProductMessage(
    to: '15551234567',
    catalogId: $catalogId,
    buttonText: 'Picked for you this week',
    sections: [
        ['title' => 'New in', 'product_items' => [['product_retailer_id' => 'SKU-123']]],
    ],
    headerText: 'Weekly picks',
);
```

For multi-product messages, `buttonText` is the body text and `headerText` is required.

### Read receipts

```php
Whatsapp::markMessageAsRead($event->messageId);
```

### Anything else

If you need a message type or option the helpers don't cover, send the raw payload. `messaging_product` and `to` are added for you:

```php
Whatsapp::sendMessage('15551234567', [
    'type' => 'text',
    'text' => ['body' => 'Hello', 'preview_url' => false],
]);
```

## Webhooks

The package registers the webhook route for you:

```
GET|POST  /whatsapp/webhook
```

In the Meta App Dashboard, go to WhatsApp → Configuration, set the callback URL to `https://your-app.com/whatsapp/webhook`, enter your verify token, and subscribe to the `messages` field. The route doesn't use the `web` middleware group, so you don't need to exclude it from CSRF protection.

When `WHATSAPP_WEBHOOK_SECRET` is set, requests with a missing or wrong signature are rejected with a 401. Without it, requests are accepted and a warning is logged each time. Set it in production.

### Events

| Event | Dispatched when | Properties |
|---|---|---|
| `MessageReceived` | A customer sends you a message | `messageId`, `from`, `message`, `timestamp` |
| `MessageDelivered` | A message reaches the customer's phone | `messageId`, `recipient` |
| `MessageRead` | The customer reads a message | `messageId`, `recipient`, `timestamp` |
| `MessageFailed` | A send request fails, or WhatsApp later reports that delivery failed | `recipient`, `error`, `messageId` |
| `MessageSent` | The API accepts a message you sent | `messageId`, `recipient`, `response` |

All events are in the `Crenspire\Whatsapp\Events` namespace. `message` is the raw message from the webhook, including the text, media ID or button reply:

```php
use Crenspire\Whatsapp\Events\MessageReceived;
use Crenspire\Whatsapp\Facades\Whatsapp;
use Illuminate\Support\Facades\Event;

// In a service provider's boot() method, or as a listener class
Event::listen(function (MessageReceived $event) {
    $message = $event->message;

    if ($message['type'] === 'interactive' && isset($message['interactive']['button_reply'])) {
        $choice = $message['interactive']['button_reply']['id']; // e.g. "delivery_yes"
        // ...
    }

    if ($message['type'] === 'text') {
        Whatsapp::markMessageAsRead($event->messageId);
        Whatsapp::sendTextMessage($event->from, 'Thanks, we got your message.');
    }
});
```

Meta retries webhooks that don't get a quick 200 response, and can deliver the same event more than once. Queue slow work (make the listener implement `ShouldQueue`) and use `messageId` to skip duplicates.

## Managing templates

These methods need `WHATSAPP_BUSINESS_ACCOUNT_ID`.

```php
$template = Whatsapp::createTemplate('order_shipped', 'en_US', 'UTILITY', [
    ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Your order is on its way'],
    ['type' => 'BODY', 'text' => 'Hi {{1}}, order {{2}} has shipped.'],
    ['type' => 'FOOTER', 'text' => 'Reply STOP to opt out'],
]);

$template['status']; // PENDING
```

New and edited templates go to Meta for review automatically; there's no separate publish step. Check the result later:

```php
Whatsapp::getTemplateStatus('order_shipped');   // APPROVED, PENDING, REJECTED, PAUSED, ...
Whatsapp::isTemplateApproved('order_shipped');

Whatsapp::getTemplates();                       // one page of results, with paging cursors
Whatsapp::getTemplates(['limit' => 100, 'after' => $cursor]);
Whatsapp::getTemplatesByStatus('REJECTED');
Whatsapp::getTemplatesByCategory('MARKETING');
Whatsapp::getTemplatesByLanguage('es_ES');
Whatsapp::getTemplate('order_shipped');

// Replaces all components of the en_US version and sends it for review again
Whatsapp::updateTemplate('order_shipped', 'en_US', 'UTILITY', $components);

// Deletes every language version
Whatsapp::deleteTemplate('order_shipped');
```

The category must be `MARKETING`, `UTILITY` or `AUTHENTICATION`. Meta limits how often an approved template can be edited, so it's worth getting templates right before they're approved.

## Media

```php
$media = Whatsapp::uploadMedia($path, 'image/jpeg');   // ['id' => '...']

$info = Whatsapp::getMediaInfo($mediaId);              // url, mime_type, file_size, sha256
$path = Whatsapp::downloadMedia($mediaId);             // path of the saved file
Whatsapp::deleteMedia($mediaId);
```

`uploadMedia` expects a MIME type. If you pass a category like `image`, the MIME type is detected from the file. Downloads are saved to the `media_storage` directory from the config, `storage/app/whatsapp-media` by default.

To save a photo a customer sent you, use the media ID from the incoming message:

```php
Event::listen(function (MessageReceived $event) {
    if ($event->message['type'] === 'image') {
        $path = Whatsapp::downloadMedia($event->message['image']['id']);
    }
});
```

## Multiple phone numbers

If your app sends from more than one WhatsApp number, for example one per customer account, add each one under `tenants` in `config/whatsapp.php`:

```php
'tenants' => [
    'acme' => [
        'phone_number_id' => env('ACME_WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('ACME_WHATSAPP_ACCESS_TOKEN'),
        'business_account_id' => env('ACME_WHATSAPP_BUSINESS_ACCOUNT_ID'), // optional
        'language' => 'de_DE',                                             // optional
        'headers' => [],                                                   // optional
    ],
],
```

Then pass the tenant ID to any method. Named arguments keep this readable:

```php
Whatsapp::sendTextMessage('15551234567', 'Hallo!', tenantId: 'acme');
Whatsapp::uploadMedia($path, 'image/png', tenantId: 'acme');
Whatsapp::getTemplates(tenantId: 'acme');
```

Calls without a tenant ID use the top-level credentials. An unknown tenant ID throws an exception instead of falling back to the default number.

## Business profile

```php
$profile = Whatsapp::getBusinessProfile()['data'][0];

Whatsapp::updateBusinessProfile([
    'about' => 'Fresh flowers, delivered daily',
    'email' => 'hello@example.com',
    'websites' => ['https://example.com'],
]);
```

## Errors and rate limiting

Failed API calls throw `Crenspire\Whatsapp\Exceptions\WhatsappException`. The exception code is the HTTP status, and the message includes Meta's error and the response body:

```php
use Crenspire\Whatsapp\Exceptions\WhatsappException;

try {
    Whatsapp::sendTextMessage($phone, $text);
} catch (WhatsappException $e) {
    report($e);
}
```

Each phone number can send `WHATSAPP_RATE_LIMIT` messages per minute. Going over throws a `WhatsappException` without calling the API. The limit is tracked in your application cache, so use a shared store such as Redis if you send from several servers or queue workers.

## Custom headers

Add headers to every request with the `default_headers` config option, per tenant with the tenant's `headers` key, or on a single call:

```php
Whatsapp::sendTextMessage('15551234567', 'Hello', customHeaders: ['X-Request-Id' => $requestId]);
```

Per-call headers override tenant headers, which override the defaults.

## Testing your app

The package uses Laravel's HTTP client, so `Http::fake()` keeps your tests from sending real messages:

```php
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

Http::fake([
    'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]]),
]);

// ... run the code that sends a message

Http::assertSent(fn (Request $request) =>
    $request['to'] === '15551234567' && $request['text']['body'] === 'Your order has shipped.'
);
```

To test webhook listeners, post a sample payload to `/whatsapp/webhook`. Meta's [webhook reference](https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks/components) has examples of every payload type.

## Development

```bash
composer test       # Pest
composer analyse    # PHPStan
composer format     # Pint
```

More documentation is in the [docs](docs) folder, including the [API reference](docs/api-reference.md) and [troubleshooting](docs/troubleshooting.md).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

Pull requests are welcome. Please add tests for your change and run the commands above before opening one. See [docs/contributing.md](docs/contributing.md).

## Security

If you find a security issue, please email akshay.joshi@crenspire.com instead of opening a public issue.

## License

Released under the MIT License.
