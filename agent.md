- When writing something intended for human consumption, (comment, commit message, reply to prompt) use as few words as possible. Pick every word meticulously to reduce the volume to a strict minimum. Be down to the point. Less is more.

- Avoid superlatives and praise. Stop telling me I am absolutely right. Give me the cold hard truth.

- Avoid magic numbers and strings by extracting recurring or meaningful values into descriptive constants (const) or enums. Keep self-explanatory, one-off values inline to avoid clutter. If a value comes from a spec (e.g. HTTP 200 OK), use a constant regardless.

- Reduce code indentation. Avoid Arrow Anti-Pattern. Leverage early return and continue.

- Keep function names short. Less than 30 characters.

- Use enums instead of booleans for function parameters.

- Let the reader of the code breathe. Add empty lines between logical blocks of code.

- Add a small, to the point, comment to explain *what* the block does and *why*. Use examples when possible. Propose ASCII drawings to explain complete systems.

- Treat member visibility changes as a breaking design shift. Keep all fields and functions private unless external access is strictly required by the design. Prompt the user for explicit approval before changing any access modifier from private to internal or public.

- Program to levels of abstraction. Lower-level mechanics (e.g., raw hardware I/O, sector parsing, direct socket streams) must be encapsulated in a dedicated driver/abstraction layer. Expose clean, high-level APIs to the rest of the application so calling code works with domain concepts, not raw implementation details.

- Don't touch blocks of code unrelated to the feature you implement. e.g. Don't add comments to a block of code if you did not create it or modify it. As much as possible try to minimize the number of changed lines when implementing a feature.

- Strictly adhere to the layered boundary hierarchy: each layer may only communicate with its immediate neighbor directly below it. Never "punch holes" through layers (e.g., controllers or UI components must never directly call database queries, raw hardware drivers, or low-level network clients; always route through the intermediate service/abstraction layer).

- Always use {}, even on a one-line "if" statement.

When you write a commit message, follow these 7 rules:
Rule 1: Separate the subject line from the body with a single blank line.
Rule 2: Limit the subject line to 50 characters (72 is the absolute hard limit).
Rule 3: Capitalize the first letter of the subject line.
Rule 4: Do not end the subject line with a period.
Rule 5: Use the imperative mood in the subject line (e.g., "Fix bug," "Add feature,"
        not "Fixed" or "Adds"). Test formula: It must complete the sentence: "If applied,
        this commit will [your subject line here]".
Rule 6: Wrap the body text manually at 72 characters to prevent Git formatting issues.
Rule 7: Use the body to explain what and why vs. how. Assume the code explains the how;
        the message must explain the context and reasoning.

- If the prompt indicates that a bug is being fixed, don't write the fix right away. First write the test. Observe it failing. Then write the fix. And observe the test passing.
  - This holds for every bug, however it was found (a user report, a review, your own run),
    and for performance problems and crashes too: stutter, late frames, a hang, a panic, a
    leak. The test stays in the suite; a one-off script run is not a test.
  - Name the failure in the commit or PR: the test and what it printed before the fix.

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

## GUI rule

- This project is a Kimai plugin. Its GUI is generated from Knust (`kimai/knust/`) and the
  Kimai plugin UI kit (`kimai/kit/`, `kpu-*` markers and macros), both in
  https://github.com/shrippen/Kante (checkout `../Kante`). Knust is the Kante spinoff that
  adapts Kante to Kimai's look. Use their tokens, classes, markers, macros and components as
  they are, not inspired by them.
- The kit in `Resources/views/_kit/` is vendored unchanged; it changes only through
  `../Kante/kimai/kit/bin/sync.sh <plugin>` (`../Kante/tools/check-kit.sh` shows whether it is current).
- No own colours, fonts, sizes, radii, shadows, focus styles, animation timings, no own copy or
  variant of a component that Knust or the kit has. Raw values (`#hex`, `px` for controls) are a bug.
  Colours needed in JavaScript (charts) are read at runtime from `--knust-*` with a fallback.
- A missing element is added to Knust or the kit first (in the Kante repo), then used here.
  Never solve it locally. Where it would also help other projects, it is added to Kante as well.
- The landing page (`docs/`) is not Kimai: it uses Kante (`https://shrippen.github.io/v1/`).
- Rule text: https://github.com/shrippen/Kante/blob/main/AGENT-RULE.md

## Release rule

Release steps, in this order (`X.Y.Z` the new version, `A.B.C` the previous one). A release is done only after step 10.

1. On a branch from `master`, bump the version in `composer.json` (`version`; the release workflow checks the tag against it). Nothing else holds it.
2. Write the release log before the tag in `CHANGELOG.md`, the single source: a section `## [X.Y.Z] — YYYY-MM-DD` at the top (newest first), under `### English` and `### Deutsch` each a lead (what the release is about), the changes grouped as `#### New`, `Improved`, `Fixed` and `Note` (breaking changes, steps the user must take, e.g. `kimai:bundle:holiday:install` for a new table) and the compare link (`https://github.com/shrippen/kimai-holiday-bundle/compare/vA.B.C...vX.Y.Z`). Each point says what changed for the user and why, not which code moved. The internal demo is never mentioned. Check the headings with `scripts/release-notes.sh --check` (CI runs it on every push) and read the notes with `scripts/release-notes.sh X.Y.Z`.
3. Landing page `docs/index.html` (Kante `.changelog`): the same text, in English and German, as a new entry at the top of `#changes`; from the fourth entry on, the oldest move into `details.changelog-more`. Bump the footer version (`vX.Y.Z`, linked to `#changes`). Graphics wherever possible: a screenshot from the demo world per visible change (`docs/shots/release-<version>-<name>-<lang>.webp`; shots in `demo/shots.json`, taken with `../shrippen.github.io/demo/tools/.venv/bin/python ../shrippen.github.io/demo/tools/screenshots.py kimai-holiday`), a diagram (Kante `.flow` or inline SVG) for changes inside. Screenshots are taken now, for the tagged release, not with every change.
4. Open the PR on Gitea, review it as the Repository rule says (a release is published: the owner confirms it in words), merge on Gitea.
5. Tag the merge commit and push the tag to Gitea only: `git tag vX.Y.Z <merge commit> && git push origin vX.Y.Z` (the mirror carries it to GitHub).
6. Gitea release, notes only, never files: `scripts/release-notes.sh X.Y.Z > notes.md`, then `tea release create --repo shrippen/kimai-holiday-bundle --tag vX.Y.Z --title "Holiday X.Y.Z" --note-file notes.md`.
7. GitHub: the tag starts `.github/workflows/release.yml`. It checks the tag against `composer.json`, builds `HolidayBundle-X.Y.Z.zip` (fails if `demo/` is in it), and publishes the release "Holiday X.Y.Z" with the same notes (fails without the section) and the zip. Check the run (`gh run list -R shrippen/kimai-holiday-bundle --workflow release.yml`) and the release (`gh release view vX.Y.Z -R shrippen/kimai-holiday-bundle`: notes and zip).
8. Update check: `python3 ../shrippen.github.io/overview/tools/build-versions.py`, then commit `docs/versions.json` in `shrippen.github.io` via a PR there.
9. Deploy to the live instance as `../CLAUDE.md` "Kimai-Plugins deployen" says, from a clean checkout of the tag (no local files):
   - `rsync -a -i --dry-run --filter=':- .gitignore' --exclude={.git,.gitea,.github,dev,tests,research,docs,reference,scripts,demo} --include=README.md --exclude='*.md' ./ regis.lan:/mnt/ApertureScience/docker/appdata/kimai/plugins/HolidayBundle/`; check the list, then run it without `--dry-run`.
   - In the same SSH step: `chown -R www-data:www-data /mnt/ApertureScience/docker/appdata/kimai/plugins/HolidayBundle`, directories `770`, files `664`.
   - `docker exec -u www-data kimai_app /opt/kimai/bin/console kimai:reload -n`, then `… console kimai:bundle:holiday:install` (migrations). Never without `-u www-data`.
   - Check: `docker logs --since 2m kimai_app | grep -E 'CRITICAL| 500 '` and `find /mnt/ApertureScience/docker/appdata/kimai/plugins/HolidayBundle -not -user www-data` and `docker exec kimai_app find /opt/kimai/var -not -user www-data` are empty, the login page answers 200, the API without token 401 (not 500).
10. Done when https://shrippen.github.io/kimai-holiday-bundle/ shows X.Y.Z at the top of `#changes` and in the footer, and https://shrippen.github.io/versions.json lists `kimai-holiday` with X.Y.Z.

`scripts/release-notes.sh` is a copy of Kante's `tools/release-notes.sh`: change it only there, then sync with `../Kante/tools/sync-release-notes.sh "$PWD"`.

## Repository rule

- This repository lives on Gitea (`git.arianw.de`). GitHub is only a push mirror of it.
- CI runs on the GitHub mirror (`.github/workflows/ci.yml`, since 16.10.2026) and reports back to Gitea as the commit status `github / test`.
- Changes arrive as pull requests: work on a branch, open a PR, merge it on Gitea (the mirror follows).
- Never merge a PR, push to `main` (or any default branch), push tags or publish releases on GitHub. A merge there is overwritten by the next Gitea push.
- Never force-push a branch that someone else's PR depends on.
- Review (all shrippen repositories, owner 18.10. and 22.10.2026): every PR is reviewed before it is merged. The owner does not read code, so this is the second reader.
  - PR-Agent first (owner, 22.10.2026): where `.gitea/workflows/pr-agent.yml` is set up, the session comments `/review` on the PR for each round with new code or a new base (the first one included; a rebase, a merge of the base or a change of the base branch, see below) and waits for PR-Agent's comment. That comment reviews the whole diff and covers the commit that was the PR's head when `/review` was posted. PR-Agent labels its findings as `REVIEW.md` says; a finding without a label counts as **Wichtig**. A run cancelled by a newer `/review` on the same PR (the concurrency group holds the PR number) is replaced, not failed.
  - If PR-Agent fails (the run fails or ends without a review comment, reports a key or cost limit, or no run is created within 10 minutes), the session posts `/review` once more. If that fails too, a new Claude agent reviews the round as below, and the session says in the PR why. Where PR-Agent is not set up, the new Claude agent always reviews.
  - The Claude agent: the session that wrote the PR starts a new agent (Agent tool, no shared context) and gives it only the PR's title and body, the diff against its base (`git diff <base>...HEAD`), the repository and `REVIEW.md`. It does not tell it what it thinks of its own change. The agent checks every finding it would post against the code (file, line, the input that goes wrong) and drops what it cannot show. It reports as `REVIEW.md` says: count first, labels **Wichtig**, **Kleinigkeit**, **Bestand**. The session posts the report unchanged as a PR comment on Gitea. Above it goes the heading `## Claude-Review (neuer Agent) zu <commit>`, below it the footer `---` and `_Generated by [Claude Code](https://claude.ai/code)_`.
  - Each **Wichtig** is fixed, or answered in the PR with a reason.
  - PR-Agent sees no earlier round. So when an earlier round had a **Wichtig** (open or closed), after PR-Agent's comment (or with answers only, alone) a new Claude agent closes the round: it gets the PR's title and body, the repository, `REVIEW.md`, the open **Wichtig** with the answers, PR-Agent's findings of this round, the closed **Wichtig** and how they were closed, and the new commits (`git diff <last reviewed commit>..HEAD`; without new commits, the code at HEAD). It says for each open **Wichtig** whether it is fixed or its answer is accepted, and whether a **Wichtig** PR-Agent raises again is one already closed (fixed, answer accepted or decided by the owner; it stays closed unless the agent shows it is not fixed). Its count holds every **Wichtig** still open, PR-Agent's new ones included. It posts like a review, heading `## Claude-Review (offene Wichtig) zu <commit>`. An answer it does not accept goes to the owner, who decides in words. With the fallback, one new agent does both: the review of the new commits and the open **Wichtig**.
  - After a rebase, a merge of the base or a change of the PR's base branch a new round is due, also without a new commit, against the new base; a Claude agent of that round (closing or fallback) gets the whole diff against the new base (`git diff <base>...HEAD`) in place of the new commits. Every round counts, also one with answers only: after three rounds that still leave a **Wichtig** open, stop and rethink the approach with the owner.
  - Merge only when the last round covers the PR's latest commit against its current base branch (the branch by name; the base moving on alone needs no new round) and leaves no **Wichtig** open (its closing agent's count, or PR-Agent's comment when no earlier round had a **Wichtig**), and CI is green on the PR's latest commit: `github / test` from the GitHub mirror (`.github/workflows/ci.yml`, runs on each push) shows success there. No status there yet means wait, not merge; if it never comes, check the mirror or ask the owner. PR-Agent's status does not count here.
  - Before the merge, the owner confirms in words, not by reading code, a PR that changes CI or release workflows (`.gitea/`, `.github/`), this review rule (`agent.md`, `REVIEW.md`), secrets or what is published (a release, a tag, a new public page, site or package; rebuilding what is already published does not count).
  - `REVIEW.md` in the repository root says what failure looks like here, what to check in particular, what to skip and how to report. A repository without one gets it in its next PR.
- PR-Agent's status is not a CI check. The owner may post `/review` at any time (PR-Agent takes no other command here); the merge waits for that run and handles its findings like a round's. Owner and session post from the same account, so a run that fails is handled the same whoever posted `/review`: as above, once more, then the fallback.
