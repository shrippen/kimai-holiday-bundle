# CLAUDE.md

## Zweck

Fork von `shrippen/kimai-holiday-bundle` für zafe (`HolidayBundle`, Kimai >= 2.64, getestet 2.70.2):
Arbeitszeit, Urlaub, Krankheit, Feiertage, dazu Dienstplanung. Spec:
`docs/superpowers/specs/2026-10-23-kimai-dienstplan-design.md`. Nie zusammen mit `WorkContractBundle`
installieren.

## Architektur

- `Controller/`, `API/` (`/api/holiday/…`), `Ics/` (Token-Feeds, ohne Session) -> `Service/` ->
  `Repository/` -> `Entity/`. Keine Schicht überspringen.
- Schema nur über neue Migrationen in `Migrations/` (angewendet von `kimai:bundle:holiday:install`);
  veröffentlichte Migrationen nie editieren.
- Berechtigungen je Rolle in `DependencyInjection/HolidayExtension.php`.
- Texte in `Resources/translations/*.{de,en}.xlf`, gleiche Keys in beiden Sprachen
  (`php dev/check-translations.php`).
- `demo/` und `dev/` werden nicht ausgeliefert (`.gitattributes`, `services.yaml`).

## GUI rule

- Das Plugin ist ein Kimai-Plugin. Die GUI nutzt Knust und das Kimai-Plugin-UI-Kit
  (`kpu-*`-Marker und Macros) unverändert: deren Tokens, Klassen, Marker, Macros und Komponenten,
  nicht davon inspiriert.
- Das Kit in `Resources/views/_kit/` ist vendored und wird lokal nicht geändert. Aktualisierung nur
  über den Upstream-Sync.
- Keine eigenen Farben, Schriften, Größen, Radien, Schatten, Fokus-Stile, Animationszeiten und keine
  eigene Kopie oder Variante einer Komponente, die Knust oder das Kit hat. Rohwerte (`#hex`, `px`
  für Controls) sind ein Fehler. Farben für JavaScript (Charts) zur Laufzeit aus `--knust-*` lesen,
  mit Fallback.
- Die Landingpage (`docs/`) ist nicht Kimai.
- UI-Regeln für eigene Seiten: `.claude/rules/*.md`. Review-Agenten: `.claude/agents/`.

## Tests und Dev-Stack

- Tests: `dev/test.sh`. Dev-Stack: `dev/kimai-dev.sh`.
- CI (`.github/workflows/ci.yml`): PHP-Syntax 8.2–8.4, Übersetzungsprüfung und `dev/ci.sh` (Release-Zip
  in Kimai 2.70.2 installieren, Migrationen, Seiten, API, ICS).
- Bugfix: erst der fehlschlagende Test, dann der Fix.

## Git

- Branch -> PR auf GitHub (`zafe-apps/kimai-holiday-bundle`) -> Merge nach grünem CI.
- Jeder Merge in `master` deployt automatisch nach `zafe-server-1` (`docs/deploy.md`); Rollouts von Hand
  nur noch, wenn der Deploy scheitert.
- Upstream ist Remote `upstream`; Bugfixes von dort per Cherry-Pick.
- Nie force-pushen, wenn ein PR von der Branch abhängt.
- Commits: deutsch, Betreff im Imperativ, höchstens 50 Zeichen, groß, ohne Punkt; Leerzeile; Body
  (72 Zeichen Umbruch) erklärt was und warum, nicht wie.

## Code-Stil

- Wenige Worte in Kommentaren und Commits, keine Superlative.
- Konstanten oder Enums statt Magic Numbers/Strings; Enums statt Boolean-Parametern.
- Früh zurückkehren (`return`, `continue`), flache Einrückung. Immer `{}` bei `if`.
- Funktionsnamen kürzer als 30 Zeichen. Leerzeilen zwischen logischen Blöcken.
- Kurzer Kommentar zu *was* und *warum* eines Blocks.
- Sichtbarkeit nur erweitern, wenn das Design es verlangt, und nur nach Rückfrage.
- Niedrige Ebenen kapseln; Aufrufer arbeiten mit Domänenbegriffen.
- Code außerhalb des Features nicht anfassen; so wenige Zeilen ändern wie möglich.
