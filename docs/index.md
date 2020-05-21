# Overview

Generate API documentation for humans from your Laravel/Lumen/[Dingo](https://github.com/dingo/api) codebase. [Here's what the output looks like](https://shalvah.me/TheCensorshipAPI/).


```eval_rst
.. admonition:: Wondering where to get started?
   
   If you're coming from mpociot/laravel-apidoc-generator, check out `what's new <whats-new.html>`_ and the `migration guide <migrating.html>`_. Otherwise, check out the `getting started guide <guide-getting-started.html>`_.
```

## Contents
```eval_rst
.. toctree::
   :maxdepth: 2

   guide-getting-started
   whats-new
   migrating
   documenting/index
   generating-documentation
   config
   troubleshooting
   customization
   architecture
   plugins
   contributing
```

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
