# UI-TODO – Umstellung auf kimai-plugin-ui

Grundlage: [kimai-plugin-ui GUIDELINES.md / CHECKLIST.md](https://github.com/shrippen/kimai-plugin-ui), Leitfaden Abschnitt 6 (Holiday)
und UI-Inventar. Kit-Version siehe `Resources/views/_kit/VERSION`.

## Alle Seiten
- [ ] Kit mit `bin/sync.sh` übernommen, Einbindung über `@Holiday/_kit/…`
- [ ] Kein `<h2>` im Inhalt; Titel „Bereich · Zeitraum“ über `PageSetup`, Kontextzeile über `kit.context_line`
- [ ] `setActionName()` + `setHelp(<volle URL>)` auf jeder Seite; Aktionen nur über `PageActionsEvent`
- [ ] Inhalt in `{% block main %}` statt `page_content`
- [ ] Kein `btn-xs`, kein `confirm()`, keine Inline-Handler (`onsubmit`, `onchange`, `onclick`)
- [ ] Formate nur über Kimai-Filter (`date_short`, `amount`, `month_name`), kein `|date('Y-m-d')`/`number_format`
- [ ] Erfolgsmeldungen mit Information (Import, Sync, ICS-Link, erneute Genehmigung) als `kpu_result`-Callout
- [ ] Keine rohen Exception-Texte als Übersetzungskey (nur `holiday.error.*`, sonst Kimai-Standardfehler)
- [ ] 390 px ohne waagrechtes Scrollen, Dunkelmodus ohne feste Farben

## Übersetzungen
- [ ] Keine Core-Keys mehr überschreiben (`confirm.delete`, `yes`, `no`, `action.save`, `action.close`, `action.delete`,
      `action.delete.success`, `action.update.success`)
- [ ] Eigene Keys unter `holiday.` (messages, flashmessages); de/en gleicher Key-Bestand
- [ ] EN „Freizeitausgleich“ → „Time off in lieu“ (Typ „Time-Off“ ebenso)
- [ ] Menü „Absence“ → „Absences“ (de „Abwesenheiten“)
- [ ] Menü-Icons als Kimai-Aliase (`holiday`, `calendar`, `public-holiday`)

## Abwesenheiten (`/holiday/absence/{year}`)
- [ ] Titel „Abwesenheiten · 2026“, Kontextzeile Benutzer · Jahr
- [ ] Zeitraum über `kit.period_nav` (nur Jahr; Monat nicht sinnvoll, Urlaubskonto ist jahresbezogen)
- [ ] Benutzerwahl über Kimai-`UserType` in der Kopfzeile statt nur `?user=`
- [ ] Kopf-Aktionen: Abwesenheit anlegen (Modal), Export, Persönlicher Kalender (ICS, Modal mit Link + Neu erzeugen)
- [ ] Urlaubskonto als `kit.kpi_bar`: Urlaub genommen, beantragt, Krankheitstage, Resturlaub (hervorgehoben)
- [ ] Liste als Kimai-DataTable, Status als `kit.status_badge`, Tage über `|amount`, Halbtag über `label_boolean`
- [ ] Spalten auf 390 px: Auswahl, Art, Zeitraum, Status, „…“
- [ ] Zeilenmenü „…“: Bearbeiten (Modal), Genehmigen, Ablehnen, Löschen (Kimai-Modal)
- [ ] Checkbox-Auswahl + Sammelaktion „Genehmigen“/„Ablehnen“ für Genehmiger, sofort + Rückgängig-Toast (Undo = wieder „Beantragt“)
- [ ] Anlegen/Bearbeiten als Kimai-Modal (`modal-ajax-form`, `_form_modal`/`_form`), Fehler als Formularfehler
- [ ] Leerzustand über `kit.empty_state` mit Link „Abwesenheit anlegen“
- [ ] ICS-Link neu erzeugen mit Kimai-Modal, Ergebnis als Callout

## Abwesenheitskalender (`/holiday/absence-calendar/{year}[/{month}]`)
- [ ] Titel „Abwesenheitskalender · 2026“ bzw. „… · September 2026“, Kontextzeile Team
- [ ] Zeitraum über `kit.period_nav` mit Segment Monat | Jahr (Monatsroute zeigt jetzt nur den Monat statt Weiterleitung)
- [ ] Teamfilter als Kimai-Formularfeld (Tom-Select) in der Kopfzeile, ohne `onchange`-Attribut
- [ ] Beantragt vs. genehmigt unterscheidbar (beantragt blass + Legende mit Status-Badges)
- [ ] Wochenenden dezent hinterlegt (Tabler-Klasse)
- [ ] Monatsüberschrift über `month_name(true)`, Leerzustand über `kit.empty_state`

## Feiertage (`/holiday/public-holidays/{year}`)
- [ ] Titel „Feiertage · 2026“, Kontextzeile Gruppe
- [ ] Jahr über `kit.period_nav` (richtige Reihenfolge ‹ 2026 ›)
- [ ] Aktive Gruppe lesbar (`list-group-item-action active` ohne `text-white`)
- [ ] Kopf-Aktionen: Gruppe anlegen, Feiertag hinzufügen, Importieren (je Modal), Synchronisieren (sofort, Ergebnis als Toast/Callout)
- [ ] Feiertagsliste mit Kimai-DataTable-Makros, Halbtag über `label_boolean`, „…“ mit Löschen (Kimai-Modal)
- [ ] Gruppe löschen über „…“ + Kimai-Modal statt `&times;` + `confirm()`
- [ ] Import-/Sync-Anzahl als Ergebnis-Callout, Fehler mit Übersetzungskey
- [ ] Leerzustände über `kit.empty_state` (keine Gruppe → „Gruppe anlegen“, keine Feiertage → „Importieren“)

## Manuelle Buchung (`/holiday/booking/create`)
- [ ] Kimai-`_form`-Karte (Seite, weil der Arbeitszeiten-Bildschirm des Cores nicht per Event neu lädt), Hinweis als Feldhilfe
- [ ] Titel „Manuelle Buchung“, Kontextzeile Benutzer, `setHelp`

## Monats-PDF (`/holiday/working-times/{year}/{month}/pdf`)
- [ ] Im Core-Arbeitszeiten-Bildschirm erreichbar (Aktion „Monats-PDF“ mit Monatsauswahl über `contract_links`)
- [ ] PDF-Inhalt selbst bleibt (Leitfaden gilt nicht für PDFs)

## Core-Overrides
- [ ] `user/contract.html.twig` entfernen: Kimai 2.67 rendert Zusatzfelder bereits über `form_rest()`
- [ ] `contract/status.html.twig` bleibt (keine Event-Alternative, um zukünftige Tage mit Abwesenheit/Feiertag zu zeigen);
      Diff zum Core 2.67 auf die eine Bedingung beschränkt
- [ ] `ContractLinksSubscriber`: Jahr aus Kimais `Year`-Objekt lesen, Icons als Kimai-Aliase
- [ ] Totes Template `contract/working_times.html.twig` entfernen

## Sicherheit (Basis-Branch, nicht zurückdrehen)
- [ ] Team-Scoping (Teamlead nur eigene Teams) für Liste, Aktionen, Export, Kalender – live geprüft
- [ ] CSRF für alle POSTs inkl. neuer Sammel-/Undo-Routen – live geprüft (403 ohne Token)
- [ ] ICS-Regeln (nur Besitzer/Admin), Validierung – unverändert
