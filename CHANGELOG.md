# Changelog

All notable changes to this project will be documented in this file.

## 2.2.1 (2026-11-07)

### Added

- The absence calendar shows the weekday under each day number (as the availability view)

## 2.2.0 (2026-11-01)

### Added

- Day shifts (Tagesverschiebung): move a contract working day to another day of the same week, with request and approval
  (`WorkdayShift`, migration `Version20261030100000`); the approved shift moves expected hours, vacation, workday
  timesheet restriction and absence timesheets to the new day
- Permissions `shift_own`, `shift_other` and `approve_shift`; nobody approves their own shift
- Day shifts in a second table on the page "Open requests" with row menu (approve/reject per row) and undo; delete with
  confirmation
- Action "Move a day" on the availability page
- Availability shows the shifted person on the new day ("shifted from") and open requests as *open*; the absence
  calendar shows entries of type `workday_shift`; the working-time year view marks both days
- Mails for requested, approved, rejected and withdrawn shifts (requester and approvers)
- The menu badge and the count of open requests include day shifts

### Changed

- `UserWorkContract::getExpectedSecondsForDate` respects approved shifts; the raw contract value is
  `getContractSecondsForDate`
- The working-time year view marks shifted days with an addon
- `AbsenceApprovalService` checks locked periods with the shared `PeriodLockGuard`
- Approver resolution (`ApproverResolver`, `ApproverAccess`) takes the permission as a parameter (default
  `approve_other_absence`), so day shifts use `approve_shift`
- Soll and absence per day come from one calculation (`DayExpectation`), used by `WorkingTimeCalculator` and the
  working-time year view; with a half public holiday plus a half absence both now follow the calculator rule

### Development

- Unit tests for shifts, overrides, permissions, the day calculation and the year view subscriber
- Update note: run `kimai:reload` as the web user (`-u www-data`)

## 2.1.0 (2026-10-28)

### Added

- Locations with opening hours per weekday, exceptions per date and members (one location per person),
  page "Locations" (`holiday_locations`)
- Extra shifts (Aushilfe): working on a day without a contract working day, without approval
- Availability view (Reports → Availability) per location, week as day cards, month as table
- Permissions `view_location`, `edit_location`, `view_availability`, `extra_shift_own`, `extra_shift_other`
- The absence calendar shows extra shifts with a plus icon

### Changed

- A public holiday group in use by a location cannot be deleted

### Development

- The dev server serves assets with their MIME type; `dev/screenshot.mjs` takes screenshots of the dev stack

## 2.0.0 (2026-10-23)

Fork for zafe (GPL-3.0-or-later, upstream `shrippen/kimai-holiday-bundle`), tested on Kimai 2.70.2.

### Breaking changes

- The vacation year is the employment year from the contract start, no longer the calendar year. Without a start date
  the calendar year is used and the absence page shows a notice
- The `{year}` route parameter of the absence page and export is the vacation-year index (calendar year in the fallback);
  values >= 1000 from old links map to the vacation year that starts in that calendar year
- The permission `approve_own_absence` is removed from all roles: nobody approves their own absence
- The supervisor is no longer a mail recipient; mails go to the requester and all approvers

### Added

- CI on Kimai 2.70.2 and PHP 8.2-8.4 (syntax, translation check, release zip installed into Kimai), Docker dev stack
  (`dev/kimai-dev.sh`) and PHPUnit tests (`dev/test.sh`)
- Vacation account per employment year with automatic carry-over, manual corrections included
- Approvers are resolved per user; decision mails name the decider
- Badge with the number of open requests in the menu and the page "Open requests" (`holiday_absence_requests`)
- Approvers who create an absence for someone else get it approved directly
- Austrian public holidays (`at`, `at-de`) with an exclusion list per group (defaults from the catalog); holidays
  deleted from a subscribed group are added to the list and do not come back on sync
- Routes `holiday_public_holiday_edit` (edit a holiday) and `holiday_public_holiday_group_edit` (edit a group, including
  its exclusion list)
- `dev/check-translations.php` also fails on duplicate `trans-unit` ids

### Changed

- The working day comes only from the contract; Saturday counts when the contract says so
- "Allow half vacation days" is now enforced: half-day vacation is rejected when the setting is off
- An approver who edits someone else's absence keeps it approved; self-edits go back to *Requested*
- Undoing an approve/reject mails the requester and all approvers again ("requested")
- `GET /api/holiday/absences?year=` and the ICS feed use calendar years, while the UI uses vacation (employment) years
- Approver lookup also finds ROLE_ADMIN users who are not the logged-in user (`view_all_data` by role)

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
- Kimai calendar: absences and public holidays use Kimai's day roles (`--kimai-holiday`, `--kimai-public-holiday`, …)
  instead of fixed colours, requested absences are faded; the theme (Knust) decides the colour
- Absence calendar: days use the kit's calendar-day marker (kit 0.7): today framed, each user's public holidays and
  approved absences as bars, weekends sunken; two-digit day numbers keep the columns equally wide

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
- `AbsenceApprovalService::request()` has an optional `$notify` argument (default true; undo mails again)

### Removed

- Unused `UserWorkContract::getVacationDaysPerYear()` and `AbsenceWorkdayHelper::countDays()`

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
