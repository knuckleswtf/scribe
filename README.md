<div align="center">
<img src="logo-scribe.png" alt="logo-scribe"><br>

[![Latest Stable Version](https://poser.pugx.org/knuckleswtf/scribe/v/stable)](https://packagist.org/packages/knuckleswtf/scribe)
[![Total Downloads](https://poser.pugx.org/knuckleswtf/scribe/downloads)](https://packagist.org/packages/knuckleswtf/scribe)
[![Code Style](https://img.shields.io/badge/code_style-pint-f58d33)](https://github.com/laravel/pint)

</div>


Scribe helps you generate API documentation for humans from your Laravel codebase. See a live example at [demo.scribe.knuckles.wtf](https://demo.scribe.knuckles.wtf).

## Features
- Useful output:
  - Pretty single-page HTML doc, with human-friendly text, code samples, and in-browser API tester ("Try It Out")
  - Generates Postman collection and OpenAPI spec (v3.0.3 or v3.1.0)
- Smarts. Scribe can:
  - extract request parameter details from FormRequests or validation rules
  - safely call API endpoints to get sample responses
  - generate sample responses from Eloquent API Resources or Transformers
- Customizable to different levels:
  - Customize the UI by adjusting text, ordering, examples, or changing the UI itself
  - Add custom strategies to adjust how data is extracted
  - Statically define extra endpoints or information not in your codebase

> [!TIP]
> 👋 Scribe helps you generate docs automatically, but if you really want to make friendly, maintainable, and testable API docs, there are some more things you need to know.
> So I made [a course](https://shalvah.teachable.com/p/api-documentation-for-developers?utm_source=scribe-laravel&utm_medium=referral&utm_campaign=none) for you.🤗

## Documentation
Check out the documentation at [scribe.knuckles.wtf/laravel](http://scribe.knuckles.wtf/laravel).

## Contributing
Contributing is easy! See our [contribution guide](https://scribe.knuckles.wtf/laravel/contributing).
