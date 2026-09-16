# Testing

## Faking WhatsApp

Call `Whatsapp::fake()` at the start of a test. Messages are recorded instead of sent, including messages sent by notifications, and you can assert on them afterwards:

```php
use Crenspire\Whatsapp\Facades\Whatsapp;
use Crenspire\Whatsapp\Testing\SentMessage;

public function test_customers_are_told_when_their_order_ships(): void
{
    Whatsapp::fake();

    $this->post("/orders/{$order->id}/ship");

    Whatsapp::assertSentTemplate('15551234567', 'order_shipped');
    Whatsapp::assertSentTo('15551234567', fn (SentMessage $message) => $message->replyTo() === null);
    Whatsapp::assertSentCount(1);
}
```

The fake builds messages exactly as the real service does, and still validates phone numbers and tenant IDs, so mistakes surface in tests.

### Assertions

| Assertion | Passes when |
|---|---|
| `assertSent($callback)` | A message matching the callback was sent |
| `assertSentTo($phone, $callback)` | A message was sent to the number, optionally matching the callback |
| `assertSentText($phone, $text)` | A text message was sent to the number, optionally with that exact text |
| `assertSentTemplate($phone, $name, $language)` | A template was sent to the number, optionally in that language |
| `assertNotSent($callback)` | No message matched the callback |
| `assertNotSentTo($phone)` | Nothing was sent to the number |
| `assertNothingSent()` | No messages were sent |
| `assertSentCount($count)` | Exactly that many messages were sent |
| `assertMarkedAsRead($messageId)` | The message was marked as read |
| `assertTypingIndicatorShown($messageId)` | A typing indicator was shown for the message |
| `assertMediaUploaded($callback)` | Media was uploaded |
| `assertTemplateCreated($name, $callback)` | A template was created |

Phone numbers are compared by their digits, so `+1 555 123 4567` matches `15551234567`. When an assertion fails, the message lists what was actually sent.

### Inspecting messages

Callbacks receive a `SentMessage` with the full request `payload`, the `to` number, the `tenantId`, and helpers: `type()`, `text()`, `template()`, `language()` and `replyTo()`.

```php
$messages = Whatsapp::sent(fn (SentMessage $message) => $message->type() === 'interactive');

$this->assertSame('delivery_yes', $messages->first()->payload['interactive']['action']['buttons'][0]['reply']['id']);
```

### Simulating failures

```php
use Crenspire\Whatsapp\Exceptions\CustomerServiceWindowException;

Whatsapp::fake()->failSending(new CustomerServiceWindowException('Outside the 24-hour window', 400));

// Or decide per message
Whatsapp::fake()->failSending(fn (SentMessage $message) => $message->isTo('15550000000')
    ? new CustomerServiceWindowException('Outside the 24-hour window', 400)
    : null);
```

### Templates in tests

If your code checks template status, give the fake some templates:

```php
Whatsapp::fake()->withTemplates([
    ['name' => 'order_shipped', 'language' => 'en_US', 'status' => 'APPROVED'],
    ['name' => 'welcome', 'language' => 'en_US', 'status' => 'REJECTED'],
]);
```

## Testing webhook listeners

Post a payload to the webhook route. Meta's [webhook reference](https://developers.facebook.com/documentation/business-messaging/whatsapp/webhooks/reference/messages) has examples of every payload type.

```php
use Crenspire\Whatsapp\Facades\Whatsapp;

public function test_the_bot_replies_to_status_requests(): void
{
    Whatsapp::fake();
    config(['whatsapp.webhook_secret' => null]);

    $this->postJson('/whatsapp/webhook', [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => 'WABA_ID',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => ['phone_number_id' => 'PHONE_NUMBER_ID'],
                    'contacts' => [['profile' => ['name' => 'Sarah'], 'wa_id' => '15551234567']],
                    'messages' => [[
                        'id' => 'wamid.test',
                        'from' => '15551234567',
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => 'status'],
                    ]],
                ],
            ]],
        ]],
    ])->assertOk();

    Whatsapp::assertSentText('15551234567', 'Your order ships today.');
}
```

To test signature checking too, keep the secret and send the header: `'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $json, $secret)`, where `$json` is the exact request body.

## Testing at the HTTP level

The package uses Laravel's HTTP client, so `Http::fake()` also stops real requests, if you'd rather assert on the raw API calls:

```php
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

Http::fake([
    'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]]),
]);

// ... run the code that sends a message

Http::assertSent(fn (Request $request) => $request['to'] === '15551234567');
```
