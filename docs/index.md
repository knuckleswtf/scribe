# Overview

Generate API documentation for humans from your Laravel/Lumen/[Dingo](https://github.com/dingo/api) codebase. [Here's what the output looks like](https://shalvah.me/TheCensorshipAPI/).

> Coming from mpociot/laravel-apidoc-generator? Check out [what's new](whats-new.md) and the [migration guide](migrating.md). Otherwise, check out the [Getting Started guide](guide-getting-started.md).

## Contents
* [Getting started](guide-getting-started.md)
* [Whats new](whats-new.md)
* [Migrating from mpociot/laravel-apidoc-generator](migrating.md)
* [Documenting Your API](documenting.md)
* [Adding general information about your API](documenting-api-information.md)
* [Documenting endpoint metadata](documenting-endpoint-metadata.md)
* [Specifying headers to be sent in requests to the endpoint](documenting-endpoint-headers.md)
* [Documenting query parameters and URL parameters to be sent in requests to the endpoint](documenting-endpoint-query-parameters.md)
* [Documenting body parameters and files to be sent in requests to the endpoint](documenting-endpoint-body-parameters.md)
* [Documenting example responses returned from the endpoint](documenting-endpoint-responses.md)
* [Generating Documentation](generating-documentation.md)
* [Configuration](config.md)
* [Helpful Tips](helpful-tips.md)
* [Troubleshooting and Debugging](troubleshooting.md)
* [Advanced Customization](customization.md)
* [How This Works](description.md)
* [Extending functionality with plugins](plugins.md)
* [Contribution guide](contributing.md)

## Installation
> Note: PHP 7.2 and Laravel 5.8 or higher are required.

```sh
composer require knuckleswtf/scribe
```

### Laravel
Publish the config file by running:

```bash
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-config
```
This will create a `scribe.php` file in your `config` folder.

### Lumen
- Register the service provider in your `bootstrap/app.php`:

```php
$app->register(\Knuckles\Scribe\ScribeServiceProvider::class);
```

- Copy the config file from `vendor/knuckleswtf/scribe/config/scribe.php` to your project as `config/scribe.php`. Then add to your `bootstrap/app.php`:

```php
$app->configure('scribe');
```
