# Changelog

All notable changes to this project are documented in this file. Each release section is also its
release note (GitHub and Gitea) and its entry under "Changes" on the landing page; `[Unreleased]`
gets that format when it is released.

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

### English

The first release of Working Hours & Holidays for Kimai: absences with approval, public holidays from ICS calendars and a vacation account, built into Kimai's own contract and working times pages.

#### New
- Absences (vacation, sickness, time off in lieu, other) with an approval workflow; an edited absence needs approval again. Type icons, and only workdays count (weekends are left out).
- Vacation account on the absence page; future absences and public holidays in the working times table.
- Extends Kimai's Profile → Contract and Working times pages (Absence menu) instead of a second contract page.
- Public holiday groups, imported and synced from ICS calendars.
- A personal ICS feed per user for calendar apps, in the user's language.
- Absence calendar for the whole year, CSV export, system settings for the calculation modes, and an API.
#### Note
- Needs Kimai 2.64 or newer. Clone or extract into `var/plugins/HolidayBundle`, then run `bin/console kimai:reload -n` and `bin/console kimai:bundle:holiday:install`.
- Do not install it alongside the commercial WorkContractBundle.

All changes: https://github.com/shrippen/kimai-holiday-bundle/commits/v1.0.0

### Deutsch

Die erste Version von Arbeitszeiten & Feiertage für Kimai: Abwesenheiten mit Genehmigung, Feiertage aus ICS-Kalendern und ein Urlaubskonto, eingebaut in Kimais eigene Seiten für Arbeitsvertrag und Arbeitszeiten.

#### Neu
- Abwesenheiten (Urlaub, Krankheit, Freizeitausgleich, Sonstiges) mit Genehmigung; eine geänderte Abwesenheit muss neu genehmigt werden. Symbole je Art, und es zählen nur Arbeitstage (Wochenenden nicht).
- Urlaubskonto auf der Abwesenheitsseite; künftige Abwesenheiten und Feiertage in der Arbeitszeiten-Tabelle.
- Erweitert Kimais Seiten Profil → Arbeitsvertrag und Arbeitszeiten (Menü Abwesenheit) statt einer zweiten Vertragsseite.
- Feiertagsgruppen, importiert und abgeglichen aus ICS-Kalendern.
- Ein persönlicher ICS-Feed je Benutzer für Kalender-Apps, in der Sprache des Benutzers.
- Abwesenheitskalender für das ganze Jahr, CSV-Export, Systemeinstellungen für die Berechnungsarten und eine API.
#### Hinweis
- Braucht Kimai 2.64 oder neuer. Nach `var/plugins/HolidayBundle` klonen oder entpacken, dann `bin/console kimai:reload -n` und `bin/console kimai:bundle:holiday:install` ausführen.
- Nicht zusammen mit dem kommerziellen WorkContractBundle installieren.

Alle Änderungen: https://github.com/shrippen/kimai-holiday-bundle/commits/v1.0.0
