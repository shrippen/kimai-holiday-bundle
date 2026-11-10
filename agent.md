# Working rules for this repository

The rules for all shrippen repositories are in `RULES.md` (writing, code, commits, bug
fixes, repository, GUI). It is a synced copy from the rules repository; do not edit it
here. This file holds only what is particular to this repository. A Claude Code session on
the owner's machine has the rules through the global `CLAUDE.md` already and reads
`RULES.md` only for its GUI rule, before work on a user interface.

## Project

Working Hours & Holidays (`HolidayBundle`, Kimai ≥ 2.64): working hours, overtime, vacation,
sickness and public holidays; an open-source alternative to `WorkContractBundle` (same permission
names). Never install both.

- `Controller/`, `API/` (`/api/holiday/…`), `Ics/` (token feeds, no session) → `Service/`
  (calculation, approval, permissions in `AbsencePermissions`) → `Repository/` → `Entity/`.
- Schema changes only as new migrations in `Migrations/`, applied by
  `kimai:bundle:holiday:install`; released migrations are never edited.
- Permissions are declared in `DependencyInjection/HolidayExtension.php` per role.
- Texts in `Resources/translations/*.{de,en}.xlf`, same keys in both.
- Checks (CI, `.github/workflows/ci.yml`): PHP syntax 8.1–8.4, `php dev/check-translations.php`,
  and `dev/ci.sh`, which installs the release zip into Kimai 2.67, runs the migrations and loads
  the pages, API and ICS feed.
- `demo/` (screenshots) and `dev/` never ship: `.gitattributes` keeps them out of the zip,
  `services.yaml` excludes them.

## Release

Release steps: `RELEASE.md`. Before a release, load the skill `release-changelog`. A release
is published, so the owner confirms it in words.

## Repository (this project)

- CI runs on the GitHub mirror (`.github/workflows/ci.yml`, since 16.10.2026) and reports back to Gitea as the commit status `github / test`.
- The merge waits for: CI is green on the PR's latest commit: `github / test` from the GitHub mirror (`.github/workflows/ci.yml`, runs on each push) shows success there. No status there yet means wait, not merge; if it never comes, check the mirror or ask the owner. PR-Agent's status does not count here.
- Everything else: `RULES.md` (Repository) and the skill `pr-review`.
