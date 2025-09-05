# API Reference

This document provides complete documentation for all methods available in the Laravel WhatsApp package.

## WhatsappService Class

### Constructor

```php
public function __construct(array $config)
```

Creates a new WhatsappService instance with the provided configuration.

**Parameters:**
- `$config` (array): Configuration array containing API settings

### sendMessage

```php
public function sendMessage(string $to, array $message, ?string $tenantId = null): array
```

Sends a WhatsApp message using the raw message format.

**Parameters:**
- `$to` (string): Recipient phone number
- `$message` (array): Message payload
- `$tenantId` (string|null): Optional tenant ID for multi-tenant setups

**Returns:** array - API response

**Throws:** `WhatsappException` on failure

**Example:**
```php
$response = $service->sendMessage('1234567890', [
    'type' => 'text',
    'text' => ['body' => 'Hello World!']
]);
```

### sendTextMessage

```php
public function sendTextMessage(string $to, string $text, ?string $tenantId = null): array
```

Sends a text message.

**Parameters:**
- `$to` (string): Recipient phone number
- `$text` (string): Message text
- `$tenantId` (string|null): Optional tenant ID

**Returns:** array - API response

**Example:**
```php
$response = $service->sendTextMessage('1234567890', 'Hello World!');
```

### sendMediaMessage

```php
public function sendMediaMessage(string $to, string $mediaId, string $type, ?string $caption = null, ?string $tenantId = null): array
```

Sends a media message (image, video, document, audio).

**Parameters:**
- `$to` (string): Recipient phone number
- `$mediaId` (string): Media ID from WhatsApp
- `$type` (string): Media type ('image', 'video', 'document', 'audio')
- `$caption` (string|null): Optional caption
- `$tenantId` (string|null): Optional tenant ID

**Returns:** array - API response

**Example:**
```php
$response = $service->sendMediaMessage('1234567890', 'media123', 'image', 'Check this out!');
```

### sendTemplateMessage

```php
public function sendTemplateMessage(string $to, string $templateName, array $parameters = [], ?string $language = 'en_US', ?string $tenantId = null): array
```

Sends a template message.

**Parameters:**
- `$to` (string): Recipient phone number
- `$templateName` (string): Template name
- `$parameters` (array): Template parameters
- `$language` (string): Language code (default: 'en_US')
- `$tenantId` (string|null): Optional tenant ID

**Returns:** array - API response

**Example:**
```php
$response = $service->sendTemplateMessage('1234567890', 'hello_world', ['John', 'Doe']);
```

### sendInteractiveMessage

```php
public function sendInteractiveMessage(string $to, array $interactive, ?string $tenantId = null): array
```

Sends an interactive message (buttons, lists).

**Parameters:**
- `$to` (string): Recipient phone number
- `$interactive` (array): Interactive message payload
- `$tenantId` (string|null): Optional tenant ID

**Returns:** array - API response

**Example:**
```php
$response = $service->sendInteractiveMessage('1234567890', [
    'type' => 'button',
    'body' => ['text' => 'Choose an option'],
    'action' => [
        'buttons' => [
            ['type' => 'reply', 'reply' => ['id' => 'btn1', 'title' => 'Option 1']]
        ]
    ]
]);
```

### sendButtonMessage

```php
public function sendButtonMessage(string $to, string $bodyText, array $buttons, ?string $headerText = null, ?string $footerText = null, ?string $tenantId = null): array
```

Sends a button message.

**Parameters:**
- `$to` (string): Recipient phone number
- `$bodyText` (string): Main message text
- `$buttons` (array): Array of button configurations
- `$headerText` (string|null): Optional header text
- `$footerText` (string|null): Optional footer text
- `$tenantId` (string|null): Optional tenant ID

**Returns:** array - API response

**Button Format:**
```php
$buttons = [
    ['id' => 'btn1', 'title' => 'Option 1'],
    ['id' => 'btn2', 'title' => 'Option 2'],
    ['id' => 'btn3', 'title' => 'Option 3']
];
```

**Example:**
```php
$response = $service->sendButtonMessage('1234567890', 'Choose an option:', $buttons);
```

### sendListMessage

```php
public function sendListMessage(string $to, string $bodyText, string $buttonText, array $sections, ?string $headerText = null, ?string $footerText = null, ?string $tenantId = null): array
```

Sends a list message.

**Parameters:**
- `$to` (string): Recipient phone number
- `$bodyText` (string): Main message text
- `$buttonText` (string): Button text
- `$sections` (array): Array of sections with rows
- `$headerText` (string|null): Optional header text
- `$footerText` (string|null): Optional footer text
- `$tenantId` (string|null): Optional tenant ID

**Returns:** array - API response

**Sections Format:**
```php
$sections = [
    [
        'title' => 'Category 1',
        'rows' => [
            [
                'id' => 'row1',
                'title' => 'Option 1',
                'description' => 'Description for option 1'
            ]
        ]
    ]
];
```

**Example:**
```php
$response = $service->sendListMessage('1234567890', 'Choose from list:', 'View Options', $sections);
```

### uploadMedia

```php
public function uploadMedia(string $filePath, string $type, ?string $tenantId = null): array
```

Uploads a media file to WhatsApp.

**Parameters:**
- `$filePath` (string): Path to the file
- `$type` (string): Media type ('image', 'video', 'document', 'audio')
- `$tenantId` (string|null): Optional tenant ID

**Returns:** array - API response containing media ID

**Example:**
```php
$response = $service->uploadMedia('/path/to/image.jpg', 'image');
$mediaId = $response['id'];
```

### downloadMedia

```php
public function downloadMedia(string $mediaId, ?string $tenantId = null): string
```

Downloads a media file from WhatsApp.

**Parameters:**
- `$mediaId` (string): Media ID from WhatsApp
- `$tenantId` (string|null): Optional tenant ID

**Returns:** string - Local file path

**Example:**
```php
$filePath = $service->downloadMedia('media123');
// File saved to storage/app/whatsapp-media/media123.jpg
```

## WhatsappWebhookController Class

### verify

```php
public function verify(Request $request)
```

Handles webhook verification from WhatsApp.

**Parameters:**
- `$request` (Request): HTTP request

**Returns:** Response - Challenge string or 403 Forbidden

### handle

```php
public function handle(Request $request)
```

Handles incoming webhook events from WhatsApp.

**Parameters:**
- `$request` (Request): HTTP request

**Returns:** Response - JSON response

## Events

### MessageSent

Dispatched when a message is successfully sent.

**Properties:**
- `$messageId` (string|null): WhatsApp message ID
- `$recipient` (string): Recipient phone number
- `$response` (array): Full API response

### MessageFailed

Dispatched when a message fails to send.

**Properties:**
- `$recipient` (string): Recipient phone number
- `$error` (array): Error details

### MessageDelivered

Dispatched when a message is delivered.

**Properties:**
- `$messageId` (string|null): WhatsApp message ID
- `$recipient` (string): Recipient phone number

### MessageRead

Dispatched when a message is read.

**Properties:**
- `$messageId` (string|null): WhatsApp message ID
- `$recipient` (string): Recipient phone number
- `$timestamp` (Carbon): Read timestamp

### MessageReceived

Dispatched when a message is received.

**Properties:**
- `$messageId` (string): WhatsApp message ID
- `$from` (string): Sender phone number
- `$message` (array): Full message data
- `$timestamp` (Carbon): Message timestamp

## Exceptions

### WhatsappException

Base exception for all WhatsApp-related errors.

**Constructor:**
```php
public function __construct(string $message, int $code = 0, ?string $body = null)
```

**Properties:**
- `$message` (string): Error message
- `$code` (int): HTTP status code
- `$body` (string|null): Response body

## Facade

### Whatsapp Facade

The package provides a facade for easy access to the service:

```php
use Crenspire\Whatsapp\Facades\Whatsapp;

// All service methods are available through the facade
Whatsapp::sendTextMessage('1234567890', 'Hello!');
Whatsapp::sendMediaMessage('1234567890', 'media123', 'image');
```

## Rate Limiting

The package uses Laravel's rate limiter to respect WhatsApp API limits:

- Rate limit is applied per phone number ID
- Default limit: 30 messages per minute
- Configurable via `WHATSAPP_RATE_LIMIT` environment variable
- Throws `WhatsappException` when limit exceeded

## Validation

### Phone Number Validation

All phone numbers are validated before sending:

- Must be 10-15 digits
- Non-digit characters are stripped
- Throws `WhatsappException` for invalid formats

### Media Type Validation

Media types are validated against WhatsApp's supported types:

- `image`: JPEG, PNG, GIF, WebP
- `video`: MP4, 3GPP
- `audio`: AAC, M4A, MP3, AMR, OGG
- `document`: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT

## Error Handling

All methods throw `WhatsappException` for various error conditions:

- Invalid phone number format
- Rate limit exceeded
- API errors
- Network errors
- File not found (for media uploads)

## Logging

The package logs all API interactions when debug mode is enabled:

- Outgoing requests
- API responses
- Webhook events
- Error details

Enable debug logging:
```env
WHATSAPP_DEBUG=true
```
