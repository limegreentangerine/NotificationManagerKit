# Changelog

Notable changes to this project are documented here.

## 0.3.0

### Added

- Statistics. Banners and popups report when they are shown, clicked and
  dismissed, as daily totals per notification and language. A Performance panel
  on each notification's details page shows the last 30 days (shown, clicks,
  click rate, dismissed, dismissal rate and a daily table), and the list gains
  Views and Clicks columns.
- Settings to switch statistics off and to choose how long to keep them
  (7 to 730 days, default 90). With statistics off no tracking code is sent to
  visitors.
- A "Prune Notification Statistics" scheduled task that deletes figures older
  than the retention period.
- A public `/ccm/notification_manager_kit/track` endpoint that receives the
  browser's report. It ignores crawlers, signed-in administrators, unknown
  notifications and languages and cross-site requests, limits one address to
  300 events a minute, and always replies `204`.
- Tests for request parsing and validation, bot and origin checks, summaries and
  rates, the new settings, and the browser tracker, banner and popup reporting.

### Changed

- New `nmNotificationStats` table (one row per notification, day and language;
  deleted with its notification), and `statsEnabled` and `statsRetentionDays`
  columns on `nmSettings`. Upgrading the package applies the schema change.
  Statistics are on by default after upgrading.
- The cached notification catalog is rebuilt after upgrading (its cache key
  changed again).

### Security

- Visitors cannot create statistics for a made-up notification or language, and
  one request can add at most one of each event per notification.

## 0.2.0

### Added

- Audience targeting for banners and popups. Optional conditions, all of which
  must match: new or returning visitor, device (mobile, tablet or desktop by
  window width), a URL parameter (with an optional value, eg.
  `?utm_campaign=spring`), and the referring site. They are evaluated in the
  browser, so full page caching still works.
- Duplicate button on a notification's details page. It copies the type
  settings, schedule, targeting, audience rules and every locale's content
  (including images and links). The copy is inactive and its names are prefixed
  "Copy of".
- Tests for audience rules (PHP and browser), duplication, and popup candidate
  selection.

### Changed

- `nmNotifications` gains an `audienceRules` column. Upgrading the package
  applies the schema change; existing notifications are for everyone.
- When a popup has audience rules the page carries the next popups in priority
  order too, and the browser keeps the first one that applies to the visitor.
  Pages without audience rules still carry exactly one popup.
- The cached notification catalog is rebuilt after upgrading (its cache key
  changed), so nothing needs flushing by hand.

## 0.1.2

### Added

- Call-to-action links can be a page, a file, an external web address, a phone
  number or an email address (or no link), chosen per locale. Blank link text
  falls back to the page or file name, the number, the address or the domain.
- A priority outside -1000 to 1000 is rejected with a validation error instead
  of being silently clamped.
- Tests for link resolution, priority validation, and a check that no mapped
  table or column name is a MySQL reserved word.

### Changed

- `nmNotificationContents` gains `linkType`, `linkValue` and `linkFID`. Existing
  rows with only a linked page are read as page links, so nothing needs
  migrating. Upgrading the package applies the schema change.
- The banner `interval` column is renamed `intervalMs`.

### Fixed

- Saving any banner failed with an SQL syntax error, because `interval` is a
  reserved word in MySQL.
- Saving a notification threw a TypeError, because the site's locales are a
  Doctrine collection rather than an array.

### Security

- Free text link targets are validated: only http(s) web addresses, tel: numbers
  and plain mailto: addresses are ever written into an href. `javascript:`,
  `data:` and similar schemes, and email addresses carrying extra headers, are
  refused.

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
