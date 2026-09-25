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
- [x] 📖 CSRF-Tokens für alle POST-Formulare (approve/reject/delete/ics regenerate/lock/unlock/sync/holiday delete)

## P2
- [x] 📖 Feiertage in Abwesenheiten überspringen (AbsenceWorkdayHelper::isAbsenceApplicableDay)
- [x] ✅ Urlaubsanspruch anteilig bei unterjährigem Ein-/Austritt (UserWorkContract.php:49) — 1/12 je vollem Monat, auf halbe Tage aufgerundet
- [x] ✅ Routen `{year}`/`{month}` mit \d+, Monat 1–12 prüfen
- [x] 📖 Monatssperre wirksam machen bzw. Kimai-Lock in assertRangeNotLocked prüfen — Kimai-Freigabe (WorkingTimeService::isApproved) wird jetzt geprüft; die Plugin-eigene Sperr-UI (working_times.html.twig) wird weiterhin nirgends gerendert, Sperren nur per POST möglich
- [x] 📖 Datumsvergleiche per format('Y-m-d') (Absence::coversDate)
- [x] 📖 Workday-Restriktion als Validierungsfehler statt InvalidArgumentException (TimesheetValidationSubscriber.php:64) — jetzt `Validator/TimesheetWorkday` (TimesheetConstraint)
- [x] 📖 Kleinkram: halbe Krankheitstage (WorkingTimeCalculator.php:262), CalendarFeed über Jahreswechsel, CSV-Formel-Injection, ICS fold() UTF-8-sicher, SSRF-Schutz beim ICS-Import (interne Adressen)
