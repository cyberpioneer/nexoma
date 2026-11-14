# Nexoma 1.2.0 – ROADMAP.md

## Verbindliche Leitplanken für den Orchestrator

1. **Bestandsschutz (Release 1.1.0):** Bestehende Funktionen (insb. Authentifizierung, das manuelle Suchformular und bestehende MCP-Tools) dürfen nicht angefasst, umgestaltet oder beeinträchtigt werden.
2. **Keine DTOs:** Nutze ausschließlich Laravel Form Requests, Eloquent Model-Instanzen, Laravel API Resources und Arrays.
3. **2-Agenten-Workflow:** Der Orchestrator implementiert und testet selbst; vor Abschluss jedes Tasks (ab `TASK-002`) wird zwingend `TASK.md` aktualisiert und der Subagent `security-reviewer` aufgerufen.
4. **GitHub Kanban "NEXOMA":** Alle Tasks dieser Roadmap werden im GitHub-Projekt `"NEXOMA"` als Issues/Karten angelegt und synchron zum Fortschritt über die Spalten (`Todo` -> `In Progress` -> `Done`) bewegt.

---

## Phase 0: Projektmanagement & Kanban-Initialisierung

- [x] **TASK-001: GitHub-Projekt "NEXOMA" und Kanban-Board einrichten**
  - **Ziel:** Anlegen des GitHub-Projekts `"NEXOMA"` und Erfassung aller Tasks (`TASK-002` bis `TASK-012`) als Issues/Karten auf dem Kanban-Board.
  - **Akzeptanzkriterien:**
    - AK-1: Das GitHub-Projekt `"NEXOMA"` existiert und verfügt über Status-Spalten zur Fortschrittssteuerung (`Todo`, `In Progress`, `Done`).
    - AK-2: Für `TASK-002` bis `TASK-012` sind Issues angelegt, dem Projekt `"NEXOMA"` zugewiesen und die jeweiligen Issue-IDs (`#...`) in dieser `ROADMAP.md` ergänzt.

---

## Phase 1: Stripe Subscription & Paywall

- [x] **TASK-002 (#12): Stripe Subscription Integration & Webhook-Verarbeitung**
  - **Ziel:** Implementierung der wiederkehrenden Zahlungsabwicklung (Subscription) über Stripe mithilfe der in `.env` und `.env.testing` hinterlegten Credentials sowie des Stripe-MCP.
  - **Akzeptanzkriterien:**
    - AK-1: Datenbank-Migrationen und Model-Methoden zur Speicherung und Prüfung des aktiven Stripe-Abonnements pro Benutzer sind implementiert.
    - AK-2: Endpunkte für Checkout-Start, Erfolgs-/Abbruch-Rückkehr und Abo-Verwaltung stehen bereit.
    - AK-3: Der Stripe-Webhook verarbeitet Statusänderungen von Subscriptions zuverlässig und prüft zwingend die Stripe-Signatur.
    - AK-4: Alle neuen UI-Texte sind in allen 6 Sprachen (`lang/*/ui.php`) hinterlegt; Pest-Feature-Tests für Checkout und Webhooks sind grün.
  - **Review-Schwerpunkte (`security-reviewer`):** Webhook-Signaturvalidierung, Schutz vor Manipulation des Abo-Status durch Client-Requests, keine Preis-/Plan-Übergabe aus unvalidierten Benutzereingaben.

- [x] **TASK-003 (#13): Paywall-Middleware (`EnsureUserIsSubscribed`) für neue Premium-Funktionen**
  - **Ziel:** Absicherung aller neuen kostenpflichtigen Funktionen aus Release 1.2.0 bei gleichzeitiger Beibehaltung der kostenlosen Nutzung aller 1.1.0-Funktionen.
  - **Akzeptanzkriterien:**
    - AK-1: Middleware `EnsureUserIsSubscribed` blockiert Zugriffe ohne aktives Abo (Redirect auf Upgrade-Seite bzw. `402/403` bei JSON/XHR).
    - AK-2: Manuelle Stellensuche, Job-Details, manuelles Speichern und bestehende Auth-/MCP-Routen bleiben für alle verifizierten Benutzer kostenlos zugänglich.
    - AK-3: Pest-Feature-Tests verifizieren sowohl den freien Zugang zu Bestandsfunktionen als auch die Paywall für Nicht-Abonnenten.
  - **Review-Schwerpunkte (`security-reviewer`):** Lückenlose Durchsetzung der Middleware auf allen Premium-Routen, Rückwärtskompatibilität der 1.1.0-Routen.

---

## Phase 2: Profil, Werdegang & KI-Optimierung (MiniMax)

- [x] **TASK-004 (#14): Datenbank-Schema, Models, Form Requests & Resources für Profil und Werdegang**
  - **Ziel:** Persistenzschicht für Profil-Freitext, Sprachwahl, Suchort und den Berufs-/Ausbildungswerdegang ohne Verwendung von DTOs.
  - **Akzeptanzkriterien:**
    - AK-1: Speicherung am Benutzerprofil für: `preferred_language` (`de`, `en`, `ru`, `uk`, `es`, `it`), `bio` (Freitext bis max. 420 Zeichen), `search_location` (Wohn-/Suchort) und `search_radius` (Umkreis: 5, 10, 25, 50, 100, 200 km).
    - AK-2: Neue Tabelle und Eloquent Model `CareerEntry` (`user_id`, `what` [Was?], `where` [Wo?], `from_date` [Von], `to_date` [Bis, nullable], `qualification_or_role` [Abschluss oder Tätigkeit?]).
    - AK-3: Validierung erfolgt über Laravel Form Requests (`UpdateCareerProfileRequest`), Datenübergabe über Eloquent Models und Laravel API Resources.
    - AK-4: Policies und Pest-Tests stellen sicher, dass Benutzer ausschließlich ihre eigenen Profil- und Werdegangsdaten lesen, anlegen, ändern und löschen können.
  - **Review-Schwerpunkte (`security-reviewer`):** IDOR-Schutz bei `CareerEntry`-Operationen, Mass-Assignment-Schutz (`$fillable`), strikte Validierung der 420-Zeichen-Grenze und erlaubter Sprachcodes.

- [x] **TASK-005 (#15): Vue-3-Formular für Profil, Sprachwahl und Berufs-/Ausbildungswerdegang**
  - **Ziel:** Benutzeroberfläche zur Eingabe und Pflege von Profil, Sprache, Wohn-/Suchort und Werdegang (geschützt hinter der Stripe-Paywall).
  - **Akzeptanzkriterien:**
    - AK-1: Formular unter Nutzung von Vue 3 (Composition API mit `script setup`) und Inertia.js im bestehenden Nexoma-Design-System (`paper`, `surface`, `ink` etc.).
    - AK-2: Textfeld für `bio` (max. 420 Zeichen inkl. Zähler), Auswahl der gewünschten Sprache (`preferred_language` für Summaries & Audio-Briefings), Wohn-/Suchort und Umkreis.
    - AK-3: Dynamische Formularsektion für den Berufs- und Ausbildungswerdegang (Hinzufügen, Bearbeiten, Löschen von Einträgen mit: Was? Wo? Von – Bis? Abschluss oder Tätigkeit?).
    - AK-4: Vollständige Übersetzung in allen 6 Sprachen (`lang/*/ui.php`) und Einhaltung von WCAG 2.2 AA.
  - **Review-Schwerpunkte (`security-reviewer`):** Schutz vor XSS, keine Offenlegung fremder oder interner Attribute über Inertia-Props.

- [x] **TASK-006 (#16): MiniMax-Anbindung (Laravel AI SDK) & Button "Mit KI optimieren"**
  - **Ziel:** Integration von MiniMax über das Laravel AI SDK (`OPENAI_COMPATIBLE_API_KEY`, `OPENAI_COMPATIBLE_URL`) und Implementierung des Buttons „Mit KI optimieren“ im Profilformular.
  - **Akzeptanzkriterien:**
    - AK-1: Konfiguration von `OPENAI_COMPATIBLE_API_KEY` und `OPENAI_COMPATIBLE_URL` in `config/services.php` bzw. `config/ai.php` gemäß MiniMax-Dokumentation (`https://platform.minimax.io/docs/api-reference/text-openai-api`).
    - AK-2: Geschützter Endpunkt (`POST /profile/optimize-bio`, Middleware `EnsureUserIsSubscribed` + Rate-Limiting `throttle:5,1`), Validierung über `OptimizeBioRequest`.
    - AK-3: Der Service passt den übergebenen Profiltext in der gewählten `preferred_language` grammatisch, syntaktisch und attraktiv an und hält das Limit von maximal 420 Zeichen strikt ein.
    - AK-4: Button „Mit KI optimieren“ im Profilformular ruft den Endpunkt auf und übernimmt den optimierten Text in das Formularfeld; abgedeckt durch Pest-Tests (mit HTTP-Fake).
  - **Review-Schwerpunkte (`security-reviewer`):** Rate-Limiting gegen Kostenexplosion, Kapselung der Benutzereingabe im Prompt, kein Leaken von API-Keys in Fehlermeldungen oder Logs.

---

## Phase 3: Automatische Hintergrund-Vakanzensuche (`ai_matcher`)

- [x] **TASK-007 (#17): Erweiterung `saved_jobs` & Deduplizierung bereits erwähnter Vakanzen**
  - **Ziel:** Erweiterung der Tabelle `saved_jobs` um KI-Bewertungsfelder, Quell-Attribut (`manual` vs. `ai_matcher`) und Schutz vor doppelter Aufnahme/Erwähnung.
  - **Akzeptanzkriterien:**
    - AK-1: Migration erweitert `saved_jobs` um: `source` (`'manual'` | `'ai_matcher'`, Default `'manual'`), `ai_summary` (String, max. 255 Zeichen, nullable), `temperature` (unsignedTinyInteger 1–10, nullable), `distance_km` (Integer/Decimal, nullable), `briefed_at` (Timestamp, nullable).
    - AK-2: Persistenter Deduplizierungs-Mechanismus (z. B. `seen_job_references` mit `user_id` und `refnr` + Unique-Constraint auf `saved_jobs[user_id, refnr]`), sodass einmal gefundene oder in einem Briefing erwähnte Vakanzen niemals erneut für denselben Benutzer gespeichert oder erwähnt werden.
    - AK-3: Bestehendes manuelles Speichern über Web und MCP (`save_job`) setzt weiterhin zuverlässig `source = 'manual'`.
  - **Review-Schwerpunkte (`security-reviewer`):** Datenintegrität durch Datenbank-Constraints, keine Regression beim bestehenden MCP-Tool `save_job`.

- [x] **TASK-008 (#18): Hintergrund-Job für KI-Vakanzensuche, Bewertung (1–10) & Distanz**
  - **Ziel:** Regelmäßiger Hintergrund-Job (Intervall über `.env` konfigurierbar), der für aktive Premium-Profile bis zu 10 neue Vakanzen sucht, bewertet und in `saved_jobs` speichert.
  - **Akzeptanzkriterien:**
    - AK-1: Steuerung des Ausführungsintervalls über `.env` (z. B. `JOB_MATCHER_INTERVAL_MINUTES`) im Laravel Scheduler; verarbeitet werden nur Benutzer mit aktivem Abo und ausgefülltem Profil.
    - AK-2: MiniMax leitet aus Profiltext (`bio`) und Werdegang (`career_entries`) automatisch den optimalen Suchbegriff (`was`) für die BA-API ab.
    - AK-3: Abruf über `JobsucheApiClient` mit abgeleitetem `was`, `wo` (`search_location`), `umkreis` (`search_radius`) und leerem `berufsfeld`. Herausfiltern bereits bekannter `refnr`; Begrenzung auf maximal **10 neue Vakanzen** pro Lauf und Profil.
    - AK-4: Übergabe der Vakanz-Details (strikt als Fremdtext `untrusted_job_data` im Prompt isoliert) an MiniMax zur Erzeugung von `ai_summary` (max. 255 Zeichen in `preferred_language`), `temperature` (Passung 1–10) und `distance_km`.
    - AK-5: Speicherung der bewerteten Vakanzen in `saved_jobs` mit `source = 'ai_matcher'` und `briefed_at = null`; vollständige Pest-Feature-Tests.
  - **Review-Schwerpunkte (`security-reviewer`):** Strikte Isolation von `untrusted_job_data` gegen Prompt-Injection, strikte Validierung der KI-Ausgaben (`temperature` 1–10, `ai_summary` max. 255 Zeichen) vor dem DB-Write, strikte Einhaltung des Limits von 10 Vakanzen pro Lauf.

---

## Phase 4: Audio-Briefings mit Piper TTS

- [x] **TASK-009 (#19): `audio_briefings`-Schema & `PiperTtsService` mit 6-Sprachen-Mapping**
  - **Ziel:** Umsetzung der lokalen Text-to-Speech-Synthese gemäß `desc.briefing.md` unter Verwendung der 6 vorhandenen ONNX-Stimmen in `/opt/piper/voices`.
  - **Akzeptanzkriterien:**
    - AK-1: Migration und Eloquent Model `AudioBriefing` (`user_id`, `locale`, `content_hash`, `speech_text`, `storage_path`, `duration_ms`, `status`, `error_code`).
    - AK-2: Konfiguration in `config/services.php` (Block `piper`) inkl. Voice-Mapping auf die realen Dateien in `/opt/piper/voices`: `de_DE-thorsten-high`, `en_US-lessac-high`, `es_ES-davefx-medium`, `it_IT-paola-medium`, `ru_RU-dmitri-medium`, `uk_UA-ukrainian_tts-medium`.
    - AK-3: `PiperTtsService::synthesize()` startet `/opt/piper/venv/bin/python3 -m piper` über `Process::run()` mit Argument-Array ohne Shell, übergibt den Sprechtext (max. 8000 Zeichen) per `stdin`, prüft Exit-Code sowie RIFF/WAVE-Header, speichert die `<uuid>.wav`-Datei auf der Disk `private` (`storage/app/private/`) und löscht die temporäre Datei garantiert.
  - **Review-Schwerpunkte (`security-reviewer`):** Ausschluss von Command-Injection und Path-Traversal (Stimmmodell darf nur über die feste Sprach-Whitelist aufgelöst werden, niemals direkt aus Benutzereingaben), Speicherung ausschließlich im privaten Storage.

- [x] **TASK-010 (#20): Queue-Job `GenerateAudioBriefingJob` & geschützter Audio-Stream**
  - **Ziel:** Automatische Generierung eines Audio-Briefings nach jedem Matcher-Lauf (nur für neue `ai_matcher`-Vakanzen) und Bereitstellung des Streaming-Endpunkts.
  - **Akzeptanzkriterien:**
    - AK-1: Nach jedem Matcher-Lauf werden ausschließlich Vakanzen des Benutzers mit `source = 'ai_matcher'` und `briefed_at IS NULL` herangezogen.
    - AK-2: MiniMax erstellt daraus in der `preferred_language` des Benutzers den Sprechtext (`BriefingSpeechText`, max. 8000 Zeichen); die verarbeiteten `saved_jobs` werden mit `briefed_at = now()` markiert, sodass sie in keinem späteren Briefing erneut erwähnt werden.
    - AK-3: `GenerateAudioBriefingJob` läuft auf der Queue `piper` (idempotent über `content_hash`, Timeouts: Piper 300 s, Job 330 s).
    - AK-4: Endpunkt `GET /audio-briefings/{audioBriefing}/stream` liefert die Audiodatei nur an den rechtmäßigen Besitzer (Policy + `EnsureUserIsSubscribed`) mit HTTP-Range-Unterstützung aus.
  - **Review-Schwerpunkte (`security-reviewer`):** IDOR-Prüfung beim Audio-Stream (Zugriff durch fremde User muss mit `403` abgewiesen werden), Idempotenz und saubere Markierung von `briefed_at`.

---

## Phase 5: Dashboard-Erweiterung & `/saved-briefings`

- [x] **TASK-011 (#21): Dashboard-Ergänzung (Zähler, Player mit Geschwindigkeitsschalter & Vakanzen-Anzeige)**
  - **Ziel:** Erweiterung des Dashboards um Kennzahlen, den Audio-Player für das neueste Briefing sowie die Darstellung der KI-Attribute in der Liste gespeicherter Vakanzen.
  - **Akzeptanzkriterien:**
    - AK-1: Das Dashboard zeigt die Anzahl der gespeicherten Vakanzen und die Anzahl der gespeicherten Audio-Briefings des Benutzers an.
    - AK-2: Das neueste fertige Audio-Briefing wird auf dem Dashboard als Player mit Play/Stop-Button, Audiospur/Fortschrittsanzeige und Geschwindigkeitsschalter (`1x`, `1.25x`, `1.5x`, `2x`) dargestellt.
    - AK-3: In der Tabelle/Liste `saved_jobs` werden für `ai_matcher`-Vakanzen der KI-Vermerk (`ai_summary`), die Temperatur/Passung (`1–10`) und die Distanz (`distance_km`) angezeigt. **[vorgezogen und umgesetzt am 2026-11-12: Liste `/saved-jobs`, vom `security-reviewer` freigegeben]**
    - AK-4: Alle neuen Texte sind in allen 6 Sprachen (`lang/*/ui.php`) vorhanden; Pest-Tests prüfen die korrekte Datenbereitstellung.
  - **Review-Schwerpunkte (`security-reviewer`):** Strikte Mandantentrennung bei Zählern und neuestem Briefing, sichere Textausgabe ohne `v-html` (XSS-Schutz für `ai_summary`).

- [x] **TASK-012 (#22): Separate Liste unter `/saved-briefings` & Dokumentation (`PRODUCT.md`)**
  - **Ziel:** Eigene Übersichtsseite für alle gespeicherten Audio-Briefings unter `/saved-briefings` und abschließende Aktualisierung des Produktzustands.
  - **Akzeptanzkriterien:**
    - AK-1: Unter `GET /saved-briefings` (geschützt durch Auth + `EnsureUserIsSubscribed`) wird die chronologische Liste aller gespeicherten Audio-Briefings des Benutzers mit Abspielmöglichkeit (Player inkl. Play/Stop, Audiospur, Geschwindigkeitsschalter) angezeigt.
    - AK-2: Vollständige Sprachabdeckung (`de`, `en`, `ru`, `uk`, `es`, `it`) und Barrierefreiheit (WCAG 2.2 AA).
    - AK-3: `PRODUCT.md` ist auf den neuen Ist-Zustand von Release 1.2.0 aktualisiert.
    - AK-4: Die gesamte Test-Suite (`php artisan test --compact`) und `vendor/bin/pint --dirty --format agent` laufen fehlerfrei durch; alle Karten im GitHub-Projekt `"NEXOMA"` stehen auf `Done`.
  - **Review-Schwerpunkte (`security-reviewer`):** Abschließende Prüfung von `/saved-briefings` auf Autorisierung, Pagination/Datenbegrenzung und Einhaltung aller Regeln aus `AGENTS.md` und `PROJECT.md`.
---

## Phase 6: Rechnungen (Nachtrag zu 1.2.0)

- [x] **TASK-013 (#33): Rechnungsliste unter `/invoices` (Rechnungen von Stripe)**
  - **Ziel:** Abonnenten sehen ihre Rechnungen und können sie herunterladen. Entscheidung (Option A): Stripe erstellt Rechnung und PDF, Nexoma zeigt sie nur an. Es werden keine Rechnungen erzeugt oder gespeichert. Rechtlicher Rahmen: Kleinunternehmer (§ 19 UStG, Hinweis in der Stripe-Fußzeile), nur Privatkunden, Kleinbetragsrechnung (§ 33 UStDV); keine Steuerberatung.
  - **Akzeptanzkriterien:**
    - AK-1: `GET /invoices` (Name `invoices.index`, Auth und verifizierte E-Mail, Limiter `billing`, bewusst ohne `subscribed`, damit Rechnungen nach einer Kündigung abrufbar bleiben) listet die Rechnungen des eigenen Stripe-Kunden (neueste zuerst, höchstens 24, ohne Entwürfe).
    - AK-2: Ausgegeben werden nur Whitelist-Felder (Nummer, Datum, Betrag, Währung, Status, Link zur Rechnungsseite und zur PDF). Links nur `https` auf `stripe.com`; keine Kunden-ID, E-Mail oder Rechnungs-ID im Seitenquelltext. Die Kunden-ID stammt aus der Datenbank, nie aus der Anfrage.
    - AK-3: Ohne Stripe-Kunden leere Liste ohne Stripe-Aufruf; bei Stripe-Fehler Hinweis „nicht abrufbar“ und Log nur mit der Exception-Klasse.
    - AK-4: Menüpunkt „Rechnungen“, alle Texte in 6 Sprachen, Pest-Tests, Pint, PHPStan, `types:check`, Build grün.
  - **Voraussetzungen außerhalb des Codes (Betreiber):** In Stripe Rechnungsdaten pflegen (Anschrift, Steuernummer unter „Steuer-ID“, Fußzeile mit dem Hinweis nach § 19 UStG, bereits gesetzt); dem eingeschränkten Schlüssel das Recht **Invoices: Lesen** geben (neuer Schlüssel, Rechte lassen sich nachträglich nicht ändern); danach `STRIPE_SECRET` austauschen. Ohne das Recht zeigt die Seite „nicht abrufbar“.
  - **Review-Schwerpunkte (`security-reviewer`):** Mandantentrennung (nur eigener Kunde), Whitelist/URL-Prüfung, kein Leck von Stripe-Kennungen, Fehlerprotokoll ohne Inhalt, Route ohne Paywall bewusst.

## Go-Live-Checkliste Release 1.2.0

Stand: 2026-11-13. Vorgaben des Betreibers: Piper und Betriebssystem des Servers entsprechen der lokalen Umgebung; die Produktions-`.env` ist angepasst; Cloudflare-Tunnel ist nur für lokale Tests relevant; eine fehlgeschlagene Zahlung führt dazu, dass der Benutzer Standard-Benutzer bleibt (kein Premium), ein Protokoll dafür folgt erst in einer späteren Version (saubere Pipeline). Haken `[x]` = vom Betreiber bestätigt oder im Repository belegt; `[ ]` = offen.

### Rechtliches (Blocker)
- [ ] **Datenschutzerklärung:** `php artisan legal:check` muss grün sein. Aktuell 7 offene Platzhalter: Frist der Logfile-Speicherung, Hoster, E-Mail-Anbieter, Zahlungsdienstleister (Stripe), KI-Anbieter (MiniMax), Verarbeitungsort/Garantien (Art. 45/46 DSGVO) für MiniMax und für Stripe.
- [ ] **Rechtliche Prüfung** (keine Rechtsberatung durch den Assistenten): Auftragsverarbeitung mit MiniMax und Stripe-Rolle, Drittlandübermittlung, Speicherdauer von `saved_jobs`/Briefings/Audiodateien, Rechtsgrundlage bzw. Einwilligung für die automatische Suche und KI-Bewertung (ggf. Datenschutz-Folgenabschätzung), Kennzeichnung von KI-Texten und synthetischer Stimme, Widerrufsbelehrung/AGB und Impressum für das kostenpflichtige Angebot.

### Stripe (live)
- [ ] **Live-Konto aktivieren:** Das Konto „NEXOMA“ (Organisation NOMNiX) zeigt im Dashboard noch „Einrichtung fortsetzen“; Geschäfts-, Identitäts- und Bankdaten müssen vom Betreiber selbst eingegeben werden.
- [ ] **Produkt und Preis (live)** wie in der Sandbox: Produkt „Nexoma Premium“ (Beschreibung: „Premium-Funktionen von Nexoma: Profil und Werdegang, automatische Vakanzensuche, Audio-Briefings“), Preis 4,95 EUR, monatlich, wiederkehrend. Steuerbehandlung (USt., Stripe Tax, Kleinunternehmerregelung) vorab klären. Die Live-Preis-ID kommt in `STRIPE_PRICE_ID` der Produktions-`.env`.
- [ ] **Webhook-Endpunkt (live):** `POST https://nexoma.me/stripe/webhook` mit den Ereignissen `customer.subscription.created`, `.updated`, `.deleted`, `.paused`, `.resumed`. Das Signing-Secret (`whsec_…`) trägt der Betreiber selbst in `.env` ein; ohne Secret antwortet der Webhook mit 500 (fail closed).
- [ ] **Kundenportal (live)** wie in der Sandbox konfigurieren: Kündigung zum Periodenende, Zahlungsart ändern, Rechnungsverlauf, Kundendaten (Name, E-Mail, Adresse, Telefon) ändern; Abo-Wechsel und Pausieren aus.
- [ ] **Live-Schlüssel** (`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_PRICE_ID`) nur in der Produktions-`.env`, nie im Repository; `APP_URL=https://nexoma.me` (Rückkehr-URLs von Checkout und Portal hängen davon ab).
- [ ] **Webhook-Ende-zu-Ende:** Die Webhook-Logik wurde bisher nur mit Fakes getestet (in der Sandbox ist kein Endpunkt angelegt). Vor dem Go-Live mit der Stripe-CLI (`stripe listen --forward-to …/stripe/webhook`) oder einem Testlauf im Sandbox-Modus prüfen: Abo anlegen, Status `active`, Kündigung → Zugang endet.
- [ ] **Echter Zahlungstest** im Live-Modus (kleiner Betrag, anschließend Kündigung/Erstattung) und Prüfung, dass Premium nach Zahlung freigeschaltet wird und bei Zahlungsausfall (`past_due`/`unpaid`/`canceled`) wieder entfällt.

### Server und Betrieb
- [x] **Piper** samt Python-Venv und Stimmen (de, en, es, it, ru, uk) entspricht laut Betreiber der lokalen Umgebung; `services.piper.python_binary`/`data_dir` und Schreibrechte auf `storage/app/private` prüfen.
- [x] **Produktions-`.env`** laut Betreiber angepasst. Neue optionale Schlüssel dieser Version (Standardwerte genügen): `JOB_MATCHER_ENABLED` (Standard `true`), `JOB_MATCHER_MAX_RUNS_PER_WEEK` (Standard `2`), `JOB_MATCHER_INTERVAL_MINUTES` (Standard `360`).
- [ ] **Queue-Worker** unter Supervisor/systemd für die Queues `default,ai,piper` (`--queue=default,ai,piper --timeout=360`, `DB_QUEUE_RETRY_AFTER=400`; `sync` ist keine Queue, sondern ein Verbindungstreiber) (ohne `piper` entstehen keine Audio-Briefings); nach jedem Deployment `php artisan queue:restart`.
- [ ] **Scheduler:** Cron `* * * * * php artisan schedule:run` (startet `jobs:match`).
- [ ] **Migration:** `php artisan migrate --force` führt nur die noch ausstehenden Migrationen aus (u. a. `audio_briefings`) und löscht keine bestehenden Daten; `--force` unterdrückt lediglich die Rückfrage in Produktion. Niemals `migrate:fresh`, `migrate:reset` oder `db:wipe`. Ein Datenbank-Backup vorher ist die übliche Vorsichtsmaßnahme.
- [ ] **Cache:** Das Wochenlimit des Matchers liegt im Cache (Treiber `database`); `cache:clear` oder `optimize:clear` im Deployment setzt es zurück und erlaubt erneute Läufe.
- [ ] **Proxy:** `TRUSTED_PROXIES` nur setzen, wenn die Anwendung hinter einem Reverse-Proxy läuft (Cloudflare-Tunnel ist nur lokal relevant).

### Kosten und Steuerung (umgesetzt)
- [x] **Hauptschalter** `JOB_MATCHER_ENABLED=false` stoppt Planer, Kommando `jobs:match` (auch `--user`) und bereits eingereihte Matcher-Jobs.
- [x] **Wochenlimit** `JOB_MATCHER_MAX_RUNS_PER_WEEK` (Standard 2) je Abonnent, gleichmäßig verteilt (Mindestabstand 7 Tage / Anzahl); `--user` für manuelle Tests ignoriert das Limit.

### Abnahme
- [x] Pest-Suite, PHPStan, Pint, Build und CI laut Betreiber grün (Stand 13.11.2026).
- [x] Russischer Sprechtext und Player vom Betreiber geprüft; Übersetzungen sind maschinell erzeugt.
- [ ] Nach dem Deployment: Registrierung, Abo-Abschluss, Profil, `php artisan jobs:match --user=<id>`, Briefing abspielen, Kündigung im Kundenportal.
