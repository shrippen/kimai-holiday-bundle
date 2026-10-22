# Review guide

Every PR is reviewed before it is merged (rule in `agent.md`): by PR-Agent
(owner, 22.10.2026); a new Claude agent checks the **Wichtig** of earlier
rounds, and reviews when PR-Agent fails. The
reviewer knows nothing of the session that wrote the change: it gets the
PR's intent, its diff and the repository, and this file. The owner does not read code; this review is the second reader.

## What failure looks like here

Working Hours & Holidays (`HolidayBundle`) is a Kimai plugin for working
hours, overtime, vacation, sickness and public holidays. A bug that matters
is one the user loses data or money to, or sees:

- **Counts wrongly:** target hours, overtime, vacation entitlement, half
  days, public holidays, month locks, year and time-zone boundaries.
- **Opens too much:** an absence approved, rejected, edited or deleted by
  someone without the right (own vs. other users, team lead vs. admin);
  other users' data through the API, an ICS feed (token only) or an
  export; a controller without the role or ownership check it needs.
- **Breaks the database:** a released migration edited instead of a new
  one; entities and migrations that do not match.
- **Looks broken:** own colours or copies of kit macros instead of Knust
  and the kit; a text missing in de or en (same keys in both).
- **Ships what it must not:** `demo/` or `dev/` in the release zip.
- **Lies:** README, CHANGELOG or comments that say something the code
  does not do.

## What to check in particular

- Layers: `Controller/`, `API/`, `Ics/` go through `Service/` (permissions
  in `AbsencePermissions`), then `Repository/`; permissions are declared
  per role in `DependencyInjection/HolidayExtension.php`.
- New pages get a check in `dev/ci.sh`.
- Bug fixes: does the fix remove the cause, with a test that failed before?
- `agent.md` code rules: braces always, early return, enums not bools,
  private by default (widening needs the owner's approval).

## What the owner confirms here

"What is published" (rule in `agent.md`) means here: a release (tag,
`.github/workflows/release.yml`, the zip) and the landing page `docs/`.

## Skip

- `Resources/views/_kit/` (vendored kit; check only that it matches the
  kit).
- `docs/shots/`, `docs/social-preview.png`, icons.

## How to report

- Every finding names file and line and the concrete input or state that
  goes wrong. No guess without evidence; if unsure, say so.
- Each finding gets one label: **Wichtig** (a bug from the list above,
  must be fixed or answered before the merge), **Kleinigkeit** (optional,
  at most five) or **Bestand** (the bug was there before this PR; not
  blocking).
- The report starts with the count, e.g. "2 Wichtig, 1 Kleinigkeit" (the
  session puts the heading above it). The count of **Wichtig** holds every
  one still open, also from an earlier round, e.g. "1 Wichtig (offen aus
  Runde 1)". Only when none is open it says plainly: "Nichts zu beheben."
- In a later round: say for each open **Wichtig** of the last round whether
  it is fixed, or whether its answer is accepted, with the reason. PR-Agent
  sees no earlier round: it reports only its own findings with their labels,
  and a new Claude agent closes the open ones (`agent.md`).
- Findings in German, code names as they are.
