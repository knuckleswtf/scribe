# Copilot Instructions for Scribe

## Project Overview

Scribe is a Laravel package that generates API documentation for humans from Laravel codebases. It extracts information from routes, controllers, FormRequests, and other sources to produce beautiful HTML documentation, Postman collections, and OpenAPI specs.

## Architecture

- **Extracting**: Core extraction logic that pulls information from Laravel applications
  - Strategies: Modular extractors for different types of information (metadata, parameters, responses, etc.)
  - Each strategy focuses on a specific aspect (DocBlocks, route bindings, validation rules, etc.)
- **Writing**: Outputs documentation in various formats (HTML, Postman, OpenAPI)
- **Commands**: Artisan commands for generating documentation
- **Camel**: Internal DTO library (`camel/` directory) for data structures

## Code Style and Conventions

### PHP Standards
- **PHP Version**: Minimum PHP 8.1
- **Coding Style**: Use PHP-CS-Fixer with auto mode (see `.php-cs-fixer.dist.php`)
- **Type Declarations**: Use strict types (`declare(strict_types=1);`)
- **Indentation**: 4 spaces for PHP, 2 spaces for YAML/JSON/Markdown
- **Line Endings**: LF (Unix-style)
- **Namespaces**: Follow PSR-4 structure
  - `Knuckles\Scribe\*` for main code
  - `Knuckles\Camel\*` for Camel library

### Documentation
- Use comprehensive DocBlocks for public methods
- Include `@param` and `@return` type hints
- Add descriptive comments for complex logic
- Follow Laravel documentation style for consistency

### Testing
- Use Pest testing framework
- Organize tests by type:
  - `tests/Unit/` for unit tests
  - `tests/GenerateDocumentation/` for documentation generation tests
  - `tests/Strategies/` for strategy tests
- Test classes should extend `BaseLaravelTest` or `BaseUnitTest`
- Use PHPUnit assertions and array subset assertions
- Mark test classes with `@internal` and `@coversNothing` annotations

## Development Commands

### Dependencies
```bash
composer install        # Install dependencies
composer update        # Update dependencies
```

### Code Quality
```bash
composer lint          # Run PHPStan static analysis (level 6)
composer style:check   # Check code style with PHP-CS-Fixer
composer style:fix     # Fix code style issues automatically
```

### Testing
```bash
composer test          # Run tests with Pest (stops on failure)
composer test-ci       # Run all tests (CI mode, no stop on failure)
```

### Git Hooks
The repository uses custom git hooks in `.githooks/`. These are automatically configured via composer scripts.

## Key Concepts

### Strategies Pattern
Scribe uses a strategy pattern for extraction:
- Each aspect of documentation (metadata, parameters, responses) has multiple strategies
- Strategies are executed in order until one provides data
- Strategies can be configured, enabled, or disabled per project
- Custom strategies can be added by users

### Configuration
- Configuration is managed through `Config` class
- Defaults are defined in `Config\Defaults`
- Users can override settings in their Laravel config
- Strategy configuration uses `configureStrategy()` helper

### Extraction Flow
1. Route matching and filtering
2. Strategy execution for each endpoint aspect
3. Data normalization and merging
4. Output generation (HTML, Postman, OpenAPI)

## Dependencies and Compatibility

### Laravel Support
- Supports Laravel 9, 10, 11, and 12
- Compatible with PHP 8.1, 8.2, 8.3, and 8.4
- Uses Orchestra Testbench for testing

### Key Dependencies
- `nikic/php-parser` for parsing PHP code
- `mpociot/reflection-docblock` for DocBlock parsing
- `fakerphp/faker` for generating examples
- `symfony/yaml` and `symfony/var-exporter` for data export

## Testing Practices

### Test Structure
- Use descriptive test method names
- Set up minimal configuration needed for tests
- Disable expensive operations (response calls, file generation) in unit tests
- Use fixtures in `tests/Fixtures/` for test data

### Test Configuration
- Tests use in-memory SQLite database
- Faker seed is set to 1234 for consistent examples
- Skip Postman and OpenAPI generation by default for speed
- Disable response calls except where explicitly needed

## CI/CD

### GitHub Actions
- **Tests**: Run on PHP 8.1-8.4 with highest and lowest dependencies
- **Lint**: Run PHPStan on PHP 8.3 and 8.4
- Runs on `v4` and `v5` branches and all PRs

### Branch Strategy
- `v4` and `v5` are the main development branches
- Features and fixes should target the appropriate version branch

## Common Tasks

### Adding a New Strategy
1. Create class in appropriate `Extracting/Strategies/` subdirectory
2. Extend base strategy class if available
3. Implement extraction logic
4. Add to default strategies in `Config\Defaults` if appropriate
5. Add tests in `tests/Strategies/`

### Modifying Output Format
1. Update writer in `Writing/` directory
2. Update corresponding tests
3. Ensure backward compatibility or update CHANGELOG

### Adding Configuration Options
1. Add to `Config` class
2. Update `Config\Defaults` if needed
3. Document in configuration files
4. Add validation if necessary

## Important Notes

- **Never break existing APIs**: This is a library used by many projects
- **Maintain backward compatibility**: Especially for configuration and public APIs
- **Test thoroughly**: Changes can affect many Laravel versions and PHP versions
- **Update CHANGELOG.md**: Document all user-facing changes
- **Follow semver**: Breaking changes require major version bumps

## Getting Help

- Documentation: https://scribe.knuckles.wtf/laravel
- Contributing Guide: https://scribe.knuckles.wtf/laravel/contributing
- Issues: Use GitHub issue templates for bugs and questions
