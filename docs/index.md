# Overview

Generate API documentation for humans from your Laravel/Lumen/[Dingo](https://github.com/dingo/api) codebase. [Here's what the output looks like](https://shalvah.me/TheCensorshipAPI/).


```eval_rst
.. admonition:: Wondering where to get started?
   
   If you're coming from mpociot/laravel-apidoc-generator, check out `what's new <whats-new.html>`_ and the `migration guide <migrating.html>`_. Otherwise, check out the `getting started guide <guide-getting-started.html>`_.
```

## Contents
* [Getting started](guide-getting-started.md)
* [Whats new](whats-new.md)
* [Migrating from mpociot/laravel-apidoc-generator](migrating.md)
* [Documenting your API](documenting/index.md)
* [Generating documentation](generating-documentation.md)
* [Configuration](config.md)
* [Troubleshooting and debugging](troubleshooting.md)
* [Advanced customization](customization.md)
* [How Scribe works](architecture.md)
* [Extending functionality with plugins](plugins.md)
* [Contributing to Scribe](contributing.md)

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
- Register the service provider in your `bootstrap/app.php`:

```php
$app->register(\Knuckles\Scribe\ScribeServiceProvider::class);
```

- Copy the config file from `vendor/knuckleswtf/scribe/config/scribe.php` to your project as `config/scribe.php`. Then add to your `bootstrap/app.php`:

```php
$app->configure('scribe');
```
