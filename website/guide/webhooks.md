# Webhooks and events

Meta delivers incoming messages, delivery receipts and template reviews to your app as webhooks. The package receives them, checks they're genuine, and dispatches Laravel events.

## The webhook route

The package registers the route for you:

```
GET|POST  /whatsapp/webhook
```

`GET` answers Meta's verification request using `WHATSAPP_WEBHOOK_VERIFY_TOKEN`. `POST` receives events. The route doesn't use the `web` middleware group, so you don't need to exclude it from CSRF protection.

When `WHATSAPP_WEBHOOK_SECRET` is set to your App Secret, requests with a missing or wrong `X-Hub-Signature-256` signature get a 401 response. Without it, requests are accepted and a warning is logged each time. Set it in production: otherwise anyone who knows the URL can send you fake events.

## Events

All events are in the `Crenspire\Whatsapp\Events` namespace.

| Event | Dispatched when | Main properties |
|---|---|---|
| `MessageReceived` | A customer sends you a message | `messageId`, `from`, `message`, `timestamp`, `phoneNumberId`, `contact`, `userId` |
| `MessageStatusUpdated` | Any status change of a message you sent: `sent`, `delivered`, `read` or `failed` | `messageId`, `recipient`, `status`, `timestamp`, `data`, `phoneNumberId` |
| `MessageDelivered` | A message reaches the customer's phone | `messageId`, `recipient`, `timestamp`, `phoneNumberId` |
| `MessageRead` | The customer reads a message | `messageId`, `recipient`, `timestamp`, `phoneNumberId` |
| `MessageFailed` | A send request fails, or WhatsApp later reports that delivery failed | `recipient`, `error`, `messageId`, `payload`, `phoneNumberId`, `tenantId` |
| `MessageSent` | The API accepts a message you sent | `messageId`, `recipient`, `response`, `payload`, `phoneNumberId`, `tenantId` |
| `TemplateStatusUpdated` | Meta approves, rejects, pauses or disables a template | `templateId`, `name`, `language`, `status`, `reason`, `category` |
| `TemplateQualityUpdated` | A template's quality score changes | `templateId`, `name`, `language`, `previousScore`, `newScore` |

`MessageSent` and `MessageFailed` for send requests are dispatched by your own code when it sends. The others come from webhooks.

## Handling incoming messages

Register a listener in a service provider's `boot()` method, or create a listener class:

```php
use Crenspire\Whatsapp\Events\MessageReceived;
use Crenspire\Whatsapp\Facades\Whatsapp;
use Illuminate\Support\Facades\Event;

Event::listen(function (MessageReceived $event) {
    if ($event->buttonReplyId() === 'delivery_yes') {
        // The customer confirmed tomorrow's delivery
    }

    if ($event->isType('text')) {
        Whatsapp::showTypingIndicator($event->messageId);
        Whatsapp::sendTextMessage($event->from, "Thanks {$event->senderName()}, we got your message.", replyTo: $event->messageId);
    }
});
```

`MessageReceived` has helpers for the common fields, so you rarely need the raw `message` array:

| Helper | Returns |
|---|---|
| `type()`, `isType('image')` | The message type: `text`, `image`, `interactive`, `button`, `location` and so on |
| `text()` | The text, media caption, or title of the tapped button or list item |
| `buttonReplyId()` | The ID of the reply button the customer tapped |
| `listReplyId()` | The ID of the list item the customer picked |
| `buttonPayload()` | The payload of a template quick-reply button |
| `flowResponse()` | The decoded response of a completed WhatsApp Flow |
| `mediaId()` | The media ID of an image, video, audio, document or sticker |
| `replyToMessageId()` | The ID of your message the customer replied to or tapped a button on |
| `senderName()` | The customer's WhatsApp profile name |
| `senderUsername()` | The customer's WhatsApp username, if they have one |
| `hasPhoneNumber()` | Whether WhatsApp shared the customer's phone number |

To save a photo a customer sent you:

```php
Event::listen(function (MessageReceived $event) {
    if ($event->isType('image')) {
        $path = Whatsapp::downloadMedia($event->mediaId());
    }
});
```

## Delivery status

```php
use Crenspire\Whatsapp\Events\MessageFailed;
use Crenspire\Whatsapp\Events\MessageRead;

Event::listen(function (MessageRead $event) {
    Order::where('whatsapp_message_id', $event->messageId)->update(['customer_read_at' => $event->timestamp]);
});

Event::listen(function (MessageFailed $event) {
    // $event->messageId is set when WhatsApp reports the failure by webhook
    logger()->warning('WhatsApp message failed', ['to' => $event->recipient, 'error' => $event->error]);
});
```

Store the message ID from the send response (`$response['messages'][0]['id']`) to match status events to your own records. The [message log](./message-log) can do this for you.

## Template reviews

Subscribe to `message_template_status_update` and `message_template_quality_update` in the Meta App Dashboard, then:

```php
use Crenspire\Whatsapp\Events\TemplateStatusUpdated;
use Illuminate\Support\Facades\Log;

Event::listen(function (TemplateStatusUpdated $event) {
    if ($event->isRejected()) {
        Log::warning("Template {$event->name} was rejected: {$event->reason}");
    }
});
```

`reason` is set for rejections, for example `INCORRECT_CATEGORY` or `PROMOTIONAL`, and is `null` otherwise. The raw webhook data, including Meta's recommendation for invalid templates, is in `$event->data`.

## Duplicate deliveries

Meta retries webhooks that don't get a quick 200 response, so the same event can arrive more than once. The package remembers processed messages and statuses in your cache for 24 hours and skips repeats, so your listeners run once per event.

If you run several servers, use a shared cache store such as Redis or your database, or set `WHATSAPP_WEBHOOK_CACHE_STORE`. You can turn this off with `WHATSAPP_WEBHOOK_DEDUPLICATE=false`.

Keep listeners fast so Meta gets its response quickly. Queue slow work by making the listener implement `ShouldQueue`.

## Customers without a phone number

WhatsApp users can hide their phone number behind a username. Messages from them only include a business-scoped user ID: `$event->from` holds that ID, `$event->userId` is set, and `$event->hasPhoneNumber()` returns `false`. The package can't send messages to user IDs yet.

## Logging

Every webhook is logged at `info` level with the number of entries. Full payloads contain customers' messages, so they're only logged when `WHATSAPP_DEBUG=true`.
