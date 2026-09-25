# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Changed (user interface)

- All pages follow the kimai-plugin-ui guidelines and kit 0.2.0 (`Resources/views/_kit/`): Kimai page title with period and
  context line, actions in the page header, period navigator, help button, dark mode and 390 px layouts
- Absences: Kimai data table with status badges and row menu; create/edit/delete and the personal ICS calendar in Kimai
  modals; bulk **Approve** / **Reject** with undo; vacation balance as KPI tiles (taken, requested, sick days, remaining);
  user picker for approvers; "Create absence" in the empty state opens the modal; creating an absence, a public holiday,
  a group or importing shows the year/group of the new entry
- Undo of approve/reject follows the kit's undo window: 15 minutes, same user, same session, only the absences of that
  action and only if unchanged since (otherwise a message instead of a silent reset)
- Absence calendar: month view (`/holiday/absence-calendar/{year}/{month}` no longer redirects), month/year switch, team
  picker, requested absences shown faded, weekends shaded
- Public holidays: year navigator in the right order, readable active group, add/import/create group in modals, deletes
  with Kimai confirmation, import and sync results shown on the page
- Manual booking: Kimai form card; working times page: one absence menu with absences, manual booking and month PDFs
- Success messages with information (import count, ICS link regenerated, re-approval) are shown as info callouts; errors
  are translated messages instead of raw exception texts
- Dates, numbers and days use Kimai's formatters (`date_short`, `amount`)

### Changed (for integrators)

- Translation keys are all prefixed with `holiday.` (e.g. `menu.absence` → `holiday.menu.absence`); the plugin no longer
  overrides Kimai core keys (`action.save`, `action.close`, `action.delete`, `confirm.delete`, `yes`, `no`,
  `action.update.success`, `action.delete.success`). English: "Time-Off" → "Time off in lieu", menu "Absence" → "Absences"
- New routes: `holiday_absence_create`, `holiday_absence_ics`, `holiday_absence_bulk_approve`, `holiday_absence_bulk_reject`,
  `holiday_absence_reopen` (undo, `POST /holiday/absence/reopen/{action}` with the action id from the undo notice), `holiday_public_holiday_group_create`, `holiday_public_holiday_create`,
  `holiday_public_holiday_import`; `holiday_absence` and `holiday_public_holidays` are GET only; delete routes show a
  confirmation on GET and delete on POST (same CSRF tokens as before)
- Removed the template overrides `user/contract.html.twig` (Kimai renders the extra contract fields itself) and the unused
  `contract/working_times.html.twig`; `contract/status.html.twig` is still overridden (one condition, see file)
- Immediate actions use the kit's `data-kpu-post` attributes and the modal forms fire `kpu.reload`
  (`AbsenceController::UPDATE_EVENT`, formerly `kimai.holidayUpdate`); the plugin's own click script is gone
- Translation keys `holiday.absence.undo.expired`, `.changed`, `.skipped` added, `holiday.error.action_failed` removed;
  approve/reject counts cover 0
- `AbsenceApprovalService::request()` has an optional `$notify` argument (undo does not mail approvers again)

### Fixed

- Bulk approve/reject with one absence the user may not approve changed the others before failing with 403; now
  nothing is changed

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
- The personal ICS feed token is no longer a Kimai user preference (`holiday_ics_token`): preferences are returned by
  `/api/users/me` and `/api/users/{id}` (visible to admins and team leads) and handed to invoice/export templates
  (`user.meta.*`), so anyone with that access could read the calendar feed. The token lives in the new table
  `kimai2_ext_holiday_ics_token` (unique index on the token)

### Migration

- Run `bin/console kimai:bundle:holiday:install`: tags existing absence timesheets with the new meta field and marks the non-exported ones as non-billable
- The same command runs `Version20260925120000`: moves existing ICS tokens unchanged into `kimai2_ext_holiday_ics_token`
  (subscribed feed URLs keep working) and deletes the `holiday_ics_token` preference rows; `--down` moves them back

## [1.0.0] — 2026-08-10

### Added

- Initial public release (Kimai ≥ 2.64): working times, absences, public holidays, calendar, reports, API
- Extends Kimai Profil → Arbeitsvertrag and Arbeitszeiten (Abwesenheit menu; no duplicate contract UI)
- Absences with approval workflow, edit + reapproval, type icons, workday-only counting (weekends excluded)
- Urlaubskonto on the absence page; future absences/public holidays in the Arbeitszeiten table
- Public holiday groups with ICS import/sync; per-user ICS feed (locale-aware) for calendar apps
- Absence calendar (full year), CSV export, system settings for calculation modes
