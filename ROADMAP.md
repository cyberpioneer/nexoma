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

- [ ] **TASK-009 (#19): `audio_briefings`-Schema & `PiperTtsService` mit 6-Sprachen-Mapping**
  - **Ziel:** Umsetzung der lokalen Text-to-Speech-Synthese gemäß `desc.briefing.md` unter Verwendung der 6 vorhandenen ONNX-Stimmen in `/opt/piper/voices`.
  - **Akzeptanzkriterien:**
    - AK-1: Migration und Eloquent Model `AudioBriefing` (`user_id`, `locale`, `content_hash`, `speech_text`, `storage_path`, `duration_ms`, `status`, `error_code`).
    - AK-2: Konfiguration in `config/services.php` (Block `piper`) inkl. Voice-Mapping auf die realen Dateien in `/opt/piper/voices`: `de_DE-thorsten-high`, `en_US-lessac-high`, `es_ES-davefx-medium`, `it_IT-paola-medium`, `ru_RU-dmitri-medium`, `uk_UA-ukrainian_tts-medium`.
    - AK-3: `PiperTtsService::synthesize()` startet `/opt/piper/venv/bin/python3 -m piper` über `Process::run()` mit Argument-Array ohne Shell, übergibt den Sprechtext (max. 8000 Zeichen) per `stdin`, prüft Exit-Code sowie RIFF/WAVE-Header, speichert die `<uuid>.wav`-Datei auf der Disk `private` (`storage/app/private/`) und löscht die temporäre Datei garantiert.
  - **Review-Schwerpunkte (`security-reviewer`):** Ausschluss von Command-Injection und Path-Traversal (Stimmmodell darf nur über die feste Sprach-Whitelist aufgelöst werden, niemals direkt aus Benutzereingaben), Speicherung ausschließlich im privaten Storage.

- [ ] **TASK-010 (#20): Queue-Job `GenerateAudioBriefingJob` & geschützter Audio-Stream**
  - **Ziel:** Automatische Generierung eines Audio-Briefings nach jedem Matcher-Lauf (nur für neue `ai_matcher`-Vakanzen) und Bereitstellung des Streaming-Endpunkts.
  - **Akzeptanzkriterien:**
    - AK-1: Nach jedem Matcher-Lauf werden ausschließlich Vakanzen des Benutzers mit `source = 'ai_matcher'` und `briefed_at IS NULL` herangezogen.
    - AK-2: MiniMax erstellt daraus in der `preferred_language` des Benutzers den Sprechtext (`BriefingSpeechText`, max. 8000 Zeichen); die verarbeiteten `saved_jobs` werden mit `briefed_at = now()` markiert, sodass sie in keinem späteren Briefing erneut erwähnt werden.
    - AK-3: `GenerateAudioBriefingJob` läuft auf der Queue `piper` (idempotent über `content_hash`, Timeouts: Piper 300 s, Job 330 s).
    - AK-4: Endpunkt `GET /audio-briefings/{audioBriefing}/stream` liefert die Audiodatei nur an den rechtmäßigen Besitzer (Policy + `EnsureUserIsSubscribed`) mit HTTP-Range-Unterstützung aus.
  - **Review-Schwerpunkte (`security-reviewer`):** IDOR-Prüfung beim Audio-Stream (Zugriff durch fremde User muss mit `403` abgewiesen werden), Idempotenz und saubere Markierung von `briefed_at`.

---

## Phase 5: Dashboard-Erweiterung & `/saved-briefings`

- [ ] **TASK-011 (#21): Dashboard-Ergänzung (Zähler, Player mit Geschwindigkeitsschalter & Vakanzen-Anzeige)**
  - **Ziel:** Erweiterung des Dashboards um Kennzahlen, den Audio-Player für das neueste Briefing sowie die Darstellung der KI-Attribute in der Liste gespeicherter Vakanzen.
  - **Akzeptanzkriterien:**
    - AK-1: Das Dashboard zeigt die Anzahl der gespeicherten Vakanzen und die Anzahl der gespeicherten Audio-Briefings des Benutzers an.
    - AK-2: Das neueste fertige Audio-Briefing wird auf dem Dashboard als Player mit Play/Stop-Button, Audiospur/Fortschrittsanzeige und Geschwindigkeitsschalter (`1x`, `1.25x`, `1.5x`, `2x`) dargestellt.
    - AK-3: In der Tabelle/Liste `saved_jobs` werden für `ai_matcher`-Vakanzen der KI-Vermerk (`ai_summary`), die Temperatur/Passung (`1–10`) und die Distanz (`distance_km`) angezeigt.
    - AK-4: Alle neuen Texte sind in allen 6 Sprachen (`lang/*/ui.php`) vorhanden; Pest-Tests prüfen die korrekte Datenbereitstellung.
  - **Review-Schwerpunkte (`security-reviewer`):** Strikte Mandantentrennung bei Zählern und neuestem Briefing, sichere Textausgabe ohne `v-html` (XSS-Schutz für `ai_summary`).

- [ ] **TASK-012 (#22): Separate Liste unter `/saved-briefings` & Dokumentation (`PRODUCT.md`)**
  - **Ziel:** Eigene Übersichtsseite für alle gespeicherten Audio-Briefings unter `/saved-briefings` und abschließende Aktualisierung des Produktzustands.
  - **Akzeptanzkriterien:**
    - AK-1: Unter `GET /saved-briefings` (geschützt durch Auth + `EnsureUserIsSubscribed`) wird die chronologische Liste aller gespeicherten Audio-Briefings des Benutzers mit Abspielmöglichkeit (Player inkl. Play/Stop, Audiospur, Geschwindigkeitsschalter) angezeigt.
    - AK-2: Vollständige Sprachabdeckung (`de`, `en`, `ru`, `uk`, `es`, `it`) und Barrierefreiheit (WCAG 2.2 AA).
    - AK-3: `PRODUCT.md` ist auf den neuen Ist-Zustand von Release 1.2.0 aktualisiert.
    - AK-4: Die gesamte Test-Suite (`php artisan test --compact`) und `vendor/bin/pint --dirty --format agent` laufen fehlerfrei durch; alle Karten im GitHub-Projekt `"NEXOMA"` stehen auf `Done`.
  - **Review-Schwerpunkte (`security-reviewer`):** Abschließende Prüfung von `/saved-briefings` auf Autorisierung, Pagination/Datenbegrenzung und Einhaltung aller Regeln aus `AGENTS.md` und `PROJECT.md`.