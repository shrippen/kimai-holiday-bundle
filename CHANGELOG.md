# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Fixed

- Absences with auto-created timesheets were credited twice in the working-time balance
- Absence timesheets are identified by the meta field `holiday_absence_id` instead of `(#N)` anywhere in the description (unrelated timesheets could be deleted); legacy entries only match the exact generated description on the configured project/activity
- Absence timesheets are non-billable; exported ones are never deleted; rejecting or re-requesting an absence removes its timesheets
- Team leads are limited to members of their own teams (view, approve, reject, edit, delete, export, PDF, calendar `?team=`), in the UI and the API
- API approve/reject only work on requested absences; errors return 400 instead of 500
- ICS link only for the owner (and admins), not auto-created when viewing other users; disabled users' feeds return 404; no comments for sickness absences; UTF-8-safe line folding
- Validation for absences (form and API): end ≥ start, at most one year, duration 0–24 h, no overlaps, strict `YYYY-MM-DD` dates
- CSRF tokens for all plain POST forms (approve, reject, delete, ICS regenerate, month lock/unlock, public holiday sync/delete)
- Full-day public holidays no longer count as vacation/absence days
- Vacation entitlement is pro-rated when the contract starts or ends during the year (1/12 per full month, rounded up to half days)
- Routes validate `{year}` / `{month}` (invalid month → 404 instead of 500)
- Kimai's own working-time approval now locks absences in approved months
- Timezone-safe date comparison in `Absence::coversDate()`; half-day sickness credits half a day; calendar feed covers year changes
- Workday restriction is reported as a timesheet validation error instead of an exception
- CSV export guards against formula injection; ICS import blocks private/internal addresses (SSRF)

### Migration

- Run `bin/console kimai:bundle:holiday:install`: tags existing absence timesheets with the new meta field and marks the non-exported ones as non-billable

## [1.0.0] — 2026-08-10

### Added

- Initial public release (Kimai ≥ 2.64): working times, absences, public holidays, calendar, reports, API
- Extends Kimai Profil → Arbeitsvertrag and Arbeitszeiten (Abwesenheit menu; no duplicate contract UI)
- Absences with approval workflow, edit + reapproval, type icons, workday-only counting (weekends excluded)
- Urlaubskonto on the absence page; future absences/public holidays in the Arbeitszeiten table
- Public holiday groups with ICS import/sync; per-user ICS feed (locale-aware) for calendar apps
- Absence calendar (full year), CSV export, system settings for calculation modes
