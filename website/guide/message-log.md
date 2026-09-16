# Message log

The package can store every message you send and receive in your database, and keep each message's status up to date from webhooks. It's a starting point for conversation history, support tools and delivery reports.

## Setup

Publish and run the migration, then turn the log on:

```bash
php artisan vendor:publish --tag=whatsapp-migrations
php artisan migrate
```

```dotenv
WHATSAPP_MESSAGE_LOG=true
```

To store messages on a different database connection, set `WHATSAPP_MESSAGE_LOG_CONNECTION` before migrating.

## What's stored

Messages are stored in the `whatsapp_messages` table and available through the `Crenspire\Whatsapp\Models\Message` model.

| Column | Contains |
|---|---|
| `wamid` | The WhatsApp message ID (empty for sends the API rejected) |
| `direction` | `inbound` or `outbound` |
| `phone` | The customer's number, digits only |
| `phone_number_id`, `tenant` | Which of your numbers sent or received it |
| `type` | `text`, `template`, `image`, `interactive` and so on |
| `body` | The text, caption, button title or template name |
| `payload` | The full message |
| `status` | `received`, `accepted`, `sent`, `delivered`, `read` or `failed` |
| `errors` | Error details for failed messages |
| `reply_to` | The ID of the message it replies to |
| `sent_at`, `delivered_at`, `read_at`, `failed_at` | When each status was reported |

Statuses only move forward, so a delivery receipt that arrives after a read receipt doesn't undo it. Duplicate webhook deliveries don't create duplicate rows.

## Querying

```php
use Crenspire\Whatsapp\Models\Message;

// A conversation with one customer, oldest first
$conversation = Message::query()->conversation('15551234567')->get();

$lines = $conversation->map(fn (Message $message) => sprintf(
    '%s: %s (%s)',
    $message->isInbound() ? 'Customer' : 'You',
    $message->body,
    $message->status,
));

// Messages that failed to deliver today
$failed = Message::query()->outbound()
    ->where('status', 'failed')
    ->whereDate('created_at', today())
    ->get();

// Messages the customer hasn't read after a day
$unread = Message::query()->outbound()
    ->whereNotNull('delivered_at')
    ->whereNull('read_at')
    ->where('created_at', '<', now()->subDay())
    ->get();
```

## Reliability

Writing to the log never interrupts sending. If a database write fails, the error is reported to your exception handler and the message is still sent, because an exception at that point could make your code retry a message that was already delivered.

Status updates for messages that weren't logged, such as messages sent before the log was turned on, are ignored.
