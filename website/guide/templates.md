# Templates

WhatsApp only lets you start a conversation, or message a customer more than 24 hours after they last wrote to you, with a template Meta has approved.

## Sending templates

```php
// Fill the body placeholders {{1}} and {{2}}
Whatsapp::sendTemplateMessage('15551234567', 'order_shipped', ['Sarah', '#1042']);

// Use a different language version
Whatsapp::sendTemplateMessage('15551234567', 'order_shipped', ['Sara', '#1042'], language: 'es_ES');
```

The language defaults to the tenant's `language`, then `WHATSAPP_DEFAULT_LANGUAGE` (`en_US`). Language codes use an underscore, like `en_US` or `pt_BR`.

### Media headers

Pass header parameters in the shape the API expects:

```php
Whatsapp::sendTemplateMessageWithComponents(
    to: '15551234567',
    templateName: 'order_shipped_with_photo',
    bodyParameters: ['Sarah', '#1042'],
    headerParameters: [
        ['type' => 'image', 'image' => ['link' => 'https://example.com/parcel.jpg']],
    ],
);
```

Use `['type' => 'image', 'image' => ['id' => $mediaId]]` for uploaded media, and `video` or `document` the same way. Footers are fixed text and take no parameters.

### Buttons

Templates with dynamic URL buttons or quick replies are easier to build with the template builder:

```php
$template = Whatsapp::template('delivery_update')
    ->body(['Sarah', 'Thursday'])
    ->button('url', ['tracking/1042'], index: 0)
    ->build();

Whatsapp::sendMessage('15551234567', $template);
```

`index` is the button's position in the template, starting at 0. When a customer taps a quick-reply button, `$event->buttonPayload()` on `MessageReceived` returns its payload.

## Creating templates

Managing templates needs `WHATSAPP_BUSINESS_ACCOUNT_ID`.

```php
$template = Whatsapp::createTemplate('order_shipped', 'en_US', 'UTILITY', [
    ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Your order is on its way'],
    ['type' => 'BODY', 'text' => 'Hi {{1}}, order {{2}} has shipped.', 'example' => ['body_text' => [['Sarah', '#1042']]]],
    ['type' => 'FOOTER', 'text' => 'Reply STOP to opt out'],
]);

$template['status']; // PENDING
```

The category must be `MARKETING`, `UTILITY` or `AUTHENTICATION`. Every template needs a `BODY` component. The package checks these before calling the API.

New and edited templates go to Meta for review automatically; there's no separate publish step.

### Image, video and document headers

Templates with a media header need a sample file when you create them. Upload it first, which needs `WHATSAPP_APP_ID`:

```php
$handle = Whatsapp::uploadTemplateMedia(storage_path('app/samples/parcel.jpg'));

Whatsapp::createTemplate('order_shipped_with_photo', 'en_US', 'UTILITY', [
    ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => ['header_handle' => [$handle]]],
    ['type' => 'BODY', 'text' => 'Hi {{1}}, order {{2}} has shipped.', 'example' => ['body_text' => [['Sarah', '#1042']]]],
]);
```

The sample can be a JPEG, PNG, MP4 or PDF file.

## Checking review status

```php
Whatsapp::getTemplateStatus('order_shipped');   // APPROVED, PENDING, REJECTED, PAUSED, ...
Whatsapp::isTemplateApproved('order_shipped');
Whatsapp::isTemplatePending('order_shipped');
```

Rather than polling, listen for the [`TemplateStatusUpdated` event](./webhooks#template-reviews), which Meta sends as soon as a review finishes. From the terminal, `php artisan whatsapp:templates --status=rejected` lists rejected templates.

## Listing templates

```php
Whatsapp::getTemplates();                                   // one page, with paging cursors
Whatsapp::getTemplates(['limit' => 100, 'after' => $cursor]);
Whatsapp::getTemplatesByStatus('REJECTED');
Whatsapp::getTemplatesByCategory('MARKETING');
Whatsapp::getTemplatesByLanguage('es_ES');
Whatsapp::getTemplate('order_shipped');                     // searches every page
```

## Editing and deleting

```php
// Replaces all components of the en_US version and sends it for review again
Whatsapp::updateTemplate('order_shipped', 'en_US', 'UTILITY', $components);

// Deletes every language version
Whatsapp::deleteTemplate('order_shipped');
```

Meta limits how often an approved template can be edited, so it's worth getting templates right before they're approved.
