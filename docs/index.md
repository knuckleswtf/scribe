# Overview

Automatically generate your API documentation from your existing Laravel/Lumen/[Dingo](https://github.com/dingo/api) routes. [Here's what the output looks like](https://shalvah.me/TheCensorshipAPI/).

`php artisan scribe:generate`

## Contents
* [How This Works](description.md)
* [Configuration](config.md)
* [Migrating from v3 to v4](migrating.md)
* [Generating Documentation](generating-documentation.md)
* [Documenting Your API](documenting.md)
* [Extending functionality with plugins](plugins.md)
* [Internal Architecture](architecture.md)

## Installation
> Note: PHP 7 and Laravel 5.5 or higher are required.

```sh
composer require knuckleswtf/scribe
```

### Laravel
Publish the config file by running:

```bash
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-config
```
This will create an `scribe.php` file in your `config` folder.

### Lumen
- Register the service provider in your `bootstrap/app.php`:

```php
$app->register(\Knuckles\Scribe\ScribeServiceProvider::class);
```

- Copy the config file from `vendor/knuckleswtf/scribe/config/scribe.php` to your project as `config/scribe.php`. Then add to your `bootstrap/app.php`:

```php
$app->configure('scribe');
```
