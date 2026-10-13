# Changelog

Notable changes to this project are documented here.

## 0.1.0

### Added

- Banners and popups in one package, as a single-table discriminator hierarchy
  (`AbstractNotification` -> `BannerNotification` | `PopupNotification`).
- Settings stored in the `nmSettings` table instead of the package config.
- `<nm-banner>` and `<nm-popup>` Web Components (native `<dialog>` for popups),
  with no jQuery dependency.
- Recurrence rules and priority for both notification types.
- Cached, query-free eligibility checks on the front end.

### Security

- All output escaped; rich text sanitised on save and on read; trigger selectors
  validated; CSRF tokens validated on every dashboard save and delete.

## 0.0.0

### Added

- Separate GitHub Actions workflows for code standards and system tests.

### Changed

- Code standards checks run on pushes and pull requests; system tests run on
  pull requests.
- Updated Composer dependencies and development tooling for the package
  template.

## 2026-09-25

### Added

- PHPStan static analysis configuration and a Composer command for type checks.
- Cloudflare Access authentication for installing dependencies from the private
  Composer registry.

### Changed

- Updated the template to use the published `limegreentangerine/class_kit`
  Composer package.
- Refined the GitHub Actions workflows and development dependencies.

## 2026-09-15

### Added

- Initial Concrete CMS Composer package template, including Composer
  autoloading, PHPUnit test setup, and a GitHub Actions test workflow.
