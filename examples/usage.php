<?php

/**
 * WhatsApp Service Usage Examples
 *
 * This file demonstrates how to use the refactored WhatsApp service
 * with the new fluent API and builder patterns.
 */

use Crenspire\Whatsapp\Facades\Whatsapp;

// Example 1: Basic text message with URL preview
Whatsapp::sendTextMessage('+1234567890', 'Hello World!', true);

// Example 2: Using the message builder for more control
$message = Whatsapp::message()::text('Hello with preview!', true);
// Note: You would still need to send this through the main service

// Example 3: Template message with components using the template builder
$template = Whatsapp::template('welcome_template', 'en_US')
    ->header([['type' => 'text', 'text' => 'Welcome!']])
    ->body([['type' => 'text', 'text' => 'Hello {{1}}!']])
    ->footer([['type' => 'text', 'text' => 'Thank you']])
    ->build();

// Example 4: Media message
Whatsapp::sendMediaMessage('+1234567890', 'media_id_123', 'image', 'Check this out!');

// Example 5: Interactive button message
$buttons = [
    ['id' => 'btn1', 'title' => 'Option 1'],
    ['id' => 'btn2', 'title' => 'Option 2']
];
Whatsapp::sendButtonMessage('+1234567890', 'Choose an option:', $buttons, 'Header', 'Footer');

// Example 6: List message
$sections = [
    [
        'title' => 'Section 1',
        'rows' => [
            ['id' => 'row1', 'title' => 'Row 1', 'description' => 'Description 1']
        ]
    ]
];
Whatsapp::sendListMessage('+1234567890', 'Choose from the list:', 'View Options', $sections);

// Example 7: Contact message
$contacts = [
    [
        'name' => [
            'formatted_name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ],
        'phones' => [
            [
                'phone' => '+1234567890',
                'type' => 'WORK'
            ]
        ]
    ]
];
Whatsapp::sendContactMessage('+1234567890', $contacts);

// Example 8: Location message
Whatsapp::sendLocationMessage('+1234567890', 40.7128, -74.0060, 'New York', 'New York, NY');

// Example 9: Sticker message
Whatsapp::sendStickerMessage('+1234567890', 'sticker_id_123');

// Example 10: Reaction message
Whatsapp::sendReactionMessage('+1234567890', 'message_id_123', '👍');

// Example 11: Flow message
$flowActionPayload = ['screen' => 'SCREEN_NAME'];
Whatsapp::sendFlowMessage('+1234567890', 'flow_token', 'flow_id', 'Click here', 'navigate', $flowActionPayload);

// Example 12: Single product message
Whatsapp::sendSingleProductMessage('+1234567890', 'catalog_id', 'product_retailer_id', 'Check this out!');

// Example 13: Multi-product message
$productSections = [
    [
        'title' => 'Products',
        'product_items' => [
            ['product_retailer_id' => 'product_1']
        ]
    ]
];
Whatsapp::sendMultiProductMessage('+1234567890', 'catalog_id', 'Browse Products', $productSections);

// Example 14: Media management
$uploadResult = Whatsapp::uploadMedia('/path/to/image.jpg', 'image');
$mediaId = $uploadResult['id'];

$mediaInfo = Whatsapp::getMediaInfo($mediaId);
$downloadedPath = Whatsapp::downloadMedia($mediaId);
Whatsapp::deleteMedia($mediaId);

// Example 15: Business profile management
$profile = Whatsapp::getBusinessProfile();
Whatsapp::updateBusinessProfile([
    'messaging_product' => 'whatsapp',
    'about' => 'Updated business description'
]);

// Example 16: Mark message as read
Whatsapp::markMessageAsRead('message_id_123');

// Example 17: Multi-tenant usage
Whatsapp::sendTextMessage('+1234567890', 'Hello from tenant!', false, 'tenant1');

// Example 18: Custom headers
Whatsapp::sendTextMessage('+1234567890', 'Hello!', false, null, ['X-Custom-Header' => 'value']);

// Example 19: Language override
Whatsapp::sendTextMessage('+1234567890', 'Hola!', false, null, [], 'es-ES');
