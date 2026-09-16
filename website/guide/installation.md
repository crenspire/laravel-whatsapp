# Installation and setup

## Requirements

- PHP 8.2 or newer (8.3 or newer for Laravel 13)
- Laravel 12 or 13
- A Meta app with the WhatsApp product added. Meta's [getting started guide](https://developers.facebook.com/docs/whatsapp/cloud-api/get-started) walks you through creating one and gives you a test phone number.

If you're on Laravel 10 or 11, use version 2.x of the package.

## Install the package

```bash
composer require crenspire/laravel-whatsapp
```

The service provider, the `Whatsapp` facade, the webhook route and the Artisan commands are registered automatically.

Publish the config file if you want to change any defaults:

```bash
php artisan vendor:publish --tag=whatsapp-config
```

## Add your credentials

```dotenv
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_BUSINESS_ACCOUNT_ID=
WHATSAPP_WEBHOOK_VERIFY_TOKEN=
WHATSAPP_WEBHOOK_SECRET=
WHATSAPP_APP_ID=
```

Where to find each value in the [Meta App Dashboard](https://developers.facebook.com/apps):

| Variable | Where to find it |
|---|---|
| `WHATSAPP_PHONE_NUMBER_ID` | WhatsApp → API Setup |
| `WHATSAPP_BUSINESS_ACCOUNT_ID` | WhatsApp → API Setup. Only needed for managing templates. |
| `WHATSAPP_ACCESS_TOKEN` | The token on the API Setup page expires after 24 hours. For production, create a System User in Business Settings and generate a permanent token with the `whatsapp_business_messaging` and `whatsapp_business_management` permissions. |
| `WHATSAPP_WEBHOOK_VERIFY_TOKEN` | Any random string you choose. You'll enter the same value in the webhook settings. |
| `WHATSAPP_WEBHOOK_SECRET` | Your app's **App Secret**, under App settings → Basic. Used to check that webhooks really come from Meta. |
| `WHATSAPP_APP_ID` | App settings → Basic. Only needed to upload sample media for template headers. |

## Set up webhooks

Webhooks are how you receive messages and delivery receipts. In the Meta App Dashboard, go to WhatsApp → Configuration and:

1. Set the callback URL to `https://your-app.com/whatsapp/webhook`.
2. Enter your verify token.
3. Subscribe to the `messages` field. For template review events, also subscribe to `message_template_status_update` and `message_template_quality_update`.

Meta needs to reach your app over HTTPS. For local development, use a tunnel such as [ngrok](https://ngrok.com) and set `APP_URL` to the tunnel address.

## Check your setup

```bash
php artisan whatsapp:check
```

This confirms your token and phone number work and your app is subscribed to webhooks. It also warns if your callback URL doesn't use HTTPS or points to a local address that Meta can't reach. Then send yourself a message:

```bash
php artisan whatsapp:test 15551234567
```

This sends Meta's `hello_world` template, which every new WhatsApp Business account has. The number has to be one you've added as a recipient if you're using Meta's test phone number.

## Next steps

- [Send your first messages](./sending-messages)
- [Send notifications over WhatsApp](./notifications)
- [Handle incoming messages](./webhooks)
