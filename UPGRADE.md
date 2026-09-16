# Upgrade Guide

## Upgrading from 2.x to 3.0

Changes are grouped by how likely they are to affect your app. Every app needs the high-impact changes. Check the others against the features you use.

### Checklist

1. Make sure you're on Laravel 12 or 13 and PHP 8.2 or newer.
2. Run `composer require crenspire/laravel-whatsapp:^3.0`.
3. If you published `config/whatsapp.php`, [update it](#update-your-published-config).
4. Search your code for tenant IDs and exception message checks (see [high](#high-impact-changes) and [medium](#medium-impact-changes) impact changes).
5. Run `php artisan whatsapp:check` to confirm your credentials and webhook setup.
6. Run your test suite.

## High impact changes

### Laravel 12 or 13 is required

Support for Laravel 10 and 11 has been dropped. Both are past their end of life, and Composer refuses to install their current releases because of unpatched security advisories. If you can't upgrade Laravel yet, stay on 2.x:

```bash
composer require crenspire/laravel-whatsapp:^2.0
```

Otherwise:

```bash
composer require crenspire/laravel-whatsapp:^3.0
```

### Update your published config

If you published `config/whatsapp.php`, make two changes.

Change the default language to Meta's format, which uses an underscore. `en-US` isn't a valid template language code:

```php
'default_language' => env('WHATSAPP_DEFAULT_LANGUAGE', 'en_US'),
```

Add the business account ID, which template management needs:

```php
'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
```

Also check any `language` values in your `tenants` config and in `WHATSAPP_DEFAULT_LANGUAGE`.

The other new options (`app_id`, `webhook`, `timeout`, `retry` and `message_log`) all have defaults, so you only need to add them to change them. The easiest way to get them all is to republish the config and copy your changes across.

### Unknown tenant IDs now throw

Passing a tenant ID that isn't in `config('whatsapp.tenants')` used to send the message from your default number. It now throws a `WhatsappException`, so a typo can't send messages from the wrong account.

If you relied on the fallback, pass `null` instead of an unknown tenant ID.

## Medium impact changes

### Failures throw more specific exceptions

API failures now throw a subclass of `WhatsappException`, such as `CustomerServiceWindowException` or `RateLimitException`. Code that catches `WhatsappException` keeps working.

The exception message format has changed. If you match on message text, switch to the exception class or Meta's error code:

```php
// Before
if (str_contains($e->getMessage(), 'Re-engagement')) { ... }

// After
if ($e instanceof CustomerServiceWindowException) { ... }
if ($e->getErrorCode() === 131047) { ... }
```

Messages now read `Failed to <action>: <Meta's message> (<details>)`. See [Errors and retries](https://crenspire.github.io/laravel-whatsapp/guide/errors) for the full list of exceptions.

### Requests are retried

Requests that fail for a temporary reason (rate limits, connection errors and server errors) are now attempted up to 3 times, with a short, growing wait between attempts. Message sends are only retried when Meta confirms the attempt failed, so a retry won't deliver a message twice.

If you already retry failed sends yourself, you may want to remove that. To keep the old behavior, set `WHATSAPP_RETRY_TIMES=1`.

### Duplicate webhook events are skipped

Events for a message or status that was already processed in the last 24 hours are no longer dispatched again. This uses your default cache store, so use a shared store such as Redis or your database if you run several servers.

If you deduplicate in your own listeners, you can remove that code, or set `WHATSAPP_WEBHOOK_DEDUPLICATE=false`.

### Webhook verification needs a verify token

In 2.x, if `WHATSAPP_WEBHOOK_VERIFY_TOKEN` was not set, a verification request without a token passed. Verification now fails unless the token is configured and matches.

Webhooks received while `WHATSAPP_WEBHOOK_SECRET` is empty still work, but log a warning. Set it to your Meta App Secret to verify request signatures.

### `MessageReceived::$from` can be a user ID

WhatsApp users can hide their phone number behind a username. Messages from them used to be ignored; they're now dispatched with the business-scoped user ID in `$from`. Check `$event->hasPhoneNumber()` before treating `$from` as a phone number, for example before saving it or replying.

## Low impact changes

### Template footers no longer accept parameters

Footers in WhatsApp templates are fixed text, and the API rejected footer parameters. Instead of failing at the API, these now throw a `WhatsappException` before any request is sent:

- `TemplateBuilder::footer()`
- The `$footerParameters` argument of `sendTemplateMessageWithComponents()`

Remove those calls. The footer text defined in the template is sent automatically.

### Multi-product messages require a header

The API requires a header on `product_list` messages. `sendMultiProductMessage()` and `MessageBuilder::multiProductInteractive()` now throw if `$headerText` is missing:

```php
Whatsapp::sendMultiProductMessage($to, $catalogId, 'Picked for you', $sections, headerText: 'Weekly picks');
```

The `$buttonText` argument has always been used as the message body. Its behavior hasn't changed, but it's now documented that way.

### Behavior fixes you may notice

- **Media uploads:** `uploadMedia()` sent a JSON content type with multipart data, which the API rejected. Uploads now work. The `$type` argument should be a MIME type such as `image/jpeg`; a category like `image` is converted using the file's detected type.
- **Media downloads:** `downloadMedia()` now throws when the file request fails. Previously it saved the error response to disk and returned its path.
- **Multiple tenants:** media and business profile calls now use the credentials of the tenant you pass. In 2.x, they reused the credentials of whichever tenant made the first call.
- **`MessageFailed` event:** has new optional `$messageId`, `$payload`, `$phoneNumberId` and `$tenantId` properties. It's also dispatched when a webhook reports a failed delivery, so listeners may now receive failures that happen after sending.
- **Logging:** webhook payloads are only logged in full when `WHATSAPP_DEBUG` is `true`, because they contain message contents.
- **`BusinessProfileManager::updateWebsite()`** sent the wrong field name and had no effect. It now updates the website.
- **Local rate limit:** exceeding `WHATSAPP_RATE_LIMIT` throws `RateLimitException` with code 429 (previously a `WhatsappException` with code 0).

### If you extend `WhatsappService` or use the managers directly

Managers are no longer cached on the service, so the protected `$mediaManager` and `$businessProfileManager` properties were removed. `getMediaManager()` and `getBusinessProfileManager()` take an extra `$customHeaders` argument. The unused protected `getFileExtensionFromMimeType()` method was removed. The same helper still exists privately on `MediaManager`.

`MediaManager`, `BusinessProfileManager` and `TemplateManager` now take a `Crenspire\Whatsapp\Http\GraphClient` instead of an array of headers:

```php
use Crenspire\Whatsapp\Http\GraphClient;

$client = GraphClient::fromConfig(config('whatsapp'), ['Authorization' => 'Bearer '.$token]);
$media = new MediaManager($baseUri, $phoneNumberId, $client, $storagePath);
```

`sendMessage()` and the send helpers have a new optional `$replyTo` argument at the end. If you override them, add it to your signatures.

## New features worth adopting

None of these are required, but they may replace code you wrote yourself on 2.x:

- **Notifications:** if you wrap WhatsApp sends in jobs or services, a [`whatsapp` notification channel](https://crenspire.github.io/laravel-whatsapp/guide/notifications) may be simpler.
- **`Whatsapp::fake()`:** if your tests fake HTTP responses from `graph.facebook.com`, [the fake](https://crenspire.github.io/laravel-whatsapp/guide/testing) lets you assert on messages directly.
- **Event helpers:** if your listeners read nested arrays such as `$event->message['interactive']['button_reply']['id']`, use [`$event->buttonReplyId()`](https://crenspire.github.io/laravel-whatsapp/guide/webhooks#handling-incoming-messages) and the other helpers.
- **Message log:** if you store messages and delivery statuses yourself, the [message log](https://crenspire.github.io/laravel-whatsapp/guide/message-log) may cover it.
- **`whatsapp:check`:** run it after deploying to catch expired tokens and webhook problems.
