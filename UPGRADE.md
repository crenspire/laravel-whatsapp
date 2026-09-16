# Upgrade Guide

## Upgrading from 2.x to 3.0

Most apps only need the first three steps. The rest apply if you use the specific feature.

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

### Unknown tenant IDs now throw

Passing a tenant ID that isn't in `config('whatsapp.tenants')` used to send the message from your default number. It now throws a `WhatsappException`, so a typo can't send messages from the wrong account.

If you relied on the fallback, pass `null` instead of an unknown tenant ID.

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

### Webhook verification needs a verify token

In 2.x, if `WHATSAPP_WEBHOOK_VERIFY_TOKEN` was not set, a verification request without a token passed. Verification now fails unless the token is configured and matches.

Webhooks received while `WHATSAPP_WEBHOOK_SECRET` is empty still work, but log a warning. Set it to your Meta App Secret to verify request signatures.

### Smaller changes

- **Media uploads:** `uploadMedia()` sent a JSON content type with multipart data, which the API rejected. Uploads now work. The `$type` argument should be a MIME type such as `image/jpeg`; a category like `image` is converted using the file's detected type.
- **Media downloads:** `downloadMedia()` now throws when the file request fails. Previously it saved the error response to disk and returned its path.
- **Multiple tenants:** media, business profile and template calls now use the credentials of the tenant you pass. In 2.x, all of them reused the credentials of whichever tenant made the first call.
- **`MessageFailed` event:** has a new optional `$messageId` property. It's also dispatched when a webhook reports a failed delivery, so listeners may now receive failures that happen after sending.
- **Logging:** webhook payloads are only logged in full when `WHATSAPP_DEBUG` is `true`, because they contain message contents.
- **`BusinessProfileManager::updateWebsite()`** sent the wrong field name and had no effect. It now updates the website.

### If you extend `WhatsappService`

Managers are no longer cached on the service, so the protected `$mediaManager` and `$businessProfileManager` properties were removed. `getMediaManager()` and `getBusinessProfileManager()` take an extra `$customHeaders` argument. The unused protected `getFileExtensionFromMimeType()` method was removed. The same helper still exists privately on `MediaManager`.
