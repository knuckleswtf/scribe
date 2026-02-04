# Agent Guidelines for Scribe

This repository contains the source code for Scribe, a PHP package that generates API documentation for Laravel applications.

## 🛠 Project Tooling & Commands

This project uses **Composer** for dependency management and scripts.
- **Test Runner**: [Pest](https://pestphp.com/) (which wraps PHPUnit).
- **Static Analysis**: [PHPStan](https://phpstan.org/).
- **Code Style**: [PHP-CS-Fixer](https://cs.symfony.com/).

### Key Commands

| Action | Command | Description |
|--------|---------|-------------|
| **Run Tests** | `composer test` | Runs all tests using Pest. |
| **Run Single Test** | `vendor/bin/pest --filter MethodName` | Runs a specific test method. |
| **Run File Tests** | `vendor/bin/pest path/to/Test.php` | Runs all tests in a specific file. |
| **Lint Code** | `composer lint` | Runs PHPStan analysis. |
| **Check Style** | `composer style:check` | Checks code style without modifying files. |
| **Fix Style** | `composer style:fix` | Automatically fixes code style issues. |

> **Note**: Always run `composer test` and `composer lint` before submitting changes.

## 📐 Code Style & Conventions

Adhere strictly to the existing style. The project generally follows **PSR-12**.

### General PHP
- **Version**: PHP 8.1+. Use modern features like typed properties, union types, and constructor promotion where appropriate.
- **Strict Types**: Do **NOT** use `declare(strict_types=1);` unless strictly necessary or found in the file being edited (it is generally absent).
- **Indentation**: 4 spaces.
- **Arrays**: Use short array syntax `[]`.
- **Trailing Commas**: Use trailing commas in multi-line arrays.

### Naming
- **Classes**: `PascalCase` (e.g., `PostmanCollectionWriter`).
- **Methods**: `camelCase` (e.g., `generatePostmanCollection`).
- **Variables**: `camelCase` (e.g., `$groupedEndpoints`).
- **Constants**: `UPPER_CASE_WITH_UNDERSCORES`.

### Imports & Namespaces
- **Ordering**: Alphabetical order.
- **Grouping**: Group standard library imports separately if applicable, but generally keep them sorted.
- **Unused Imports**: Remove unused imports.

### Type Hinting & Docblocks
- **Types**: Use native PHP type hints (param and return types) whenever possible.
    - Example: `public function writeDocs(array $groupedEndpoints): void`
- **Docblocks**:
    - Use PHPDoc for generic arrays to specify content types (e.g., `/** @param array<string, mixed> $config */`).
    - Use `/** @var ClassName $var */` for inline type hinting when the static analyzer cannot infer it (e.g., resolving from container).
    - Do not duplicate native type info in docblocks unless adding detail.

### Laravel Integration
- **Dependency Injection**: Prefer constructor injection.
- **Helpers**: Use Laravel helpers (`app()`, `config()`, `public_path()`) where appropriate and consistent with existing code.
- **Facades**: Facades are used (e.g., `Storage`, `Route`). Import them via `Illuminate\Support\Facades\...`.

## 🧪 Testing Guidelines

- **Framework**: Tests are written using PHPUnit class syntax (extending `BaseLaravelTest` or `TestCase`), but executed via Pest.
- **Location**: Place unit tests in `tests/Unit/`.
- **Naming**: Test classes end in `Test.php`. Test methods use the `/** @test */` annotation or start with `test`.
- **Assertions**: Use standard PHPUnit assertions (e.g., `$this->assertEquals`, `$this->assertCount`).
- **Mocking**: Use Mockery or Laravel's mocking helpers if applicable.

## 📂 File Structure

- `src/`: Core package source code.
    - `Writing/`: Logic for generating output files (HTML, Postman, OpenAPI).
    - `Tools/`: Utility classes.
    - `Attributes/`: PHP Attributes used by the package.
- `tests/`: Test suite.
- `resources/views/`: Blade templates for generated documentation.
- `config/`: Configuration files.

## 📝 Error Handling

- Use specific exceptions where possible (e.g., `ScribeException`).
- Handle errors gracefully, especially when parsing user code or configuration.
- Use `ConsoleOutputUtils` (aliased as `c`) for CLI output in commands.
