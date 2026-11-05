# Working Hours & Holidays

Fork of [shrippen/kimai-holiday-bundle](https://github.com/shrippen/kimai-holiday-bundle) for [zafe](https://zafe.at), licensed GPL-3.0-or-later. Adds shift planning ([spec](docs/superpowers/specs/2026-10-23-kimai-dienstplan-design.md)). Requires Kimai ≥ 2.64, tested with 2.70.2.

Open-source Kimai plugin for working hours, overtime, absences, and public holidays — an alternative to the commercial [WorkContractBundle / Controlling](https://www.kimai.org/en/store/controlling.html) plugin.

**Requires [Kimai](https://www.kimai.org/) ≥ 2.64.** Do **not** install alongside `WorkContractBundle`; remove the commercial plugin first. Permission names and UX intentionally overlap so existing docs and habits transfer.

> **Status / caveat:** This plugin was entirely vibe-coded. Not all functionality is tested end-to-end — treat it as early / experimental. In particular, **permissions around the absence approval workflow** (own vs other users, team lead vs admin, approve / reject / edit / delete) have not been fully verified. Test roles carefully before using this in production.

| | |
|---|---|
| **Kimai plugin id** | `HolidayBundle` |
| **License** | [GPL-3.0-or-later](LICENSE) |
| **PHP** | ≥ 8.2 |

## Features

- Extends **Profil → Arbeitsvertrag** with vacation days, public-holiday group, and contract start/end
- Extends **Arbeitsvertrag / Arbeitszeiten** (adds **Abwesenheit** — no duplicate sidebar section)
- Absences and public holidays in the core Arbeitszeiten year view (including known future days)
- Absences: vacation (half-day), sickness (+ relative), time off in lieu (Freizeitausgleich), other — approval workflow with bulk approve/reject and undo, email notifications
- Edit absences (resets approval when the type requires it)
- Vacation balance (taken, requested, sick days, remaining) on the absence page
- Per-user ICS calendar feed (public holidays + approved absences) for Outlook / Google / Apple
- Public holiday groups, manual entry, ICS import (curated calendars + custom ICS URL) and sync
- Absences & public holidays on the Kimai calendar
- Day shifts (Tagesverschiebung): move a contract working day to another day of the same week, with approval
- Absence calendar team report (year or month, requested vs. approved) + CSV export
- System settings: calculation modes (compensate vs reduce), comment required, workday timesheet restriction, auto absence timesheets
- REST API under `/api/holiday/...`

## Installation

1. Remove `WorkContractBundle` if it is installed.
2. Install this plugin as **`HolidayBundle`** under Kimai’s plugins directory (the folder must contain `HolidayBundle.php`).

### From GitHub

```bash
cd /path/to/kimai/var/plugins
git clone https://github.com/zafe-apps/kimai-holiday-bundle.git HolidayBundle
```

Or download a release archive and extract it to `var/plugins/HolidayBundle/`.

### Activate

```bash
bin/console kimai:reload -n
bin/console kimai:bundle:holiday:install
```

### Docker

Kimai’s app root in the official image is `/opt/kimai/`. Use the full console path:

```bash
# Example: plugin mounted at /opt/kimai/var/plugins/HolidayBundle
docker exec -it CONTAINER /opt/kimai/bin/console kimai:reload -n
docker exec -it CONTAINER /opt/kimai/bin/console kimai:bundle:holiday:install
```

**Updating:** run the reload as the web server user, e.g.
`docker exec -u www-data -w /opt/kimai kimai bin/console kimai:reload -n`, then `kimai:bundle:holiday:install` (migrations). Running
the reload as root leaves `var/cache` owned by root and breaks pages that need an un-warmed translation catalogue
(HTTP 500). `chown -R www-data:www-data var/cache` heals it.

Public-holiday **Import** fetches an ICS calendar over HTTPS (Germany: [ics.tools](https://ics.tools/); other countries: Google holiday calendars or a custom URL). Stored feeds can be refreshed with:

```bash
bin/console kimai:bundle:holiday:sync-ics
```

Schedule that on the **host** if you want automatic updates (the official image has no cron).

3. Assign permissions under **System → Roles** (section *Working Hours & Holidays*).

## Usage

### Absences

**Employment contract → Absences** lists the absences of one user and year (`/holiday/absence/{year}`, `{year}` = index of the vacation year (0 = first employment year; calendar year number without start date), `?user=` for other
users you may access). The tiles at the top show vacation taken, vacation requested, sick days and the remaining vacation
(entitlement from **Profile → Employment contract**).
The vacation year runs from the contract start date (anniversary to anniversary) and the balance of the previous year
carries over automatically; without a start date the calendar year is used and nothing carries over.

- **Create** opens the absence form in a modal. Vacation, time off in lieu and "other" absences start as *Requested*;
  sickness is approved immediately.
- Click a row (or **… → Edit**) to change an absence. Saving an approved absence that needs approval sets it back to
  *Requested*, unless an approver edits someone else's absence (it stays approved).
- Approvers select requested absences with the checkboxes and use **Approve** / **Reject** below the table, or the row menu.
  Both run immediately; the notice offers **Undo**, which sets the absences back to *Requested*. Undo works for
  15 minutes, only for the approver who acted, in the same browser session, and only for absences nobody changed since.
- **… → Delete** asks for confirmation. Timesheets created for the absence are removed, exported ones are kept.
- **Export** downloads the year as CSV.

### Personal calendar (ICS)

**Personal calendar (ICS)** on the absence page shows your subscription link (public holidays and approved absences) for
Outlook, Google Calendar or Apple Calendar. **Regenerate link** makes the old link invalid.

### Absence calendar

**Reporting → Absence calendar** shows the absences of your teams per year or month (`/holiday/absence-calendar/{year}`
or `/{year}/{month}`). Approved absences and each user's public holidays are shown as bars,
requested absences as faded icons; today is framed. Filter by team with the team picker.

### Public holidays

**Administration → Public holidays** manages public holiday groups. Select a group on the left, then add single holidays,
import a calendar (curated ICS feeds or a custom HTTPS URL) or sync a subscribed group. The number of imported holidays
is shown after the import. Editing a group's exclusion list applies from the next sync or import; holidays already
imported stay until they are deleted.

### Manual bookings

**Employment contract → Working times**, absence menu (umbrella icon) next to the user picker → **Manual booking**, adds working time or vacation days to a user's balance. Bookings
cannot be edited; book the opposite amount to correct a mistake. The same menu opens the month PDFs.

### Locations and availability

**Administration → Locations** (`view_location`, edit with `edit_location`) manages locations:

- name, public holiday group, active flag
- opening hours per weekday (empty = closed)
- exceptions per date: closed, or special opening with its own times
- members; a person belongs to at most one location

A date is an **opening day** of a location by this rule, first match wins: exception for the date; full-day public
holiday of the location's holiday group (closed); the weekday pattern. An inactive location is always closed. A holiday
group used by a location cannot be deleted.

**Reports → Availability** (`view_availability`) shows who is on site, per location (a selector appears with more than
one visible location). The week is a row of day cards, the month a table with open days only. A member is **present** on
an open day when the contract has working time that day, or when an extra shift exists, and there is no approved
full-day absence. A half-day absence still counts as present and is marked. Further lists per day: **open** (requested,
not yet approved absence) and **absent**. A day without anyone present is flagged **Unbesetzt**. Closed days have nobody
present.

**Extra shift** (Aushilfe): a person works on a day without a contract working day. It needs no approval. Rules: no
contract working day, no approved absence, an opening day of the person's own location, one entry per person and day.
Create it with the button on the availability page; delete it from the day card (creator, or
`extra_shift_own` / `extra_shift_other` for the person). The absence calendar shows extra shifts with a plus icon.

### Day shifts

A **day shift** (Tagesverschiebung) moves one contract working day to another day of the same week, for example
Friday to Thursday. It is hours-neutral: the old day becomes a free day, the expected hours of the old day move to the new
day.

Rules, checked when the shift is requested and again when it is approved:

- both days are in the same ISO week and differ
- the source day is a contract working day, not a public holiday (full or half), and is not part of another open or approved shift
- the target day is not a contract working day, not a public holiday (full or half), inside the contract period, and not part of
  another shift
- the person belongs to a location, and the location is open on the target day (see opening day rule above)
- no approved absence on either day (also blocks deleting or taking back an approved shift), no extra shift on the
  target day
- no locked period on either day (Kimai approval or plugin month lock)

A shift needs approval by an approver who is not the requester (nobody approves their own shift, admins included).
Approvers are resolved with the permission `approve_shift`, in the same scope as for absences. An approver who creates a
shift for someone else gets it approved directly. Status flow: *Requested* → *Approved* / *Rejected*. After approval the
shift is not edited: delete it and request again.

**What approval does:** the expected hours (`UserWorkContract::getExpectedSecondsForDate`) follow the shift. Vacation and
sickness are counted on the new day, the working-time balance and the working-time year view use the new day, and the
workday timesheet restriction and automatic absence timesheets apply to the new day. The old day counts as free (no
expected hours, no vacation deducted there).

**Request:** on **Reports → Availability** use the action **Move a day** (needs `shift_own`; for another person
`shift_own` and `shift_other`). The form takes the person, the day to move, the new day and a comment.

**Decide:** **Open requests** (`holiday_absence_requests`) lists day shifts in a second table below the absences, with
the row menu: approve / reject per row. The result toast offers *Undo* (**reopen**, back to *Requested*).
Delete an approved or open shift from the row menu or the availability page (a confirmation mail goes to the requester
and approvers when an approved shift is withdrawn). The menu badge counts open absences and open shifts that you may approve.

**Mails:** request, approval, rejection and withdrawal go to the requester and all approvers of the shift.

**Where it shows:**

- Availability (week and month): the person is present on the new day, marked "shifted from dd.mm."; open requests are
  listed as *open* on both days
- Absence calendar: entries of type `workday_shift` (repeat icon, faded while requested)
- Working-time year view: addons "shifted to" / "shifted from" on the two days

Pages: `/holiday/workday-shifts/create` (`holiday_workday_shift_create`), `/holiday/workday-shifts/approve` and
`/reject` (`holiday_workday_shift_bulk_approve`, `holiday_workday_shift_bulk_reject`, POST),
`/holiday/workday-shifts/reopen/{action}` (`holiday_workday_shift_reopen`, POST),
`/holiday/workday-shifts/{id}/delete` (`holiday_workday_shift_delete`, POST). All POST routes need a CSRF token. There is no
API and no ICS/FullCalendar export for shifts yet.

## User interface

The plugin pages follow the shared UI guidelines and kit for Kimai plugins
([kimai-plugin-ui](https://github.com/shrippen/Kante/tree/main/kimai/kit), `GUIDELINES.md` and `CHECKLIST.md`): Kimai page header with
actions, period navigator, Kimai data tables with row menu, status badges, KPI tiles, Kimai modals for forms and
confirmations. The kit is copied to `Resources/views/_kit/` and `Resources/translations/kpu.*.xlf` with
`kimai/kit/bin/sync.sh` in [Kante](https://github.com/shrippen/Kante) and must not be edited here.

Translation keys of this plugin all start with `holiday.`.

## Permissions

| Permission | Purpose |
|---|---|
| `hours_own_profile` / `hours_other_profile` | Working times screen |
| `contract_other_profile` | Edit other users’ contracts |
| `view_booking_contract` / `create_booking_contract` | PDF / manual bookings |
| `approve_times_contract` / `unlock_times_contract` | Month lock / unlock |
| `workdays_override_timesheet` | Bypass workday timesheet restriction |
| `absence`, `edit_*_absence`, `delete_*_absence` | Absence UI |
| `view_team_absence` / `view_other_absence` | Team / other users in reports |
| `approve_*_absence` / `approval_other_absence` | Approval workflow |
| `edit_public_holidays` | Admin public holidays |
| `view_location` / `edit_location` | See / edit locations |
| `view_availability` | Availability view |
| `extra_shift_own` / `extra_shift_other` | Create extra shifts for yourself / others (others: `access_user` scope) |
| `shift_own` / `shift_other` | Request day shifts for yourself / others (others: `access_user` scope) |
| `approve_shift` | Approve or reject day shifts (not your own; `access_user` scope) |

Defaults: `ROLE_USER` gets `view_availability`, `extra_shift_own` and `shift_own`; `ROLE_TEAMLEAD` additionally
`view_location`, `extra_shift_other`, `shift_other` and `approve_shift`; `ROLE_ADMIN` and `ROLE_SUPER_ADMIN` all of them.

Pages: `/holiday/locations`, `/holiday/availability`, `/holiday/availability/{year}/week/{week}`,
`/holiday/availability/{year}/month/{month}`, `/holiday/extra-shifts/create`, `/holiday/extra-shifts/{id}/delete`.

### Scope of the `*_other_*` permissions

Permissions for other users (`shift_other`, `approve_shift`, `view_other_absence`, `edit_other_absence`, `approve_other_absence`, `delete_other_absence`, `hours_other_profile`, …) only apply to users you may access by Kimai's own rule (`access_user`):

- users with Kimai's **`view_all_data`** permission (by default `ROLE_ADMIN` / `ROLE_SUPER_ADMIN`) — all users,
- **team leads** — only members of the teams they lead (plus users that are in no team at all, same as Kimai core).

A team lead of *Team A* therefore cannot list, approve or reject absences of *Team B*, neither in the UI nor via the API.
The absence calendar only offers teams you lead (`view_other_absence`) or belong to (`view_team_absence`); admins with `view_all_data` see all teams.

The personal **ICS calendar link** is a secret of its owner: it is created when the owner opens their absence page. Admins (`view_all_data` + `edit_other_absence`) can see or regenerate an existing link of another user; team leads cannot. Feeds of disabled users return 404, and comments of sickness absences are never published in the feed. The token is stored in the plugin table `kimai2_ext_holiday_ics_token`, not in the user preferences, so it is not part of `/api/users/…` responses or invoice/export templates.

## API (examples)

- `GET /api/holiday/absences?year=2026` (`year` is a calendar year, as in the ICS feed; the UI uses vacation years)
- `POST /api/holiday/absences` — JSON: `type`, `startDate`, `endDate`, `halfDay`, `comment`
- `POST /api/holiday/absences/{id}/approve|reject|request`
- `GET /api/holiday/absences/types`
- `GET /api/holiday/public-holidays?year=2026`
- `GET /api/holiday/public-holidays/calendar`

Dates must use `YYYY-MM-DD`. Invalid input (unknown type, bad date, end before start, more than one year, `duration` outside 0–86400 seconds, overlap with an existing absence) and approve/reject of an absence that is not `requested` return **400** with a `message`.

## Absence timesheets

With an absence project/activity configured (system settings) and calculation mode *compensate*, approving an absence creates one timesheet per workday. These entries are tagged with the timesheet meta field `holiday_absence_id`, are **not billable**, and replace the absence credit in the working-time balance (no double counting). They are removed when the absence is rejected, re-requested, edited or deleted — except entries that were already **exported**, which are kept. Absence days skip non-working days (no contract hours) and full-day public holidays.

## Compatibility

- Declared Kimai version: **≥ 2.64** (`extra.kimai.require: 26400`)
- Incompatible with the paid WorkContractBundle (same feature area and permissions)

## Development

`dev/kimai-dev.sh` starts the Docker dev stack (Kimai on `http://localhost:8001`), `dev/test.sh` runs PHPUnit.
`node dev/screenshot.mjs <out.png> <url> [width]` takes a styled screenshot of a dev-stack page with headless Chrome
(Node >= 22, no dependencies; `--user`/`--password` default to `admin`/`admin1234`, `CHROME_BIN` overrides the Chrome
path), e.g. `node dev/screenshot.mjs /tmp/a.png http://localhost:8001/de/holiday/availability`.

## Contributing

Issues and pull requests are welcome. Please target Kimai ≥ 2.64 and keep the install folder name `HolidayBundle`.

## License

This program is free software: you can redistribute it and/or modify it under the terms of the **GNU General Public License** as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

See [LICENSE](LICENSE) for the full text.

Copyright (C) 2026 Arian
