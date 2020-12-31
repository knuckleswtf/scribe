# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project aims to adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.9.1 (Monday, 19 October, 2020)
### Fixes
- Set correct base URL protocol for Postman collection (https://github.com/knuckleswtf/scribe/pull/114)

## 1.9.0 (Thursday, 1 October, 2020)
### Changes
- Start database transaction much earlier and close it much later for ApiResource and Transformer strategies. Also set current route properly when resolving (https://github.com/knuckleswtf/scribe/pull/104)

## 1.8.3 (Thursday, 17 September, 2020)
### Fixes
- Reverts 1.8.2 as it broke a few things (https://github.com/knuckleswtf/scribe/commit/5a2217513945bcb92ca26e463f7717c0efb99ac1)

## 1.8.2 (Thursday, 17 September, 2020)
### Fixes
- Regex in URL parameters shouldn't fuck the generated examples up anymore (https://github.com/knuckleswtf/scribe/commit/cf44fbbcd3643086859ba724f6e4d4315941b471)

## 1.8.1 (Thursday, 17 September, 2020)
### Fixes
- Printing form data body parameters no longer throws an error with nested arrays or objects (https://github.com/knuckleswtf/scribe/commit/33a58a18a8712d20ab90c06bf0bb087f6fb5869a)

## 1.8.0 (Tuesday, 15 September, 2020)
- Lumen 8 support
- Fixed Laravel 8 + legacy factories support
- Fixed the OpenAPI route for `laravel` type docs (https://github.com/knuckleswtf/scribe/pull/96)

## 1.7.0 (Saturday, 12 September, 2020)
Laravel 8 support

## 1.6.0 (Tuesday, 8 September, 2020)
### Additions
- New `description` field, where you can add a description of your API. This field will be used as the `info.description` field in the Postman collection and OpenAPI spec, and as the first paragraph under the "Introduction" section on the generated webpage, before the `intro_text`. (https://github.com/knuckleswtf/scribe/pull/90/commits/dc356f3f2b13732d567dbee88dad07fc0441f40e)
- Postman collection `auth` information is now populated from Scribe's auth info. (https://github.com/knuckleswtf/scribe/pull/90/commits/33c00a7a0b915e9cbedccdb13d7cb4fcc3c76dc5)

#### Changes
- Postman collection schema version has been updated to 2.1.0. (https://github.com/knuckleswtf/scribe/pull/90/commits/cc7e4cbfae08999f555f7a105ab3c2993fdbb2c1)
- The `float` type is now `number`: Previously, `float` was used in the generated webpage as the default type for non-integer numbers, in alignment with PHP's type system. We've changed this to `number`, to align with standards like OpenAPI and JSON types. You can still use `float` in your annotations, but it will be rendered as `number`. (https://github.com/knuckleswtf/scribe/pull/90/commits/66993d2d2c7a1a57806960dd4cc428068fb0f589)
- [Internal] Reworked PostmanCollectionWriter API: The `PostmanCollectionWriter` has been reworked to be more in line with the `OpenAPISpecWriter`. See the class for details.

#### Deprecations
- Deprecated `postman.auth` in favour of `postman.overrides`: It didn't make sense to have two ways of setting Postman-specific auth information (`postman.auth` and `postman.overrides`). Will be removed in v2.
- Deprecated Postman-specific `postman.description` in favour of `description`. Will be removed in v2.

## 1.5.0 (Thursday, 3 September, 2020)
### Additions
- Added `auth.placeholder` value so you can customise the placeholder API users will see in the example requests. (https://github.com/knuckleswtf/scribe/pull/84)
- Added `Generator::getRouteBeingProcessed()` method that returns the current route. (https://github.com/knuckleswtf/scribe/pull/79)

### Fixes
- Response calls should now properly start/stop database transactions for all connections (https://github.com/knuckleswtf/scribe/pull/89)
- Generated OpenAPI spec should now correctly use `"apiKey"` as the value of `type` in the security scheme (https://github.com/knuckleswtf/scribe/commit/896c2132ad3a2cfe89e0fba524aa489661823a11)

## 1.4.1 (Monday, 24 August, 2020)
### Fixed
- Set proper defaults for Postman overrides, handle empt array examples in OAS (https://github.com/knuckleswtf/scribe/pull/77)

## 1.4.0 (Sunday, 23 August, 2020)
### Added
- Support for resourceKey in Transformers(https://github.com/knuckleswtf/scribe/pull/73)
- OpenAPI (Swagger) spec generation (https://github.com/knuckleswtf/scribe/pull/75)
- Ability to override specific fields in generated Postman collection and OpenAPI spec (https://github.com/knuckleswtf/scribe/pull/76)

## 1.3.0 (Friday, 17 July, 2020)
### Fixed
- Provided option to bypass database drivers that don't support transactions (https://github.com/knuckleswtf/scribe/pull/55, https://github.com/knuckleswtf/scribe/pull/57)

## 1.2.0 (Sunday, 5 July, 2020)
### Added
- Include raw request URL in Postman collection (https://github.com/knuckleswtf/scribe/pull/43)

## 1.1.1 (Friday, 3 July, 2020)
### Fixed
- Support HEAD-only endpoints (https://github.com/knuckleswtf/scribe/pull/54)

## 1.1.0 (Monday, 1 June, 2020)
### Modified
- Added ability to set postman base_url independently (https://github.com/knuckleswtf/scribe/pull/31)

## 1.0.3 (Monday, 25 May, 2020)
### Modified
- Updated dependencies (https://github.com/knuckleswtf/scribe/pull/26)

## 1.0.2 (Sunday, 24 May, 2020)
### Fixed
- Set badge colour for OPTIONS method too. (https://github.com/knuckleswtf/scribe/commit/ccce82cf75502493d776a4ec2378de9cda1659f3)

## 1.0.1 (Saturday, 23 May, 2020)
### Fixed
- Pinned erusev/parsedown dependency (from mnapoli/front-yaml) to ^1.7.4 to fix incompatibilities. (https://github.com/knuckleswtf/scribe/commit/fd623238852dca0e77aa88e86220830d71a460d4)

## 1.0.0 (Saturday, 23 May, 2020)
See [what's new](https://scribe.readthedocs.io/en/latest/whats-new.html) and the [migration guide](https://scribe.readthedocs.io/en/latest/migrating.html).

### Fixed
- Access correct config key for docs URL in `laravel` type. (https://github.com/knuckleswtf/scribe/pull/23)
- Add GetFromHeaderTag strategy to the default config. (https://github.com/knuckleswtf/scribe/commit/72902cbba9d10303cf2580cb4e96107317734fc0)
- Properly handle from-data in Postman collection (https://github.com/knuckleswtf/scribe/pull/13)

## 1.0.0-beta4 (Thursday, 21 May, 2020)
### Fixed
- Update deps to fix installation problems

## 1.0.0-beta3 (Tuesday, 19 May, 2020)
### Fixed
- Create missing folders when generating for `laravel` type (https://github.com/knuckleswtf/scribe/commit/1289d399e98c24f6b0f9cfe04a8defd6a47ac000)

## 1.0.0-beta2 (Tuesday, 19 May, 2020)
### Added
- Support for multiline descriptions and examples in @xParam tags (https://github.com/knuckleswtf/scribe/commit/660ebcadc88be46b8c3f35b769ce4c320219f201)

### Changed
- Show 'Empty response' for 204 responses (https://github.com/knuckleswtf/scribe/commit/f63536c76dbd286e6e3c9b63b1a4172bafa5a86f)

### Fixed
- Allow Markdown to work in parameter descriptions from annotations (https://github.com/knuckleswtf/scribe/commit/72c54dc9bfd8e9f3b79c88f9e25161629d066ffd)
- Properly fetch pagination type for API Resources (https://github.com/knuckleswtf/scribe/commit/d442641b4be197838adf4bd01e0c0ebdbfb49af9)
- Properly parse examples for array parameters in @xParam tags (https://github.com/knuckleswtf/scribe/commit/b89c35755fec3006975221041e61e7107b4346bb)
- Set paths properly when generating for `laravel` type (https://github.com/knuckleswtf/scribe/commit/ee7efd4efb1a55b9b245277a9fef53cc33d04130)
