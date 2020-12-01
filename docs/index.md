# Overview

Generate API documentation for humans from your Laravel/Lumen/[Dingo](https://github.com/dingo/api) codebase. [Here's what the output looks like](https://shalvah.me/TheCensorshipAPI/). There's a [Node.js version](https://github.com/knuckleswtf/scribe-js), too!

```eval_rst
.. admonition:: Wondering where to start? Try one of these links:
   
   - `What's new in v2 <migrating-v2.html>`_
   - `Migrating from mpociot/laravel-apidoc-generator <migrating.html>`_, or
   - the `getting started guide <guide-getting-started.html>`_.
```

```eval_rst
.. Tip:: Scribe helps you generate docs automatically, but if you really want to make friendly, maintainable and testable API docs, there's some more stuff you need to know. So I made `a course <https://apidocsfordevs.com?utm_source=scribe-laravel-docs&utm_medium=referral&utm_campaign=launch>`_ for you.🤗
```

## Features
- Pretty HTML documentation page, with included code samples and friendly text
- Included "Try It Out" button so users can test endpoints right from their browser
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
   migrating-v2
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
PHP 7.2.5 and Laravel/Lumen 6 or higher are required.

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

Next up: follow the [Getting Started guide](./guide-getting-started.html) to see what you can do with Scribe.