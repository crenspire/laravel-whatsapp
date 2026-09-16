# Sending messages

All sending goes through the `Whatsapp` facade, or the `Crenspire\Whatsapp\WhatsappService` class if you prefer dependency injection.

Pass phone numbers with the country code, like `15551234567`. A leading `+` and spaces or dashes are accepted too.

Every send method returns the decoded API response. The message ID is in `$response['messages'][0]['id']`, and it's the same ID you'll see in status webhooks.

## Text

```php
use Crenspire\Whatsapp\Facades\Whatsapp;

Whatsapp::sendTextMessage('15551234567', 'Hello!');

// Show a preview for the first link in the message
Whatsapp::sendTextMessage('15551234567', 'Track it here: https://example.com/t/123', previewUrl: true);
```

## Media

Media is sent by ID, so upload the file first. The ID can be reused for 30 days.

```php
$media = Whatsapp::uploadMedia(storage_path('app/invoices/1042.pdf'), 'application/pdf');

Whatsapp::sendMediaMessage('15551234567', $media['id'], 'document', caption: 'Invoice #1042');
```

The type is `image`, `video`, `audio`, `document` or `sticker`. Captions work on images, videos and documents. See [Media](./media) for downloading and deleting.

## Templates

Outside the 24-hour window after a customer last messaged you, WhatsApp only lets you send approved templates.

```php
// Fill the body placeholders {{1}} and {{2}}
Whatsapp::sendTemplateMessage('15551234567', 'order_shipped', ['Sarah', '#1042']);

// Use a different language version of the template
Whatsapp::sendTemplateMessage('15551234567', 'order_shipped', ['Sara', '#1042'], language: 'es_ES');
```

For media headers, buttons and managing templates, see [Templates](./templates).

## Buttons and lists

```php
Whatsapp::sendButtonMessage('15551234567', 'Can we deliver tomorrow?', [
    ['id' => 'delivery_yes', 'title' => 'Yes'],
    ['id' => 'delivery_no', 'title' => 'Pick another day'],
]);

Whatsapp::sendListMessage(
    to: '15551234567',
    bodyText: 'Choose a delivery slot',
    buttonText: 'View slots',
    sections: [
        [
            'title' => 'Thursday',
            'rows' => [
                ['id' => 'thu_am', 'title' => 'Morning', 'description' => '8am to 12pm'],
                ['id' => 'thu_pm', 'title' => 'Afternoon', 'description' => '12pm to 5pm'],
            ],
        ],
    ],
);
```

WhatsApp allows up to three reply buttons. When the customer taps one, you get a `MessageReceived` event, and `$event->buttonReplyId()` returns the button's `id`. See [Webhooks and events](./webhooks).

## Location, contacts, stickers and reactions

```php
Whatsapp::sendLocationMessage('15551234567', 51.5072, -0.1276, name: 'Our shop', address: '1 High Street, London');

Whatsapp::sendContactMessage('15551234567', [
    [
        'name' => ['formatted_name' => 'Support Team', 'first_name' => 'Support'],
        'phones' => [['phone' => '+15550001111', 'type' => 'WORK']],
    ],
]);

Whatsapp::sendStickerMessage('15551234567', $stickerMediaId);

Whatsapp::sendReactionMessage('15551234567', $messageId, '👍');
```

## Flows and product messages

```php
Whatsapp::sendFlowMessage(
    to: '15551234567',
    flowToken: 'booking-1042',
    flowId: '1234567890',
    flowCta: 'Book a table',
    flowAction: 'navigate',
    flowActionPayload: ['screen' => 'BOOKING'],
);

Whatsapp::sendSingleProductMessage('15551234567', $catalogId, 'SKU-123', bodyText: 'Back in stock');

Whatsapp::sendMultiProductMessage(
    to: '15551234567',
    catalogId: $catalogId,
    buttonText: 'Picked for you this week',
    sections: [
        ['title' => 'New in', 'product_items' => [['product_retailer_id' => 'SKU-123']]],
    ],
    headerText: 'Weekly picks',
);
```

For multi-product messages, `buttonText` is the body text and `headerText` is required.

## Replies, read receipts and typing indicators

```php
// Quote the customer's message in your reply
Whatsapp::sendTextMessage($event->from, 'Yes, it ships today.', replyTo: $event->messageId);

// Mark a message as read (the blue ticks)
Whatsapp::markMessageAsRead($event->messageId);

// Mark it as read and show "typing…" while you prepare a reply
Whatsapp::showTypingIndicator($event->messageId);
```

Every send method accepts `replyTo`, except reactions. The typing indicator disappears when you send a message, or after 25 seconds, so only show it when you're about to reply.

## Anything else

If you need a message type or option the helpers don't cover, send the raw payload. `messaging_product` and `to` are added for you:

```php
Whatsapp::sendMessage('15551234567', [
    'type' => 'text',
    'text' => ['body' => 'Hello', 'preview_url' => false],
]);
```

The payload format is described in Meta's [messages reference](https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages).

## Business profile

Read and update the profile customers see when they open your business in WhatsApp:

```php
$profile = Whatsapp::getBusinessProfile()['data'][0];

Whatsapp::updateBusinessProfile([
    'about' => 'Fresh flowers, delivered daily',
    'email' => 'hello@example.com',
    'websites' => ['https://example.com'],
]);
```
