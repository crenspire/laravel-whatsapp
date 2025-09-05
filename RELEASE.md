# Release Guide

This guide explains how to release new versions of the Laravel WhatsApp package.

## Quick Release Commands

### Patch Release (Bug Fixes)
```bash
composer release:patch
```

### Minor Release (New Features)
```bash
composer release:minor
```

### Major Release (Breaking Changes)
```bash
composer release:major
```

## Manual Release Process

### 1. Update Version
```bash
# Show current version
composer version:show

# Update patch version (1.0.0 → 1.0.1)
composer version:patch

# Update minor version (1.0.0 → 1.1.0)
composer version:minor

# Update major version (1.0.0 → 2.0.0)
composer version:major
```

### 2. Run Tests
```bash
composer test
```

### 3. Update Documentation
- Update CHANGELOG.md
- Update VERSION.md if needed
- Update README.md if needed

### 4. Commit and Tag
```bash
git add .
git commit -m "Release v$(composer version:show)"
git tag v$(composer version:show)
git push origin main
git push origin v$(composer version:show)
```

## Version Guidelines

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

## Pre-Release Checklist

- [ ] All tests pass
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] Version updated in composer.json
- [ ] Version updated in all doc comments
- [ ] No linting errors
- [ ] Git working directory clean

## Post-Release

- [ ] Push to repository
- [ ] Create GitHub release
- [ ] Update package on Packagist (if published)
- [ ] Notify users of breaking changes (if major release)

## Example Release Workflow

```bash
# 1. Start with clean working directory
git status

# 2. Update version
composer version:patch

# 3. Run tests
composer test

# 4. Update changelog
echo "## [$(composer version:show)] - $(date +%Y-%m-%d)" >> CHANGELOG.md
echo "" >> CHANGELOG.md
echo "### Fixed" >> CHANGELOG.md
echo "- Fixed phone number validation issue" >> CHANGELOG.md
echo "" >> CHANGELOG.md

# 5. Commit and tag
git add .
git commit -m "Release v$(composer version:show)"
git tag v$(composer version:show)

# 6. Push
git push origin main
git push origin v$(composer version:show)
```

## Troubleshooting

### Version Script Not Working
```bash
# Make sure the script is executable
chmod +x scripts/version.php

# Test the script directly
php scripts/version.php show
```

### Git Tag Issues
```bash
# List all tags
git tag -l

# Delete a tag (if needed)
git tag -d v1.0.1
git push origin :refs/tags/v1.0.1
```

### Composer Script Issues
```bash
# Clear composer cache
composer clear-cache

# Reinstall dependencies
composer install
```
