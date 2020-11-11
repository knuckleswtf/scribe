# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project aims to adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## <Version> (<Release date>)
### Fixes

### Changes

### Additions

### Removals

## 2.2.0 (Wednesday, 11 November 2020)
Scribe is now **much** faster! In my tests, processing an application with about a dozen routes went from 4 minutes to 3 seconds. Fixed a pain point by using amphp/parallel-functions to start/stop database transactions for all connections in parallel. (https://github.com/knuckleswtf/scribe/commit/20980712e5ed46c059d1d4a2d67aee3051ef84c4)

## 2.1.0 (Tuesday, 10 November 2020)
### Changes
- tryitout.js will now include the current Scribe version number in its filename, for automatic cache busting (https://github.com/knuckleswtf/scribe/commit/69a643c47ad3756ba0a78e322b58df4955508f76)

### Fixes
- Fixed bug where query param values that were objects were set as [object Object] in Try It Out url (https://github.com/knuckleswtf/scribe/commit/a507945c4da84f96feefd42967fb9c4d3b7f68e8)
- Renamed internal property 'fields' to '__fields' to prevent possible clashes with a user-supplied field called 'fields'. (https://github.com/knuckleswtf/scribe/commit/72d530b18a8851412d771177c998779557ca2a68)
- Don't crash when printing empty arrays or object for query params (https://github.com/knuckleswtf/scribe/commit/eaa820fe7c2f605d5fb8f9914f7a9e7b0e19efdd)


## 2.0.3 (Monday, 2 November 2020)
### Changes
- Switch to fakerphp/faker (https://github.com/knuckleswtf/scribe/commit/bad1b1fe98ff50253d88cb2fece943e8f5e600f4)

## 2.0.2 (Friday, 30 October 2020)
### Fixes
- Properly exclude package's routes when generating (https://github.com/knuckleswtf/scribe/commit/a57a7fe232e44894b5fe542463ff27a1cc9e6405)

## 2.0.1 (Monday, 26 October 2020)
### Fixes
- Added a missing colon in Try It Out buttons' CSS (https://github.com/knuckleswtf/scribe/pull/123)

## 2.0.0 (Saturday, 24 October 2020)
See https://scribe.readthedocs.io/en/latest/migrating-v2.html
