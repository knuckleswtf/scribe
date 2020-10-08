<h1 align="center">Scribe</h1>

<p align="center">
  <img src="logo-scribe.png"><br>
</p>

> v2 is in beta now! Try it out by changing your composer requirement to `^2.0.0-beta`. See the [migration guide](https://scribe.readthedocs.io/en/v2/migrating-v2.html).

Generate API documentation for humans from your Laravel codebase. [Here's what the output looks like](https://shalvah.me/TheCensorshipAPI/).

[![Latest Stable Version](https://poser.pugx.org/knuckleswtf/scribe/v/stable)](https://packagist.org/packages/knuckleswtf/scribe) [![Total Downloads](https://poser.pugx.org/knuckleswtf/scribe/downloads)](https://packagist.org/packages/knuckleswtf/scribe) [![Build Status](https://travis-ci.com/knuckleswtf/scribe.svg?branch=master)](https://travis-ci.com/knuckleswtf/scribe)

> Looking to document your Node.js APIs? Check out [Scribe for JS](https://github.com/knuckleswtf/scribe-js).

## Features
- Pretty HTML documentation page, with included code samples and friendly text
- Markdown source files that can be edited to modify docs
- Extracts body parameters information from Laravel FormRequests
- Safely calls API endpoints to generate sample responses, with authentication and other custom configuration supported
- Supports generating responses from Eloquent API Resources or Fractal Transformers
- Supports Postman collection and OpenAPI (Swagger) spec generation
- Included UI components for additional styling
- Easily customisable with custom views
- Easily extensible with custom strategies

## Documentation
> Scribe is a fork of [mpociot/laravel-apidoc-generator](https://github.com/mpociot/laravel-apidoc-generator), so see the [migration guide](https://scribe.rtfd.io/en/latest/migrating.html) if you're coming from there.

Check out the documentation at [ReadTheDocs](http://scribe.rtfd.io/).

## Installation
PHP 7.2.5 and Laravel/Lumen 5.8 or higher are required.

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
- When using Lumen, you will need to run `composer require knuckleswtf/scribe` instead (no `--dev`.
- Register the service provider in your `bootstrap/app.php`:

```php
$app->register(\Knuckles\Scribe\ScribeServiceProvider::class);
```

- Copy the config file from `vendor/knuckleswtf/scribe/config/scribe.php` to your project as `config/scribe.php`. Then add to your `bootstrap/app.php`:

```php
$app->configure('scribe');
```

## Contributing
Contributing is easy! See our [contribution guide](https://scribe.rtfd.io/en/latest/contributing.html).
