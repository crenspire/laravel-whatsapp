# Laravel WhatsApp Business API Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/crenspire/laravel-whatsapp.svg?style=flat-square)](https://packagist.org/packages/crenspire/laravel-whatsapp)
[![Total Downloads](https://img.shields.io/packagist/dt/crenspire/laravel-whatsapp.svg?style=flat-square)](https://packagist.org/packages/crenspire/laravel-whatsapp)
[![Build Status](https://img.shields.io/github/actions/workflow/status/crenspire/laravel-whatsapp/tests.yml?branch=main&style=flat-square)](https://github.com/crenspire/laravel-whatsapp/actions)
[![Test Coverage](https://img.shields.io/codecov/c/github/crenspire/laravel-whatsapp?style=flat-square)](https://codecov.io/gh/crenspire/laravel-whatsapp)
[![PHP Version](https://img.shields.io/packagist/php-v/crenspire/laravel-whatsapp?style=flat-square)](https://packagist.org/packages/crenspire/laravel-whatsapp)
[![Laravel Version](https://img.shields.io/packagist/dependency-v/crenspire/laravel-whatsapp/illuminate/contracts?style=flat-square)](https://packagist.org/packages/crenspire/laravel-whatsapp)
[![License](https://img.shields.io/packagist/l/crenspire/laravel-whatsapp?style=flat-square)](https://packagist.org/packages/crenspire/laravel-whatsapp)
[![StyleCI](https://github.styleci.io/repos/123456789/shield?branch=main)](https://github.styleci.io/repos/123456789)

A comprehensive Laravel package for integrating with the WhatsApp Business Cloud API. This package provides a clean, easy-to-use interface for sending messages, handling webhooks, managing media, and supporting multi-tenant applications.

## Features

- ✅ **Complete Message Types**: Text, media, templates, interactive messages (buttons, lists), contacts, location, stickers, reactions, flows, and product messages
- ✅ **Fluent API**: Builder patterns for easy message construction with `MessageBuilder` and `TemplateBuilder`
- ✅ **Webhook Support**: Secure webhook handling with signature verification and comprehensive message type processing
- ✅ **Media Management**: Upload, download, info retrieval, and deletion with automatic type detection via `MediaManager`
- ✅ **Business Profile Management**: Get and update business profile information via `BusinessProfileManager`
- ✅ **Multi-tenant Support**: Per-tenant configuration for phone numbers and access tokens
- ✅ **Rate Limiting**: Built-in rate limiting to respect WhatsApp API limits
- ✅ **Event System**: Laravel events for message status updates and incoming messages
- ✅ **Comprehensive Testing**: Full test coverage with realistic scenarios
- ✅ **Security**: Webhook verification and signature validation
- ✅ **Logging**: Detailed logging for debugging and monitoring
- ✅ **Clean Architecture**: Separated concerns with dedicated managers and builders

## Installation

### 1. Install the Package

```bash
composer require crenspire/laravel-whatsapp
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=config --provider="Crenspire\\Whatsapp\\WhatsappServiceProvider"
```

### 3. Configure Environment Variables

Add these variables to your `.env` file:

```env
# WhatsApp Business API Configuration
WHATSAPP_BASE_URI=https://graph.facebook.com/v20.0
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
WHATSAPP_ACCESS_TOKEN=your_access_token

# Webhook Configuration
WHATSAPP_WEBHOOK_VERIFY_TOKEN=your_webhook_verify_token
WHATSAPP_WEBHOOK_SECRET=your_webhook_secret

# Optional Configuration
WHATSAPP_RATE_LIMIT=30
WHATSAPP_DEBUG=false
```

### 4. Set Up Webhook Routes

The package automatically registers webhook routes. Make sure your webhook URL is configured in your WhatsApp Business API settings:

- **Verification URL**: `https://yourdomain.com/whatsapp/webhook`
- **Webhook URL**: `https://yourdomain.com/whatsapp/webhook`

## Quick Start

### Basic Usage

```php
use Crenspire\Whatsapp\Facades\Whatsapp;

// Send a text message
$response = Whatsapp::sendTextMessage('1234567890', 'Hello from Laravel!');

// Send a media message
$response = Whatsapp::sendMediaMessage('1234567890', 'media_id_123', 'image', 'Check this out!');

// Send a template message
$response = Whatsapp::sendTemplateMessage('1234567890', 'hello_world', ['John', 'Doe']);
```

### Using the Service Directly

```php
use Crenspire\Whatsapp\WhatsappService;

$whatsapp = app(WhatsappService::class);

$response = $whatsapp->sendTextMessage('1234567890', 'Hello World!');
```

## Message Types

### Text Messages

```php
Whatsapp::sendTextMessage('1234567890', 'Hello World!');
```

### Media Messages

```php
// Send image with caption
Whatsapp::sendMediaMessage('1234567890', 'media_id_123', 'image', 'Check this out!');

// Send video
Whatsapp::sendMediaMessage('1234567890', 'media_id_456', 'video', 'Watch this video');

// Send document
Whatsapp::sendMediaMessage('1234567890', 'media_id_789', 'document', 'Important document');
```

### Template Messages

```php
// Send template with parameters
Whatsapp::sendTemplateMessage(
    '1234567890', 
    'hello_world', 
    ['John', 'Doe'], 
    'en_US'
);
```

### Interactive Messages

#### Button Messages

```php
$buttons = [
    ['id' => 'btn1', 'title' => 'Option 1'],
    ['id' => 'btn2', 'title' => 'Option 2'],
    ['id' => 'btn3', 'title' => 'Option 3']
];

Whatsapp::sendButtonMessage(
    '1234567890',
    'Choose an option:',
    $buttons,
    'Header Text',
    'Footer Text'
);
```

#### List Messages

```php
$sections = [
    [
        'title' => 'Category 1',
        'rows' => [
            [
                'id' => 'row1',
                'title' => 'Option 1',
                'description' => 'Description for option 1'
            ],
            [
                'id' => 'row2',
                'title' => 'Option 2',
                'description' => 'Description for option 2'
            ]
        ]
    ]
];

Whatsapp::sendListMessage(
    '1234567890',
    'Choose from the list:',
    'View Options',
    $sections,
    'Header Text',
    'Footer Text'
);
```

## Media Management

### Upload Media

```php
$response = Whatsapp::uploadMedia('/path/to/image.jpg', 'image');
$mediaId = $response['id'];

// Use the media ID to send the image
Whatsapp::sendMediaMessage('1234567890', $mediaId, 'image', 'Uploaded image');
```

### Download Media

```php
$filePath = Whatsapp::downloadMedia('media_id_123');
// File is saved to storage/app/whatsapp-media/media_id_123.jpg
```

## Multi-tenant Support

Configure multiple WhatsApp Business accounts:

```php
// In config/whatsapp.php
'tenants' => [
    'company1' => [
        'phone_number_id' => 'phone_number_1',
        'access_token' => 'access_token_1'
    ],
    'company2' => [
        'phone_number_id' => 'phone_number_2',
        'access_token' => 'access_token_2'
    ]
]
```

Use tenant-specific configuration:

```php
// Send message using specific tenant
Whatsapp::sendTextMessage('1234567890', 'Hello!', 'company1');
```

## Webhook Handling

### Event Listeners

The package dispatches several events that you can listen to:

```php
// In your EventServiceProvider
protected $listen = [
    \Crenspire\Whatsapp\Events\MessageSent::class => [
        // Your listener
    ],
    \Crenspire\Whatsapp\Events\MessageDelivered::class => [
        // Your listener
    ],
    \Crenspire\Whatsapp\Events\MessageRead::class => [
        // Your listener
    ],
    \Crenspire\Whatsapp\Events\MessageReceived::class => [
        // Your listener
    ],
    \Crenspire\Whatsapp\Events\MessageFailed::class => [
        // Your listener
    ],
];
```

### Example Event Listener

```php
<?php

namespace App\Listeners;

use Crenspire\Whatsapp\Events\MessageReceived;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleIncomingMessage implements ShouldQueue
{
    public function handle(MessageReceived $event)
    {
        // Handle incoming message
        $messageId = $event->messageId;
        $from = $event->from;
        $message = $event->message;
        $timestamp = $event->timestamp;
        
        // Your logic here
    }
}
```

## Error Handling

The package throws `WhatsappException` for various error conditions:

```php
use Crenspire\Whatsapp\Exceptions\WhatsappException;

try {
    Whatsapp::sendTextMessage('1234567890', 'Hello!');
} catch (WhatsappException $e) {
    // Handle WhatsApp API errors
    logger('WhatsApp error: ' . $e->getMessage());
}
```

## Rate Limiting

The package includes built-in rate limiting to respect WhatsApp API limits:

```php
// Configure rate limit in config/whatsapp.php
'rate_limit' => 30, // messages per minute
```

## Logging

Enable debug logging:

```php
// In config/whatsapp.php
'debug' => true,
```

Or set in your `.env`:

```env
WHATSAPP_DEBUG=true
```

## Testing

Run the test suite:

```bash
composer test
```

The package includes comprehensive tests covering all functionality.

## Security

### Webhook Verification

The package automatically verifies webhook signatures when `WHATSAPP_WEBHOOK_SECRET` is configured:

```env
WHATSAPP_WEBHOOK_SECRET=your_webhook_secret
```

### Phone Number Validation

All phone numbers are validated before sending messages to ensure they meet WhatsApp's requirements.

## 🔧 Custom Headers Support

The package now supports custom headers for all API requests, allowing you to add authentication, tracking, or other custom headers:

### Basic Usage

```php
// Using custom headers with individual requests
$customHeaders = [
    'X-Request-ID' => 'req_12345',
    'X-Client-Version' => '2.0.0',
    'X-Custom-Auth' => 'custom_token'
];

Whatsapp::sendTextMessage('+1234567890', 'Hello!', null, $customHeaders);

// Using custom headers with media upload
Whatsapp::uploadMedia('/path/to/file.jpg', 'image', null, $customHeaders);

// Using custom headers with media download
$filePath = Whatsapp::downloadMedia('media_id_123', null, $customHeaders);
```

### Multi-tenant Headers

Configure per-tenant headers in your configuration:

```php
// config/whatsapp.php
'tenants' => [
    'tenant1' => [
        'phone_number_id' => '123456789',
        'access_token' => 'token1',
        'headers' => [
            'X-Tenant-ID' => 'tenant1',
            'X-API-Version' => 'v2.0',
            'X-Custom-Header' => 'tenant1-value'
        ]
    ],
    'tenant2' => [
        'phone_number_id' => '987654321',
        'access_token' => 'token2',
        'headers' => [
            'X-Tenant-ID' => 'tenant2',
            'X-API-Version' => 'v1.5'
        ]
    ]
]
```

### Default Headers

Set default headers for all requests:

```php
// config/whatsapp.php
'default_headers' => [
    'Content-Type' => 'application/json',
    'Accept' => 'application/json',
    'User-Agent' => 'Laravel-WhatsApp-Package/1.0.0',
    'X-Client-Name' => 'MyApp',
    'X-Client-Version' => '1.0.0'
],
```

### Header Priority

Headers are merged in the following order (later overrides earlier):
1. Default headers from configuration
2. Tenant-specific headers
3. Custom headers passed to individual methods

## Configuration Reference

See [Configuration](docs/configuration.md) for detailed configuration options.

## API Reference

See [API Reference](docs/api-reference.md) for complete method documentation.

## Enhanced Features

### New Message Types

The package now supports all WhatsApp Cloud API message types:

- **Contact Messages**: Send contact cards
- **Location Messages**: Share location with optional name and address
- **Sticker Messages**: Send stickers
- **Reaction Messages**: React to existing messages
- **Flow Messages**: Send interactive flows
- **Product Messages**: Single and multi-product catalogs

### Fluent API

Use the new builder patterns for cleaner code:

```php
// Template builder
$template = Whatsapp::template('welcome_template', 'en_US')
    ->header([['type' => 'text', 'text' => 'Welcome!']])
    ->body([['type' => 'text', 'text' => 'Hello {{1}}!']])
    ->footer([['type' => 'text', 'text' => 'Thank you']])
    ->build();

// Message builder
$message = Whatsapp::message()::text('Hello with preview!', true);
```

### Business Profile Management

```php
// Get business profile
$profile = Whatsapp::getBusinessProfile();

// Update business profile
Whatsapp::updateBusinessProfile([
    'messaging_product' => 'whatsapp',
    'about' => 'Updated business description'
]);
```

### Enhanced Media Management

```php
// Get media information
$info = Whatsapp::getMediaInfo('media_id_123');

// Delete media
Whatsapp::deleteMedia('media_id_123');
```

## Examples

See [Examples](docs/examples.md) for real-world usage examples.

## Troubleshooting

See [Troubleshooting](docs/troubleshooting.md) for common issues and solutions.

## Contributing

Contributions are welcome! Please see [Contributing](docs/contributing.md) for guidelines.

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
