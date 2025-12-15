# Contributing to Binary Compound File Reader

Thank you for considering contributing to this project! This document outlines the process and guidelines for contributing.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check existing issues to avoid duplicates. When creating a bug report, include:

- **Clear title and description**
- **Steps to reproduce** the behavior
- **Expected vs actual behavior**
- **PHP version** and operating system
- **Sample file** that reproduces the issue (if applicable)

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion, include:

- **Clear title and description**
- **Use case** explaining why this would be useful
- **Proposed implementation** (if you have ideas)
- **Alternative solutions** you've considered

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Make your changes** following the code style guidelines
3. **Add tests** for any new functionality
4. **Ensure all tests pass**: `composer test`
5. **Run static analysis**: `composer phpstan`
6. **Check code style**: `composer cs-check` (fix with `composer cs-fix`)
7. **Update documentation** if needed
8. **Commit with clear messages** following conventional commits format

## Development Setup

```bash
# Clone your fork
git clone https://github.com/YOUR-USERNAME/binary-compound-file-reader.git
cd binary-compound-file-reader

# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer phpstan

# Check code style
composer cs-check
```

## Code Style

This project follows PSR-12 coding standards. Code style is automatically checked in CI and can be fixed locally:

```bash
# Check for style issues
composer cs-check

# Automatically fix style issues
composer cs-fix
```

## Testing Guidelines

- **Write tests** for all new features and bug fixes
- **Unit tests** go in `tests/Unit/`
- **Integration tests** go in `tests/Integration/`
- Tests should be **clear and descriptive**
- Aim for **high code coverage**

### Running Tests

```bash
# Run all tests
composer test

# Run with coverage report
composer test-coverage
```

## Static Analysis

All code must pass PHPStan level 8:

```bash
composer phpstan
```

## Commit Messages

Follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation changes
- `style:` Code style changes (formatting, etc.)
- `refactor:` Code refactoring
- `test:` Adding or updating tests
- `chore:` Maintenance tasks

Example:
```
feat: add support for version 5 compound files

- Implement new sector size calculation
- Add tests for v5 files
- Update documentation
```

## Code Review Process

1. All submissions require review
2. Changes may be requested by maintainers
3. Once approved, maintainers will merge the PR
4. CI must pass before merging

## Release Process

Releases follow [Semantic Versioning](https://semver.org/):

- **MAJOR**: Breaking changes
- **MINOR**: New features (backwards compatible)
- **PATCH**: Bug fixes (backwards compatible)

## Questions?

Feel free to open an issue with the `question` label if you need clarification on anything.

## License

By contributing, you agree that your contributions will be licensed under the GPL-3.0-or-later License.
