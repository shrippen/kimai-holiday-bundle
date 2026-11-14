# Architecture

How this repository is meant to be built. Where the code differs today is listed under
"Known deviations". Change this file in the PR that changes the structure.

## What it does

A Kimai plugin for working hours and absences (`HolidayBundle`): vacation, sickness and
other absences with approval, public holidays per group (federal state), and from them
target hours, overtime and vacation left. It adds to Kimai's own working time and
contract pages instead of replacing them, and offers an API, calendar feeds and
exports.

## Building blocks

| Path | Its one task |
|---|---|
| `Enum/` | The fixed choices of the domain (absence type and status, calculation mode, kind of a manual booking). |
| `Entity/` | What is stored: absence, public holiday and its group, manual booking, month lock, calendar token. |
| `Repository/` | The queries, one class per entity. |
| `Migrations/` | One version file per schema change; a released one is never edited. |
| `Service/WorkingTimeCalculator.php`, `AbsenceWorkdayHelper.php`, `UserWorkContract.php` | The calculation: target, actual and balance per day and year, vacation days, what an absence or public holiday credits on a day; which days are workdays; the user's contract data. |
| `Service/AbsenceApprovalService.php`, `AbsenceTimesheetService.php`, `AbsenceNotifier.php`, `MonthLockService.php` | The use cases on absences: create, change, approve, reject, delete; the compensating timesheet entries; mails; locked months. |
| `Service/AbsencePermissions.php` | Who may see, edit, approve or delete whose absences. The one place for these rules. |
| `Service/HolidayImporter.php`, `HolidayIcsCatalog.php`, `IcsHolidayParser.php` | Public holidays from calendar feeds; the only code that fetches from another server. |
| `Service/UserIcsCalendarBuilder.php`, `UserIcsTokenService.php` | The user's own calendar feed and its secret token. |
| `Service/AbsenceExcelExporter.php`, `WorkingTimePdfExporter.php` | The exports of absences and of the working time report. |
| `Service/HolidayConfiguration.php` | The plugin's system settings. |
| `Controller/` | The pages: absences (with the settings of the user's calendar feed), absence calendar, contract and working time report, public holidays; the absences as events for Kimai's own calendar (`CalendarFeedController`). The two traits hold what pages share. |
| `API/`, `Ics/` | The REST API (`/api/holiday/…`); the calendar feed that needs no login, only the token. |
| `Command/` | Console commands: install, fetch public holidays. |
| `Form/`, `Validator/` | Symfony forms, the extension of Kimai's contract form; the rule "timesheets on workdays only". |
| `EventSubscriber/` | Hooks into Kimai: menu, permissions, settings, page actions (`Actions/`), calendar sources, absences and public holidays in Kimai's working time view. |
| `Resources/views/`, `Resources/translations/` | Templates per page; all texts, German and English, same keys. `_kit/` and `kpu.*.xlf` are the vendored Kimai plugin kit, never edited here. |
| `HolidayBundle.php`, `DependencyInjection/`, `Resources/config/` | Registration in Kimai; the permissions per role (`HolidayExtension.php`). |
| `dev/` | The checks of translations and of the release zip in a real Kimai. |
| `demo/`, `docs/`, `scripts/`, `tools/` | Not part of the plugin zip: demo data, landing page, release and check scripts. |

## Layers

```
 entry points   Controller   API   Ics   Command   EventSubscriber   Form   Validator
                     │  ask for a use case or a number, show the answer
                     ▼
 work           Service ─────────► Kimai's services      calendar feeds
                     │                                    (HolidayImporter only)
                     ▼
 storage        Repository
                     │
                     ▼
 model          Entity   Enum   Kimai's entities   (used by every layer above)
```

- An entry point calls services, never a repository or the entity manager.
- A query is written in `Repository/`, also one for Kimai's timesheets; a service
  builds none itself.
- A number is computed once, in the calculation: what a day credits, how many vacation
  days count, target and balance. Pages, Kimai's working time view, the compensating
  entries, exports and API ask the same function.
- A right on absences, and on another user's data, is decided once, in
  `AbsencePermissions`. Pages, API, calendar and page actions ask it; none carries
  its own check. A plain gate by one permission at a route or a page action is fine.
- "The public holidays of this user in this period" is one service function (group
  first, then its days, with the weekend rule); nobody else puts it together.
- A use case exists once: creating and changing an absence share what they have in
  common.
- A name says what a thing produces.
- Nothing is kept that no page, API, feed or command reaches: a setting has an effect,
  a table has a page that uses it, a function has a caller.
- The calculation has unit tests; `dev/ci.sh` loads every page, the API and the feed.

## Where new things go

| Change | Touch |
|---|---|
| A rule of how hours or vacation days are counted | The calculation (`Service/WorkingTimeCalculator.php`, `AbsenceWorkdayHelper.php`), a unit test |
| A new absence type or status | `Enum/` (a type also says whether it needs approval), the calculation, the calendar feed (`Service/UserIcsCalendarBuilder.php`), the forms and templates, texts; a status also in `Service/AbsenceApprovalService.php` |
| A rule on who may do what | `Service/AbsencePermissions.php`; a new permission in `DependencyInjection/HolidayExtension.php` and `EventSubscriber/PermissionSectionSubscriber.php` |
| A step in creating, approving or deleting an absence | `Service/AbsenceApprovalService.php` |
| Something about public holidays or their import | The repository and service of public holidays, `Service/HolidayImporter.php` |
| A page | `Controller/`, a template folder in `Resources/views/`, the menu, page actions in `EventSubscriber/Actions/`, texts, a check in `dev/ci.sh` |
| An API endpoint or field | `API/AbsenceApiController.php`, README, a check in `dev/ci.sh` |
| A setting | `Service/HolidayConfiguration.php`, `EventSubscriber/SystemConfigurationSubscriber.php`, texts |
| A table or column | `Entity/`, `Repository/`, a new file in `Migrations/` |
| A text | `Resources/translations/` in German and English, same key |
| A design element that is missing | Knust or the kit in Kante, then sync; never `_kit/` here |

## Known deviations

- What an absence or a public holiday credits on a day is built three times, and the
  versions differ (`AUDIT.md`, finding 1).
- The calculation has no test, and `dev/ci.sh` loads only five pages
  (`AUDIT.md`, finding 2).
- The absence page counts requested vacation days and sick days itself, and counts
  sickness differently from the calculation (not in `AUDIT.md`).
- The rights stand in four places; the API checks differently from the pages
  (`AUDIT.md`, finding 3). The absence page and the contract page each decide access
  to another user themselves (not in `AUDIT.md`).
- The public holidays of a user are put together in seven places, often straight from
  the database (`AUDIT.md`, finding 4).
- Pages, API, a subscriber and a form extension use repositories directly, also for
  absences, bookings, public holidays and Kimai's users and teams
  (not in `AUDIT.md`).
- Two services build queries themselves: `WorkingTimeCalculator` and
  `AbsenceTimesheetService` (not in `AUDIT.md`).
- One setting has no effect and some functions nothing calls
  (`AUDIT.md`, finding 5).
- The plugin's own month lock (table, service, two addresses) has no page; Kimai's
  approval lock is the one in use (`AUDIT.md`, finding 6).
- Creating and changing an absence repeat the same steps (`AUDIT.md`, finding 7).
- Two names state the wrong format: the "Excel" export writes CSV, the "PDF" export
  HTML (`AUDIT.md`, finding 8).
- Small copies: the list of rights for administrators, the help address, the mark of
  a public holiday taken from an unrelated subscriber (`AUDIT.md`, finding 9).
