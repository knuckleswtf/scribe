# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project aims to adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## <Version> (<Release date>)
### Fixes

### Changes

### Additions

### Removals

## 3.3.1 (Friday, 25 June 2021)
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