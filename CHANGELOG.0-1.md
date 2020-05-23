# Changelog (0.0.1 - 1.0.0)
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project aims to adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased
### Added

### Changed

### Fixed

### Removed

## 1.0.0 (Saturday, 23 May, 2020)
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

### Removed





