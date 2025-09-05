# Troubleshooting

This document helps you resolve common issues when using the Laravel WhatsApp package.

## Common Issues

### 1. "Rate limit exceeded" Error

**Problem:** You're getting rate limit exceeded errors when sending messages.

**Solutions:**
- Check your current rate limit configuration: `WHATSAPP_RATE_LIMIT=30`
- Reduce the rate limit if you're hitting the limit frequently
- Implement exponential backoff in your application
- Consider using multiple phone numbers for high-volume messaging

```php
// Example: Implement retry with backoff
try {
    Whatsapp::sendTextMessage('1234567890', 'Hello!');
} catch (WhatsappException $e) {
    if ($e->getCode() === 429) {
        // Wait and retry
        sleep(60);
        Whatsapp::sendTextMessage('1234567890', 'Hello!');
    }
}
```

### 2. "Invalid phone number format" Error

**Problem:** Phone number validation is failing.

**Solutions:**
- Ensure phone numbers are in international format (e.g., 1234567890)
- Remove any formatting characters (spaces, dashes, parentheses)
- Check that the number is 10-15 digits long

```php
// Good: 1234567890
// Bad: +1 (234) 567-8900
// Bad: 123-456-7890
```

### 3. Webhook Verification Failed

**Problem:** WhatsApp webhook verification is failing.

**Solutions:**
- Check that `WHATSAPP_WEBHOOK_VERIFY_TOKEN` is set correctly
- Ensure the webhook URL is accessible from the internet
- Verify the webhook URL in your WhatsApp Business API settings
- Check that the verification endpoint returns the challenge string

```env
WHATSAPP_WEBHOOK_VERIFY_TOKEN=your_secure_random_string
```

### 4. "Failed to send WhatsApp message" Error

**Problem:** Generic API errors when sending messages.

**Solutions:**
- Check your access token is valid and not expired
- Verify the phone number ID is correct
- Ensure the message format is valid
- Check WhatsApp Business API status

```php
// Enable debug logging to see detailed error information
WHATSAPP_DEBUG=true
```

### 5. Media Upload/Download Issues

**Problem:** Media files are not uploading or downloading correctly.

**Solutions:**
- Check file permissions on the media storage directory
- Ensure the file exists and is readable
- Verify the media type is supported by WhatsApp
- Check file size limits (images: 5MB, videos: 16MB, documents: 100MB)

```php
// Check if file exists and is readable
if (!file_exists($filePath) || !is_readable($filePath)) {
    throw new Exception('File not found or not readable');
}
```

### 6. Multi-tenant Configuration Issues

**Problem:** Tenant-specific configuration is not working.

**Solutions:**
- Verify tenant configuration in `config/whatsapp.php`
- Check that tenant ID is passed correctly
- Ensure tenant has valid phone number ID and access token

```php
// Check tenant configuration
$config = config('whatsapp.tenants.tenant_id');
if (!$config) {
    throw new Exception('Tenant configuration not found');
}
```

## Debug Mode

Enable debug mode to get detailed logging:

```env
WHATSAPP_DEBUG=true
```

This will log:
- All API requests and responses
- Webhook events
- Error details
- Rate limiting information

## Logging

Check your Laravel logs for detailed error information:

```bash
tail -f storage/logs/laravel.log
```

Look for entries with:
- `Sending WhatsApp message`
- `WhatsApp message failed`
- `WhatsApp webhook received`

## API Status

Check WhatsApp Business API status:
- [Facebook Developer Status](https://developers.facebook.com/status/)
- [WhatsApp Business API Status](https://status.whatsapp.com/)

## Common Error Codes

| Code | Error | Solution |
|------|-------|----------|
| 400 | Bad Request | Check message format and phone number |
| 401 | Unauthorized | Verify access token |
| 403 | Forbidden | Check permissions and rate limits |
| 404 | Not Found | Verify phone number ID |
| 429 | Too Many Requests | Reduce sending rate |
| 500 | Internal Server Error | Check WhatsApp API status |

## Performance Issues

### Slow Message Sending

**Solutions:**
- Implement queuing for bulk messages
- Use multiple phone numbers
- Optimize message content
- Check network connectivity

```php
// Queue messages for better performance
dispatch(new SendWhatsappMessage($phoneNumber, $message));
```

### Memory Issues

**Solutions:**
- Process messages in batches
- Clear media files after processing
- Use streaming for large files
- Monitor memory usage

## Security Issues

### Webhook Security

**Problem:** Unauthorized webhook requests.

**Solutions:**
- Enable webhook signature verification
- Use HTTPS for webhook URLs
- Implement IP whitelisting if possible
- Monitor webhook logs

```env
WHATSAPP_WEBHOOK_SECRET=your_secure_secret
```

### Access Token Security

**Solutions:**
- Rotate access tokens regularly
- Use environment variables for tokens
- Never commit tokens to version control
- Use different tokens for different environments

## Testing Issues

### Tests Failing

**Solutions:**
- Mock HTTP requests properly
- Use fake events for testing
- Check test configuration
- Verify test data format

```php
// Example test setup
Http::fake([
    'graph.facebook.com/v20.0/*' => Http::response([
        'messaging_product' => 'whatsapp',
        'messages' => [['id' => 'test123']]
    ], 200)
]);

Event::fake();
```

## Getting Help

If you're still experiencing issues:

1. Check the [GitHub Issues](https://github.com/your-repo/laravel-whatsapp/issues)
2. Review the [API Reference](api-reference.md)
3. Check the [Examples](examples.md)
4. Enable debug mode and check logs
5. Verify your configuration

## Support

For additional support:
- Create an issue on GitHub
- Check the documentation
- Review the examples
- Test with debug mode enabled
