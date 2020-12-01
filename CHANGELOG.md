# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project aims to adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## <Version> (<Release date>)
### Fixes

### Changes

### Additions

### Removals

## 2.4.2 (Tuesday, 1 December 2020)
### Fixes
- Specify the `local` disk when fetching `openapi.yaml` file. (https://github.com/knuckleswtf/scribe/pull/150)

## 2.4.1 (Monday, 30 November 2020)
### Changes
- Scribe will no longer throw an error if you describe an object subfield without adding the parent. We'll add it automatically for you (but you really should). (https://github.com/knuckleswtf/scribe/commit/77d516cbdbc6aa66466a640e20092d6e7a8df456)
- Changed the auto-generated descriptions when using validation rules to work without "The". (https://github.com/knuckleswtf/scribe/commit/0b6e609dd067b43301e709e54c339c64519725dd)

## 2.4.0 (Monday, 30 November 2020)
Turns out 2.2.0 wasn't really working.😕 This version fixes that, but introduces a behaviour change, so it may be a breaking change.

This version introduces the config key `database_connections_to_transact` (and deprecates `continue_without_database_transactions`). To enable database transactions for a connection, add it to `database_connections_to_transact`; To skip it, remove it. By default `database_connections_to_transact` is set to `config('database.default')`, so most people shouldn't need to do anything.

Commit: https://github.com/knuckleswtf/scribe/commit/5c51486a138b831aa9b6bad549dace80bfcc3e5d

## 2.3.0 (Sunday, 15 November 2020)
### Changes
- Create and bind the current request globally in ApiResource strategy so accessing `request()` works (https://github.com/knuckleswtf/scribe/commit/cb3fa1fa4c09447c65650a4ad7dff9e969f344c8)
- Bind the form request in route in FormRequest strategy (https://github.com/knuckleswtf/scribe/commit/de67b760daf149fbfbf379531567eb89ea6ae198)

## 2.2.1 (Saturday, 14 November 2020)
### Fixes
- Fixed errors with handling arrays of files (https://github.com/knuckleswtf/scribe/commit/b57eae26d048fb37833d6b47e98df47b0c5cf7b6)
- Fixed errors with handling nested objects in arrays of objects (https://github.com/knuckleswtf/scribe/commit/13b15797e07ee2f7c3e558fc11ca6a4bddf4f264)
- Fixed a little problem with escaped newlines in auth text (https://github.com/knuckleswtf/scribe/commit/a27d8c7aa9079b5f6b6155220639926aac2466f2)


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
