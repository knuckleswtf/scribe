# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project aims to adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## <Version> (<Release date>)
### Fixed

### Modified

### Added

### Removed

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
