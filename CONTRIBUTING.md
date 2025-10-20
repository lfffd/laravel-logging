# Contributing to Superlog

Thank you for considering contributing to Superlog! This document provides guidelines for contributing to the project.

## Code of Conduct

- Be respectful to other contributors
- Focus on what is best for the community
- Show empathy towards other community members

## Getting Started

1. **Fork the repository**
   ```bash
   git clone https://github.com/lfffd/laravel-logging.git
   cd laravel-logging
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

## Development Setup

### Prerequisites
- PHP 8.1 or higher
- Composer
- Laravel 10 or 11

### Running Tests

```bash
composer test
```

Or with PHPUnit directly:
```bash
./vendor/bin/phpunit
```

### Code Style

We follow PSR-12 coding standards. Run code analysis:

```bash
./vendor/bin/pint
```

## Making Changes

### Code Structure
- Keep files focused and single-purpose
- Follow PSR-4 autoloading standards
- Use type hints for all method parameters and returns
- Add phpDoc blocks for public methods

### Testing
- Add tests for new features
- Ensure all tests pass before submitting PR
- Aim for >80% code coverage

### Documentation
- Update README.md if adding user-facing features
- Add examples in `examples/` directory
- Update configuration documentation in `config/superlog.php`

## Commit Guidelines

- Use clear, descriptive commit messages
- Reference issues when applicable
- Keep commits focused on a single change

Examples:
```
git commit -m "Add cache statistics logging"
git commit -m "Fix redaction of custom keys (#123)"
git commit -m "Improve async shipping reliability"
```

## Pull Request Process

1. **Update Documentation**
   - Update README.md with new features
   - Add entries to QUICK_REFERENCE.md if user-facing

2. **Test Thoroughly**
   ```bash
   composer test
   ./vendor/bin/pint
   ```

3. **Submit PR**
   - Provide clear description of changes
   - Link to relevant issues
   - Include before/after examples if applicable

4. **Review Process**
   - Address feedback promptly
   - Keep PR focused on the feature/fix
   - Be open to suggestions

## Project Areas

### High-Priority
- Performance optimizations
- PII redaction enhancements
- Database query profiling
- External handler implementations

### Good for Beginners
- Documentation improvements
- Additional test coverage
- Example code
- Configuration utilities

### Known Issues
See GitHub Issues for current development priorities.

## Setting Up Your Development Environment

### Local Testing with Laravel

Create a test Laravel application:

```bash
# Install Superlog locally
cd /path/to/test-app
composer require --dev file:///path/to/laravel-logging

# Update config/logging.php
# Register middleware
# Run tests
php artisan tinker
```

### Testing with Different Laravel Versions

```bash
# Test with Laravel 10
composer require laravel/framework:^10.0

# Test with Laravel 11
composer require laravel/framework:^11.0
```

## Debugging

### Enable Debug Mode
```php
// In tests or local development
\Superlog\SuperlogServiceProvider::$debug = true;
```

### Using Tinker
```bash
php artisan tinker
>>> $logger = app(\Superlog\Logger\StructuredLogger::class)
>>> $logger->log('info', 'TEST', 'Debug message')
```

## Documentation

### File Structure
- **README.md** - Main documentation
- **SETUP_GUIDE.md** - Installation & configuration
- **QUICK_REFERENCE.md** - Quick API reference
- **STRUCTURE.md** - Package architecture
- **CONTRIBUTING.md** - This file
- **examples/** - Code examples
- **src/** - Inline phpDoc comments

### Adding Documentation
1. Keep language clear and concise
2. Include code examples
3. Use code blocks for clarity
4. Update table of contents if needed

## Performance Considerations

When contributing, consider:
- **Memory usage**: Use generators for large datasets
- **CPU usage**: Avoid recursive operations at scale
- **I/O**: Defer heavy operations to shutdown
- **Async**: Use queue jobs for external calls

## Security

### Sensitive Information
- Never commit `.env` files
- Use environment variables for secrets
- Test redaction thoroughly
- Document any PII patterns you add

### Reporting Security Issues
Please report security vulnerabilities by opening a GitHub issue on the repository: https://github.com/lfffd/laravel-logging/issues

## License

By contributing to Superlog, you agree that your contributions will be licensed under its MIT License.

## Questions?

- **Documentation**: See README.md and SETUP_GUIDE.md
- **Issues**: Open a GitHub issue: https://github.com/lfffd/laravel-logging/issues
- **Discussions**: Use GitHub Discussions: https://github.com/lfffd/laravel-logging/discussions
- **Repository**: https://github.com/lfffd/laravel-logging

## Release Process

### Versioning
We follow [Semantic Versioning](https://semver.org/):
- MAJOR: Breaking changes
- MINOR: New features (backward compatible)
- PATCH: Bug fixes

### Creating a Release
1. Update version in `composer.json`
2. Update CHANGELOG.md
3. Tag release: `git tag v1.2.3`
4. Push to GitHub
5. Create release notes

## Thank You

Your contributions help make Superlog better for everyone. We appreciate your time and effort!

---

**Happy Contributing! 🚀**