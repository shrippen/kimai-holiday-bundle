# UI-TODO – Umstellung auf kimai-plugin-ui

Grundlage: [kimai-plugin-ui GUIDELINES.md / CHECKLIST.md](https://github.com/shrippen/kimai-plugin-ui), Leitfaden Abschnitt 6 (Holiday)
und UI-Inventar. Kit-Version siehe `Resources/views/_kit/VERSION`.

## Alle Seiten
- [x] Kit mit `bin/sync.sh` übernommen, Einbindung über `@Holiday/_kit/…` (Kit 0.2.0)
- [x] Sofort-Aktionen („…“ Genehmigen/Ablehnen, Feiertage „Synchronisieren“) über Kit-Attribute `data-kpu-post`/`-token`/`-ids`;
      eigenes Klick-Skript (`data-holiday-post`) entfernt
- [x] Modal-Formulare: `data-form-event: kpu.reload` statt eigenem Listener auf `kimai.holidayUpdate`;
      `formSuccess()` = Kit-Muster `kpuFormSuccess` (URL behalten: Bearbeiten, Löschen, ICS; `redirectToRouteAfterCreate()`:
      Abwesenheit anlegen → Jahr der Abwesenheit, Gruppe/Feiertag anlegen und Import → Gruppe/Jahr, Gruppe löschen → Übersicht)
- [x] Plural-Texte decken 0 ab (`{0}…` bei genehmigt/abgelehnt ergänzt)
- [x] Kein `<h2>` im Inhalt; Titel „Bereich · Zeitraum“ über `PageSetup`, Kontextzeile über `kit.context_line`
- [x] `setActionName()` + `setHelp(<volle URL>)` auf jeder Seite; Aktionen nur über `PageActionsEvent`
- [x] Inhalt in `{% block main %}` statt `page_content`
- [x] Kein `btn-xs`, kein `confirm()`, keine Inline-Handler (`onsubmit`, `onchange`, `onclick`)
- [x] Formate nur über Kimai-Filter (`date_short`, `amount`, `month_name`), kein `|date('Y-m-d')`/`number_format`
- [x] Erfolgsmeldungen mit Information (Import, Sync, ICS-Link, erneute Genehmigung) als `kpu_result`-Callout
- [x] Keine rohen Exception-Texte als Übersetzungskey (nur `holiday.error.*`, sonst Kimai-Standardfehler)
- [x] 390 px ohne waagrechtes Scrollen, Dunkelmodus ohne feste Farben (Core-Arbeitszeiten: Plugin-Links in ein Menü gefasst, sonst 479 px)

## Übersetzungen
- [x] Keine Core-Keys mehr überschreiben (`confirm.delete`, `yes`, `no`, `action.save`, `action.close`, `action.delete`,
      `action.delete.success`, `action.update.success`)
- [x] Eigene Keys unter `holiday.` (messages, flashmessages); de/en gleicher Key-Bestand
- [x] EN „Freizeitausgleich“ → „Time off in lieu“ (Typ „Time-Off“ ebenso)
- [x] Menü „Absence“ → „Absences“ (de „Abwesenheiten“)
- [x] Menü-Icons als Kimai-Aliase (`holiday`, `calendar`, `public-holiday`)

## Abwesenheiten (`/holiday/absence/{year}`)
- [x] Titel „Abwesenheiten · 2026“, Kontextzeile Benutzer · Jahr
- [x] Zeitraum über `kit.period_nav` (nur Jahr; Monat nicht sinnvoll, Urlaubskonto ist jahresbezogen)
- [x] Benutzerwahl über Kimai-`UserType` in der Kopfzeile statt nur `?user=`
- [x] Kopf-Aktionen: Abwesenheit anlegen (Modal), Export, Persönlicher Kalender (ICS, Modal mit Link + Neu erzeugen)
- [x] Urlaubskonto als `kit.kpi_bar`: Urlaub genommen, beantragt, Krankheitstage, Resturlaub (hervorgehoben)
- [x] Liste als Kimai-DataTable, Status als `kit.status_badge`, Tage über `|amount`, Halbtag über `label_boolean`
- [x] Spalten auf 390 px: Auswahl, Art, Zeitraum, Status, „…“
- [x] Zeilenmenü „…“: Bearbeiten (Modal), Genehmigen, Ablehnen, Löschen (Kimai-Modal)
- [x] Checkbox-Auswahl + Sammelaktion „Genehmigen“/„Ablehnen“ für Genehmiger, sofort + Rückgängig-Toast (Undo = wieder „Beantragt“)
- [x] Rückgängig-Fenster nach GUIDELINES 3.5: Session-Eintrag je Aktion (Benutzer, IDs, Zustand danach, Zeit), 15 min, nur
      gleicher Benutzer/gleiche Sitzung, nur IDs der Aktion, nur unverändert; Genehmiger-Recht wird weiter geprüft (keine Ausnahme)
- [x] Anlegen/Bearbeiten als Kimai-Modal (`modal-ajax-form`, `_form_modal`/`_form`), Fehler als Formularfehler
- [x] Leerzustand über `kit.empty_state` mit Link „Abwesenheit anlegen“, öffnet das Kimai-Modal (`'modal-ajax-form'`, Kit 0.2)
- [x] ICS-Link neu erzeugen mit Kimai-Modal, Ergebnis als Callout

## Abwesenheitskalender (`/holiday/absence-calendar/{year}[/{month}]`)
- [x] Titel „Abwesenheitskalender · 2026“ bzw. „… · September 2026“, Kontextzeile Team
- [x] Zeitraum über `kit.period_nav` mit Segment Monat | Jahr (Monatsroute zeigt jetzt nur den Monat statt Weiterleitung)
- [x] Teamfilter als Kimai-Formularfeld (Tom-Select) in der Kopfzeile, ohne `onchange`-Attribut
- [x] Beantragt vs. genehmigt unterscheidbar (beantragt blass + Legende mit Status-Badges)
- [x] Wochenenden dezent hinterlegt (Tabler-Klasse)
- [x] Monatsüberschrift über `month_name(true)`, Leerzustand über `kit.empty_state`

## Feiertage (`/holiday/public-holidays/{year}`)
- [x] Titel „Feiertage · 2026“, Kontextzeile Gruppe
- [x] Jahr über `kit.period_nav` (richtige Reihenfolge ‹ 2026 ›)
- [x] Aktive Gruppe lesbar (`list-group-item-action active` ohne `text-white`)
- [x] Kopf-Aktionen: Gruppe anlegen, Feiertag hinzufügen, Importieren (je Modal), Synchronisieren (sofort, Ergebnis als Toast/Callout)
- [x] Feiertagsliste mit Kimai-DataTable-Makros, Halbtag über `label_boolean`, „…“ mit Löschen (Kimai-Modal)
- [x] Gruppe löschen über „…“ + Kimai-Modal statt `&times;` + `confirm()`
- [x] Import-/Sync-Anzahl als Ergebnis-Callout, Fehler mit Übersetzungskey
- [x] Leerzustände über `kit.empty_state` (keine Gruppe → „Gruppe anlegen“, keine Feiertage → „Importieren“), Links als Modal

## Manuelle Buchung (`/holiday/booking/create`)
- [x] Kimai-`_form`-Karte (Seite, weil der Arbeitszeiten-Bildschirm des Cores nicht per Event neu lädt), Hinweis als Feldhilfe
- [x] Titel „Manuelle Buchung“, Kontextzeile Benutzer, `setHelp`

## Monats-PDF (`/holiday/working-times/{year}/{month}/pdf`)
- [x] Im Core-Arbeitszeiten-Bildschirm erreichbar (Aktion „Monats-PDF“ mit Monatsauswahl über `contract_links`)
- [x] PDF-Inhalt selbst bleibt (Leitfaden gilt nicht für PDFs)

## Core-Overrides
- [x] `user/contract.html.twig` entfernen: Kimai 2.67 rendert Zusatzfelder bereits über `form_rest()`
- [x] `contract/status.html.twig` bleibt (keine Event-Alternative, um zukünftige Tage mit Abwesenheit/Feiertag zu zeigen);
      Diff zum Core 2.67 auf die eine Bedingung beschränkt
- [x] `ContractLinksSubscriber`: Jahr aus Kimais `Year`-Objekt lesen, Icons als Kimai-Aliase
- [x] Totes Template `contract/working_times.html.twig` entfernen

## Sicherheit (Basis-Branch, nicht zurückdrehen)
- [x] Team-Scoping (Teamlead nur eigene Teams) für Liste, Aktionen, Export, Kalender – live geprüft
- [x] CSRF für alle POSTs inkl. neuer Sammel-/Undo-Routen – live geprüft (403 ohne Token)
- [x] Sammelaktion prüft die Berechtigung für alle gewählten Abwesenheiten, bevor eine geändert wird (vorher: teilweise geändert, dann 403)
- [x] ICS-Regeln (nur Besitzer/Admin), Validierung – unverändert

## Offen / bewusst nicht umgesetzt
- [ ] Radio-Buttons „Art“ zeigen im horizontalen Kimai-Formular an jeder Option ein Pflicht-Sternchen – Kimai-Theme-Verhalten, nicht plugin-spezifisch
- [ ] Monats-PDF selbst (HTML-Ausgabe mit festen Formaten/Texten) – Leitfaden gilt nicht für PDFs
- [ ] Monatsansicht der Abwesenheitsliste – Urlaubskonto und Export sind jahresbezogen, Monat brächte keinen Mehrwert
