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
- Checks (CI, `.gitea/workflows/ci.yml`): PHP syntax 8.1–8.4, `php dev/check-translations.php`,
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

## Repository rule

- This repository lives on Gitea (`git.arianw.de`). GitHub is only a push mirror of it.
- Changes arrive as pull requests: work on a branch, open a PR, merge it on Gitea (the mirror follows).
- Never merge a PR, push to `main` (or any default branch), push tags or publish releases on GitHub. A merge there is overwritten by the next Gitea push.
- Never force-push a branch that someone else's PR depends on.
- PR-Agent (`.gitea/workflows/pr-agent.yml`) reviews every PR before it is merged. Wait for its comment on the PR's latest commit; after further pushes, ask for a new one with a `/review` comment. Fix or answer each finding in the PR, then merge. Without a review (the run skipped for lack of `PR_AGENT_LLM_KEY` or `PR_AGENT_MODEL`, or it failed), do not merge: ask the owner.
