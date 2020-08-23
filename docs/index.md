# Overview

Generate API documentation for humans from your Laravel/Lumen/[Dingo](https://github.com/dingo/api) codebase. [Here's what the output looks like](https://shalvah.me/TheCensorshipAPI/).


```eval_rst
.. admonition:: Wondering where to get started?
   
   If you're coming from mpociot/laravel-apidoc-generator, check out `what's new <whats-new.html>`_ and the `migration guide <migrating.html>`_. Otherwise, check out the `getting started guide <guide-getting-started.html>`_.
```

```eval_rst
.. Tip:: Looking to document your Node.js APIs? Check out `Scribe for JS <https://github.com/knuckleswtf/scribe-js>`_.
```
## Features
- Pretty HTML documentation page, with included code samples and friendly text
- Markdown source files that can be edited to modify docs
- Extracts body parameters information from FormRequests
- Safely calls API endpoints to generate sample responses, with authentication and other custom configuration supported
- Supports generating responses from Transformers or Eloquent API Resources
- Supports Postman collection and OpenAPI (Swagger) spec generation
- Included UI components for additional styling
- Easily customisable with custom views
- Easily extensible with custom strategies

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
