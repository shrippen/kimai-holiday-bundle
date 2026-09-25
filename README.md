# Working Hours & Holidays

Open-source Kimai plugin for working hours, overtime, absences, and public holidays — an alternative to the commercial [WorkContractBundle / Controlling](https://www.kimai.org/en/store/controlling.html) plugin.

**Requires [Kimai](https://www.kimai.org/) ≥ 2.64.** Do **not** install alongside `WorkContractBundle`; remove the commercial plugin first. Permission names and UX intentionally overlap so existing docs and habits transfer.

> **Status / caveat:** This plugin was entirely vibe-coded. Not all functionality is tested end-to-end — treat it as early / experimental. In particular, **permissions around the absence approval workflow** (own vs other users, team lead vs admin, approve / reject / edit / delete) have not been fully verified. Test roles carefully before using this in production.

| | |
|---|---|
| **Kimai plugin id** | `HolidayBundle` |
| **License** | [GPL-3.0-or-later](LICENSE) |
| **PHP** | ≥ 8.1 |

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
- Absence calendar team report (year or month, requested vs. approved) + CSV export
- System settings: calculation modes (compensate vs reduce), comment required, workday timesheet restriction, auto absence timesheets
- REST API under `/api/holiday/...`

## Installation

1. Remove `WorkContractBundle` if it is installed.
2. Install this plugin as **`HolidayBundle`** under Kimai’s plugins directory (the folder must contain `HolidayBundle.php`).

### From GitHub

```bash
cd /path/to/kimai/var/plugins
git clone https://github.com/shrippen/kimai-holiday-bundle.git HolidayBundle
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

Public-holiday **Import** fetches an ICS calendar over HTTPS (Germany: [ics.tools](https://ics.tools/); other countries: Google holiday calendars or a custom URL). Stored feeds can be refreshed with:

```bash
bin/console kimai:bundle:holiday:sync-ics
```

Schedule that on the **host** if you want automatic updates (the official image has no cron).

3. Assign permissions under **System → Roles** (section *Working Hours & Holidays*).

## Usage

### Absences

**Employment contract → Absences** lists the absences of one user and year (`/holiday/absence/{year}`, `?user=` for other
users you may access). The tiles at the top show vacation taken, vacation requested, sick days and the remaining vacation
(entitlement from **Profile → Employment contract**).

- **Create** opens the absence form in a modal. Vacation, time off in lieu and "other" absences start as *Requested*;
  sickness is approved immediately.
- Click a row (or **… → Edit**) to change an absence. Saving an approved absence that needs approval sets it back to
  *Requested*.
- Approvers select requested absences with the checkboxes and use **Approve** / **Reject** below the table, or the row menu.
  Both run immediately; the notice offers **Undo**, which sets the absences back to *Requested*.
- **… → Delete** asks for confirmation. Timesheets created for the absence are removed, exported ones are kept.
- **Export** downloads the year as CSV.

### Personal calendar (ICS)

**Personal calendar (ICS)** on the absence page shows your subscription link (public holidays and approved absences) for
Outlook, Google Calendar or Apple Calendar. **Regenerate link** makes the old link invalid.

### Absence calendar

**Reporting → Absence calendar** shows the absences of your teams per year or month (`/holiday/absence-calendar/{year}`
or `/{year}/{month}`). Requested absences are shown faded, approved ones in full. Filter by team with the team picker.

### Public holidays

**Administration → Public holidays** manages public holiday groups. Select a group on the left, then add single holidays,
import a calendar (curated ICS feeds or a custom HTTPS URL) or sync a subscribed group. The number of imported holidays
is shown after the import.

### Manual bookings

**Employment contract → Working times**, absence menu (umbrella icon) next to the user picker → **Manual booking**, adds working time or vacation days to a user's balance. Bookings
cannot be edited; book the opposite amount to correct a mistake. The same menu opens the month PDFs.

## User interface

The plugin pages follow the shared UI guidelines and kit for Kimai plugins
([kimai-plugin-ui](https://github.com/shrippen/kimai-plugin-ui), `GUIDELINES.md` and `CHECKLIST.md`): Kimai page header with
actions, period navigator, Kimai data tables with row menu, status badges, KPI tiles, Kimai modals for forms and
confirmations. The kit is copied to `Resources/views/_kit/` and `Resources/translations/kpu.*.xlf` with
`kimai-plugin-ui/bin/sync.sh` and must not be edited here.

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

### Scope of the `*_other_*` permissions

Permissions for other users (`view_other_absence`, `edit_other_absence`, `approve_other_absence`, `delete_other_absence`, `hours_other_profile`, …) only apply to users you may access by Kimai's own rule (`access_user`):

- users with Kimai's **`view_all_data`** permission (by default `ROLE_ADMIN` / `ROLE_SUPER_ADMIN`) — all users,
- **team leads** — only members of the teams they lead (plus users that are in no team at all, same as Kimai core).

A team lead of *Team A* therefore cannot list, approve or reject absences of *Team B*, neither in the UI nor via the API.
The absence calendar only offers teams you lead (`view_other_absence`) or belong to (`view_team_absence`); admins with `view_all_data` see all teams.

The personal **ICS calendar link** is a secret of its owner: it is created when the owner opens their absence page. Admins (`view_all_data` + `edit_other_absence`) can see or regenerate an existing link of another user; team leads cannot. Feeds of disabled users return 404, and comments of sickness absences are never published in the feed.

## API (examples)

- `GET /api/holiday/absences?year=2026`
- `POST /api/holiday/absences` — JSON: `type`, `startDate`, `endDate`, `halfDay`, `comment`
- `POST /api/holiday/absences/{id}/approve|reject|request`
- `GET /api/holiday/absences/types`
- `GET /api/holiday/public-holidays?year=2026`
- `GET /api/holiday/public-holidays/calendar`

Dates must use `YYYY-MM-DD`. Invalid input (unknown type, bad date, end before start, more than one year, `duration` outside 0–86400 seconds, overlap with an existing absence) and approve/reject of an absence that is not `requested` return **400** with a `message`.

## Absence timesheets

With an absence project/activity configured (system settings) and calculation mode *compensate*, approving an absence creates one timesheet per workday. These entries are tagged with the timesheet meta field `holiday_absence_id`, are **not billable**, and replace the absence credit in the working-time balance (no double counting). They are removed when the absence is rejected, re-requested, edited or deleted — except entries that were already **exported**, which are kept. Absence days skip weekends, non-working days and full-day public holidays.

## Compatibility

- Declared Kimai version: **≥ 2.64** (`extra.kimai.require: 26400`)
- Incompatible with the paid WorkContractBundle (same feature area and permissions)

## Contributing

Issues and pull requests are welcome. Please target Kimai ≥ 2.64 and keep the install folder name `HolidayBundle`.

## License

This program is free software: you can redistribute it and/or modify it under the terms of the **GNU General Public License** as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

See [LICENSE](LICENSE) for the full text.

Copyright (C) 2026 Arian
