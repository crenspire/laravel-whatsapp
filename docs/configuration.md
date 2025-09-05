# Configuration

This document describes all configuration options available for the Laravel WhatsApp package.

## Environment Variables

### Required Variables

```env
# WhatsApp Business API Base URL
WHATSAPP_BASE_URI=https://graph.facebook.com/v20.0

# Your WhatsApp Business Phone Number ID
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id

# Your WhatsApp Business Access Token
WHATSAPP_ACCESS_TOKEN=your_access_token

# Webhook verification token (for webhook setup)
WHATSAPP_WEBHOOK_VERIFY_TOKEN=your_webhook_verify_token
```

### Optional Variables

```env
# Webhook secret for signature verification (recommended for production)
WHATSAPP_WEBHOOK_SECRET=your_webhook_secret

# Rate limit per minute (default: 30)
WHATSAPP_RATE_LIMIT=30

# Enable debug logging (default: false)
WHATSAPP_DEBUG=false
```

## Configuration File

The configuration file is located at `config/whatsapp.php`:

```php
<?php

return [
    // WhatsApp Business API Base URI
    'base_uri' => env('WHATSAPP_BASE_URI', 'https://graph.facebook.com/v20.0'),

    // Default phone number ID
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    // Default access token
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),

    // Webhook verification token
    'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),

    // Webhook secret for signature verification
    'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),

    // Multi-tenant configuration
    'tenants' => [
        // 'tenant_id' => [
        //     'phone_number_id' => 'phone_number_id',
        //     'access_token' => 'access_token'
        // ],
    ],

    // Rate limit per minute
    'rate_limit' => env('WHATSAPP_RATE_LIMIT', 30),

    // Storage path for media downloads
    'media_storage' => storage_path('app/whatsapp-media'),

    // Debug mode
    'debug' => env('WHATSAPP_DEBUG', false),
];
```

## Multi-tenant Configuration

For applications serving multiple clients, you can configure different WhatsApp Business accounts:

```php
'tenants' => [
    'client1' => [
        'phone_number_id' => '123456789012345',
        'access_token' => 'EAABwzLixnjYBO...'
    ],
    'client2' => [
        'phone_number_id' => '987654321098765',
        'access_token' => 'EAABwzLixnjYBO...'
    ],
    'client3' => [
        'phone_number_id' => '555666777888999',
        'access_token' => 'EAABwzLixnjYBO...'
    ]
]
```

Then use tenant-specific configuration:

```php
// Use default configuration
Whatsapp::sendTextMessage('1234567890', 'Hello!');

// Use specific tenant
Whatsapp::sendTextMessage('1234567890', 'Hello!', 'client1');
```

## Rate Limiting

WhatsApp Business API has rate limits that vary by phone number. The package includes built-in rate limiting:

```php
// Set rate limit per minute
'rate_limit' => 30, // 30 messages per minute
```

Rate limiting is applied per phone number ID, so multi-tenant applications can have different limits for different tenants.

## Media Storage

Configure where media files are stored when downloaded:

```php
'media_storage' => storage_path('app/whatsapp-media'),
```

The package will automatically create this directory if it doesn't exist.

## Debug Mode

Enable debug logging to see detailed information about API calls:

```php
'debug' => true,
```

When enabled, the package will log:
- All outgoing API requests
- API responses
- Webhook events
- Error details

## Webhook Configuration

### Webhook Verification

WhatsApp requires webhook verification during setup. Configure the verification token:

```env
WHATSAPP_WEBHOOK_VERIFY_TOKEN=your_random_string
```

### Webhook Security

For production environments, enable webhook signature verification:

```env
WHATSAPP_WEBHOOK_SECRET=your_webhook_secret
```

This ensures that webhook requests are actually from WhatsApp.

### Webhook URLs

Configure these URLs in your WhatsApp Business API settings:

- **Verification URL**: `https://yourdomain.com/whatsapp/webhook`
- **Webhook URL**: `https://yourdomain.com/whatsapp/webhook`

## Environment-specific Configuration

### Development

```env
WHATSAPP_DEBUG=true
WHATSAPP_RATE_LIMIT=10
```

### Staging

```env
WHATSAPP_DEBUG=true
WHATSAPP_RATE_LIMIT=20
```

### Production

```env
WHATSAPP_DEBUG=false
WHATSAPP_RATE_LIMIT=30
WHATSAPP_WEBHOOK_SECRET=your_secure_secret
```

## Validation

The package validates configuration on startup:

- Phone number ID format
- Access token format
- Base URI format
- Rate limit range (1-1000)
- Media storage path accessibility

## Caching

Configuration values are cached for performance. Clear the cache after changing configuration:

```bash
php artisan config:clear
```

## Security Best Practices

1. **Never commit access tokens** to version control
2. **Use environment variables** for all sensitive data
3. **Enable webhook signature verification** in production
4. **Use different tokens** for different environments
5. **Rotate access tokens** regularly
6. **Monitor API usage** and rate limits
7. **Use HTTPS** for webhook URLs
8. **Validate webhook signatures** in production
