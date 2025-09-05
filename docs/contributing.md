# Contributing

Thank you for considering contributing to the Laravel WhatsApp package! This document provides guidelines for contributing to the project.

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- Laravel 10.x or 11.x
- Git

### Fork and Clone

1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/your-username/laravel-whatsapp.git
   cd laravel-whatsapp
   ```

### Install Dependencies

```bash
composer install
```

### Run Tests

```bash
composer test
```

## Development Setup

### Environment Configuration

Create a `.env.testing` file for testing:

```env
WHATSAPP_BASE_URI=https://graph.facebook.com/v20.0
WHATSAPP_PHONE_NUMBER_ID=test_phone_number
WHATSAPP_ACCESS_TOKEN=test_token
WHATSAPP_WEBHOOK_VERIFY_TOKEN=test_verify_token
WHATSAPP_RATE_LIMIT=30
WHATSAPP_DEBUG=true
```

### Code Style

The project follows PSR-12 coding standards. Run the code style checker:

```bash
composer cs-check
```

Fix code style issues:

```bash
composer cs-fix
```

## Contributing Guidelines

### 1. Create a Feature Branch

```bash
git checkout -b feature/your-feature-name
```

### 2. Make Your Changes

- Write clean, readable code
- Follow PSR-12 coding standards
- Add tests for new functionality
- Update documentation as needed
- Ensure all tests pass

### 3. Write Tests

All new functionality must include tests:

```php
<?php

use Crenspire\Whatsapp\WhatsappService;
use Illuminate\Support\Facades\Http;

it('does something new', function () {
    // Arrange
    Http::fake([
        'graph.facebook.com/v20.0/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'test123']]
        ], 200)
    ]);

    // Act
    $result = $this->service->newMethod();

    // Assert
    expect($result)->toBe('expected_value');
});
```

### 4. Update Documentation

Update relevant documentation files:
- `README.md` for new features
- `docs/api-reference.md` for new methods
- `docs/examples.md` for usage examples
- `docs/configuration.md` for new config options

### 5. Commit Your Changes

Use conventional commit messages:

```bash
git commit -m "feat: add new message type support"
git commit -m "fix: resolve webhook verification issue"
git commit -m "docs: update API reference"
```

### 6. Push and Create Pull Request

```bash
git push origin feature/your-feature-name
```

Then create a pull request on GitHub.

## Pull Request Guidelines

### Before Submitting

- [ ] All tests pass
- [ ] Code follows PSR-12 standards
- [ ] Documentation is updated
- [ ] No breaking changes (or clearly documented)
- [ ] Commit messages are conventional

### Pull Request Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Tests added/updated
- [ ] All tests pass
- [ ] Manual testing completed

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] No breaking changes
```

## Code Review Process

1. **Automated Checks**: CI/CD pipeline runs tests and code style checks
2. **Manual Review**: Maintainers review the code
3. **Feedback**: Address any feedback or requested changes
4. **Approval**: Once approved, the PR will be merged

## Development Workflow

### 1. Feature Development

```bash
# Create feature branch
git checkout -b feature/new-feature

# Make changes
# ... code changes ...

# Run tests
composer test

# Fix any issues
# ... fix code ...

# Commit changes
git add .
git commit -m "feat: add new feature"

# Push branch
git push origin feature/new-feature
```

### 2. Bug Fixes

```bash
# Create bugfix branch
git checkout -b fix/bug-description

# Make changes
# ... fix code ...

# Add tests
# ... add tests ...

# Commit changes
git add .
git commit -m "fix: resolve bug description"

# Push branch
git push origin fix/bug-description
```

### 3. Documentation Updates

```bash
# Create docs branch
git checkout -b docs/update-documentation

# Update documentation
# ... update docs ...

# Commit changes
git add .
git commit -m "docs: update documentation"

# Push branch
git push origin docs/update-documentation
```

## Testing Guidelines

### Unit Tests

Write unit tests for all new methods:

```php
it('validates input correctly', function () {
    // Test validation logic
});

it('handles errors gracefully', function () {
    // Test error handling
});

it('returns expected output', function () {
    // Test return values
});
```

### Integration Tests

Write integration tests for API interactions:

```php
it('sends message successfully', function () {
    Http::fake([
        'graph.facebook.com/v20.0/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'test123']]
        ], 200)
    ]);

    $result = $this->service->sendTextMessage('1234567890', 'Hello');

    expect($result)->toHaveKey('messages');
});
```

### Event Tests

Test event dispatching:

```php
it('dispatches message sent event', function () {
    Event::fake();

    $this->service->sendTextMessage('1234567890', 'Hello');

    Event::assertDispatched(MessageSent::class);
});
```

## Documentation Guidelines

### API Documentation

Update `docs/api-reference.md` for new methods:

```markdown
### newMethod

```php
public function newMethod(string $param): array
```

Description of the method.

**Parameters:**
- `$param` (string): Parameter description

**Returns:** array - Return description

**Example:**
```php
$result = $service->newMethod('value');
```
```

### Examples

Add examples to `docs/examples.md`:

```markdown
### New Feature Example

```php
// Example code showing how to use the new feature
$result = Whatsapp::newMethod('parameter');
```
```

## Release Process

### Version Numbering

Follow [Semantic Versioning](https://semver.org/):
- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)

### Changelog

Update `CHANGELOG.md` with:
- New features
- Bug fixes
- Breaking changes
- Deprecations

## Community Guidelines

### Code of Conduct

- Be respectful and inclusive
- Provide constructive feedback
- Help others learn and grow
- Follow the golden rule

### Communication

- Use clear, descriptive commit messages
- Provide context in pull requests
- Ask questions if unsure
- Be patient with reviews

## Getting Help

If you need help:

1. Check the [documentation](README.md)
2. Search [existing issues](https://github.com/your-repo/laravel-whatsapp/issues)
3. Create a new issue with detailed information
4. Join the community discussions

## Thank You

Thank you for contributing to the Laravel WhatsApp package! Your contributions help make the package better for everyone.
