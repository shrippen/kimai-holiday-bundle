# Automatisches Deployment

Jeder Push auf `master` (also jeder Merge) installiert das Plugin auf `zafe-server-1` — gleiches Muster wie
`simudrom-website`: GitHub Action → SSH (Port 2222) → Skript auf dem Server.
Änderungen nur an Doku, `dev/`, `demo/`, `Tests/`, `.claude/` oder `*.md` lösen keinen Deploy aus.
Von Hand: Actions → „Deploy to Server" → *Run workflow*.

## Ablauf

1. `.github/workflows/deploy.yml`: PHP-Lint, Zip wie im Release (`git archive`, `export-ignore`), Zip und
   `deploy/deploy-prod.sh` per SCP nach `~felix/deploy/kimai-holiday/incoming/`.
2. `deploy/deploy-prod.sh` auf dem Server:
   - Datenbank-Dump nach `~felix/deploy/kimai-holiday/backups/` (die letzten 10 bleiben)
   - aktuelles Plugin nach `~felix/deploy/kimai-holiday/previous/HolidayBundle`
   - Zip entpacken, `chown www-data`, `kimai:reload` und `kimai:bundle:holiday:install` als www-data
   - Prüfung: `/de/login` → 200, Plugin-Seiten → 302, keine 500/CRITICAL/Fatal im Log seit dem Reload
   - schlägt die Prüfung fehl: vorheriges Plugin zurück, Reload, Job wird rot

Migrationen rollt der Rückweg nicht zurück; dafür gibt es den Dump. Das Release-Zip auf GitHub entsteht
weiter nur über ein Tag `vX.Y.Z` (`release.yml`); der Deploy braucht kein Tag und keinen Versionssprung.

## Zugang

Secrets im Repo: `SERVER_HOST`, `SERVER_USER` (`felix`), `SERVER_SSH_KEY`. Der Schlüssel ist ein eigener
Deploy-Schlüssel (Kommentar `github-actions-kimai-holiday-deploy`), in `~felix/.ssh/authorized_keys` mit
`restrict` eingetragen (kein Port-Forwarding, kein PTY). Neuer Schlüssel: Paar erzeugen, öffentliche Hälfte
dort ersetzen, `gh secret set SERVER_SSH_KEY < key`.

## Von Hand zurück

```
docker exec kimai rm -rf /opt/kimai/var/plugins/HolidayBundle
docker cp ~/deploy/kimai-holiday/previous/HolidayBundle kimai:/opt/kimai/var/plugins/HolidayBundle
docker exec kimai chown -R www-data:www-data /opt/kimai/var/plugins/HolidayBundle
docker exec -u www-data -w /opt/kimai kimai bin/console kimai:reload
```
