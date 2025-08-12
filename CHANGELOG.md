# Changelog

All notable changes to this project will be documented in this file.

## [1.5.2] - 2025-08-12
- Fixed: search not working when using applySearchToCriteria in custom routes

## [1.5.1] - 2025-05-22
- Fixed: symfony 6 support
- Fixed: warnings

## [1.5.0] - 2025-05-12
- Changed: allow contao 5

## [1.4.1] - 2025-02-27
- Fixed: datatables not initialized correctly when language is not set on widget

## [1.4.0] - 2025-01-30
- Changed: replaced `heimrichhannot/datatables` with bundled datatables library
- Changed: Raised datatables version to 2. May need some adjustments of your configuration and locales.

## [1.3.0] - 2024-11-05
- Added: custom routes for ajax requests
- Changed: modernized bundle structure

## [1.2.2] - 2022-03-20
- Fixed: issues with php 8
- 
## [1.2.1] - 2022-03-15
- Fixed: array index issues

## [1.2.0] - 2022-03-04
- Changed: load assets only on pages where widget is used
- Fixed: deprecation warning

## [1.1.0] - 2022-03-04
- Changed: allow php 8
- Changed: allow symfony/kernel 5

## [1.0.1] - 2021-06-11

- restored asset files in `config.php` because else they aren't loaded correctly if not logged in as admin

## [1.0.0] - 2020-12-04

- refactored to bundle
