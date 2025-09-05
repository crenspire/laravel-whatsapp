# Semantic Versioning Guide

This package follows [Semantic Versioning (SemVer)](https://semver.org/) for version management.

## Version Format

`MAJOR.MINOR.PATCH` (e.g., `1.0.0`)

- **MAJOR** version: Incompatible API changes
- **MINOR** version: New functionality in a backwards compatible manner
- **PATCH** version: Backwards compatible bug fixes

## Current Version

**1.0.0** - Initial release

## Version Management Commands

### View Current Version
```bash
composer version:show
```

### Update Version
```bash
# Patch version (bug fixes)
composer version:patch

# Minor version (new features)
composer version:minor

# Major version (breaking changes)
composer version:major
```

### Release Commands
```bash
# Release patch version
composer release:patch

# Release minor version
composer release:minor

# Release major version
composer release:major
```

### Generate Changelog
```bash
composer changelog
```

## Version Update Guidelines

### PATCH (1.0.1, 1.0.2, etc.)
- Bug fixes
- Documentation updates
- Code refactoring (no API changes)
- Dependency updates (non-breaking)

### MINOR (1.1.0, 1.2.0, etc.)
- New features
- New methods added to existing classes
- New configuration options
- New events or exceptions
- Backwards compatible enhancements

### MAJOR (2.0.0, 3.0.0, etc.)
- Breaking changes to existing APIs
- Method signature changes
- Removed methods or classes
- Incompatible configuration changes
- Dropped support for Laravel versions

## Release Checklist

Before releasing a new version:

1. ✅ Run tests: `composer test`
2. ✅ Update documentation if needed
3. ✅ Update CHANGELOG.md
4. ✅ Update version in composer.json
5. ✅ Update version in all doc comments
6. ✅ Commit changes
7. ✅ Create git tag
8. ✅ Push to repository

## Changelog Template

```markdown
## [1.0.1] - 2024-01-15

### Fixed
- Fixed phone number validation issue
- Resolved memory leak in media handling

### Changed
- Improved error messages for better debugging

## [1.1.0] - 2024-01-20

### Added
- Support for interactive messages
- New webhook verification method
- Rate limiting configuration

### Changed
- Updated Laravel 12 compatibility
- Improved documentation
```

## Git Tags

Each release should be tagged with the version number:
```bash
git tag v1.0.0
git push origin v1.0.0
```

## Package Installation

Users can install specific versions:
```bash
# Latest version
composer require crenspire/laravel-whatsapp

# Specific version
composer require crenspire/laravel-whatsapp:^1.0.0

# Latest patch of 1.0
composer require crenspire/laravel-whatsapp:~1.0.0
```
