# TODO – Code-Review-Befunde

Legende: ✅ = live reproduziert, 📖 = aus Code-Review

## P0
- [x] ✅ Doppelzählung: Abwesenheits-Timesheets nicht zusätzlich als Gutschrift zählen bzw. Gutschrift weglassen, wenn Auto-Timesheets aktiv (WorkingTimeCalculator.php:182/295, WorkingTimeYearSubscriber.php:195)
- [x] ✅ Abwesenheits-Timesheets per Meta-Feld (META_KEY) statt `LIKE '%(#N)%'` identifizieren (AbsenceTimesheetService.php:113)
- [x] ✅ Auto-Timesheets billable=false; exportierte nicht löschen
- [x] ✅ reject()/request() rufen removeAbsenceTimesheets() auf

## P1
- [ ] ✅ Teamlead-Scoping (isTeamleadOfUser) für view/approve/pdf/export/ICS, ?team=-Parameter im Kalender
- [ ] ✅ API approve/reject: Owner-/Team-Check, nur REQUESTED, Exceptions → 400
- [ ] ✅ ICS-Token nur für Owner (oder Admin), kein Auto-Create beim Ansehen fremder User, isEnabled() in findUserByToken, keine DESCRIPTION/comment bei Krankheit
- [ ] ✅ Validierung (Form + API): Ende ≥ Beginn, max ~1 Jahr, duration 0..24h, keine Überschneidung, Datum-Parse → 400
- [ ] 📖 CSRF-Tokens für alle POST-Formulare (approve/reject/delete/ics regenerate/lock/unlock/sync/holiday delete)

## P2
- [ ] 📖 Feiertage in Abwesenheiten überspringen (AbsenceWorkdayHelper::isAbsenceApplicableDay)
- [ ] ✅ Urlaubsanspruch anteilig bei unterjährigem Ein-/Austritt (UserWorkContract.php:49)
- [ ] ✅ Routen `{year}`/`{month}` mit \d+, Monat 1–12 prüfen
- [ ] 📖 Monatssperre wirksam machen bzw. Kimai-Lock in assertRangeNotLocked prüfen
- [ ] 📖 Datumsvergleiche per format('Y-m-d') (Absence::coversDate)
- [ ] 📖 Workday-Restriktion als Validierungsfehler statt InvalidArgumentException (TimesheetValidationSubscriber.php:64)
- [ ] 📖 Kleinkram: halbe Krankheitstage (WorkingTimeCalculator.php:262), CalendarFeed über Jahreswechsel, CSV-Formel-Injection, ICS fold() UTF-8-sicher, SSRF-Schutz beim ICS-Import (interne Adressen)
