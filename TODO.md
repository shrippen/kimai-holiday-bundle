# TODO – Code-Review-Befunde

Legende: ✅ = live reproduziert, 📖 = aus Code-Review

## P0
- [x] ✅ Doppelzählung: Abwesenheits-Timesheets nicht zusätzlich als Gutschrift zählen bzw. Gutschrift weglassen, wenn Auto-Timesheets aktiv (WorkingTimeCalculator.php:182/295, WorkingTimeYearSubscriber.php:195)
- [x] ✅ Abwesenheits-Timesheets per Meta-Feld (META_KEY) statt `LIKE '%(#N)%'` identifizieren (AbsenceTimesheetService.php:113)
- [x] ✅ Auto-Timesheets billable=false; exportierte nicht löschen
- [x] ✅ reject()/request() rufen removeAbsenceTimesheets() auf

## P1
- [x] ✅ Teamlead-Scoping (isTeamleadOfUser) für view/approve/pdf/export/ICS, ?team=-Parameter im Kalender
- [x] ✅ API approve/reject: Owner-/Team-Check, nur REQUESTED, Exceptions → 400
- [x] ✅ ICS-Token nur für Owner (oder Admin), kein Auto-Create beim Ansehen fremder User, isEnabled() in findUserByToken, keine DESCRIPTION/comment bei Krankheit
- [x] ✅ Validierung (Form + API): Ende ≥ Beginn, max ~1 Jahr, duration 0..24h, keine Überschneidung, Datum-Parse → 400
- [x] ✅ ICS-Token als Benutzereinstellung `holiday_ics_token` über `GET /api/users/me` und `/api/users/{id}` (Admin/Teamleiter) sichtbar und in Rechnungs-/Exportvorlagen (`user.meta.*`) — jetzt eigene Tabelle `kimai2_ext_holiday_ics_token` (Token eindeutig indiziert), Migration `Version20260925120000` verschiebt die Werte unverändert und löscht die Präferenzzeilen
- [x] 📖 CSRF-Tokens für alle POST-Formulare (approve/reject/delete/ics regenerate/lock/unlock/sync/holiday delete)

## P2
- [x] 📖 Feiertage in Abwesenheiten überspringen (AbsenceWorkdayHelper::isAbsenceApplicableDay)
- [x] ✅ Urlaubsanspruch anteilig bei unterjährigem Ein-/Austritt (UserWorkContract.php:49) — 1/12 je vollem Monat, auf halbe Tage aufgerundet
- [x] ✅ Routen `{year}`/`{month}` mit \d+, Monat 1–12 prüfen
- [x] 📖 Monatssperre wirksam machen bzw. Kimai-Lock in assertRangeNotLocked prüfen — Kimai-Freigabe (WorkingTimeService::isApproved) wird jetzt geprüft; die Plugin-eigene Sperr-UI (working_times.html.twig) wird weiterhin nirgends gerendert, Sperren nur per POST möglich
- [x] 📖 Datumsvergleiche per format('Y-m-d') (Absence::coversDate)
- [x] 📖 Workday-Restriktion als Validierungsfehler statt InvalidArgumentException (TimesheetValidationSubscriber.php:64) — jetzt `Validator/TimesheetWorkday` (TimesheetConstraint)
- [x] 📖 Kleinkram: halbe Krankheitstage (WorkingTimeCalculator.php:262), CalendarFeed über Jahreswechsel, CSV-Formel-Injection, ICS fold() UTF-8-sicher, SSRF-Schutz beim ICS-Import (interne Adressen)

## Update-Hinweis (Kit 0.8)

Ein Hinweis für Admins, wenn es ein neueres Release gibt, weil Kimai-Plugins von Hand kopiert werden und sonst niemand davon erfährt.
Format und Regeln: `shrippen.github.io/overview/VERSIONS.md`.

- [ ] Kit auf 0.8 bringen: `../Kante/kimai/kit/bin/sync.sh .`
- [ ] `{{ kit.update_hint('kimai-holiday', <version>, {enabled: …}) }}` auf der Einstellungs- oder Übersichtsseite; Version aus `composer.json` (`version`)
- [ ] Einstellung „Nach Updates suchen“ (Standard an); im Demo-Modus immer aus
- [ ] README: was abgerufen wird (`https://shrippen.github.io/versions.json` ohne Parameter, höchstens einmal am Tag, nur im Browser von Nutzern mit dem Recht `plugins`)
- [ ] Nach jedem Release `python3 ../shrippen.github.io/overview/tools/build-versions.py` und `docs/versions.json` dort committen

## Plan 2
- [ ] 📖 WebTestCase-Abdeckung der Freigabe-Routen (Zeilenaktion, Sammelaktion, API), damit Selbstfreigabe über HTTP getestet statt nur geraucht wird

## Offen nach Plan 3
- [ ] API für Standorte und Aushilfen (`/api/holiday/…`)
- [ ] API für Tagesverschiebungen fehlt (`/api/holiday/shifts`, Logik in `Service/WorkdayShiftService`, Routen heute nur in `Controller/WorkdayShiftController.php`)
- [ ] FullCalendar-Quelle und ICS-Feed kennen keine Verschiebungen (`EventSubscriber/CalendarSourceSubscriber::onCalendarSource`, `Controller/CalendarFeedController::absences`, `Ics/UserIcsController::feed`)
- [ ] 📖 WebTestCase-Abdeckung der Freigabe-Routen (Zeilenaktion, Sammelaktion, API), damit Selbstfreigabe über HTTP getestet statt nur geraucht wird; gilt auch für `holiday_workday_shift_*`

## Kleinbefunde Tagesverschiebung
- [ ] `AvailabilityController::cell()`: Tooltip eines halben Tages nennt die Art (Fest/verschoben/Aushilfe) nicht, und es gibt keinen Unit-Test für `cell()`
- [ ] Teamkalender: Beschriftungen für `workday_shift` werden im Controller übersetzt, die übrigen im Twig (`Controller/AbsenceCalendarController.php`, `Resources/views/report/absence_calendar.html.twig`)
- [ ] `AvailabilityCalculator`: wird das Ziel einer genehmigten Verschiebung später nicht mehr anwendbar (z. B. neuer Feiertag), fehlt die Person an beiden Tagen ohne Hinweis
- [ ] Monatsansicht: mehrere offene Einträge einer Zelle stehen nur kommagetrennt im Tooltip (`AvailabilityController::cell()`, Schlüssel `pending`)
- [ ] Anträge-Seite: Rückfall „nichts gewählt" der Sammelaktion nutzt die falsche Übersetzungsdomäne (`Controller/WorkdayShiftController.php` ~l.218 `bulkDecision()`, zusätzlich als Fehler-Rückfall in `delete()` ~l.183)
- [ ] `shift_other` ohne `shift_own` liefert 403 statt Formularfehler (`Controller/WorkdayShiftController::create`, `Service/WorkdayShiftPermissions::canCreateFor`)
- [ ] Menüeintrag „Offene Anträge" erscheint nur im `absence`-Block (`EventSubscriber/MenuSubscriber.php`)
- [ ] Spaltenkonfiguration gibt es nur für die Abwesenheits-Tabelle, nicht für die Verschiebungs-Tabelle (`Resources/views/absence/requests.html.twig`)
- [ ] `reopen`-Tests decken `excludeId` und Periodensperre nicht ab (`Tests/Service/WorkdayShiftServiceTest.php`, `WorkdayShiftService::reopen`)
- [ ] `EventSubscriber/WorkingTimeYearSubscriber`: Test für T + halber Urlaub fehlt (`Tests/Service/WorkingTimeYearSubscriberTest.php`)
- [ ] Antragsteller hat keine eigene Liste seiner Verschiebungen und erfährt eine Ablehnung nur per Mail (`Controller/WorkdayShiftController.php`)
- [ ] Eine Aushilfe auf einem freigewordenen Herkunftstag bleibt beim Löschen der Verschiebung stehen (`Service/WorkdayShiftService::delete`, `Service/ExtraShiftService`)
- [ ] Vertragsänderung nach der Genehmigung ist nicht behandelt: wird T später Vertragstag, liefert die Überschreibung weiter die Stunden von F (`Service/WorkdayShiftOverrides`)
- [ ] Keine DB-Bedingung für höchstens eine aktive Verschiebung je Tag, Rennen möglich (`Migrations/Version20261030100000.php`)
- [ ] `WorkdayShiftPermissions` vergleicht Benutzer mit `===` statt über die ID (`Service/WorkdayShiftPermissions`)
- [ ] `reopen` verarbeitet alle IDs des Session-Eintrags, geprüft wird nur die angefragte Teilmenge (`Controller/WorkdayShiftController::reopen`)
- [ ] Zeitraumprüfung doppelt (`Service/WorkdayShiftService::isOutsideContract` ↔ `Service/UserWorkContract::isInContractPeriod`)

## Kleinbefunde Plan 2
- [ ] Woche 53 in Jahren ohne KW 53 rollt über (`Controller/AvailabilityController.php`)
- [ ] `?location=abc` fällt still auf den Default (`Controller/AvailabilityController.php`)
- [ ] Print-CSS versteckt den `actions`-Slot der `hint_card`, Wochenkarten ohne Namen im Druck (Kit-Option nötig, `Resources/views/availability/index.html.twig`)
- [ ] Gruppe bearbeiten löst keinen Re-Import aus (`Controller/PublicHolidayController.php`)
- [ ] `LocationException` erlaubt OPEN ohne Zeiten auf Entity-Ebene, nur das Formular validiert (`Entity/LocationException.php`)
- [ ] Mitglieder-Filter ohne Test (`Form/LocationMemberType.php`)
- [ ] Kit `hint_card` kennt keinen neutralen Typ, besetzte Tage heißen „Hinweis“ (`Resources/views/availability/index.html.twig`)
- [ ] Aushilfe an einem später geschlossenen Tag, Vertragstag oder nach Standortwechsel der Person ist in der Verfügbarkeit verborgen und im Teamkalender nicht löschbar (`AvailabilityCalculator`, `AbsenceCalendarController`) – Löschaktion in die Teamkalender-Zelle
- [ ] `AvailabilityPeriod::label()` ist nur deutsch („KW", `d.m.`) – über `holiday.availability.week_label` übersetzen, sobald die Oberfläche zweisprachig wird
