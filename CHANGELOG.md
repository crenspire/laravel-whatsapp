# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2026-09-16

This release contains breaking changes. See [UPGRADE.md](UPGRADE.md) for how to upgrade from 2.x, and [RELEASE_NOTES_v3.0.0.md](RELEASE_NOTES_v3.0.0.md) for an overview.

### Added
- Notification channel: return a `WhatsappMessage` from `toWhatsapp()` and send with `$notifiable->notify()`
- `Whatsapp::fake()` with assertions such as `assertSentText()`, `assertSentTemplate()` and `assertSentTo()`
- An exception class for each kind of API failure (`CustomerServiceWindowException`, `RateLimitException`, `TemplateException` and more), with Meta's error code, details and trace ID
- Automatic retries with exponential backoff for rate limits and temporary errors, configured with `retry.times` and `retry.sleep`
- Webhook duplicate filtering using the cache, configured under `webhook`
- `MessageStatusUpdated`, `TemplateStatusUpdated` and `TemplateQualityUpdated` events
- Helpers on `MessageReceived`: `text()`, `buttonReplyId()`, `listReplyId()`, `buttonPayload()`, `flowResponse()`, `mediaId()`, `replyToMessageId()`, `senderName()` and more
- Replies that quote a message, with the `replyTo` argument on every send method except reactions
- `showTypingIndicator()`
- `uploadTemplateMedia()` to upload sample media for template headers, using the new `app_id` config option
- Optional message log: a publishable migration, the `Message` model, and status tracking from webhooks
- `whatsapp:check`, `whatsapp:test` and `whatsapp:templates` Artisan commands
- `getPhoneNumber()` and `getWebhookSubscriptions()`
- Events carry the phone number ID, and `MessageSent`/`MessageFailed` carry the payload and tenant
- Messages from users who hide their phone number are handled using their business-scoped user ID
- Template management: create, edit, delete, list, and status checks
- `MessageFailed` is dispatched for `failed` webhook statuses, with the message ID
- `TemplateBuilder::button()` accepts the button index
- Laravel 13 is tested in CI
- A documentation website at https://crenspire.github.io/laravel-whatsapp/

### Removed
- Support for Laravel 10 and 11, which no longer receive security fixes

### Changed
- Requires Laravel 12 or 13, and Carbon 3
- API failures throw subclasses of `WhatsappException`, and messages include Meta's error details
- Temporary failures are retried by default (3 attempts). Message sends are only retried when Meta confirms they failed
- Webhook events delivered more than once are dispatched once
- Media, business profile and template managers take a `GraphClient` instead of a headers array
- Unknown tenant IDs throw `WhatsappException` instead of silently using the default account
- `default_language` defaults to `en_US` (Meta language codes use underscores)
- `sendMultiProductMessage()` requires header text, as the API does
- Template footer parameters throw `WhatsappException`; footers take no parameters
- `uploadMedia()` detects the MIME type when given a category such as `image`
- Webhooks received without a configured `webhook_secret` log a warning

### Fixed
- Media and business profile calls reused the credentials of the first tenant that made a call, for every tenant
- Webhook verification logged the expected verify token, and full payloads were logged outside debug mode
- Webhook verification passed when no verify token was configured
- The package couldn't be installed in new Laravel 13 apps, which use Guzzle 8. The package no longer requires Guzzle directly; it uses the version Laravel's HTTP client requires
- Media uploads were sent with a JSON Content-Type and rejected
- Webhooks only processed the first entry and change of a batched payload
- Messages without a `from` phone number were dropped
- Media header parameters in template messages had the wrong shape
- Custom headers were ignored for media and business profile calls
- `updateWebsite()` sent `website` instead of `websites`
- The test suite never booted Laravel, so most tests passed regardless of behavior

## [2.0.0] - 2026-07-25

### Added
- Allow Laravel 13 (`illuminate/contracts` ^13.0)

## [1.0.0] - 2025-09-08

### Added
- Semantic versioning support
- Version management scripts
- Initial release of Laravel WhatsApp Business API package
- WhatsApp service with comprehensive message sending capabilities
- Support for text, media, template, and interactive messages
- Webhook handling for incoming messages and status updates
- Multi-tenant support
- Rate limiting
- Event system for message lifecycle tracking
- Comprehensive test suite
- Full documentation

### Features
- **Message Types**: Text, media (image/video/document/audio), template, interactive (buttons/lists)
- **Webhook Support**: Verification, incoming messages, status updates
- **Multi-tenant**: Support for multiple WhatsApp Business accounts
- **Rate Limiting**: Built-in rate limiting to respect API limits
- **Events**: MessageSent, MessageFailed, MessageDelivered, MessageRead, MessageReceived
- **Media Management**: Upload and download media files
- **Laravel Integration**: Service provider, facade, configuration publishing
- **Testing**: Comprehensive test suite with Pest

### Technical Details
- PHP 8.2+ support
- Laravel 10, 11, 12 compatibility
- PSR-4 autoloading
- Comprehensive PHPDoc documentation
- MIT License

[3.0.0]: https://github.com/crenspire/laravel-whatsapp/compare/v2.0.0...v3.0.0
[2.0.0]: https://github.com/crenspire/laravel-whatsapp/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/crenspire/laravel-whatsapp/releases/tag/v1.0.0
