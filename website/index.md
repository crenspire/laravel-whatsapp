---
layout: home

hero:
  name: Laravel WhatsApp
  text: WhatsApp messaging for Laravel apps
  tagline: Send messages and templates, receive webhooks as events, and send notifications on a whatsapp channel, using Meta's WhatsApp Business Cloud API.
  image:
    src: /logo.svg
    alt: Laravel WhatsApp
  actions:
    - theme: brand
      text: Get started
      link: /guide/installation
    - theme: alt
      text: What's new in 3.0
      link: /whats-new
    - theme: alt
      text: View on GitHub
      link: https://github.com/crenspire/laravel-whatsapp

features:
  - title: Every message type
    details: Text, media, templates, reply buttons, lists, WhatsApp Flows, locations, contacts, reactions and catalog messages, with replies that quote the customer's message.
    link: /guide/sending-messages
  - title: Notifications
    details: Add whatsapp to a notification's via() method and send with $user->notify(). Queued notifications work too.
    link: /guide/notifications
  - title: Webhooks as events
    details: Incoming messages, delivery and read receipts, and template reviews arrive as Laravel events. Signatures are checked and duplicate deliveries are skipped.
    link: /guide/webhooks
  - title: Templates
    details: Send templates with text or media parameters, and create, edit and delete them from code, including sample media for image and video headers.
    link: /guide/templates
  - title: Errors you can act on
    details: A separate exception for each kind of failure, and automatic retries for rate limits and temporary errors that never send a message twice.
    link: /guide/errors
  - title: Testing without the API
    details: Call Whatsapp::fake() and assert what was sent with assertSentText(), assertSentTemplate() and more.
    link: /guide/testing
  - title: Message log
    details: Optionally store every sent and received message, with sent, delivered and read times kept up to date from webhooks.
    link: /guide/message-log
  - title: Artisan commands
    details: Check your credentials and webhook setup, send a test message, and list your templates from the terminal.
    link: /guide/commands
  - title: Multiple phone numbers
    details: Send from several WhatsApp numbers or business accounts, each with its own token, by passing a tenant ID.
    link: /guide/multiple-numbers
---

## What it looks like

Send a message from anywhere in your app:

```php
use Crenspire\Whatsapp\Facades\Whatsapp;

Whatsapp::sendTextMessage('15551234567', 'Your order has shipped.');

Whatsapp::sendTemplateMessage('15551234567', 'order_shipped', ['Sarah', '#1042']);
```

Or send it as a notification:

```php
class OrderShipped extends Notification
{
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsapp(object $notifiable): WhatsappMessage
    {
        return WhatsappMessage::template('order_shipped', [$notifiable->name, $this->order->number]);
    }
}
```

And reply to customers from an event listener:

```php
Event::listen(function (MessageReceived $event) {
    if ($event->text() === 'status') {
        Whatsapp::showTypingIndicator($event->messageId);
        Whatsapp::sendTextMessage($event->from, 'Your order ships today.', replyTo: $event->messageId);
    }
});
```

Requires PHP 8.2+ and Laravel 12 or 13. [Install it in a few minutes →](/guide/installation)
