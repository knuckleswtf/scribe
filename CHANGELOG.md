# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project aims to adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## <Version> (<Release date>)
### Fixed

### Modified

### Added

### Removed

## 5.6.0 (23 November 2025)
- Add support for OpenAPI v3.1 specification ([#1040](https://github.com/knuckleswtf/scribe/pull/1040))
    - Added `openapi.version` configuration option to choose between OpenAPI 3.0.3 (default) and 3.1.0
    - OpenAPI 3.1 uses JSON Schema Draft 2020-12 compatible nullable syntax: `type: ["string", "null"]`
    - OpenAPI 3.0 continues to use `nullable: true` property
    - Fully backward compatible - defaults to 3.0.3 if not configured

## 5.5.0 (25 October 2025)
### Modified
- Change shalvah/clara constraint

### Fixed
- fix: handle rare edge case in base OpenApi spec generator after parallel merge ([#1031](https://github.com/knuckleswtf/scribe/pull/1031))

## 5.4.0 (21 October 2025)
### Fixed
- fix: arrays of objects support in OpenAPI response generation ([1021](https://github.com/knuckleswtf/scribe/pull/1021))
- fix: white space issue with headers displaying in endpoint view ([#1023](https://github.com/knuckleswtf/scribe/pull/1023))
- fix: crash when API resource __construct enforces type in ResponseFromApiResource ([#1028](https://github.com/knuckleswtf/scribe/pull/1028))

### Modified
- Replace abandoned spatie/data-transfer-object with own DTO implementation ([#1024](https://github.com/knuckleswtf/scribe/pull/1024))

### Added
- Support `deprecated:` option for `#[BodyParam]/#[QueryParam]` attributes ([#1022](https://github.com/knuckleswtf/scribe/pull/1022))
- Support for `sometimes` rule ([958](https://github.com/knuckleswtf/scribe/pull/958))
- Support for custom response Content-Types (problem+json, etc.) ([#1029](https://github.com/knuckleswtf/scribe/pull/1029))
- Support strings for the `#[Deprecated]` attribute ([#1019](https://github.com/knuckleswtf/scribe/pull/1019))

## 5.3.0 (29 July 2025)
### Added
- Support `@deprecated`/`[#Deprecated]` annotations for deprecating endpoints, along with deprecated badge in the included themes ([#994](https://github.com/knuckleswtf/scribe/pull/994))
- Add enum list to Open API spec response properties ([#902](https://github.com/knuckleswtf/scribe/pull/902))

### Fixed
- Format response codes as strings in OpenAPI spec ([80d21f1c46](https://github.com/knuckleswtf/scribe/commit/80d21f1c4678e44ba8e2e549f075e7b3bfd72fe5))
- Don't escape $baseUrl in view ([39695304c9c](https://github.com/knuckleswtf/scribe/commit/39695304c9cd75d627a4e8b59fe20b4636581066))
- Possible empty part of Route when path_param is not mandatory ([#992](https://github.com/knuckleswtf/scribe/pull/992))
- Postman collection generation failing due to invalid UTF-8 characters ([#997](https://github.com/knuckleswtf/scribe/pull/997))
- Use Recursive Spec Merge in OpenAPI SecurityGenerator & Fix OverrideGenerator base ([#1003](https://github.com/knuckleswtf/scribe/pull/1003))

### Changed
- Resolve fully qualified names ([#1008](https://github.com/knuckleswtf/scribe/pull/1008))
- Ensure Validator facade rules are detected ([#1006](https://github.com/knuckleswtf/scribe/pull/1006))
- Move intro_text directly after description in config/scribe.php for easier discovery ([#1001](https://github.com/knuckleswtf/scribe/pull/1001))

## 5.2.1 (1 May 2025)
### Added
- Fix regressions in parsing validation rules [a9e7a668d](https://github.com/knuckleswtf/scribe/commit/a9e7a668d7fa74ad8a1591e443db6600498238ef)

## 5.2.0 (17 April 2025)
### Added
- Fix breaking bugfix for validation rules (array of objects) in newer Laravel versions [03968babc9](https://github.com/knuckleswtf/scribe/commit/03968babc901d38a284d3569000205e7d38ba1e1)

### Fixed
- Avoid swallowing errors on example model instantiation (#964)[https://github.com/knuckleswtf/scribe/pull/964]

## 5.1.0 (25 February 2025)
### Added
- Support for streamed responses in response calls [790ad94e512](https://github.com/knuckleswtf/scribe/commit/790ad94e512d987feae6f0443835d8cf8de64f53)

### Fixed
- Fixed use of `URL::useOrigin` vs `URL::forceRootURL` [956e9bf418](https://github.com/knuckleswtf/scribe/commit/956e9bf418f5fc06fe70009e476b1e8524aff5b1)

## 5.0.1 (20 February 2025)
### Fixed
- Fix bug in wrongly trying to determine required fields for array of strings [#951](https://github.com/knuckleswtf/scribe/pull/951)

## 5.0.0 (19 February 2025)
See the [migration guide](https://scribe.knuckles.wtf/migrating).
