# Multiple phone numbers

If your app sends from more than one WhatsApp number, for example one per customer account, add each one as a tenant in `config/whatsapp.php`:

```php
'tenants' => [
    'acme' => [
        'phone_number_id' => env('ACME_WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('ACME_WHATSAPP_ACCESS_TOKEN'),
        'business_account_id' => env('ACME_WHATSAPP_BUSINESS_ACCOUNT_ID'), // optional
        'app_id' => env('ACME_WHATSAPP_APP_ID'),                           // optional
        'language' => 'de_DE',                                             // optional
        'headers' => [],                                                   // optional
    ],
],
```

Optional keys fall back to the top-level settings.

## Using a tenant

Pass the tenant ID to any method. Named arguments keep this readable:

```php
Whatsapp::sendTextMessage('15551234567', 'Hallo!', tenantId: 'acme');
Whatsapp::uploadMedia($path, 'image/png', tenantId: 'acme');
Whatsapp::getTemplates(tenantId: 'acme');
```

Calls without a tenant ID use the top-level credentials. An unknown tenant ID throws an exception instead of falling back to the default number, so a typo can't send messages from the wrong account.

## Everywhere else

- **Notifications:** return `['to' => $phone, 'tenant' => 'acme']` from `routeNotificationForWhatsapp()`, or call `->tenant('acme')` on the `WhatsappMessage`. See [Notifications](./notifications).
- **Webhooks:** events include `phoneNumberId`, the number that received the message or sent the original message.
- **Message log:** the `tenant` column is filled for sent messages, and for received messages whose `phone_number_id` matches a tenant.
- **Commands:** every command accepts `--tenant`.
- **Testing:** `Whatsapp::fake(['tenants' => [...]])` sets tenants for the fake.

## Custom headers

Add headers to every request with the `default_headers` config option, per tenant with the tenant's `headers` key, or on a single call:

```php
Whatsapp::sendTextMessage('15551234567', 'Hello', customHeaders: ['X-Request-Id' => $requestId]);
```

Per-call headers override tenant headers, which override the defaults.
