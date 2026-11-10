<p align="center">
  <img src="https://nexoma.me/nexoma.png" alt="Nexoma Logo" width="140">
</p>

<h1 align="center">Nexoma</h1>

<p align="center">
  Stellensuche der Bundesagentur für Arbeit – in sechs Sprachen,<br>
  im Browser und über KI-Assistenten.
</p>

<p align="center">
  <a href="https://nexoma.me">nexoma.me</a> · <a href="LICENSE">Lizenz</a> · <a href="mailto:service@nomnix.net">Kontakt</a>
</p>

---

## Was ist Nexoma?

Nexoma macht die Stellensuche der Bundesagentur für Arbeit (BA) mehrsprachig zugänglich. Wer in Deutschland Arbeit sucht und die deutsche Oberfläche nicht gut versteht, findet hier dieselben Stellen – bedienbar auf **Deutsch, Englisch, Russisch, Ukrainisch, Spanisch oder Italienisch**.

Zwei Wege zur Stelle:

1. **Direkt im Browser:** suchen, Details ansehen, zum offiziellen Bewerbungsweg.
2. **Über den eigenen KI-Assistenten** (ChatGPT, Claude, Gemini u. a.): Der Assistent wird mit dem eigenen Nexoma-Konto verbunden und sucht per MCP.

Stellentexte bleiben in der Originalsprache der BA. Nexoma speichert keine Suchtexte, setzt kein Tracking ein und zeigt keine Werbung.

## Funktionen

- Konto mit E-Mail-Verifizierung, Anmeldung und Passwort zurücksetzen
- Stellensuche mit Filtern (Ort, Umkreis, Angebotsart, Berufsfeld, Zeitraum) und Detailseite mit geprüftem Link zur Bewerbung
- **Gespeicherte Vakanzen:** mit oder ohne persönlichen Vermerk speichern, Vermerk ändern, wieder löschen
- Oberfläche in 6 Sprachen (de, en, ru, uk, es, it), Hell- und Dunkelmodus
- **KI-Anbindung per MCP** (nur lesend): `job_search`, `job_details`, `job_search_options`; Anmeldung der Assistenten per OAuth (PKCE), Zustimmung und Widerruf unter „Verbundene Apps“
- Installierbar als **PWA** (Android, iOS, Windows, macOS, Linux)
- Impressum, Datenschutzerklärung und Haftungsausschluss als eigene Seiten

## Technik

| Bereich     | Eingesetzt                                                                 |
| ----------- | -------------------------------------------------------------------------- |
| Backend     | Laravel 13, PHP 8.5, Fortify (Anmeldung), Passport (OAuth), `laravel/mcp`  |
| Frontend    | Inertia v3, Vue 3 (TypeScript), Tailwind CSS 4, Wayfinder                  |
| Datenbank   | PostgreSQL (Tests: SQLite im Speicher)                                     |
| Datenquelle | Jobsuche-API der Bundesagentur für Arbeit (nur serverseitig)               |
| Qualität    | Pest, Larastan, Pint, Vite+ (`vp check`), `vue-tsc`, CI mit GitHub Actions |

## Installation (Entwicklung)

Voraussetzungen: PHP 8.5, Composer, Node.js, PostgreSQL.

```bash
composer setup        # Abhängigkeiten, .env, App-Key, Migrationen, Build
composer dev          # Entwicklungsserver
vendor/bin/pest       # Tests
composer ci:check     # alle Prüfungen wie in der CI
```

Für OAuth und MCP zusätzlich Passport-Schlüssel erzeugen (`php artisan passport:keys`) und die MCP-Einstellungen in der `.env` prüfen (siehe `.env.example`, z. B. `MCP_REDIRECT_DOMAINS`).

**Produktivbetrieb (Auswahl):** `APP_ENV=production`, `APP_DEBUG=false`, `composer install --no-dev`, `npm run build`, `php artisan migrate`, Passport-Schlüssel in `storage/` (Rechte `0600`, über Deployments erhalten). Die Deployment-Checkliste steht in `ROADMAP.md`.

## Lizenz

**Alle Rechte vorbehalten.** Die Software darf nur privat und nicht kommerziell für eigene Zwecke genutzt werden. Weitergabe, Bereitstellung für Dritte (auch Hosting oder SaaS, kostenfrei oder gegen Entgelt), Verkauf und Änderungen sind ohne schriftliche Kommerzlizenz nicht erlaubt.

Maßgeblich ist der Text in der Datei [LICENSE](LICENSE) (PolyForm Strict License 1.0.0 mit Zusatzbedingungen). Für eine Kommerzlizenz melden Sie sich bitte unter [kontakt@nomnix.net](mailto:kontakt@nomnix.net).

Drittkomponenten (Laravel, Pakete, Schriften) behalten ihre eigenen Lizenzen.

## Betreiber und Hinweis

NOMNIX // Slawa Mironow, Bad Mergentheim.

Die Stellenangebote stammen von der Bundesagentur für Arbeit. Nexoma ist ein unabhängiges Projekt und kein offizielles Angebot der BA.
