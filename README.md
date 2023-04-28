# Scribe

[![Latest Stable Version](https://poser.pugx.org/knuckleswtf/scribe/v/stable)](https://packagist.org/packages/knuckleswtf/scribe) [![Total Downloads](https://poser.pugx.org/knuckleswtf/scribe/downloads)](https://packagist.org/packages/knuckleswtf/scribe)

<p align="center">
  <img src="logo-scribe.png"><br>
</p>


> [v4 is out now](https://scribe.knuckles.wtf/blog/laravel-v4)! Featuring subgroups, easier sorting, and an automated upgrade command.

Scribe helps you generate API documentation for humans from your Laravel/Lumen/[Dingo](https://github.com/dingo/api) codebase. See a live example at [demo.scribe.knuckles.wtf](https://demo.scribe.knuckles.wtf).

## Features
- Useful output:
  - Pretty single-page HTML doc, with human-friendly text, code samples, and in-browser API tester ("Try It Out")
  - Generates Postman collection and OpenAPI spec
- Smarts. Scribe can:
  - extract request parameter details from FormRequests or validation rules
  - safely call API endpoints to get sample responses
  - generate sample responses from Eloquent API Resources or Transformers
- Customisable to different levels:
  - Customise the UI by adjusting text, ordering, examples, or change the UI itself
  - Add custom strategies to adjust how data is extracted
  - Statically define extra endpoints or information that isn't in your codebase

> 👋 Scribe helps you generate docs automatically, but if you really want to make friendly, maintainable and testable API docs, there's some more things you need to know. So I made [a course](https://apidocsfordevs.com?utm_source=scribe-laravel&utm_medium=referral&utm_campaign=none) for you.🤗

## Documentation
Check out the documentation at [scribe.knuckles.wtf/laravel](http://scribe.knuckles.wtf/laravel).

If you're coming from `mpociot/laravel-apidoc-generator`, first [migrate to v3](http://scribe.knuckles.wtf/blog/laravel/3.x/migrating-apidoc)`, then [to v4](http://scribe.knuckles.wtf/blog/laravel/migrating-v4).

## Contributing
Contributing is easy! See our [contribution guide](https://scribe.knuckles.wtf/laravel/contributing).
