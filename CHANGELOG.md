# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project aims to adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## <Version> (<Release date>)
### Fixed

### Modified

### Added

### Removed

# 4.25.0 (30 September 2023)
## Added
- Support wildcards in `groups.order` (top-level only) ([#723](https://github.com/knuckleswtf/scribe/pull/731))

# 4.24.0 (16 September 2023)
## Added
- Support dependency injection in FormRequests ([84078358ce](https://github.com/knuckleswtf/scribe/commit/84078358ce32ff0656a9ab03f062e952f721f1a2))
- Include `auth.extra_info` in OpenAPI security scheme ([#727](https://github.com/knuckleswtf/scribe/pull/727))
- Support dynamic base URL ([#723](https://github.com/knuckleswtf/scribe/pull/723))

## Fixed
- Generate proper sample for array of objects ([#720](https://github.com/knuckleswtf/scribe/pull/720))

# 4.23.1 (25 August 2023)
## Fixed
- Break in attributes due to enum support ([4c49e81e0](https://github.com/knuckleswtf/scribe/commit/4c49e81e0a6f4a257c3945a139b9a3bf35d85b2b))

# 4.23.0 (24 August 2023)
## Added
- Support for enums: you can now specify the allowed values of a parameter ([#713](https://github.com/knuckleswtf/scribe/pull/713))

## Modified
- Exclude Authorization header from generated OpenAPI spec, per spec ([#714](https://github.com/knuckleswtf/scribe/pull/714))

## Fixed
- Improve endpointId generation ([#700](https://github.com/knuckleswtf/scribe/pull/700))
- Improve empty checks in OpenAPI spec generation ([#712](https://github.com/knuckleswtf/scribe/pull/712))
- Don't export auth.use_value to Postman ([6a9d51b3a2](https://github.com/knuckleswtf/scribe/commit/6a9d51b3a215a89e8b8af47f796ffaa10993c171))

# 4.22.0 (1 July 2023)
## Added
- Make included package attributes extensible ([#680](https://github.com/knuckleswtf/scribe/pull/680))

## Fixed
- Ensure example code supports multipart/form-data without files ([#685](https://github.com/knuckleswtf/scribe/pull/685))
- Fix path traversal on Laravel 10 ([#686](https://github.com/knuckleswtf/scribe/pull/686))
- Properly support floats in docs UI ([#693](https://github.com/knuckleswtf/scribe/pull/693))
- Properly normalise URLs with multi-word Eloquent parameters ([#670](https://github.com/knuckleswtf/scribe/pull/670))
- Also show empty array query parameters in Postman ([#691](https://github.com/knuckleswtf/scribe/pull/691))


# 4.21.2 (5 June 2023)
## Fixed
- Typehint interface in CustomTranslationsLoader for maximum compatibility ([#679](https://github.com/knuckleswtf/scribe/pull/679))


# 4.21.1 (3 June 2023)
## Fixed
- Load translations on demand to get around false negative for `runningInConsole()` ([963340f2](https://github.com/knuckleswtf/scribe/commit/963340f2bea654bb267286f807bcdf15b699d06e))
- Correctly set pagination data on collections in `#[ResponseFromApiResource]` ([d53776bee](https://github.com/knuckleswtf/scribe/commit/d53776bee25daff8f0070e49a32b127b4de21c9c))

# 4.21.0 (29 May 2023)
## Added
- API resources: Infer model name from `@mixin` ([f0ed9565](https://github.com/knuckleswtf/scribe/commit/f0ed95653b401b188e76e6ba9057406710f2cc2b))
- New translation layer ([#673](https://github.com/knuckleswtf/scribe/pull/673))
  - This fixes the problems with the recently introduced localization feature, by switching to a custom translation system. Users should delete the `en.json` file, if they had previously published it. See [the docs](https://scribe.knuckles.wtf/laravel/advanced/localization) for details.


# 4.20.0 (28 May 2023)
## Modified
- Support Laravel 10's optional `rules()` in Form Requests ([#664](https://github.com/knuckleswtf/scribe/pull/664))
- Allow `@apiResource` without `@apiResourceModel` ([#662](https://github.com/knuckleswtf/scribe/pull/662))

## Fixed
- Don't autoload in `class_exists()` check ([#659](https://github.com/knuckleswtf/scribe/pull/659))
- Don't override description in enum rules ([#667](https://github.com/knuckleswtf/scribe/pull/667))

# 4.19.1 (5 May 2023)
## Fixed
- Fix translations when locale is not EN and no strings are defined ([8d2be2c6](https://github.com/knuckleswtf/scribe/commit/8d2be2c681f71ed31c6be5043d6b1a0761f0235e))

# 4.19.0 (22 April 2023)
## Added
- Internationalization / Localization - You can now translate text in the Scribe-generated docs UI into your language, or just change the warning. To see the available strings to customise, run `php artisan vendor:publish --tag=scribe-translations`. [#647](https://github.com/knuckleswtf/scribe/pull/647)

## Fixed
- (In the default theme) Menu items now open when you scroll down. [#644](https://github.com/knuckleswtf/scribe/pull/644)

# 4.18.0 (8 April 2023)
## Fixed
- Upgrader: Ignore `examples.models_source` ([#636](https://github.com/knuckleswtf/scribe/pull/636))
- OpenAPI spec: Don't include forbidden headers (fixes #625) ([56d589a48](https://github.com/knuckleswtf/scribe/commit/56d589a484263119bebbb4ca1a26f60fa10ea87f))
- Correctly support No-example in inline validators  (fixes #637) ([5fbe5fcab](https://github.com/knuckleswtf/scribe/commit/5fbe5fcab009139809c8a3e316ca753b23eb18ce))
- FormRequest: Don't emit warning message if subfields are present in extra data (fixes #643) ([112bba0ec](https://github.com/knuckleswtf/scribe/commit/112bba0ec883b59c4879682670823bc7c7a5ca63))

## Modified
- Remove`auth description randomisation ([#639](https://github.com/knuckleswtf/scribe/pull/639))

# 4.17.0 (19 March 2023)
## Added
- Proper support for invokable rules and Laravel 10 validation rules changes ([ea3418](https://github.com/knuckleswtf/scribe/commit/ea341869b4062c9303507cae06fe1da1e12d7ccc))

## Modified
- Experimental: don't URL-encode Postman query parameters (closes #442) ([df4d86fa1](https://github.com/knuckleswtf/scribe/commit/df4d86fa1df06e9d2a2a294d39ee258ac94f02de))
- Replace ":attribute" at the start of validation messages (closes #633) ([ea122486d](https://github.com/knuckleswtf/scribe/commit/ea122486daa4168e612a5c40f8d385bb38fb8ae7))

# 4.16.1 (19 March 2023)
## Fixed
- Fix sorting of responses by status code ([#623](https://github.com/knuckleswtf/scribe/pull/623))
- Skip upgrade check if user hasn't published config yet ([#628](https://github.com/knuckleswtf/scribe/pull/628))
- Upgrader: Ignore `examples.models_source` ([#631](https://github.com/knuckleswtf/scribe/pull/631))
- More robust replacement of `:attribute` in validation rule descriptions (closes #633)
- Remove invalid JS file request (closes #634)

# 4.16.0 (16 February 2023)
## Added
- Support for Laravel enum validation rule in inline validators ([#616](https://github.com/knuckleswtf/scribe/pull/616))


# 4.15.0 (14 February 2023)
## Added
- Support for Laravel enum validation rule ([#614](https://github.com/knuckleswtf/scribe/pull/614))


# 4.14.0 (07 February 2023)
## Added
- Support for Laravel 10 ([#610](https://github.com/knuckleswtf/scribe/pull/610))
- Support extracting docs from custom validation rules ([#611](https://github.com/knuckleswtf/scribe/pull/611))


# 4.13.0 (22 January 2023)
## Added
- Support for Laravel Actions package ([#606](https://github.com/knuckleswtf/scribe/pull/606))
- Support nested query parameters in example requests - Bash ([#603](https://github.com/knuckleswtf/scribe/pull/605))


# 4.12.0 (15 January 2023)
## Added
- Allow `Endpoint` attribute to be used at the class level ([#602](https://github.com/knuckleswtf/scribe/pull/602))
- Support nested query parameters in example requests ([#603](https://github.com/knuckleswtf/scribe/pull/603))


# 4.11.0 (8 January 2023)
## Added
- Pass `$default` parameter to URL normalizer callback ([8fe91d86](https://github.com/knuckleswtf/scribe/commit/8fe91d86e63e227873d7d37ad1677f622d9b7ef8))
- OpenAPI spec: set `properties` of objects nested in arrays ([#600](https://github.com/knuckleswtf/scribe/pull/600))


# 4.10.1 (14 December 2022)
## Fixed
- Set HTTP method correctly for FormRequests (fixes #532, #585). ([e8098714](https://github.com/knuckleswtf/scribe/commit/e80987145f50719156d1497a74f68139e9602593))

# 4.10.0 (14 December 2022)
## Added
- `bootstrap` hook ([#576](https://github.com/knuckleswtf/scribe/pull/576))
- Support PHP 8 attributes in FormRequests. ([e8098714](https://github.com/knuckleswtf/scribe/commit/e80987145f50719156d1497a74f68139e9602593))

## Modified
- Generate properties for nested objects in OpenAPI spec ([#587](https://github.com/knuckleswtf/scribe/pull/587))

# 4.9.0 (5 December 2022)
## Modified
- `default` theme: Show nested fields short names in UI, since we now indent. ([dbe8492a](https://github.com/knuckleswtf/scribe/commit/dbe8492a6915698b0801b44712441e2a58e0614a))

## Fixed
- Don't error when nesting params for response fields (closes #578) ([42df1b15](https://github.com/knuckleswtf/scribe/commit/42df1b1597d36e4aeae62320f1c33fe5a4793cc5))


# 4.8.0 (2 December 2022)
## Added
- Support nested `MorphToMany` with pivot values ([#575](https://github.com/knuckleswtf/scribe/pull/575))

## Fixed
- Remove array setter when default param type to object (closes #573) ([f0a3205](https://github.com/knuckleswtf/scribe/commit/f0a320584713e9e01332d1294dc5d08965cfeea4))


# 4.7.1 (28 November 2022)
## Fixed
- Use correct URL in response calls ([ebadfcdc](https://github.com/knuckleswtf/scribe/commit/ebadfcdcaf6eac2a36f7c080570c12a2b2fffbd6))

# 4.7.0 (28 November 2022)
## Added
- `scribe:config:diff` command for easier debugging

## Modified
- Don't escape slashes in response content ([fdb8f4e5](https://github.com/knuckleswtf/scribe/commit/fdb8f4e5cab6e9b16339a5af1cb698fbcf77dafa))

## Fixed
- Fix default theme CSS ([#571](https://github.com/knuckleswtf/scribe/pull/571))

# 4.6.1 (25 November 2022)
## Fixed
- Fix content overflow (closes #567) ([1fad3eb0](https://github.com/knuckleswtf/scribe/commit/1fad3eb021e3fd763485e5cf4c9d9ce495e9dd4a))

# 4.6.0 (18 November 2022)
## Modified
- Styling improvements for the default theme; also show example with parameter description. ([e9bd84fb](https://github.com/knuckleswtf/scribe/commit/e9bd84fb7d1ad3330506ff045380a362bfaa4d99))
- Description generation: pluralize/singularize values from Laravel's validator. ([0b9473b5](https://github.com/knuckleswtf/scribe/commit/0b9473b5cd8046df9fb230b36fff61b3159e9fb2))

## Fixed
- Don't include status code in description (closes #561) ([8a90c2d1](https://github.com/knuckleswtf/scribe/commit/8a90c2d1ea808a39e570778e8717604c07d8326f))
- Remove mistaken example check (Fix #557) ([ad4f808](https://github.com/knuckleswtf/scribe/commit/ad4f8089692d75fc4870f37bbd96ba1df9f34dea))

# 4.5.0 (16 November 2022)
## Modified
- Smarter example generation; Scribe now uses the parameter name as an added hint. ([46e3bbc](https://github.com/knuckleswtf/scribe/commit/46e3bbc2e007566df1bbef8c32b940bb1f4c0f58))

# 4.4.0 (16 November 2022)
- Fixes and improvements for the `default` theme

# 4.3.0 (15 November 2022)
### Added
- New theme (beta)! Try it out by setting `theme` in your config to `elements`. ([#559](https://github.com/knuckleswtf/scribe/pull/559))


# 4.2.2 (10 November 2022)
### Fixed
- Support #[ResponseField] on API resources ([66492aa](https://github.com/knuckleswtf/scribe/commit/66492aabdddb9481f0b74adca40fc6ce9a015253))

# 4.2.1 (9 November 2022)
### Fixed
- Fix display of headings when append file has a H1 ([4924499](https://github.com/knuckleswtf/scribe/commit/4924499dc4411aa656e0e41dac7a317ab5fba94c))

# 4.2.0 (8 November 2022)
### Added
- Allow users customize endpoint URL normalization ([fe70df9e](https://github.com/knuckleswtf/scribe/commit/fe70df9ef07c9f1a21bdc4f06c59435ab24b2141))
- Set operationId on endpoints in OpenAPI spec ([69aeec6](https://github.com/knuckleswtf/scribe/commit/69aeec6fe37d0946e104d676455aa91f32856599))

### Fixed
- Fixed bug in extracting URL "thing" ([#548](https://github.com/knuckleswtf/scribe/pull/548/))
- Fix bug in normalizing URL ([d0e7e3](https://github.com/knuckleswtf/scribe/commit/d0e7e3a4b26031bfa24fc71cf88cacf2da61f921/))

# 4.1.0 (15 October 2022)
### Added
- Set bearer token properly in Postman Collection ([#529](https://github.com/knuckleswtf/scribe/pull/529/))
- Customizable "Last updated at" label ([44996fe](https://github.com/knuckleswtf/scribe/commit/44996fe6f09b42648da19df97dd444d1aac8b003))
- Turn subgroups into folders in Postman collection ([3152793](https://github.com/knuckleswtf/scribe/commit/3152793064afdf26a5de2c310bac73acc6581c48))

# 4.0.0 (10 September 2022)
### Removed
- [Breaking Change] Sorting groups or endpoints via editing/renaming the Camel files is no longer supported. Use the `groups.order` config item instead. 

### Added
- Support for specifying groups and endpoints order in config file ([29ddcfc](https://github.com/knuckleswtf/scribe/commit/29ddcfcf284a06da0ae6cb399d09ee5cf1f9ffa7))
- Support for specifying example model sources ([39ff208](https://github.com/knuckleswtf/scribe/commit/39ff208085d68eed4c459768ac5a1120934f021a))
- Support for subgroups ([7cf07738](https://github.com/knuckleswtf/scribe/commit/7cf0773864fbdd1772fea9a5ff9e7ffd3360d7d2),[2ebf40b](https://github.com/knuckleswtf/scribe/commit/2ebf40b5e5be309bf5e685a0cd58bb70856b033d)). Some details in the Blade files were also adjusted for this.
- Nested response fields are now collapsed ([00b09bb](https://github.com/knuckleswtf/scribe/commit/00b09bbea8ec64006db864bf807004d48926c6d3)). Some details in the Blade files were also adjusted for this.
- `add_routes` now uses inline routes (no more `Scribe\Controller` class)
- Changed signature of Strategy ($routeRules is now optional,and there's now an instance var $endpointData, although it's not set by default)
- Parameter data from successive stages is now merged
- Support overriding docs for inherited methods ([9735fdf](9735fdf150469f186bab395fcfabd042f570c50c))

## 3.37.2 (8 September 2022)
### Fixed
- Multi-docs: Use correct routes in Laravel view ([a41e717](https://github.com/knuckleswtf/scribe/commit/a41e71707b3a33e4c80614c678c389c9fd5196de))

## 3.37.1 (5 September 2022)
### Fixed
- Fix regression in parsing API resource tags that have a status code when generating response fields ([#516](https://github.com/knuckleswtf/scribe/pull/516))
- Don't crash if instantiation of method argument fails ([#515](https://github.com/knuckleswtf/scribe/pull/515))

## 3.37.0 (27 August 2022)
### Added
- Support `"No-example"` as `example` value in `bodyParameters()` and friends ([#511](https://github.com/knuckleswtf/scribe/pull/511))

## 3.36.0 (12 August 2022)
### Added
- Support `@responseField` on Eloquent API resources ([#505](https://github.com/knuckleswtf/scribe/pull/505))

## 3.35.0 (27 July 2022)
### Modified
- Use correct folders when generating in multi-docs ([ac47c67](https://github.com/knuckleswtf/scribe/commit/ac47c67eeb5d47cd30db74302d1cdd97720dd695))


## 3.34.0 (16 July 2022)
### Modified
- URL parameter inference bugfixes and refactor ([#497](https://github.com/knuckleswtf/scribe/pull/497))


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
