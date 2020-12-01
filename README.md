<h1 align="center">Scribe</h1>

<p align="center">
  <img src="logo-scribe.png"><br>
</p>

> Still on v1? Here's the [v2 migration guide](https://scribe.rtfd.io/en/latest/migrating-v2.html).

Generate API documentation for humans from your Laravel codebase. [Here's what the output looks like](https://shalvah.me/TheCensorshipAPI/). There's a [Node.js version](https://github.com/knuckleswtf/scribe-js), too!

[![Latest Stable Version](https://poser.pugx.org/knuckleswtf/scribe/v/stable)](https://packagist.org/packages/knuckleswtf/scribe) [![Total Downloads](https://poser.pugx.org/knuckleswtf/scribe/downloads)](https://packagist.org/packages/knuckleswtf/scribe) [![Build Status](https://travis-ci.com/knuckleswtf/scribe.svg?branch=master)](https://travis-ci.com/knuckleswtf/scribe)

> 👋 Scribe helps you generate docs automatically, but if you really want to make friendly, maintainable and testable API docs, there's some more things you need to know. So I made [a course](https://apidocsfordevs.com?utm_source=scribe-laravel&utm_medium=referral&utm_campaign=launch) for you.🤗

## Features
- Pretty HTML documentation page, with included code samples and friendly text
- Included "Try It Out" button so users can test endpoints right from their browser
- Markdown source files that can be edited to modify docs
- Extracts body parameters information from Laravel FormRequests
- Safely calls API endpoints to generate sample responses, with authentication and other custom configuration supported
- Supports generating responses from Eloquent API Resources or Fractal Transformers
- Supports Postman collection and OpenAPI (Swagger) spec generation
- Included UI components for additional styling
- Easily customisable with custom views
- Easily extensible with custom strategies

## Documentation
> See the [migration guide](https://scribe.rtfd.io/en/latest/migrating.html) if you're coming from [mpociot/laravel-apidoc-generator](https://github.com/mpociot/laravel-apidoc-generator).

Check out the documentation at [ReadTheDocs](http://scribe.rtfd.io/).

## Installation
PHP 7.2.5 and Laravel/Lumen 6.0 or higher are required.

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
- When using Lumen, you will need to run `composer require knuckleswtf/scribe` instead (no `--dev`).
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
