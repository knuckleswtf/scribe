# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project aims to adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## <Version> (<Release date>)
### Fixed

### Modified

### Added

### Removed

## 3.33.2 (9 July 2022)
### Fixed
- Infer URL parameter name correctly when an interface is used for binding ([#494](https://github.com/knuckleswtf/scribe/pull/494))


## 3.33.1 (8 July 2022)
### Fixed
- Don't send empty query parameter field if it's optional ([#493](https://github.com/knuckleswtf/scribe/commit/493))
- Infer URL parameter name correctly when `getRouteKeyName()` is set ([#492](https://github.com/knuckleswtf/scribe/pull/492))


## 3.33.0 (27 June 2022)
### Added
- Include description in Postman collection for formdata body parameters ([10faa500](https://github.com/knuckleswtf/scribe/commit/10faa500e36e02d4efcecf8ad5e1d91ba1c7728d))
- Support for attributes in `@apiResource` ([8b8bc6b0](https://github.com/knuckleswtf/scribe/commit/8b8bc6b04242ea5d35916db84e2a7cbe73e0cef5))


## 3.32.0 (23 June 2022)
### Modified
- Improved code blocks hiding ([#486](https://github.com/knuckleswtf/scribe/pull/486))


## 3.31.0 (16 June 2022)
### Modified
- Postman collection: replace multipart PUT/PATCH requests with POST & `_method` ([#480](https://github.com/knuckleswtf/scribe/pull/480))

### Fixed
- Fix logo image partially covered by sidebar ([#481](https://github.com/knuckleswtf/scribe/pull/481))


## 3.30.0 (11 June 2022)
### Added
- Support for more inline validator forms (`$request->validate(...)` without assignment, and `$this->validate($request, ...)`) ([29940c2e](https://github.com/knuckleswtf/scribe/commit/29940c2e05c8035a1ab85d9482c2335e1747ab41))

### Fixed
- Fix incorrect public_path check on Lumen ([64ad2f6e](https://github.com/knuckleswtf/scribe/commit/64ad2f6e059bea03e9ba5b209818e916758ca36a))

## 3.29.1 (22 May 2022)
### Fixed
- Make output path for `laravel` type configurable ([48b2b90](https://github.com/knuckleswtf/scribe/commit/48b2b90580f92dbe2fa5ebdad207fa082c875532))

## 3.29.0 (22 May 2022)
### Added
- 🎉🎉 Support multiple docs with the `--config` flag ([#472](https://github.com/knuckleswtf/scribe/pull/472), [cc6c95e](https://github.com/knuckleswtf/scribe/commit/cc6c95eed2a999a640666eab8b7dad1b417c9aca))

## 3.28.0 (14 May 2022)
### Added
- Add `--no-upgrade-check` CLI option ([6950f4b](https://github.com/knuckleswtf/scribe/commit/6950f4bfc8b270f47f0318124a5610f2abd95182))

### Modified
- [Internal] Fix Faker deprecations ([8093961](https://github.com/knuckleswtf/scribe/commit/8093961c8df008451e2edae0f97c529efb509ac9))

## 3.27.0 (30 April 2022)
### Modified
- Add `assets_directory` config option for `laravel` type ([#462](https://github.com/knuckleswtf/scribe/pull/462))

## 3.26.0 (3 April 2022)
### Modified
- Update `GroupedEndpoint` classes to be easier to extend ([#456](https://github.com/knuckleswtf/scribe/pull/456))

## 3.25.0 (21 March 2022)
### Added
- Support validation rules `accepted` and `accepted_if` ([#438](https://github.com/knuckleswtf/scribe/pull/438))

### Fixed
- fix(model factory chain): implode relation chains for bigger relations ([#447](https://github.com/knuckleswtf/scribe/pull/447))

## 3.24.1 (7 March 2022)
### Fixed
- Don't crash on auto upgrade check fail ([c4afdcd59d3fbe300679013877891a45d2e3782e](https://github.com/knuckleswtf/scribe/commit/c4afdcd59d3fbe300679013877891a45d2e3782e))

## 3.24.0 (21 February 2022)
### Added
- `Scribe::instantiateFormRequestUsing()` hook to allow customising FormRequest instantiation ([3fb9872fa6fb84b0cfc1fdb6900f4d7008b7389c](https://github.com/knuckleswtf/scribe/commit/3fb9872fa6fb84b0cfc1fdb6900f4d7008b7389c))

## 3.23.0 (31 January 2022)
### Added
- Try loading an example URL parameter from the database ([409](https://github.com/knuckleswtf/scribe/pull/409))

### Fixed
- Load `BelongsTo` relations correctly. ([#417](https://github.com/knuckleswtf/scribe/pull/417))
- Load relations correctly on factory-generated models. ([#419](https://github.com/knuckleswtf/scribe/pull/419))

## 3.22.0 (26 January 2022)
### Added
- `@apiResourceAdditional` tag for setting extra attributes on API Resources ([414](https://github.com/knuckleswtf/scribe/pull/414))

### Modified
- Print multiple fields in `required_if` ([406](https://github.com/knuckleswtf/scribe/pull/406))

### Fixed
- JS theme error ([ce94c03966ebf97a20442b2a92dda3db9c6c52d5](https://github.com/knuckleswtf/scribe/commit/ce94c03966ebf97a20442b2a92dda3db9c6c52d5))

## 3.22.0 (26 January 2022)
### Added
- `@apiResourceAdditional` tag for setting extra attributes on API Resources ([414](https://github.com/knuckleswtf/scribe/pull/414))

### Modified
- Print multiple fields in `required_if` ([406](https://github.com/knuckleswtf/scribe/pull/406))

### Fixed
- JS theme error ([ce94c03966ebf97a20442b2a92dda3db9c6c52d5](https://github.com/knuckleswtf/scribe/commit/ce94c03966ebf97a20442b2a92dda3db9c6c52d5))

## 3.21.0 (Sunday, 2 January 2022)
## Modified
- Include protocol in baseUrl for Postman collections ([391](https://github.com/knuckleswtf/scribe/pull/391))

### Fixed
- Fix bug where toggling the menu on mobile would jump to the top of the page ([400](https://github.com/knuckleswtf/scribe/pull/400))

## 3.20.0 (Tuesday, 21 December 2021)
## Added
- `custom` field for `Parameter` class ([2abd751d64a2a38648b41d2b97054a16f156a3e0](https://github.com/knuckleswtf/scribe/commit/2abd751d64a2a38648b41d2b97054a16f156a3e0))

### Fixed
- Try It Out: Use correct input names for lists ([375](https://github.com/knuckleswtf/scribe/pull/375))
- Fixed error when copying custom theme assets files ([379](https://github.com/knuckleswtf/scribe/pull/379))
- Fix sidebar navigation bug ([2abd751d64a2a38648b41d2b97054a16f156a3e0](https://github.com/knuckleswtf/scribe/commit/2abd751d64a2a38648b41d2b97054a16f156a3e0))

## 3.19.1 (Thursday, 9 December 2021)
### Fixed
- Use HTTPS for external assets so they load on file:// URLs ([388](https://github.com/knuckleswtf/scribe/pull/388))

## 3.19.0 (Sunday, 28 November 2021)
### Added
- Custom names for example languages, using the array key ([382](https://github.com/knuckleswtf/scribe/pull/382))
- Custom endpoint metadata attributes ([381](https://github.com/knuckleswtf/scribe/pull/381))

### Fixed
- Corrected paths in `afterGenerating` hook ([385](https://github.com/knuckleswtf/scribe/pull/385))
- Fix missing headings in sidebar ([376](https://github.com/knuckleswtf/scribe/pull/376))

## 3.18.0
Reverted changes in 3.17.0, which broke the display of headings in the sidebar.

## 3.17.0 (Sunday, 28 November 2021)
### Modified
- Refactored sidebar and external JS to improve usability and performance ([354](https://github.com/knuckleswtf/scribe/pull/354))

## 3.16.0 (Tuesday, 16 November 2021)
### Added
- Support for nested relations in factories ([364](https://github.com/knuckleswtf/scribe/pull/364))

### Fixed
- Try it Out: show examples for empty array ([4351be8567f98f7779422a3a5beaaf5f3ca53e00](https://github.com/knuckleswtf/scribe/commit/4351be8567f98f7779422a3a5beaaf5f3ca53e00))
- Route docs properly in Lumen ([b859f9ac7d2bac0b43b5358337565889593af6b7](https://github.com/knuckleswtf/scribe/commit/b859f9ac7d2bac0b43b5358337565889593af6b7))

## 3.15.0 (Monday, 8 November 2021)
### Added
- Try it Out: entering an auth header value will auto-set it for all endpoints ([3f9800924128536a4d8b8ea366be9573da758ad2](https://github.com/knuckleswtf/scribe/commit/3f9800924128536a4d8b8ea366be9573da758ad2))

## 3.14.1 (Tuesday, 2 November 2021)
### Fixed
- Example display in input ([43f37502a3fdccdcc7799b8160c2d4921f4d75f1](https://github.com/knuckleswtf/scribe/commit/43f37502a3fdccdcc7799b8160c2d4921f4d75f1))

## 3.14.0 (Friday, 29 October 2021)
### Added
- `beforeGroup` and `afterGroup` ([98d5be7ade2a549e72b343b7f660b412188c30c8](https://github.com/knuckleswtf/scribe/commit/98d5be7ade2a549e72b343b7f660b412188c30c8))

### Modified
- Try It Out: Remove `required` input field validation ([#3722637092d1c40060169cf22054a0145a0d2cec](https://github.com/knuckleswtf/scribe/commit/3722637092d1c40060169cf22054a0145a0d2cec))

### Fixed
- Remove invalid characters from endpoint ID ([#352](https://github.com/knuckleswtf/scribe/pull/352))

## 3.13.0 (Tuesday, 26 October 2021)
### Added
- Add `afterGenerating()` hook ([61a4821e103bad2bdd5068890c7ed2c90ba6cdee](https://github.com/knuckleswtf/scribe/commit/61a4821e103bad2bdd5068890c7ed2c90ba6cdee))

### Fixed
- Prefill examples without breaking BC ([4fb2447182998835ba1ddcc10af12dfbe3a49f4b](https://github.com/knuckleswtf/scribe/commit/4fb2447182998835ba1ddcc10af12dfbe3a49f4b)

## 3.12.1 (Monday, 25 October 2021)
### Fixed
- Fix for prefilling examples

## 3.12.0 (Sunday, 24 October 2021)
### Added
- Try It Out: Prepopulate fields with examples ([#324](https://github.com/knuckleswtf/scribe/pull/324))

### Fixed
- Display form-encoded data properly in examples ([#331](https://github.com/knuckleswtf/scribe/pull/331))
- Cast response status to int ([#346](https://github.com/knuckleswtf/scribe/pull/346))

## 3.11.1 (Thursday, 23 September 2021)
### Modified
- Infer array type name properly ([7457dccf19218a80e43e2fc7d5ec4c2c4b1816e3](https://github.com/knuckleswtf/scribe/commit/7457dccf19218a80e43e2fc7d5ec4c2c4b1816e3))

## 3.11.0 (Wednesday, 22 September 2021)
### Added
- Introduced `beforeResponseCall()` ([25cbdc193f277c70d471a92b5019156c603255b7](https://github.com/knuckleswtf/scribe/commit/25cbdc193f277c70d471a92b5019156c603255b7))

### Fixed
- Parse multiline validation comments properly  ([e3b7dbefc1cbb25ca773f7c74c84bbe8fe8740e5](https://github.com/knuckleswtf/scribe/commit/e3b7dbefc1cbb25ca773f7c74c84bbe8fe8740e5))
- Respect examples set on parent items for array/objects (closes #328)  ([12937e1ea148cb5bf162f4c8688f4c2816b679b0](https://github.com/knuckleswtf/scribe/commit/12937e1ea148cb5bf162f4c8688f4c2816b679b0))

## 3.10.3 (Monday, 20 September 2021)
### Fixed
- Ignore user-specified values in upgrader (fixes #327) ([75b592724a8639583b4d660033549c8645a61b2b](https://github.com/knuckleswtf/scribe/commit/75b592724a8639583b4d660033549c8645a61b2b))

## 3.10.2 (Friday, 10 September 2021)
### Fixed
- Shim `newLine()` method on Laravel 6 (fixes #320) ([31087fc330ebb305b163d72fc68d0603687df8d2](https://github.com/knuckleswtf/scribe/commit/31087fc330ebb305b163d72fc68d0603687df8d2))

## 3.10.1 (Thursday, 9 September 2021)
### Fixed
- Try It Out: Fixed default CSRF URL for Laravel Sanctum ([#319](https://github.com/knuckleswtf/scribe/pull/319))

## 3.10.0 (Thursday, 9 September 2021)
### Added
- Scribe will now check for new config items automatically on each run ([3d451f556da08e9f236ca45e373905e3dd8f76e7](https://github.com/knuckleswtf/scribe/commit/3d451f556da08e9f236ca45e373905e3dd8f76e7))
- Try It Out: Support CSRF tokens for Laravel Sanctum ([#317](https://github.com/knuckleswtf/scribe/pull/317))

### Modified
- Throw error on missing response file ([123e64b8203c55e359c76cd477dacb3e324846c4](https://github.com/knuckleswtf/scribe/commit/123e64b8203c55e359c76cd477dacb3e324846c4))

### Fixed
- Try It Out: Only set checked radio buttons in query ([#312](https://github.com/knuckleswtf/scribe/pull/312))
- Try It Out: Format booleans properly in query ([#313](https://github.com/knuckleswtf/scribe/pull/313))
- Support body params in GET requests🤷‍♀️ ([#318](https://github.com/knuckleswtf/scribe/pull/318))

## 3.9.1 (Thursday, 26 August 2021)
### Modified
- Unescape slashes in JSON ([#304](https://github.com/knuckleswtf/scribe/pull/304))

## 3.9.0 (Saturday, 21 August 2021)
### Modified
- Change `digits_between` generation to support longer numbers ([#299](https://github.com/knuckleswtf/scribe/pull/299))
- OAS: Include group descriptions as tags ([84e2c95ce3e086a9cfe41f42ae71852debe91504](https://github.com/knuckleswtf/scribe/commit/84e2c95ce3e086a9cfe41f42ae71852debe91504))
- OAS/Postman: Dont include response status code in description ([a81d8785ed3f928f5c6a4dccc7f65968ede4987f](https://github.com/knuckleswtf/scribe/commit/a81d8785ed3f928f5c6a4dccc7f65968ede4987f))

## 3.8.0 (Wednesday, 28 July 2021)
### Modified
- Fallback generated validation rules examples to null ([204d3dbab8665c9478f2808cf0b2ac2329c608ea](https://github.com/knuckleswtf/scribe/commit/204d3dbab8665c9478f2808cf0b2ac2329c608ea))
- Extract Upgrader to separate package ([d17cd655b4f02e9701e47a4d328dfebfc1dd4610](https://github.com/knuckleswtf/scribe/commit/d17cd655b4f02e9701e47a4d328dfebfc1dd4610)))

### Fixed
- Better error handling (factories, validation rule parsing) ([#287](https://github.com/knuckleswtf/scribe/pull/287), [#288](https://github.com/knuckleswtf/scribe/pull/288), [a768c4733a3d397efdbac83067032a68abd66838](https://github.com/knuckleswtf/scribe/commit/a768c4733a3d397efdbac83067032a68abd66838), [0c4da381da7505afec6dd8e8ed082dcc4e1b7a3d](https://github.com/knuckleswtf/scribe/commit/0c4da381da7505afec6dd8e8ed082dcc4e1b7a3d))

## 3.7.0 (Thursday, 22 July 2021)
### Added
- Allow installation of spatie/dto 3 [#285]((https://github.com/knuckleswtf/scribe/pull/285))

## 3.6.3 (Tuesday, 20 July 2021)
### Fixed
- Stop Validator::make parsing from crashing unnecessarily [#281]((https://github.com/knuckleswtf/scribe/pull/281))

## 3.6.2 (Saturday, 17 July 2021)
### Fixed
- Encode Postman collection items correctly (fixes #278)([87b99bc717f541d6a4d2925fc7bc544958451d12](https://github.com/knuckleswtf/scribe/commit/87b99bc717f541d6a4d2925fc7bc544958451d12),

## 3.6.1 (Friday, 16 July 2021)
### Fixed
- Use correct asset path (fixes #274)([00b77e4b144e13b579e5ad820ab79a1b42eac756](https://github.com/knuckleswtf/scribe/commit/00b77e4b144e13b579e5ad820ab79a1b42eac756),

## 3.6.0 (Tuesday, 13 July 2021)
### Fixed
- Sort group filenames numerically (fixes #273)([c77fed23f04ab1f13bb06bf5b099227ced46dbdc](https://github.com/knuckleswtf/scribe/commit/c77fed23f04ab1f13bb06bf5b099227ced46dbdc),

## 3.5.2 (Monday, 12 July 2021)
### Modified
- Internal change: refactor RouteDocBlocker (https://github.com/knuckleswtf/scribe/pull/272)

## 3.5.1 (Tuesday, 6 July 2021)
### Fixed
- Try It Out: Turn off autocomplete; make sure it works for array body; improve UI spacing ([579f672b57ad0417a5563aee1621b84c3b4ff1f2](https://github.com/knuckleswtf/scribe/commit/579f672b57ad0417a5563aee1621b84c3b4ff1f2), [2af8d8eacd661e0601b2d6f4dbc1766bf75e702a](https://github.com/knuckleswtf/scribe/commit/2af8d8eacd661e0601b2d6f4dbc1766bf75e702a))

## 3.5.0 (Monday, 5 July 2021)
### Modified
- Get URL parameter name from field bindings (https://github.com/knuckleswtf/scribe/commit/ce6be7ca68ed0e682258eca5bbeb2f7d84774714)

## 3.4.3 (Monday, 5 July 2021)
### Modified
- Internal change: switch to using strategies to get "grouped endpoints" (https://github.com/knuckleswtf/scribe/pull/263)

## 3.4.2 (Monday, 5 July 2021)
### Modified
- Only use model key type for URL param type if it's the same as the route key name (https://github.com/knuckleswtf/scribe/pull/265)
### Fixed
- Merge user-defined endpoints correctly. (https://github.com/knuckleswtf/scribe/commit/b7f8539b1bd5cd4d97496fa93803a3d7894889f6)

## 3.4.1 (Friday, 2 July 2021)
### Fixed
- Set nested file fields properly in Postman. (https://github.com/knuckleswtf/scribe/commit/39d53eac5db30c1d4b6b16cff836c1d3a3898f89)

## 3.4.0 (Thursday, 1 July 2021)
### Added
- Support better examples based on the `where` clause in routes. (https://github.com/knuckleswtf/scribe/commit/cf2b53c16d405e655886b6225e9ebbf29a6621a8)

### Fixed
- Correctly generate params and description for explicit field bindings in routes (https://github.com/knuckleswtf/scribe/commit/b0b89195e6ce0333cf07573462fa9ec083d04f4d)
- Fix content-type header not showing (https://github.com/knuckleswtf/scribe/commit/d5a7b6d8be9f257df3146cd6026729232aa63f1e)
- Try It Out: Spoof HTTP method for PUT/PATCH requests (https://github.com/knuckleswtf/scribe/pull/257)

## 3.3.2 (Wednesday, 30 June 2021)
### Fixed
- Try It Out: Add cancellable requests (https://github.com/knuckleswtf/scribe/commit/816e6fbd37ead033ca58bad048f38455622cb0b9)
- Try It Out: Restore sample request/response after cancel (https://github.com/knuckleswtf/scribe/commit/25aaabbea3a4b0482e510932cc095c8ce9495427)
- Try It Out: set FormData content-type properly (https://github.com/knuckleswtf/scribe/pull/249)

## 3.3.1 (Tuesday, 29 June 2021)
### Fixed
- Set nested file parameters properly in examples (https://github.com/knuckleswtf/scribe/commit/6354b5592d1e042fe627894156ff17a684fce667)

## 3.3.0 (Friday, 25 June 2021)
### Fixed
- Don't depend on unavailable view service provider (https://github.com/knuckleswtf/scribe/pull/254)
- Delete older versioned assets (https://github.com/knuckleswtf/scribe/commit/b02af7e21f89406ec33be2e6ca1c206df3733b1b)
- Generate proper OAS types for files and request body arrays (https://github.com/knuckleswtf/scribe/commit/8b51d839d213a1abe110e439281273b33facb344)

### Modified
- Exclude more specific headers from sample responses (https://github.com/knuckleswtf/scribe/commit/5583b725d714090a198cf906115860626f537c09)

## 3.2.0 (Thursday, 24 June 2021)
### Added
- Support simple key-value for response headers (https://github.com/knuckleswtf/scribe/commit/20afb7e10ca8c5616fd5b9ce1b5333739fdd2348)

### Modified
- Throw helpful error if factory instantiation errors (https://github.com/knuckleswtf/scribe/commit/5eb0d72f9b2898702c14d28582195722c27f00d0)

## 3.1.0 (Friday, 18 June 2021)
### Added
- Support nullable and union responseField types (https://github.com/knuckleswtf/scribe/commit/2912ac2344b37e30599aa1004c90e146a6f76aaa)

### Fixed
- Fix OAS generation (https://github.com/knuckleswtf/scribe/commit/a5f51714eafe9b281cc1bbeb1b3186c03f4e3e61)

## 3.0.3 (Friday, 18 June 2021)
### Fixed
- Try It Out: Send body params in the right format (https://github.com/knuckleswtf/scribe/pull/245)

## 3.0.2 (Friday, 11 June 2021)
### Fixed
- Use regular relative paths for assets if not using default static output path (https://github.com/knuckleswtf/scribe/commit/05aaba1877e9ca3dbf7a130dcbd12a2ba9438136)

## 3.0.1 (Tuesday, 8 June 2021)
### Fixed
- Properly cast status codes for API Resource and Transformer responses (https://github.com/knuckleswtf/scribe/pull/235)
- Don't crash on unrecognized validation rule formats (https://github.com/knuckleswtf/scribe/commit/c86ea65e903a013f33dc660269d7fff5e2376490)

## 3.0.0 (Monday, 7 June 2021)
[Release announcement](https://scribe.knuckles.wtf/blog/2021/06/08/laravel-v3)
