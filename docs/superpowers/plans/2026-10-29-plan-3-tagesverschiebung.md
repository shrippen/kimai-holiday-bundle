# Plan 3: Tagesverschiebung — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ein Mitarbeiter verlegt einen seiner Vertragstage innerhalb derselben Woche auf einen
anderen Tag („diese Woche Do statt Fr"); der andere Chef genehmigt; Sollstunden, Urlaubszählung,
Arbeitszeiten-Jahresansicht, Teamkalender, Feeds und Verfügbarkeitsansicht folgen dem einen
Chokepoint (Spec 4.4, 4.7, 5). Dazu die Zusammenführung der doppelten Soll-/Abwesenheits-Rechnung.
Release 2.2.0, Rollout auf zeiterfassung.zafe.at.

**Architecture:** Neue Entity `WorkdayShift` (Antrag mit Status wie `Absence`). Die Wirkung
läuft ausschließlich über `UserWorkContract::getExpectedSecondsForDate()`, das einen
`WorkdayShiftOverrides`-Zulieferer befragt (F → 0, T → Soll von F); der Rohvertrag bleibt als
`getContractSecondsForDate()` für die Prüfungen erreichbar. Genehmigung, Benachrichtigung,
Sperren und Zähler verallgemeinern die Plan-1-Bausteine (Recht als Parameter, `PeriodLockGuard`).
`DayExpectation` wird die eine reine Tagesrechnung für `WorkingTimeCalculator` und
`WorkingTimeYearSubscriber`.

**Tech Stack:** PHP 8.2+, Symfony 6 (Kimai 2.70.2), Doctrine-Attribute, Twig + Kit 0.7.1
(unverändert), PHPUnit über `dev/test.sh`, Docker-Dev-Stack (`dev/kimai-dev.sh`,
`admin`/`admin1234`, Screenshots `node dev/screenshot.mjs`).

**Spec:** `docs/superpowers/specs/2026-10-23-kimai-dienstplan-design.md` — 4.4 (Verschiebung),
4.7 (Vermerk „verschoben von", offene Verschiebungen), 5 (Chokepoint, Subscriber, Duplikate,
Rechte `shift_own`, `shift_other`, `approve_shift`).

## Global Constraints

- Tabelle `kimai2_ext_holiday_workday_shift`, Migration `Version20261030100000` im Stil von
  `Migrations/Version20261024100000.php` (Guards, benannte FKs/Indizes); veröffentlichte
  Migrationen nie editieren.
- Rechte genau: `shift_own`, `shift_other`, `approve_shift`. Rollen-Defaults: USER `shift_own`;
  TEAMLEAD `shift_own`, `shift_other`, `approve_shift`; ADMIN/SUPER_ADMIN alle drei (jede Rolle
  explizit). Genehmiger einer Verschiebung = `approve_shift` + Kimai-`access_user` auf den
  Antragsteller, **nie der Antragsteller selbst** (wie bei Abwesenheiten).
- Regeln der Verschiebung (F = Ursprungstag, T = Zieltag), alle serverseitig, Fehlerschlüssel
  `holiday.error.shift_*` in `flashmessages` (nächste ID `h109`):
  `shift_same_day` (F = T), `shift_not_same_week` (ISO-Woche `o-W` verschieden),
  `shift_from_not_workday` (Rohvertrag an F = 0 oder ganztägiger Feiertag), `shift_from_taken`
  (aktive, d. h. nicht abgelehnte Verschiebung berührt F), `shift_to_workday` (Rohvertrag an T > 0),
  `shift_to_holiday` (ganztägiger Feiertag an T), `shift_to_outside_contract` (T vor Vertragsbeginn
  oder nach Vertragsende), `shift_to_taken` (aktive Verschiebung berührt T), `shift_no_location`
  (Nutzer ohne Standort), `shift_to_closed` (`LocationSchedule::slotFor(T)->open` false),
  `shift_absent` (genehmigte Abwesenheit deckt F oder T), `shift_target_extra` (Aushilfe an T),
  `shift_locked` (Monatssperre oder Kimai-Arbeitszeitfreigabe an F oder T),
  `shift_has_absence` (Ablehnen/Löschen einer genehmigten Verschiebung, solange an T eine genehmigte
  Abwesenheit liegt), `self_approval` (vorhanden).
- Übersetzungen de+en (`messages` nächste ID `h263`), eindeutige IDs; `php dev/check-translations.php`.
- Routen `#[Route('/holiday/workday-shifts')]`, Namen `holiday_workday_shift_*`; POST mit CSRF;
  Modale wie `ExtraShiftController`.
- UI nur Kit-Makros/Tabler; Status nie nur durch Farbe.
- Tests zuerst für jede Verhaltensänderung; Testhelfer in `Tests/Support/`.
- Commit-Messages: Imperativ, deutsch, ≤ 50 Zeichen, Body, Trailer
  `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.
- Version `2.2.0` (Task 7). Merge/Release/Rollout nur nach Freigabe des Betreibers.

## Entscheidungen (Controller, vor dem Bau)

1. Eigenes Recht `approve_shift` (Spec 5); `ApproverAccess`/`ApproverResolver` bekommen das
   Recht als Parameter mit `approve_other_absence` als Default.
2. Verschiebung ohne Standort ist ein Fehler (`shift_no_location`) — Spec 4.4 „nur innerhalb
   eines Standorts".
3. Aushilfe an einem freigewordenen F ist erlaubt (F ist dann kein Arbeitstag); Verschiebung auf
   ein T mit Aushilfe ist verboten (`shift_target_extra`), auch bei `approve` erneut geprüft.
4. Halber Feiertag plus halbe Abwesenheit: Calculator-Semantik (beide auf die Vertragsbasis
   bezogen) wird die eine Regel.
5. Keine eigenen FullCalendar-/ICS-Events für Verschiebungen; die indirekte Wirkung über den
   Chokepoint reicht (TODO bleibt).
6. Verschiebungen verändern nie den `WorkdayShift` nach Genehmigung: Bearbeiten = ablehnen/löschen
   und neu beantragen (kein `update`).

## Review Focus

1. **Woche über Monatsgrenze mit Sperre nur in einem Monat** (F 30.10., T 02.11.? — nein, andere
   Woche; nimm F Fr 30.10.2026, T Do 29.10. mit Sperre Oktober): erwartet `shift_locked`. Test Task 4
   (`WorkdayShiftServiceTest::testLockOnEitherDayRejects`).
2. **Aushilfe entsteht zwischen Antrag und Genehmigung an T:** `approve` lehnt mit
   `shift_target_extra` ab. Test Task 4 (`::testApproveRevalidatesExtraShiftOnTarget`).
3. **Genehmigte Verschiebung löschen, obwohl an T Urlaub genehmigt ist:** `shift_has_absence`.
   Test Task 4 (`::testDeleteApprovedBlockedByAbsenceOnTarget`).
4. **Vergangener Tag in Kimais Arbeitszeiten-Jahresansicht:** F zeigt Soll 0, T das Soll von F,
   beide mit Vermerk. Test Task 3 (`WorkingTimeYearSubscriberTest::testShiftOverridesPastDays`).
5. **Teamkalender-Performance:** Nutzer × 365 Chokepoint-Aufrufe dürfen höchstens eine Abfrage je
   Nutzer und Jahr auslösen. Test Task 2 (`WorkdayShiftOverridesTest::testOneQueryPerUserAndYear`).

---

## Dateistruktur

**Neu:** `Entity/WorkdayShift.php`, `Repository/WorkdayShiftRepository.php`,
`Migrations/Version20261030100000.php`, `Service/PeriodLockGuard.php`,
`Service/WorkdayShiftOverrides.php`, `Service/DayExpectation.php`, `Service/DayBalance.php`,
`Service/WorkdayShiftService.php`, `Service/WorkdayShiftPermissions.php`,
`Form/WorkdayShiftType.php`, `Controller/WorkdayShiftController.php`,
`EventSubscriber/Actions/WorkdayShiftRowSubscriber.php`,
`Resources/views/workday_shift/form.html.twig`, `Tests/Support/WorkdayShiftBuilder.php`,
`Tests/Service/{PeriodLockGuardTest,WorkdayShiftOverridesTest,DayExpectationTest,WorkingTimeYearSubscriberTest,WorkdayShiftServiceTest,WorkdayShiftPermissionsTest}.php`.

**Geändert:** `Service/UserWorkContract.php`, `Service/ApproverAccess.php`,
`Service/ApproverResolver.php`, `Service/AbsenceApprovalService.php` (nutzt `PeriodLockGuard`),
`Service/AbsenceNotifier.php`, `Service/OpenRequestCounter.php`, `Service/WorkingTimeCalculator.php`,
`EventSubscriber/WorkingTimeYearSubscriber.php`, `Service/AvailabilityCalculator.php`,
`Service/PresenceEntry.php`, `Service/AvailabilityDay.php`, `Enum/PresenceKind.php`,
`Controller/AvailabilityController.php`, `Controller/AbsenceController.php` (Anträge-Seite),
`Controller/AbsenceCalendarController.php`, `EventSubscriber/MenuSubscriber.php`,
`EventSubscriber/Actions/AvailabilityPageSubscriber.php`, `DependencyInjection/HolidayExtension.php`,
Templates `availability/index.html.twig`, `absence/requests.html.twig`,
`report/absence_calendar.html.twig`, Übersetzungen, `dev/ci.sh`, `README.md`, `CHANGELOG.md`,
`composer.json`, `TODO.md`.

---

### Task 1: Entity, Repository, Migration, Rechte, Genehmiger je Recht, Sperr-Guard

**Files:** Create `Entity/WorkdayShift.php`, `Repository/WorkdayShiftRepository.php`,
`Migrations/Version20261030100000.php`, `Service/PeriodLockGuard.php`,
`Tests/Support/WorkdayShiftBuilder.php`, `Tests/Service/PeriodLockGuardTest.php`,
`Tests/Service/ApproverResolverTest.php` (erweitern). Modify `DependencyInjection/HolidayExtension.php`,
`Service/ApproverAccess.php`, `Service/ApproverResolver.php`, `Service/AbsenceApprovalService.php`.

**Interfaces:**
- `WorkdayShift`: `id`, `user` (CASCADE), `fromDate`/`toDate` (`from_date`/`to_date`,
  DATE_IMMUTABLE), `status` (`AbsenceStatus`, Default REQUESTED), `comment` (TEXT null),
  `createdAt`, `approvedBy` (SET NULL), `approvedAt`; Indizes `(user_id, from_date)`,
  `(user_id, to_date)`, **kein Unique**; `covers(\DateTimeInterface): ?string` (`'from'|'to'|null`,
  Vergleich über `Y-m-d`); `isActive(): bool` (Status ≠ REJECTED).
- `WorkdayShiftRepository`: `findApprovedForUserBetween(User, from, to)`,
  `findApprovedForUsersBetween(array, from, to)`, `findVisibleForUsersBetween(array, from, to)`
  (APPROVED + REQUESTED), `findRequestedForUsers(array): WorkdayShift[]`,
  `findActiveTouching(User, \DateTimeInterface $day, ?int $excludeId = null): WorkdayShift[]`
  (nicht abgelehnt, `from_date = :d OR to_date = :d`), `save`, `remove`.
- `PeriodLockGuard::assertOpen(User $user, \DateTimeInterface $from, \DateTimeInterface $to): void`
  — wirft `\RuntimeException('holiday.error.month_locked')`; Inhalt = heutiges
  `AbsenceApprovalService::assertRangeNotLocked` (Kimai `getLatestApprovalDate` + `MonthLockRepository`).
  `AbsenceApprovalService` ruft nur noch den Guard (Verhalten unverändert).
- `ApproverAccess::mayApprove(User $candidate, string $permission = 'approve_other_absence')`;
  `ApproverResolver::approversFor(User $subject, string $permission = 'approve_other_absence')`,
  `subjectsFor(User $approver, string $permission = …)`, `isApproverOf(User $actor, User $subject, string $permission = …)`;
  der Cache der aktiven Nutzer bleibt gemeinsam.
- `WorkdayShiftBuilder::approved(User, string $fromYmd, string $toYmd): WorkdayShift`,
  `::requested(...)`, `::rejected(...)`, `id(int)` per Reflection.
- Rechte `shift_own`, `shift_other`, `approve_shift` in `$permissions` und allen Rollenlisten
  (Global Constraints); `PermissionSectionSubscriber` hat den Filter `shift` bereits.

- [ ] **Step 1: Failing Tests** — `PeriodLockGuardTest`: Datum vor Kimai-Freigabe → Exception;
  Monatssperre im Zielmonat → Exception; frei → kein Fehler. `ApproverResolverTest`: Kandidat mit
  nur `approve_shift` ist Genehmiger für `'approve_shift'`, nicht für den Default; Subject nie
  eigener Genehmiger (beide Rechte). `Tests/Entity/WorkdayShiftTest`: `covers('2026-10-29') === 'to'`,
  `isActive()` false bei REJECTED.
- [ ] **Step 2: Fail prüfen** — `dev/test.sh --filter 'PeriodLockGuard|ApproverResolver|WorkdayShift'`.
- [ ] **Step 3: Implementieren** (Entity, Repo, Migration, Guard-Extraktion, Parametrisierung,
  Rechte); `AbsenceApprovalServiceTest` muss unverändert grün bleiben (Stub des Guards).
- [ ] **Step 4: Tests grün; Dev-Stack:** `kimai:bundle:holiday:install`, `doctrine:schema:validate --skip-sync`,
  Tabelle vorhanden.
- [ ] **Step 5: Commit** — `Verschiebung: Datenmodell, Rechte, Sperr-Guard`

---

### Task 2: Chokepoint mit Überschreibung

**Files:** Create `Service/WorkdayShiftOverrides.php`, `Tests/Service/WorkdayShiftOverridesTest.php`.
Modify `Service/UserWorkContract.php`, `Tests/Service/UserWorkContractTest.php`,
`Tests/Service/AbsenceWorkdayHelperTest.php` (ein Fall), `Tests/Service/AvailabilityCalculatorTest.php`
(ein Fall: T erscheint als anwesend — vorerst `fixed`, Task 6 macht `shifted`).

**Interfaces:**
- `WorkdayShiftOverrides::sourceFor(User $user, \DateTimeInterface $day): ?\DateTimeImmutable`
  (für T das F), `isShiftedAway(User, \DateTimeInterface): bool` (F), `reset(?User $user = null): void`;
  intern eine Abfrage `findApprovedForUserBetween(user, 1.1., 31.12.)` je Nutzer und Jahr, Cache
  Schlüssel `spl_object_id($user)`/`getId()` + Jahr. Nur **genehmigte** Verschiebungen wirken.
- `UserWorkContract::__construct(WorkingTimeModeFactory, PublicHolidayGroupRepository, ?WorkdayShiftOverrides $overrides = null)`;
  `getContractSecondsForDate(User, \DateTimeInterface): int` (heutiger Inhalt);
  `getExpectedSecondsForDate(User, \DateTimeInterface): int` = F → 0; T → `getContractSecondsForDate(F)`;
  sonst Rohvertrag. T außerhalb des Vertragszeitraums kann es nicht geben (Task 4 prüft), der
  Chokepoint prüft es trotzdem defensiv (→ 0).

- [ ] **Step 1: Failing Tests** — `WorkdayShiftOverridesTest`: `testOneQueryPerUserAndYear`
  (Repository-Mock `once()` bei 365 Aufrufen für einen Nutzer; zwei Nutzer → zwei Aufrufe),
  `testRejectedShiftHasNoEffect`, `testResetClearsCache`. `UserWorkContractTest`:
  `testShiftMovesExpectedSeconds` (Moritz Fr+Sa 8 h; genehmigt Fr 30.10. → Do 29.10.: Do = 28800,
  Fr = 0, Sa unverändert), `testContractSecondsIgnoreShift`. `AbsenceWorkdayHelperTest`:
  `testShiftedTargetIsApplicableDay` (Urlaub Do 29.10. zählt 1 Tag, Fr 30.10. zählt 0).
- [ ] **Step 2: Fail prüfen.** — [ ] **Step 3: Implementieren.** — [ ] **Step 4: Tests grün**
  (alle bestehenden Konstruktionen von `UserWorkContract` bleiben durch den optionalen Parameter
  grün). — [ ] **Step 5: Commit** — `Soll je Tag kennt genehmigte Verschiebungen`

---

### Task 3: Eine Tagesrechnung (`DayExpectation`) und Subscriber mit Überschreibung

**Files:** Create `Service/DayExpectation.php`, `Service/DayBalance.php`,
`Tests/Service/DayExpectationTest.php`, `Tests/Service/WorkingTimeYearSubscriberTest.php`.
Modify `Service/WorkingTimeCalculator.php` (`calculateDay` nutzt `DayExpectation`, private Helfer
entfallen), `EventSubscriber/WorkingTimeYearSubscriber.php`, Übersetzungen (Vermerk-Texte).

**Interfaces:**
- `final class DayBalance { public function __construct(public readonly int $expected /* nach Reduce */, public readonly int $credit /* Compensate-Gutschrift */, public readonly int $absenceSeconds, public readonly int $holidaySeconds) {} }`
- `DayExpectation::evaluate(int $base, int $timesheetSeconds, ?Absence $absence, bool $absenceHasTimesheet, ?PublicHoliday $holiday, CalculationMode $absenceMode, CalculationMode $holidayMode): DayBalance`
  — reine Funktion; Regeln exakt wie `WorkingTimeCalculator::calculateDay`/`absenceDurationSeconds`
  heute (OTHER mit Dauer; Krankheit = Ziel − Ist; halber Tag = halbe Basis; TIME_OFF nur Marker;
  Feiertag halb/ganz auf **Basis** bezogen; `absenceHasTimesheet` ⇒ keine Gutschrift).
- `WorkingTimeYearSubscriber`: nutzt `DayExpectation` (Duplikat `absenceSeconds` entfällt) und
  die Basis aus dem Chokepoint; **neu:** für jeden Tag mit Überschreibung (vergangen, heute,
  künftig; nicht gesperrt) `setExpectedTime(chokepoint)` und ein `DayAddon` mit Titel
  `holiday.shift.addon.away` („Arbeitstag verschoben nach %to%") an F bzw. `holiday.shift.addon.to`
  („Arbeitstag verschoben von %from%") an T; der frühe `continue` greift nicht mehr bei Override.
  Dafür bekommt der Subscriber `WorkdayShiftOverrides` (oder `WorkdayShiftRepository`, eine
  Abfrage je Jahr).

- [ ] **Step 1: Failing Tests** — `DayExpectationTest` (8 Fälle: Urlaub compensate/reduce, halber
  Urlaub, Krankheit mit Ist 3 h bei 8 h, OTHER 2 h, Feiertag ganz/halb, halber Feiertag + halber
  Urlaub → `expected`/`credit` nach Calculator-Semantik, TIME_OFF). `WorkingTimeYearSubscriberTest`:
  `testShiftOverridesPastDays` (Kimai-`Year`/`Month`/`Day`-Objekte oder minimale Doubles: F in der
  Vergangenheit bekommt `setExpectedTime(0)` + Addon, T bekommt `setExpectedTime(28800)` + Addon,
  gesperrter Tag unverändert).
- [ ] **Step 2: Fail prüfen.** — [ ] **Step 3: Implementieren;** PDF-Pfad (`ContractController::monthPdf`)
  unverändert lauffähig (Smoke: `/en/holiday/contract/pdf/...` oder die Route aus `ContractController`
  → 200). — [ ] **Step 4: Tests grün.** — [ ] **Step 5: Commit** — `Eine Tagesrechnung für Soll und Abwesenheit`

---

### Task 4: Antrag, Genehmigung, Benachrichtigung

**Files:** Create `Service/WorkdayShiftService.php`, `Service/WorkdayShiftPermissions.php`,
`Tests/Service/WorkdayShiftServiceTest.php`, `Tests/Service/WorkdayShiftPermissionsTest.php`.
Modify `Service/AbsenceNotifier.php` (Shift-Methoden, gemeinsamer `send`/`recipients`-Pfad),
`Service/OpenRequestCounter.php`, Übersetzungen (`holiday.mail.shift_{requested,approved,rejected}.{subject,body}`
mit `%user% %from% %to% %comment% %approver%`, Fehler `holiday.error.shift_*`).

**Interfaces:**
- `WorkdayShiftService::validate(WorkdayShift $shift): void` (Reihenfolge wie Global
  Constraints, erste Verletzung wirft), `create(WorkdayShift, User $actor): WorkdayShift`
  (Genehmiger des Antragstellers → APPROVED + `approvedBy`, sonst REQUESTED + Mail),
  `approve(WorkdayShift, User $approver)` (validiert erneut, nicht selbst, REQUESTED, Sperre),
  `reject(...)`, `request(WorkdayShift, bool $notify)` (Undo), `delete(WorkdayShift)`;
  nach jeder Statusänderung `overrides->reset($user)`; `reject`/`delete` einer genehmigten
  Verschiebung mit genehmigter Abwesenheit an T → `shift_has_absence`.
- `WorkdayShiftPermissions::canCreateFor(?User)`, `canApproveFor(?User)` (nie selbst,
  `approve_shift` + `access_user`), `canApprove(WorkdayShift)`, `canDelete(WorkdayShift)`
  (Antragsteller mit `shift_own` solange REQUESTED; `shift_other`+access; Genehmiger).
- `AbsenceNotifier::notifyShiftRequested/Approved/Rejected(WorkdayShift)` — Empfänger Antragsteller
  + `approversFor($user, 'approve_shift')`.
- `OpenRequestCounter::countFor(User $approver): int` = Abwesenheiten (`subjectsFor(…, 'approve_other_absence')`)
  + Verschiebungen (`subjectsFor(…, 'approve_shift')`).

- [ ] **Step 1: Failing Tests** (`WorkdayShiftServiceTest`, Fixture wie `AbsenceApprovalServiceTest`
  mit echtem `ApproverResolver`; Moritz Fr+Sa, Wien, Gruppe mit Feiertag 01.05.):
  `testValidShiftByEmployeeIsRequested` (Fr 30.10. → Do 29.10.), `testShiftByApproverIsApprovedDirectly`,
  jede Regel je ein Test mit dem exakten Schlüssel (14 Regeln), `testLockOnEitherDayRejects`
  (Sperre Oktober, F 30.10./T 29.10. → `shift_locked`), `testApproveRevalidatesExtraShiftOnTarget`,
  `testDeleteApprovedBlockedByAbsenceOnTarget`, `testApproverMayNotApproveOwnShift`,
  `testStatusChangeResetsOverrides` (Spy auf `reset`). `WorkdayShiftPermissionsTest` (3 Fälle).
  `OpenRequestCounterTest`: Summe aus beiden Quellen.
- [ ] **Step 2: Fail prüfen.** — [ ] **Step 3: Implementieren.** — [ ] **Step 4: Tests grün,
  Übersetzungen.** — [ ] **Step 5: Commit** — `Verschiebung beantragen und genehmigen`

---

### Task 5: UI — Formular, Entscheidungen, Seite „Offene Anträge", Zähler

**Files:** Create `Form/WorkdayShiftType.php`, `Controller/WorkdayShiftController.php`,
`EventSubscriber/Actions/WorkdayShiftRowSubscriber.php`, `Resources/views/workday_shift/form.html.twig`.
Modify `Controller/AbsenceController.php` (Anträge-Seite: zweite DataTable `holiday_shift_requests`,
Zugriff `approve_other_absence or approve_shift` im Code), `Resources/views/absence/requests.html.twig`,
`EventSubscriber/MenuSubscriber.php` (Badge bei einem der beiden Rechte),
`EventSubscriber/Actions/AvailabilityPageSubscriber.php` (Aktion „Tag verschieben" mit `?from=`),
`dev/ci.sh` (`check "/en/holiday/absence/requests" 200`), Übersetzungen.

**Interfaces:** Routen `holiday_workday_shift_create` (GET/POST Modal; Felder `user` nur mit
`shift_other`, `fromDate`, `toDate` als `DatePickerType`, `comment`; Vorbelegung `?from=` aus der
Verfügbarkeitsseite), `holiday_workday_shift_bulk_approve`/`_bulk_reject` (POST, CSRF-ID
`holiday_workday_shift_action`, `data-kpu-ids`), `holiday_workday_shift_reopen/{action}` (Undo
analog `AbsenceController::reopen`, Session-Schlüssel `holiday.shift_undo.<hex>`),
`holiday_workday_shift_delete/{id}` (POST). Service-Fehler als Formularfehler am `toDate`-Feld
(wie `ExtraShiftController`). Zeilenaktionen (Genehmigen/Ablehnen/Löschen) über den neuen
RowSubscriber, Action-Name `holiday_workday_shift`.

- [ ] **Step 1: Smoke-Plan statt Unit-Tests** (Controller/Form): Dev-Stack: als Moritz (ROLE_USER,
  `shift_own`) Fr 30.10. → Do 29.10. beantragen → Badge beim Admin 1, Seite „Offene Anträge" zeigt
  die Verschiebung in der zweiten Tabelle; als Admin genehmigen → Status, Badge 0; als Admin eigene
  Verschiebung beantragen → kein Genehmigen-Button; ungültige Anträge (gleiche Woche verletzt,
  Zieltag Montag) → Formularfehler mit den richtigen Schlüsseln. HTTP-Nachweise im Report.
- [ ] **Step 2: Implementieren.** — [ ] **Step 3: `lint:twig`, Übersetzungen, voller `dev/test.sh`,
  Smoke, CI.** — [ ] **Step 4: Commit** — `Verschiebung: Formular, Entscheidung, Anträge-Seite`

---

### Task 6: Sichtbarkeit — Verfügbarkeit, Teamkalender

**Files:** Modify `Enum/PresenceKind.php` (`SHIFTED = 'shifted'`, Label `holiday.availability.kind.shifted`),
`Service/PresenceEntry.php` (`?WorkdayShift $shift = null`), `Service/AvailabilityDay.php`
(`pending` wird `array<array{user: User, absence?: Absence, shift?: WorkdayShift}>`),
`Service/AvailabilityCalculator.php` (+`WorkdayShiftRepository`; genehmigt: an T `SHIFTED` statt
`FIXED`, Vermerk-Datum = F; beantragt: `pending` an F **und** T), `Controller/AvailabilityController.php`
(`CELL_SHIFTED`, `cell()` für Shift-Pending), `Resources/views/availability/index.html.twig`
(Vermerk „verschoben von %from%", offen: „Verschiebung F → T", Monats-Icon + Legende),
`Controller/AbsenceCalendarController.php` + `report/absence_calendar.html.twig` (Einträge
`type: 'workday_shift'` an F und T, `requested` bei offen, Legende; vom Abwesenheitsbalken
ausnehmen), Tests `AvailabilityCalculatorTest` (+3), Übersetzungen.

- [ ] **Step 1: Failing Tests** — `testApprovedShiftShowsAsShiftedOnTarget` (Moritz Fr→Do: Do
  `SHIFTED` mit `shift->fromDate` 30.10., Fr nicht present), `testRequestedShiftIsPendingOnBothDays`,
  `testShiftedBeatsExtraShiftOrdering` (fixed, shifted, extra_shift Reihenfolge).
- [ ] **Step 2: Fail prüfen.** — [ ] **Step 3: Implementieren.** — [ ] **Step 4: Tests grün,
  `lint:twig`, Screenshots Woche/Monat mit einer genehmigten und einer offenen Verschiebung
  (`node dev/screenshot.mjs`), Teamkalender-Screenshot; Dateien nach
  `docs/pr-screenshots/feat-tagesverschiebung/`.** — [ ] **Step 5: Commit** — `Verschiebung in Verfügbarkeit und Teamkalender`

---

### Task 7: Doku, Version 2.2.0, PR

- [ ] README (Abschnitt „Tagesverschiebung": Regeln, Rechte-Tabelle, Routen; Hinweis, dass nach
  Genehmigung Urlaub/Soll/Timesheet-Sperre am neuen Tag gelten), CHANGELOG `2.2.0`, `composer.json`
  `2.2.0`, `TODO.md` (Plan-3-Zeilen entfernen, Rest aktualisieren: FullCalendar/ICS-Events,
  API, Kleinbefunde). Gesamtlauf. PR mit Screenshots durch den Controller (Skill `pr-screenshot`);
  Merge/Tag `v2.2.0` nach Freigabe.

### Task 8: Rollout (mit dem Betreiber, nicht an Agenten)

- [ ] Backup → Zip 2.2.0 → `kimai:reload`, `kimai:bundle:holiday:install` (Migration
  `Version20261030100000`) → Rollen prüfen (USER `shift_own`) → Probe: Moritz verschiebt einen Tag,
  der andere Chef genehmigt, Verfügbarkeit und Arbeitszeiten-Jahresansicht zeigen es → Protokoll
  `docs/rollout/…-plugin-2.2.0.md`.

---

## Selbst-Review (ausgeführt)

- **Spec-Deckung:** 4.4 Felder/Antrag/Prüfungen/Wirkung/Stundenneutralität/Standort → Task 1, 2, 4;
  Wirkung auf Urlaubszählung, Timesheet-Anlage, Jahresansicht, Kalender, ICS, Verfügbarkeit →
  Task 2 (Chokepoint) + Task 3 (Subscriber) + Task 6; 4.7 Vermerk/offen → Task 6; 5 Rechte → Task 1,
  Subscriber `setExpectedTime` → Task 3, Duplikate → Task 3, Zähler „Abwesenheiten + Verschiebungen"
  → Task 4/5. Bearbeiten einer Verschiebung bewusst nicht (Entscheidung 6).
- **Typen:** `WorkdayShift` Felder in Task 1/4/6 gleich; `PeriodLockGuard::assertOpen(User, from, to)`
  in Task 1 und 4; `ApproverResolver`-Signaturen mit Recht in Task 1/4; `PresenceEntry::shift`
  in Task 6 und Template.
- **Review Focus:** 1–3 Task 4, 4 Task 3, 5 Task 2 — alle mit benanntem Test.
- **Offen für den Bauer:** Doubles für Kimais `Year/Month/Day/WorkingTime` im Subscriber-Test;
  Icon-Namen; Kit-Makro für die zweite DataTable; ob `ApproverAccess` eine Konstante je Recht
  behält.
