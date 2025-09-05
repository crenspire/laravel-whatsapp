# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Semantic versioning support
- Version management scripts
- Comprehensive documentation

## [1.0.0] - 2024-09-05

### Added
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
