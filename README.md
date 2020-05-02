## Scribe ✍

Generate API documentation for humans from your Laravel codebase. [Here's what the output looks like](https://shalvah.me/TheCensorshipAPI/).

[![Latest Stable Version](https://poser.pugx.org/knuckleswtf/scribe/v/stable)](https://packagist.org/packages/knuckleswtf/scribe)[![Total Downloads](https://poser.pugx.org/knuckleswtf/scribe/downloads)](https://packagist.org/packages/knuckleswtf/scribe) [![Build Status](https://travis-ci.org/knuckleswtf/scribe.svg?branch=master)](https://travis-ci.org/knuckleswtf/scribe) [![Total Downloads](https://poser.pugx.org/knuckleswtf/scribe/downloads)](https://packagist.org/packages/knuckleswtf/scribe)

## Installation
PHP 7.2 and Laravel/Lumen 6 or higher are required.

> If your application does not meet these requirements, you can check out the 3.x branch for older releases.

```sh
composer require --dev knuckleswtf/scribe
```

### Laravel
Publish the config file by running:

```bash
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider" --tag=scribe-config
```

This will create a `scribe.php` file in your `config` folder.

### Lumen
- When using Lumen, you will need to run `composer require knuckleswtf/scribe` instead.
- Register the service provider in your `bootstrap/app.php`:

```php
$app->register(\Knuckles\Scribe\ScribeServiceProvider::class);
```

- Copy the config file from `vendor/knuckleswtf/scribe/config/scribe.php` to your project as `config/scribe.php`. Then add to your `bootstrap/app.php`:

```php
$app->configure('scribe');
```

## Documentation
Check out the documentation at [ReadTheDocs](http://scribe.rtfd.io/). Don't forget to check out the [migration guide](https://scribe.rtfd.io/en/latest/migrating.html) if you're coming from v3 to v4.

### License

The Laravel API Documentation Generator is free software licensed under the MIT license.
