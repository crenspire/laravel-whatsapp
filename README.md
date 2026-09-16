# Laravel WhatsApp

[![Latest Version on Packagist](https://img.shields.io/packagist/v/crenspire/laravel-whatsapp.svg?style=flat-square)](https://packagist.org/packages/crenspire/laravel-whatsapp)
[![Tests](https://img.shields.io/github/actions/workflow/status/crenspire/laravel-whatsapp/tests.yml?branch=develop&label=tests&style=flat-square)](https://github.com/crenspire/laravel-whatsapp/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/crenspire/laravel-whatsapp.svg?style=flat-square)](https://packagist.org/packages/crenspire/laravel-whatsapp)

Send and receive WhatsApp messages from Laravel using Meta's [WhatsApp Business Cloud API](https://developers.facebook.com/docs/whatsapp/cloud-api).

**Documentation: [crenspire.github.io/laravel-whatsapp](https://crenspire.github.io/laravel-whatsapp/)**

```php
use Crenspire\Whatsapp\Facades\Whatsapp;

Whatsapp::sendTextMessage('15551234567', 'Your order has shipped.');
```

You can send messages as Laravel notifications, and incoming messages and delivery receipts arrive as regular events, so WhatsApp fits into your app like mail or Slack.

## Features

| | |
|---|---|
| **Messages** | Text, image, video, audio, document, sticker, location, contacts and reactions, optionally as a reply to a customer's message |
| **Interactive messages** | Reply buttons, lists, WhatsApp Flows, single and multi-product catalog messages |
| **Notifications** | A `whatsapp` notification channel, so `$user->notify(new OrderShipped)` sends a WhatsApp message |
| **Templates** | Send templates with text or media parameters; create, edit and delete them; upload sample media for headers |
| **Webhooks** | Verification, signature checking, duplicate filtering, and events for received messages, delivery status and template reviews |
| **Error handling** | Automatic retries for rate limits and temporary errors, and an exception class for each kind of failure |
| **Testing** | `Whatsapp::fake()` with assertions such as `assertSentText()` and `assertSentTemplate()` |
| **Message log** | Optionally store sent and received messages, with delivery and read times |
| **Artisan commands** | `whatsapp:check` to verify your setup, `whatsapp:test` to send a test message, `whatsapp:templates` to list templates |
| **Media** | Upload, download to local storage, look up and delete media |
| **Multiple numbers** | Send from several WhatsApp numbers or business accounts, each with its own token |
| **Extras** | Read receipts, typing indicators, business profile, per-number rate limiting, custom request headers |

## Requirements

- PHP 8.2+ (8.3+ for Laravel 13)
- Laravel 12 or 13
- A Meta app with the WhatsApp product added ([getting started guide](https://developers.facebook.com/docs/whatsapp/cloud-api/get-started))

## Installation

```bash
composer require crenspire/laravel-whatsapp
```

Upgrading from 2.x? See [UPGRADE.md](UPGRADE.md). For Laravel 10 or 11, use version 2.x.

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
WHATSAPP_APP_ID=
```

Where to find them in the [Meta App Dashboard](https://developers.facebook.com/apps):

- **Phone number ID** and **WhatsApp Business Account ID**: WhatsApp → API Setup.
- **Access token**: the token on the API Setup page expires after 24 hours. For production, create a System User in Business Settings and generate a permanent token with the `whatsapp_business_messaging` and `whatsapp_business_management` permissions.
- **Webhook verify token**: any random string you choose. You'll enter the same value when configuring the webhook.
- **Webhook secret**: your app's **App Secret** (App settings → Basic). It's used to check that webhook requests really come from Meta.
- **App ID**: also on App settings → Basic.

The business account ID is only needed for template management, and the app ID only for uploading sample media for template headers.

Once everything is set, run `php artisan whatsapp:check`. It checks that your token works, that your app is subscribed to webhooks and that the callback URL is reachable.

Other options:

| Variable | Default | |
|---|---|---|
| `WHATSAPP_BASE_URI` | `https://graph.facebook.com/v20.0` | Graph API version to call |
| `WHATSAPP_DEFAULT_LANGUAGE` | `en_US` | Language used for template messages when none is given |
| `WHATSAPP_RATE_LIMIT` | `30` | Maximum messages sent per minute, per phone number |
| `WHATSAPP_TIMEOUT` | `30` | Request timeout in seconds |
| `WHATSAPP_RETRY_TIMES` | `3` | Attempts per request when a failure is temporary (`1` disables retries) |
| `WHATSAPP_RETRY_SLEEP` | `200` | Milliseconds before the first retry, doubled on each retry |
| `WHATSAPP_WEBHOOK_DEDUPLICATE` | `true` | Skip webhook events that were already processed |
| `WHATSAPP_WEBHOOK_CACHE_STORE` | default store | Cache store used to remember processed webhook events |
| `WHATSAPP_MESSAGE_LOG` | `false` | Store sent and received messages in the database (see [Message log](#message-log)) |
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

### Replies, read receipts and typing indicators

```php
// Quote the customer's message in your reply
Whatsapp::sendTextMessage($event->from, 'Yes, it ships today.', replyTo: $event->messageId);

// Mark a message as read (the blue ticks)
Whatsapp::markMessageAsRead($event->messageId);

// Mark it as read and show "typing…" while you prepare a reply
Whatsapp::showTypingIndicator($event->messageId);
```

Every send method accepts `replyTo`, except reactions. The typing indicator disappears when you send a message, or after 25 seconds.

### Anything else

If you need a message type or option the helpers don't cover, send the raw payload. `messaging_product` and `to` are added for you:

```php
Whatsapp::sendMessage('15551234567', [
    'type' => 'text',
    'text' => ['body' => 'Hello', 'preview_url' => false],
]);
```

## Notifications

Add `whatsapp` to a notification's `via()` method and return a `WhatsappMessage` from `toWhatsapp()`:

```php
use Crenspire\Whatsapp\Notifications\WhatsappMessage;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification
{
    public function __construct(public string $customerName, public string $orderNumber) {}

    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsapp(object $notifiable): WhatsappMessage
    {
        return WhatsappMessage::template('order_shipped', [$this->customerName, $this->orderNumber]);
    }
}
```

Tell Laravel which number to send to by adding a route method to your notifiable model:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public function routeNotificationForWhatsapp(): ?string
    {
        return $this->phone;
    }
}
```

If you use [multiple phone numbers](#multiple-phone-numbers), return `['to' => $this->phone, 'tenant' => $this->team->whatsapp_tenant]` instead. Notifiables without a number are skipped.

`toWhatsapp()` can return any of these:

```php
WhatsappMessage::text('Your order has shipped.');
WhatsappMessage::template('order_shipped', ['Sarah', '#1042'], language: 'es_ES');
WhatsappMessage::fromTemplate(Whatsapp::template('delivery_update')->body(['Sarah'])->button('url', ['tracking/1042']));
WhatsappMessage::media('document', $mediaId, caption: 'Invoice #1042');
WhatsappMessage::buttons('Can we deliver tomorrow?', [['id' => 'yes', 'title' => 'Yes'], ['id' => 'no', 'title' => 'No']]);
WhatsappMessage::list('Choose a slot', 'View slots', $sections);
WhatsappMessage::location(51.5072, -0.1276, name: 'Our shop');
WhatsappMessage::payload(['type' => 'text', 'text' => ['body' => 'Hello']]);

// Each can be adjusted before it's sent
WhatsappMessage::text('On our way!')->replyTo($messageId)->tenant('acme')->to('15551234567');
```

Returning a plain string sends it as a text message. To notify a number that isn't a model, use an on-demand notification:

```php
use Illuminate\Support\Facades\Notification;

Notification::route('whatsapp', '15551234567')->notify(new OrderShipped('Sarah', '#1042'));
```

## Webhooks

The package registers the webhook route for you:

```
GET|POST  /whatsapp/webhook
```

In the Meta App Dashboard, go to WhatsApp → Configuration, set the callback URL to `https://your-app.com/whatsapp/webhook`, enter your verify token, and subscribe to the `messages` field. To get template review events, also subscribe to `message_template_status_update` and `message_template_quality_update`. The route doesn't use the `web` middleware group, so you don't need to exclude it from CSRF protection.

When `WHATSAPP_WEBHOOK_SECRET` is set, requests with a missing or wrong signature are rejected with a 401. Without it, requests are accepted and a warning is logged each time. Set it in production.

### Events

| Event | Dispatched when | Main properties |
|---|---|---|
| `MessageReceived` | A customer sends you a message | `messageId`, `from`, `message`, `timestamp`, `phoneNumberId` |
| `MessageStatusUpdated` | Any status change of a message you sent: `sent`, `delivered`, `read` or `failed` | `messageId`, `recipient`, `status`, `timestamp`, `data` |
| `MessageDelivered` | A message reaches the customer's phone | `messageId`, `recipient`, `timestamp` |
| `MessageRead` | The customer reads a message | `messageId`, `recipient`, `timestamp` |
| `MessageFailed` | A send request fails, or WhatsApp later reports that delivery failed | `recipient`, `error`, `messageId`, `payload` |
| `MessageSent` | The API accepts a message you sent | `messageId`, `recipient`, `response`, `payload` |
| `TemplateStatusUpdated` | Meta approves, rejects, pauses or disables a template | `templateId`, `name`, `language`, `status`, `reason` |
| `TemplateQualityUpdated` | A template's quality score changes | `templateId`, `name`, `previousScore`, `newScore` |

All events are in the `Crenspire\Whatsapp\Events` namespace. `MessageReceived` has helpers for the common fields, so you rarely need the raw `message` array:

```php
use Crenspire\Whatsapp\Events\MessageReceived;
use Crenspire\Whatsapp\Facades\Whatsapp;
use Illuminate\Support\Facades\Event;

// In a service provider's boot() method, or as a listener class
Event::listen(function (MessageReceived $event) {
    if ($event->buttonReplyId() === 'delivery_yes') {
        // ...
    }

    if ($event->isType('text')) {
        Whatsapp::showTypingIndicator($event->messageId);
        Whatsapp::sendTextMessage($event->from, "Thanks {$event->senderName()}, we got your message.", replyTo: $event->messageId);
    }
});
```

| Helper | Returns |
|---|---|
| `type()`, `isType('image')` | The message type |
| `text()` | The text, media caption, or title of the tapped button or list item |
| `buttonReplyId()`, `listReplyId()` | The ID of the reply button or list item the customer picked |
| `buttonPayload()` | The payload of a template quick-reply button |
| `flowResponse()` | The decoded response of a completed WhatsApp Flow |
| `mediaId()` | The media ID of an image, video, audio, document or sticker |
| `replyToMessageId()` | The ID of your message the customer replied to or tapped a button on |
| `senderName()` | The customer's WhatsApp profile name |

Template reviews work the same way:

```php
use Crenspire\Whatsapp\Events\TemplateStatusUpdated;
use Illuminate\Support\Facades\Log;

Event::listen(function (TemplateStatusUpdated $event) {
    if ($event->isRejected()) {
        Log::warning("Template {$event->name} was rejected: {$event->reason}");
    }
});
```

### Duplicates and slow listeners

Meta retries webhooks that don't get a quick 200 response, so the same event can arrive more than once. The package remembers processed messages and statuses in your cache for 24 hours and skips repeats, so your listeners run once per event. If you run several servers, use a shared cache store such as Redis or your database.

Queue slow work (make the listener implement `ShouldQueue`) so Meta gets its response quickly.

### Customers without a phone number

WhatsApp users can hide their phone number behind a username. Messages from them have a business-scoped user ID instead: `$event->from` holds that ID, and `$event->hasPhoneNumber()` returns `false`. The package can't send messages to user IDs yet.

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

Templates with an image, video or document header need a sample file when you create them. Upload it first (this needs `WHATSAPP_APP_ID`):

```php
$handle = Whatsapp::uploadTemplateMedia(storage_path('app/samples/parcel.jpg'));

Whatsapp::createTemplate('order_shipped_with_photo', 'en_US', 'UTILITY', [
    ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => ['header_handle' => [$handle]]],
    ['type' => 'BODY', 'text' => 'Hi {{1}}, order {{2}} has shipped.', 'example' => ['body_text' => [['Sarah', '#1042']]]],
]);
```

The sample can be a JPEG, PNG, MP4 or PDF file. To find out when Meta has reviewed a template, listen for [`TemplateStatusUpdated`](#events), or list templates with `php artisan whatsapp:templates`.

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

## Errors and retries

Failed API calls throw an exception from `Crenspire\Whatsapp\Exceptions`. Each kind of failure has its own class, and they all extend `WhatsappException`:

| Exception | When |
|---|---|
| `CustomerServiceWindowException` | You sent a regular message more than 24 hours after the customer last messaged you. Send a template instead. |
| `RateLimitException` | You hit your own `WHATSAPP_RATE_LIMIT` or one of Meta's limits |
| `UndeliverableMessageException` | The recipient can't get the message, for example because they aren't on WhatsApp or opted out of marketing |
| `TemplateException` | The template doesn't exist in that language, isn't approved, or the parameters don't match |
| `AuthenticationException` | The access token is invalid, expired, or missing a permission |
| `InvalidRequestException` | A parameter is missing or invalid |
| `AccountException` | Your business account or phone number is restricted, locked, or has a payment problem |
| `ServiceUnavailableException` | Meta had a temporary problem |
| `ConnectionException` | The API couldn't be reached |

```php
use Crenspire\Whatsapp\Exceptions\CustomerServiceWindowException;
use Crenspire\Whatsapp\Exceptions\WhatsappException;

try {
    Whatsapp::sendTextMessage($phone, $text);
} catch (CustomerServiceWindowException $e) {
    Whatsapp::sendTemplateMessage($phone, 'follow_up', [$name]);
} catch (WhatsappException $e) {
    report($e);
}
```

The exception code is the HTTP status. `getErrorCode()` returns Meta's error code, `getErrorDetails()` its explanation, and `getFbtraceId()` the ID to quote to Meta support.

Temporary failures are retried automatically, up to `WHATSAPP_RETRY_TIMES` attempts, waiting longer each time and respecting `Retry-After`. Meta's API has no way to detect a duplicate send, so a message is only sent again when Meta confirms the first attempt failed, as with a rate limit error. If sending times out or fails with an unexplained server error, it isn't retried, because the message may already have been delivered.

Each phone number can send `WHATSAPP_RATE_LIMIT` messages per minute. Going over throws a `RateLimitException` without calling the API. The limit is tracked in your application cache, so use a shared store such as Redis if you send from several servers or queue workers.

## Message log

The package can store every message you send and receive, and keep each message's status up to date from webhooks. Publish and run the migration, then turn it on:

```bash
php artisan vendor:publish --tag=whatsapp-migrations
php artisan migrate
```

```env
WHATSAPP_MESSAGE_LOG=true
```

Messages are stored in the `whatsapp_messages` table and available through the `Crenspire\Whatsapp\Models\Message` model:

```php
use Crenspire\Whatsapp\Models\Message;

$conversation = Message::query()->conversation('15551234567')->get();

$lines = $conversation->map(fn (Message $message) => sprintf(
    '%s: %s (%s)',
    $message->isInbound() ? 'Customer' : 'You',
    $message->body,     // text, caption, button title or template name
    $message->status,   // received, accepted, sent, delivered, read or failed
));

$undelivered = Message::query()->outbound()->where('status', 'failed')->get();
```

Each row has `wamid`, `direction`, `phone`, `tenant`, `type`, `body`, the full `payload`, `status`, `errors`, and `sent_at`, `delivered_at`, `read_at` and `failed_at` timestamps. Statuses only move forward, so a delivery receipt that arrives after a read receipt doesn't undo it. If writing to the log fails, the error is reported but sending carries on.

## Artisan commands

```bash
php artisan whatsapp:check                          # check config, token, webhook subscription and callback URL
php artisan whatsapp:test 15551234567               # send Meta's hello_world template
php artisan whatsapp:test 15551234567 --text="Hi"   # send a text (only within 24 hours of the recipient messaging you)
php artisan whatsapp:templates --status=rejected    # list templates, or add --json
```

Each command accepts `--tenant` to use a tenant's credentials.

## Custom headers

Add headers to every request with the `default_headers` config option, per tenant with the tenant's `headers` key, or on a single call:

```php
Whatsapp::sendTextMessage('15551234567', 'Hello', customHeaders: ['X-Request-Id' => $requestId]);
```

Per-call headers override tenant headers, which override the defaults.

## Testing your app

Call `Whatsapp::fake()` to record messages instead of sending them. Messages sent by notifications are recorded too:

```php
use Crenspire\Whatsapp\Facades\Whatsapp;
use Crenspire\Whatsapp\Testing\SentMessage;

Whatsapp::fake();

// ... run the code that sends messages

Whatsapp::assertSentText('15551234567', 'Your order has shipped.');
Whatsapp::assertSentTemplate('15551234567', 'order_shipped');
Whatsapp::assertSentTo('15551234567', fn (SentMessage $message) => $message->replyTo() === 'wamid.in');
Whatsapp::assertSentCount(3);
Whatsapp::assertNotSentTo('15550000000');
```

There are also `assertSent()`, `assertNotSent()`, `assertNothingSent()`, `assertMarkedAsRead()`, `assertTypingIndicatorShown()`, `assertMediaUploaded()` and `assertTemplateCreated()`. `SentMessage` has the full `payload` plus `type()`, `text()`, `template()`, `language()` and `replyTo()`.

To test how your code handles failures, or code that looks up templates:

```php
use Crenspire\Whatsapp\Exceptions\CustomerServiceWindowException;

Whatsapp::fake()->failSending(new CustomerServiceWindowException('Outside the 24-hour window', 400));

Whatsapp::fake()->withTemplates([
    ['name' => 'order_shipped', 'language' => 'en_US', 'status' => 'APPROVED'],
]);
```

To test at the HTTP level instead, use `Http::fake()`. The package uses Laravel's HTTP client, so no real requests are sent.

To test webhook listeners, post a sample payload to `/whatsapp/webhook`. Meta's [webhook reference](https://developers.facebook.com/documentation/business-messaging/whatsapp/webhooks/reference/messages) has examples of every payload type.

## Development

```bash
composer test       # Pest
composer analyse    # PHPStan
composer format     # Pint
```

The full documentation is at [crenspire.github.io/laravel-whatsapp](https://crenspire.github.io/laravel-whatsapp/). Its source is in the [website](website) folder, and older notes are in [docs](docs).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

Pull requests are welcome. Please add tests for your change and run the commands above before opening one. See [docs/contributing.md](docs/contributing.md).

## Security

If you find a security issue, please email akshay.joshi@crenspire.com instead of opening a public issue.

## License

MIT. See [LICENSE.md](LICENSE.md).
