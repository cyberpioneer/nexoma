# ROADMAP (Status)

Stand: 2026-11-09. Gitflow: `main` nur Releases, `develop` Integration, `feature/*` je Meilenstein.

| Meilenstein | Inhalt | Status |
|---|---|---|
| M1 | BA-API-Client | fertig, `main` (2b15eba) |
| M2 | Requests, Limiter | fertig, `develop` |
| M3 | Auth/Fortify | fertig, `develop` |
| M4 | Mail, Verifizierung, Reset | fertig, `develop` |
| M5 | i18n, Frontend-Basis | fertig, `develop` |
| M6 | Jobs-Seiten | fertig, `develop` |
| M7 Teil A | Passport-Basis, OAuth-Endpunkte, DCR-Schutz, PKCE S256 | fertig, `develop` |
| M7 Teil B | Consent-Seite, Verbundene Apps, Fehlerseite | fertig, `develop` |
| M7/M8 Teil C | MCP-Server, Tools, Audit | fertig, `develop` |
| M9 | Favoriten (gespeicherte Vakanzen mit Vermerk) | umgesetzt und geprüft auf `feature/saved-jobs`; Migration `saved_jobs` noch auszuführen; Datenschutzerklärung anpassen |
| M9a | MCP-Tool `save_job` (MCP-SAVE-1): erstes schreibendes Tool, Scope `mcp:use` erweitert (kein neuer Scope, kein Re-Consent) | umgesetzt auf `feature/mcp-save-job`, Review ausstehend |
| REG-CONSENT-1 | Pflicht-Checkbox bei der Registrierung (Kenntnisnahme Datenschutz/Haftungsausschluss) mit Nachweis (`users.consented_at`, `users.consent_version`); Migration noch auszuführen | umgesetzt auf `feature/register-consent`, Review ausstehend |
| M10 | Härtung, Deployment-Checkliste | fertig, `develop` |

## Deployment-Checkliste (Release 1)

- [ ] **Umgebung:** `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` gesetzt, `APP_URL=https://<host>` (maßgeblich für Mail-Links, Host-Prüfung, Passport-Issuer), `LOG_LEVEL` auf `warning` oder höher, Cache-/Queue-/Session-Treiber produktiv (nicht `array`/`sync`), Konfiguration mit `php artisan config:cache`.
- [ ] **Cookies:** `SESSION_SECURE_COOKIE=true` (in Produktion Standard), `SESSION_SAME_SITE=lax`, `SESSION_HTTP_ONLY=true`.
- [ ] **Proxys/Hosts:** `TRUSTED_PROXIES` auf die IPs/CIDR des Proxys setzen (leer = kein Vertrauen; `*` nur, wenn die App ausschließlich hinter dem Proxy erreichbar ist, sonst lassen sich IP-Limiter umgehen; bei `*` wird auch `X-Forwarded-Host` vertraut, ein direkt erreichbarer Server erlaubt dann Host-Spoofing). Ohne `TRUSTED_PROXIES` wird nie vertraut, auch nicht auf Laravel Cloud oder `*.on-forge.com`/`*.on-vapor.com` (Framework-Fallback bewusst abgeschaltet: dort muss die Proxy-Liste ausdrücklich gesetzt werden). `TRUSTED_HOSTS` leer = Host aus `APP_URL`; die Host-Prüfung gilt auch für `/up`, ein Load-Balancer-Healthcheck muss den App-Host senden (oder `TRUSTED_HOSTS` ergänzt werden), sonst 400. HTTPS am Proxy terminieren, HSTS sendet die App selbst (nur Produktion + HTTPS).
- [ ] **Datenbank:** `php artisan migrate --force` (Backup vorher); keine `migrate:fresh`/`db:wipe`.
- [ ] **Passport-Schlüssel:** `php artisan passport:keys` in der Zielumgebung ausführen (nicht aus Entwicklung kopieren) oder `PASSPORT_PRIVATE_KEY`/`PASSPORT_PUBLIC_KEY` aus dem Secret-Store; `storage/oauth-private.key` Rechte `0600`, nicht im Repository. Hinweis: In der Entwicklungsumgebung stürzte die Schlüsselerzeugung wegen der GMP-Erweiterung ab; Zielumgebung vorab prüfen.
- [ ] **MCP/OAuth:** `MCP_REDIRECT_DOMAINS` auf verifizierte Clients begrenzen (kein `localhost`/`127.0.0.1` in Produktion), `MCP_AUTHORIZATION_SERVER` = `APP_URL`, `MCP_REGISTRATIONS_PER_DAY`/`MCP_REGISTRATIONS_TOTAL` prüfen.
- [ ] **BA-API:** `JOBSUCHE_BASE_URL` (https), `JOBSUCHE_API_KEY`, `JOBSUCHE_GLOBAL_LIMIT_PER_MINUTE` (Standard 600) mit dem BA-Kontingent abstimmen.
- [ ] **Scheduler:** Cron `* * * * * php artisan schedule:run` (führt `passport:purge` stündlich und `oauth:prune-clients` täglich aus).
- [ ] **Queue-Worker:** `php artisan queue:work` unter Supervisor/systemd, Neustart nach Deployments (`queue:restart`); Mails (Verifizierung, Passwort-Reset) laufen sonst verzögert, Reset-Timing beachten (M3 F-06).
- [ ] **Logs:** `MCP_AUDIT_LOG_DAYS` (Standard 90) und Rotation des Log-Kanals festlegen; Logs enthalten keine Suchparameter oder Tokens.
- [ ] **Build:** `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan route:cache`/`view:cache`/`event:cache`.
- [ ] **Rechtstexte:** `php artisan legal:check` muss vor dem Go-Live grün sein (keine offenen `[…]`-Platzhalter in `resources/legal/*.md`); Texte sind Entwürfe ohne Rechtsberatung und vorher rechtlich zu prüfen (Hinweise in den Kommentaren der Dateien).
- [ ] **Abnahme vor Freigabe:** gesamte Suite, Pint, PHPStan, `npm run types:check`; manuelle Abnahme T-13 (OAuth-Flow mit realem MCP-Client, Consent, Widerruf unter „Verbundene Apps").
- Offen (nicht in Release 1): Content-Security-Policy, Erkennung der Wiederverwendung von Refresh-Tokens, Kontolöschung.
- [ ] **Datenschutzerklärung abgleichen:** `MCP_AUDIT_LOG_DAYS` (Text: 90 Tage) und `LOG_DAILY_DAYS` (Text: 14 Tage) müssen mit der Produktions-`.env` übereinstimmen; Hoster, Mail-Anbieter und Logfile-Frist ersetzen (`php artisan legal:check`).
