# Working Hours & Holidays

Open-source Kimai plugin for working hours, overtime, absences, and public holidays — an alternative to the commercial [WorkContractBundle / Controlling](https://www.kimai.org/en/store/controlling.html) plugin.

**Requires [Kimai](https://www.kimai.org/) ≥ 2.64.** Do **not** install alongside `WorkContractBundle`; remove the commercial plugin first. Permission names and UX intentionally overlap so existing docs and habits transfer.

| | |
|---|---|
| **Kimai plugin id** | `HolidayBundle` |
| **License** | [GPL-3.0-or-later](LICENSE) |
| **PHP** | ≥ 8.1 |

## Features

- Extends **Profil → Arbeitsvertrag** with vacation days, public-holiday group, and contract start/end
- Extends **Arbeitsvertrag / Arbeitszeiten** (adds **Abwesenheit** — no duplicate sidebar section)
- Absences and public holidays in the core Arbeitszeiten year view (including known future days)
- Absences: vacation (half-day), sickness (+ relative), Freizeitausgleich, other — approval workflow and email notifications
- Edit absences (resets approval when the type requires it)
- Urlaubskonto on the absence page
- Per-user ICS calendar feed (public holidays + approved absences) for Outlook / Google / Apple
- Public holiday groups, manual entry, ICS import (curated calendars + custom ICS URL) and sync
- Absences & public holidays on the Kimai calendar
- Absence calendar team report + CSV export
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

## API (examples)

- `GET /api/holiday/absences?year=2026`
- `POST /api/holiday/absences` — JSON: `type`, `startDate`, `endDate`, `halfDay`, `comment`
- `POST /api/holiday/absences/{id}/approve|reject|request`
- `GET /api/holiday/absences/types`
- `GET /api/holiday/public-holidays?year=2026`
- `GET /api/holiday/public-holidays/calendar`

## Compatibility

- Declared Kimai version: **≥ 2.64** (`extra.kimai.require: 26400`)
- Incompatible with the paid WorkContractBundle (same feature area and permissions)

## Contributing

Issues and pull requests are welcome. Please target Kimai ≥ 2.64 and keep the install folder name `HolidayBundle`.

## License

This program is free software: you can redistribute it and/or modify it under the terms of the **GNU General Public License** as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

See [LICENSE](LICENSE) for the full text.

Copyright (C) 2026 Arian
