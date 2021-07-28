# Scribe

[![Latest Stable Version](https://poser.pugx.org/knuckleswtf/scribe/v/stable)](https://packagist.org/packages/knuckleswtf/scribe) [![Total Downloads](https://poser.pugx.org/knuckleswtf/scribe/downloads)](https://packagist.org/packages/knuckleswtf/scribe)

<p align="center">
  <img src="logo-scribe.png"><br>
</p>


> [v3 is out now](https://scribe.knuckles.wtf/blog/2021/06/08/laravel-v3)!

Scribe helps you generate API documentation for humans from your Laravel/Lumen/[Dingo](https://github.com/dingo/api) codebase. See a live example at [demo.scribe.knuckles.wtf](https://demo.scribe.knuckles.wtf). There's a [Node.js version](https://github.com/knuckleswtf/scribe-js), too!

## Features
- Pretty single-page HTML doc, with human-friendly text, code samples, and included in-browser API tester ("Try It Out")
- Extracts body parameters details from FormRequests or validation rules
- Safely calls API endpoints to get sample responses
- Supports generating responses from Transformers or Eloquent API Resources
- Generates Postman collection and OpenAPI spec
- Easily customisable with custom views and included UI components
- Easily extensible with custom strategies
- Statically define extra endpoints that aren't in your codebase

> 👋 Scribe helps you generate docs automatically, but if you really want to make friendly, maintainable and testable API docs, there's some more things you need to know. So I made [a course](https://apidocsfordevs.com?utm_source=scribe-laravel&utm_medium=referral&utm_campaign=none) for you.🤗

## Documentation
Check out the documentation at [scribe.knuckles.wtf/laravel](http://scribe.knuckles.wtf/laravel).

v2 docs (PHP 7.2+, not actively maintained) are at [scribe.rtfd.io](http://scribe.rtfd.io).

If you're coming from `mpociot/laravel-apidoc-generator`, there's a [migration guide](https://scribe.knuckles.wtf/laravel/migrating-apidoc).

## Contributing
Contributing is easy! See our [contribution guide](https://scribe.knuckles.wtf/laravel/contributing).
