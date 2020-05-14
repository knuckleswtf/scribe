# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project aims to adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased
### Added

### Changed

### Fixed

### Removed

## 1.0.0-alpha1 ()
### Added
- Support for closures (https://github.com/mpociot/laravel-apidoc-generator/pull/690)

### Changed
- Switch from Documentarian to Pastel
- Switch to Clara for output
- Split documentation across multiple Markdown files
- Change behaviour of command. Running `generate` will generate afresh, keeping any changes to MD files. Use --force to discard your changes. Implement new system for tracking and responding to modifications to files; remove update command
- Added ability to include files by creating arbitrary markdown files

Appearance:
- Move Postman collection -> TOC footer
- Change default page title
- Improve output: use badges, drop tables for paragraphs, style headings

### Fixed

### Removed





