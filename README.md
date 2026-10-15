# Notification Manager

Banners and popups for Concrete CMS, managed from one dashboard.

- **Banners** render in the page flow at the top of `<body>` (no layout shift). Several banners can show at
  once and rotate (fade, vertical scroll or ticker); each can be dismissible.
- **Popups** use a native `<dialog>` (focus trap, Esc, inert background) and open on page load (with an
  optional delay), scroll depth, an element scrolling into view, or a click. One popup shows per page: the
  highest priority eligible one.
- Each notification can carry a call-to-action link: a page, a file, an external web address, a phone number
  or an email address, set per locale.
- Both have a start/end window, an optional recurrence rule, all-pages or specific-pages targeting, and
  per-locale content with a fallback language.
- Optional audience rules per notification: new or returning visitors, device, a URL parameter and the
  referring site. They are checked in the browser, so pages stay cacheable.
- Statistics: how often each notification was shown, clicked and dismissed, as daily totals with no visitor
  data, shown on the notification and in the list, with a Statistics page for charts, filters and downloads
  (Excel, JSON, PDF).
- Settings live in the `nmSettings` table, not in package config files.
- Notifications are one Doctrine entity hierarchy (`AbstractNotification`, single table, discriminator
  `banner` | `popup`).

## Dashboard

`Dashboard > Notification Manager` has **Notifications** (add, edit, duplicate, delete), **Statistics** and **Settings** (enable banners,
enable popups, fallback language, excluded paths, statistics, bundled CSS on/off).

**Duplicate** (on a notification's details page) copies the type settings, schedule, targeting, audience rules and
every locale's content, including images and links. The copy is inactive and its names are prefixed "Copy of", so
it never goes live until you activate it.

## Styling

The bundled CSS is driven by custom properties, eg. `--nm-banner-bg`, `--nm-banner-color`, `--nm-popup-bg`,
`--nm-popup-accent`. Set them on `:root`, or tick "Use my own CSS" in Settings and style `<nm-banner>`,
`.nm-banner__item`, `<nm-popup>` and `.nm-popup` yourself.

## Links

Choose a link type per locale on the notification form. Page and file links are picked from the sitemap and
file manager. Web addresses must be http(s) (or a plain domain such as `example.com`), phone numbers become
`tel:` links, and email addresses become `mailto:` links; anything that doesn't validate is rejected on save.
Leave the link text blank to use the page or file name, the number, the address or the domain.

## Audience targeting

The **Audience** section of the notification form narrows who sees a notification. Every condition you set
must match; blank conditions are ignored.

- **Visitors**: _new_ for the whole of a visitor's first session, _returning_ from their next one. The browser
  keeps a small marker (`nm_visitor_seen`) to tell, and only does so when a notification actually uses this
  condition. A visitor who clears it, or blocks storage, counts as new.
- **Devices**: by window width. Mobile is under 768px, tablet under 1024px, desktop otherwise.
- **URL parameter**: eg. `utm_campaign` with the value `spring-sale`. A blank value matches any value. The value
  is remembered for the rest of the visit, so it still applies after the visitor leaves the landing page.
- **Referring site**: a domain such as `google.com`, matched as that site or any of its subdomains. The
  original referrer is remembered for the visit; links within your own site don't count.

Rules are evaluated in the browser. The server sends the same markup to everyone, so a targeted banner is
rendered with the `hidden` attribute and revealed for the visitors it is for. If you tick "Use my own CSS",
make sure `.nm-banner__item[hidden]` stays hidden. When a popup has rules, the page also carries the next popups
in priority order and the browser keeps the first one that applies; a popup the visitor has already closed is
not replaced by the next one.

## Statistics

Each notification's details page has a **Performance** panel (last 30 days: shown, clicks, click rate, dismissed,
dismissal rate, and a daily table), and the list shows views and clicks per notification.

- **What is counted**: a banner when it is on screen (the active one while several take turns), a popup when it
  opens, a click on the call-to-action link, and a dismissal (a banner's close button, or closing a popup). Each
  is counted once per notification per page view.
- **What is stored**: one row per notification, day and language holding three counters (`nmNotificationStats`).
  Nothing identifies a visitor, and nothing is stored in their browser for this. Deleting a notification deletes
  its figures.
- **Who is not counted**: crawlers and tools, automated browsers, and signed-in users who can open Notification
  Manager in the dashboard (so editing your own pages doesn't inflate the figures).
- **How it works**: the page tells the browser where to report (`/ccm/notification_manager_kit/track`), and the
  browser sends one beacon per page view when the page is hidden or left. Because pages can be cached this has
  to happen in the browser, so visitors with ad blockers are missed and the figures are approximate.
- **Switching it off**: untick _Count impressions, clicks and dismissals_ in Settings. No attribute, script or
  request is then sent to visitors.
- **Retention**: _Keep statistics for (days)_ in Settings (7 to 730, default 90). Older rows are removed by the
  **Prune Notification Statistics** task. Schedule it to run daily from the Automation section of the dashboard;
  `concrete task:prune-notification-statistics` runs it from the command line.

### The Statistics page

`Dashboard > Notification Manager > Statistics` reports across every notification.

- **Filters**: last 7, 30 or 90 days, or a custom range (up to two years), plus type (banners or popups) and a single
  notification. The filters are in the address, so a filtered view can be bookmarked or shared.
- **On the page**: totals, a daily column chart each for shown, clicks and dismissed (ranges over 110 days are shown
  by week), a table with one row per notification, and the day by day figures.
- **Downloads** (the buttons at the top use whatever filters are chosen):
    - **Excel** (`.xlsx`): a _Summary_ sheet (one row per notification and a total), a _Daily_ sheet (one row per
      notification per day with activity) and a _Report_ sheet recording the range and filters. Rates are real
      numbers formatted as percentages, so they sort and calculate; a notification named `=SUM(...)` is stored as
      text, never as a formula.
    - **JSON**: the range, filters, totals, a list of notifications and the daily rows, with rates as fractions.
    - **PDF**: the totals and the per-notification table on landscape A4. The daily breakdown is left to the
      other two formats. The PDF font covers Latin, Greek and Cyrillic; names in Chinese, Japanese or Korean show as
      empty boxes (the Excel and JSON downloads are unaffected).
- Column names in the downloads are always English, so they don't change with the dashboard's language.

The endpoint is public and cannot use a CSRF token, so it is defensive instead: it only adds to notifications that
are live, ignores unknown ids and languages, rejects cross-site requests, drops bots and signed-in editors,
limits one address to 300 events a minute (approximate under heavy parallel load), and always answers `204`
with no body.

## Upgrading

Entity changes are applied by the package upgrade, which Concrete only runs when `$pkgVersion` in
`controller.php` increases. Bump it, then run `c5:package:update notification_manager_kit` (or update from
the dashboard).

After upgrading to 0.4.0 run Composer in your site so that its `vendor/` gets the new libraries (OpenSpout and
dompdf, used for the Excel and PDF downloads). Concrete also keeps each administrator's dashboard menu in their
session, so the new **Statistics** item appears once they sign out and back in.

## Security and performance notes

- Every value written into HTML goes through `NotificationManagerKit\Support\Output`; rich text is sanitised
  on save and again on read (`Support\Sanitizer`); trigger selectors are validated on save (`Support\SelectorValidator`).
- Settings and active notifications are cached as plain data in Concrete's expensive cache and flushed on
  every save (which also flushes the full page cache). Schedules are checked per request against that cache.
  A start/end time passing does not itself flush a cached page: with full page caching on, a change takes
  effect when the page's cache lifetime expires.
- Assets load only on pages that show something, banner and popup code are separate, and neither uses jQuery.

## Requirements

- PHP 8.4 or later.
- Composer.
- PHP extensions `zip` (Excel files), `dom`, `mbstring` and `gd` (PDF files), all of which Composer checks for.
- Node.js and npm, used by the Composer post-install script and JavaScript
  formatting commands.
- Access to the private Composer registry at
  `https://packages.limegreentangerine.net` to install private dependencies.

Requires `limegreentangerine/class_kit`. Developed against Concrete CMS 9.2 and PHPUnit 11.

## Install

```bash
composer install
```

Composer runs `npm install` after installation. If you need to authenticate to
the private registry, configure Composer to send the Cloudflare Access service
token headers for `packages.limegreentangerine.net`:

- `CF-ACCESS-CLIENT-ID` -> Cloudflare Access service-token client ID.
- `CF-ACCESS-CLIENT-SECRET` -> matching client secret.

Keep these credentials private; do not commit them to the repository.

## Development commands

Run these commands from the repository root:

| Command                                             | Purpose                                                                                                                        |
| --------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| `composer test`                                     | Run PHPUnit tests.                                                                                                             |
| `npm test`                                          | Run the JavaScript tests (Vitest and jsdom) for the Web Components.                                                            |
| `composer test-coverage`                            | Run PHPUnit and print a coverage report.                                                                                       |
| `composer typecheck`                                | Run PHPStan using `phpstan.neon`. Findings are displayed, but the command exits successfully even when PHPStan reports errors. |
| `composer format:check`                             | Check PHP and JavaScript formatting.                                                                                           |
| `composer format`                                   | Format PHP and JavaScript files.                                                                                               |
| `composer format:php:check` / `composer format:php` | Check or format PHP files with PHP-CS-Fixer.                                                                                   |
| `composer format:js:check` / `composer format:js`   | Check or format files with Prettier.                                                                                           |

PHPStan analyzes `src` and `tests` at level 4, with bootstrap configuration
from `phpstan-bootstrap.php`. If you add package directories, update the
`paths` in `phpstan.neon` and the directories in `.php-cs-fixer.dist.php` so
they are included in analysis and formatting.

## GitHub Actions

- **CodeStandards** runs formatting checks and PHPStan on pushes and pull
  requests.
- **SystemTests** runs PHPUnit on pull requests.

Both workflows install dependencies from the private Composer registry. Add
these **repository secrets** under **Settings > Secrets and variables >
Actions** so the workflows can authenticate:

- `CF_ACCESS_CLIENT_ID` -> Cloudflare Access service-token client ID.
- `CF_ACCESS_CLIENT_SECRET` -> matching client secret.
