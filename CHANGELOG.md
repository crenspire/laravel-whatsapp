# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - Unreleased

This release contains breaking changes. See [UPGRADE.md](UPGRADE.md) for how to upgrade from 2.x.

### Added
- Laravel 13 support is now tested in CI
- Template management: create, edit, delete, list, and status checks
- `MessageFailed` is dispatched for `failed` webhook statuses, with the message ID
- `TemplateBuilder::button()` accepts the button index

### Removed
- Support for Laravel 10 and 11, which no longer receive security fixes

### Changed
- Requires Laravel 12 or 13, and Carbon 3
- Unknown tenant IDs throw `WhatsappException` instead of silently using the default account
- `default_language` defaults to `en_US` (Meta language codes use underscores)
- `sendMultiProductMessage()` requires header text, as the API does
- Template footer parameters throw `WhatsappException`; footers take no parameters
- `uploadMedia()` detects the MIME type when given a category such as `image`
- Webhooks received without a configured `webhook_secret` log a warning

### Fixed
- Media, business profile, and template calls reused the first tenant's credentials for every tenant
- Tenant-specific `business_account_id` was ignored
- Media uploads were sent with a JSON Content-Type and rejected
- Custom headers were ignored for media and business profile calls
- Media header parameters in template messages had the wrong shape
- Template edit and delete used the wrong endpoints
- `updateWebsite()` sent `website` instead of `websites`
- Webhooks only processed the first entry and change of a batched payload
- Webhook verification logged the expected verify token, and full payloads were logged outside debug mode
- Webhook verification passed when no verify token was configured
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

[3.0.0]: https://github.com/crenspire/laravel-whatsapp/compare/v2.0.0...develop
[2.0.0]: https://github.com/crenspire/laravel-whatsapp/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/crenspire/laravel-whatsapp/releases/tag/v1.0.0
