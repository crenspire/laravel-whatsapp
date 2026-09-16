# Artisan commands

## whatsapp:check

Checks your configuration, credentials and webhook setup.

```bash
php artisan whatsapp:check
php artisan whatsapp:check --tenant=acme
```

```
   INFO  Configuration.

  Phone number ID ................................................ ✓ 106540352242922
  Access token ......................................................... ✓ set
  Business account ID ............................................. ✓ 102290129340398
  Webhook verify token ................................................. ✓ set
  Webhook secret ....................................................... ✓ set

   INFO  API access.

  Phone number ..................................... ✓ +1 555-010-0199 Acme Flowers
  Quality rating ..................................................... ✓ GREEN
  Webhook subscription ................................. ✓ 1 app(s) subscribed

   INFO  Webhook.

  Callback URL ............................. ✓ https://example.com/whatsapp/webhook

   INFO  Everything looks good.
```

It fails, with exit code 1, when required credentials are missing, the token is rejected, or no app is subscribed to webhooks for the business account. It warns when the webhook secret isn't set, the quality rating has dropped, or the callback URL doesn't use HTTPS or points to a local address. With the message log enabled, it also checks that the table exists.

## whatsapp:test

Sends a test message.

```bash
php artisan whatsapp:test 15551234567
php artisan whatsapp:test 15551234567 --template=order_shipped --language=es_ES
php artisan whatsapp:test 15551234567 --text="Testing, testing"
php artisan whatsapp:test 15551234567 --tenant=acme
```

Without options, it sends Meta's `hello_world` template, which exists in every new WhatsApp Business account. Text messages only work if the recipient has messaged your number in the last 24 hours. If sending fails, the command shows Meta's error message and code.

## whatsapp:templates

Lists the message templates in your business account, across all pages of results.

```bash
php artisan whatsapp:templates
php artisan whatsapp:templates --status=rejected
php artisan whatsapp:templates --category=marketing --language=en_US
php artisan whatsapp:templates --json
php artisan whatsapp:templates --tenant=acme
```

```
+---------------+----------+-----------+----------+-----------------+
| Name          | Language | Category  | Status   | ID              |
+---------------+----------+-----------+----------+-----------------+
| order_shipped | en_US    | UTILITY   | APPROVED | 784932018475021 |
| welcome       | en_US    | MARKETING | REJECTED | 784932018475022 |
+---------------+----------+-----------+----------+-----------------+
```
