# Laravel WhatsApp 3.0.0

WhatsApp now fits into a Laravel app the way mail and Slack do: send messages as notifications, fake them in tests, and handle incoming messages with event listeners. This release also fixes several bugs in 2.x, including two security issues, so we recommend upgrading.

**[Documentation](https://crenspire.github.io/laravel-whatsapp/)** · **[Upgrade guide](https://crenspire.github.io/laravel-whatsapp/upgrade)** · **[Full changelog](https://github.com/crenspire/laravel-whatsapp/blob/develop/CHANGELOG.md)**

## Highlights

### Notifications

Add `whatsapp` to a notification's `via()` method, return a message from `toWhatsapp()`, and send it with `$user->notify()`. Queued and on-demand notifications work too.

```php
public function toWhatsapp(object $notifiable): WhatsappMessage
{
    return WhatsappMessage::template('order_shipped', [
        $notifiable->name,
        $this->order->number,
    ]);
}
```

[Notifications guide →](https://crenspire.github.io/laravel-whatsapp/guide/notifications)

### Testing with `Whatsapp::fake()`

Record messages instead of sending them, then assert on them. Messages sent by notifications are recorded too.

```php
Whatsapp::fake();

$this->post("/orders/{$order->id}/ship");

Whatsapp::assertSentTemplate('15551234567', 'order_shipped');
```

You can also simulate failures with `failSending()`, and provide templates for code that checks approval status. [Testing guide →](https://crenspire.github.io/laravel-whatsapp/guide/testing)

### Errors you can act on

Each kind of failure has its own exception, such as `CustomerServiceWindowException`, `RateLimitException`, `TemplateException` or `AuthenticationException`. Each one includes Meta's error code, details and trace ID.

```php
try {
    Whatsapp::sendTextMessage($phone, $text);
} catch (CustomerServiceWindowException $e) {
    Whatsapp::sendTemplateMessage($phone, 'follow_up', [$name]);
}
```

Rate limits and temporary errors are retried automatically with exponential backoff. Meta's API can't detect a duplicate send, so messages are only retried when Meta confirms the first attempt failed, never after a timeout. [Errors guide →](https://crenspire.github.io/laravel-whatsapp/guide/errors)

### Better webhooks

- **Duplicate filtering:** Meta can deliver the same webhook more than once. Repeats are now skipped, so your listeners run once per event.
- **Message helpers:** `MessageReceived` has `text()`, `buttonReplyId()`, `listReplyId()`, `flowResponse()`, `mediaId()`, `senderName()` and more.
- **New events:**
  - `MessageStatusUpdated` for every status change
  - `TemplateStatusUpdated` when Meta approves or rejects a template
  - `TemplateQualityUpdated` when a template's quality score changes
- **Hidden phone numbers:** messages from users who hide their number behind a username are now handled. 2.x dropped them.

[Webhooks guide →](https://crenspire.github.io/laravel-whatsapp/guide/webhooks)

### Replies and typing indicators

Quote the customer's message in your reply, and show "typing…" while you prepare it.

```php
Whatsapp::showTypingIndicator($event->messageId);
Whatsapp::sendTextMessage($event->from, 'It ships today.', replyTo: $event->messageId);
```

### Template management

Create, edit, delete and list message templates from code, and check whether Meta has approved them. `uploadTemplateMedia()` uploads the sample file that templates with image, video or document headers need. [Templates guide →](https://crenspire.github.io/laravel-whatsapp/guide/templates)

### Message log

An optional database log of every message you send and receive. Statuses (sent, delivered, read, failed) are updated from webhooks, and a `conversation()` query scope returns the history with a customer. [Message log guide →](https://crenspire.github.io/laravel-whatsapp/guide/message-log)

### Artisan commands

- `whatsapp:check` verifies your token, phone number, webhook subscription and callback URL.
- `whatsapp:test` sends a test message.
- `whatsapp:templates` lists your templates.

[Commands guide →](https://crenspire.github.io/laravel-whatsapp/guide/commands)

## Security fixes

These affect every 2.x release.

- **API calls could use another tenant's credentials.** With multiple phone numbers configured, media and business profile calls reused the credentials of whichever tenant made the first call. Separately, a message sent with an unknown tenant ID was silently sent from your default number. Each call now uses the tenant you pass, and unknown tenant IDs throw an exception.
- **The webhook verify token was written to the log.** A failed verification logged the expected token. Also, when no verify token was configured, verification requests passed. Both are fixed, and full webhook payloads, which contain customers' messages, are now only logged in debug mode.

## Other fixes

- The package couldn't be installed in new Laravel 13 apps, because it required Guzzle 7 and new Laravel 13 apps use Guzzle 8.
- Media uploads were rejected by the API, because they were sent with a JSON content type.
- Webhooks only processed the first entry of a batched delivery, so events in the same delivery were lost.
- Template media header parameters were sent in the wrong format.
- Custom headers were ignored for media and business profile calls.
- `BusinessProfileManager::updateWebsite()` sent the wrong field name and had no effect.

## Breaking changes

Most apps only need to upgrade Laravel if required and update the published config. See the **[upgrade guide](https://crenspire.github.io/laravel-whatsapp/upgrade)** for details on each change.

- **Laravel:** requires Laravel 12 or 13. Laravel 10 and 11 users should stay on 2.x.
- **Config:** `default_language` now defaults to `en_US`. Meta's language codes use an underscore, so `en-US` was never valid for templates.
- **Tenants:** unknown tenant IDs throw instead of using the default number.
- **Exceptions:** API failures throw subclasses of `WhatsappException`, and the messages include Meta's error details.
- **Retries:** temporary failures are retried by default. Set `WHATSAPP_RETRY_TIMES=1` to turn this off.
- **Webhooks:**
  - Duplicate events are no longer dispatched.
  - Verification requires a configured verify token.
  - `MessageReceived::$from` can be a user ID instead of a phone number.
- **Templates and messages:** template footer parameters and multi-product messages without a header now throw, where the API used to reject them.
- **Extending the package:** managers take a `GraphClient` instead of a headers array. This only matters if you extend `WhatsappService` or create managers yourself.

## Upgrading

```bash
composer require crenspire/laravel-whatsapp:^3.0
```

Then follow the [upgrade guide](https://crenspire.github.io/laravel-whatsapp/upgrade) and run `php artisan whatsapp:check` to confirm your setup.

## Requirements

- PHP 8.2 or newer (8.3 or newer for Laravel 13)
- Laravel 12 or 13

**Full changelog:** https://github.com/crenspire/laravel-whatsapp/compare/v2.0.0...v3.0.0
