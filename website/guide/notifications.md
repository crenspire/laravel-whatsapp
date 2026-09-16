# Notifications

The package adds a `whatsapp` channel to Laravel's notification system, so WhatsApp messages can be sent the same way as mail or Slack notifications.

## Create a notification

Add `whatsapp` to `via()` and return a `WhatsappMessage` from `toWhatsapp()`:

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

You can also use `WhatsappChannel::class` instead of `'whatsapp'` in `via()`. Returning a plain string from `toWhatsapp()` sends it as a text message.

## Route notifications to a phone number

Add a route method to your notifiable model:

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

Then notify as usual:

```php
$user->notify(new OrderShipped($user->name, $order->number));
```

Notifiables without a number are skipped. If you use [multiple phone numbers](./multiple-numbers), return the tenant along with the number:

```php
public function routeNotificationForWhatsapp(): array
{
    return ['to' => $this->phone, 'tenant' => $this->team->whatsapp_tenant];
}
```

To notify a number that doesn't belong to a model, use an on-demand notification:

```php
use Illuminate\Support\Facades\Notification;

Notification::route('whatsapp', '15551234567')->notify(new OrderShipped('Sarah', '#1042'));
```

## Message types

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
```

Each message can be adjusted before it's sent:

```php
WhatsappMessage::text('On our way!')
    ->replyTo($messageId)       // quote a message the customer sent
    ->tenant('acme')            // send from a tenant's number
    ->to('15551234567')         // override the notifiable's route
    ->withHeaders(['X-Request-Id' => $requestId]);
```

Template languages default to the tenant's language, then `WHATSAPP_DEFAULT_LANGUAGE`.

## Queued notifications

Implement `ShouldQueue` to send in the background:

```php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class OrderShipped extends Notification implements ShouldQueue
{
    use Queueable;

    // ...
}
```

If sending fails, the channel throws the matching [exception](./errors), so the queue retries the job according to its `tries` and `backoff` settings.

## Testing notifications

With `Whatsapp::fake()`, notifications are recorded instead of sent, so you can check the message itself:

```php
Whatsapp::fake();

$user->notify(new OrderShipped('Sarah', '#1042'));

Whatsapp::assertSentTemplate($user->phone, 'order_shipped');
```

Laravel's `Notification::fake()` also works if you only want to check that the notification was sent. See [Testing](./testing).
