# Nexoma – PROJECT.md

## 1. Projektziel

Entwicklung einer mehrsprachigen, mandantenfähigen Job-Suchplattform auf Basis von Laravel 13. Kunden können sich registrieren bzw. anmelden und Stellenangebote aus der API der Bundesagentur für Arbeit (BA) über eine Weboberfläche oder über eine geschützte MCP-Schnittstelle suchen und abrufen.

Die Anwendung greift serverseitig auf die BA Jobs API zu. Zugangsdaten bzw. API-Schlüssel der BA dürfen nicht an Browser, Kunden oder externe MCP-Clients weitergegeben werden.

## 2. Produktumfang

### Webanwendung

- Browserbasierte grafische Benutzeroberfläche zur Stellensuche.
- Suche in allen verfügbaren Berufsfeldern.
- Öffentliche Kundenregistrierung mit E-Mail-Bestätigung.
- Anmeldung für registrierte und verifizierte Kunden.
- Mehrbenutzerbetrieb mit getrennten persönlichen Daten.
- Persönliche Suchprofile, Suchverläufe und Favoriten können lokal in der Anwendungsdatenbank verwaltet werden, sofern sie implementiert werden.

### Sprachen

Die Benutzeroberfläche unterstützt:

- Deutsch (`de`)
- Englisch (`en`)
- Russisch (`ru`)
- Ukrainisch (`uk`)
- Spanisch (`es`)
- Italienisch (`it`)

Die Sprache der Benutzeroberfläche ist unabhängig von der Sprache der Stellenangebote. Übersetzungen müssen zentral und konsistent verwaltet werden.

### MCP

- Die Anwendung stellt Job-Suchfunktionen zusätzlich über MCP bereit.
- Der produktive MCP-Zugang ist ausschließlich authentifizierten und autorisierten Kunden der Anwendung zugänglich.
- MCP-Aufrufe verwenden dieselben Geschäftsregeln und Berechtigungsprüfungen wie die Webanwendung.
- Die produktive MCP-Schnittstelle der Anwendung ist von den MCP-Entwicklungswerkzeugen Laravel Boost zu unterscheiden.
- Externe KI-Clients wie ChatGPT, Claude und Gemini sollen die MCP-Schnittstelle nutzen können, sofern sie das implementierte MCP- und OAuth-Verfahren unterstützen.

## 3. Verbindlicher Technologie-Stack

| Bereich | Technologie |
|---|---|
| Projektname | Nexoma |
| Backend | Laravel 13 |
| Frontend | Vue.js 3 |
| Laravel–Frontend-Integration | Inertia.js |
| Vue-Komponenten | Composition API mit `<script setup>` |
| Datenbank | PostgreSQL |
| Browser-Authentifizierung | Laravel Sanctum (Session-basiert) |
| OAuth2 für externe MCP-Clients | Laravel Passport |
| OAuth-Metadaten | RFC 8414 |
| Dynamische OAuth-Clientregistrierung | RFC 7591 |
| Externe Stellendaten | API der Bundesagentur für Arbeit (BA Jobs API) |
| Entwicklungsunterstützung | Laravel Boost einschließlich tatsächlich verfügbarer MCP-Werkzeuge |
| Versionsverwaltung | GitHub (bereits eingerichtet) |

### Frontend-Qualitätsanforderung

Vue.js-Code muss gut lesbar, klar strukturiert, verständlich und einfach wartbar sein. Lesbarkeit für Programmierer hat Vorrang vor unnötig cleveren oder komplexen Konstruktionen.

## 4. Architekturprinzipien

- Laravel bleibt das zentrale Backend; Vue 3 und Inertia.js bilden die Benutzeroberfläche.
- Kein separates SPA-Backend.
- Laravel übernimmt Routing, Authentifizierung, Autorisierung und Geschäftslogik.
- Controller bleiben schlank und delegieren fachliche Arbeit an Services.
- Die BA-API-Kommunikation wird zentral in einem dedizierten Client bzw. Service gekapselt.
- Weboberfläche und MCP verwenden dieselben Business-Services und Sicherheitsprüfungen.
- API-Ergebnisse werden nicht automatisch vollständig lokal gespiegelt. Persistiert werden nur Daten, für die ein konkreter Anwendungsbedarf besteht.
- Bevorzugt werden einfache, Laravel-übliche und wartbare Lösungen.
- GitHub wird für Versionsverwaltung und Projektentwicklung verwendet.

## 5. Datenverarbeitung und DTOs

- DTOs werden im Projekt nicht verwendet.
- Für Datenverarbeitung und Übergaben werden Laravel-übliche Arrays, Request-Objekte und Eloquent-Modelle eingesetzt.
- Value Objects sind optional und dürfen nur bei einem klaren fachlichen Nutzen eingeführt werden.
- Es dürfen keine unnötigen Abstraktionen oder zusätzlichen Architektur-Schichten entstehen.

## 6. Authentifizierung und Sicherheit

### Web

- Browser-Anmeldung wird session-basiert über Laravel Sanctum umgesetzt.
- Jeder Benutzer muss seine E-Mail-Adresse bestätigen.
- Unverifizierte Benutzer dürfen keine geschützten Funktionen verwenden.

### MCP und externe Clients

- OAuth2 wird mit Laravel Passport umgesetzt.
- Die OAuth-Konfiguration basiert auf RFC 8414 (OAuth 2.0 Authorization Server Metadata) und RFC 7591 (Dynamic Client Registration).
- Externe MCP-Clients sollen sich dynamisch registrieren können, sofern die jeweilige Client-Implementierung das Verfahren unterstützt.
- Die konkrete Implementierung muss mit der verwendeten Laravel-MCP-Version und dem vorgesehenen Client kompatibel sein.
- Tokens und Berechtigungen müssen pro Benutzer verwaltet und bei Bedarf widerrufbar sein.
- Berechtigungen sind nach dem Least-Privilege-Prinzip zu vergeben.
- MCP-Tools müssen authentifizieren und autorisieren, bevor sie Daten zurückgeben oder Aktionen ausführen.

### Allgemeine Sicherheitsanforderungen

- Autorisierung serverseitig durch geeignete Laravel Policies, Gates und Middleware.
- Kundendaten müssen voneinander getrennt bleiben (Mandantentrennung).
- Kein Zugriff auf Daten anderer Kunden durch manipulierte IDs oder Requests (IDOR-Schutz).
- Rate Limiting für relevante Web- und MCP-Endpunkte.
- BA-API-Zugangsdaten ausschließlich serverseitig in sicherer Konfiguration bzw. Umgebungsvariablen halten.
- Eingaben validieren und externe API-Antworten robust behandeln.
- Sicherheitsrelevante MCP-Aufrufe angemessen protokollieren; keine Tokens oder Geheimnisse loggen.

## 7. Vorgesehene MCP-Funktionen

Die folgenden Funktionen sind fachliche Zielvorgaben. Namen und konkrete Tool-Schemas werden bei der Implementierung festgelegt:

- Stellenangebote suchen.
- Details zu einem Stellenangebot abrufen.
- Stellenangebote nach Ort bzw. Region suchen.
- Stellenangebote nach Berufsfeld und Angebotsart filtern.
- Verfügbare Such- bzw. Berufskategorien abrufen.
- Persönliche Favoriten verwalten und abrufen, sofern diese Funktion implementiert wird.

MCP-Tools dürfen ausschließlich Daten und Aktionen freigeben, die der angemeldete Kunde verwenden darf.

## 8. BA Jobs API

### 8.1 API-Client

Die Kommunikation mit der Bundesagentur für Arbeit erfolgt über einen dedizierten Laravel-Service:

`App\Services\Jobsuche\JobsucheApiClient`

Der Client verwendet die offizielle Jobsuche API:

`https://jobsuche.api.bund.dev`

Die Basis-URL wird über `config('services.jobsuche.base_url')` konfiguriert.

Der API-Key wird serverseitig über den Header `X-API-Key` übermittelt und aus der Laravel-Konfiguration bezogen.

Die HTTP-Konfiguration umfasst:

- Konfigurierbare Request-Timeouts.
- Konfigurierbare Connection-Timeouts.
- JSON als erwartetes Antwortformat.
- Zentrale HTTP-Kommunikation über den Laravel HTTP Client.

### 8.2 Stellensuche

Die Stellensuche erfolgt über:

`GET /pc/v6/jobs`

Die Methode `search()` verwendet folgende Parameter:

| Parameter | Standardwert | Bedeutung |
|---|---:|---|
| `was` | erforderlich | Suchbegriff bzw. Beruf |
| `page` | `1` | Ergebnisseite |
| `size` | `100` | Anzahl der Ergebnisse pro Seite |
| `veroeffentlichtseit` | `100` | Veröffentlichungszeitraum |
| `wo` | optional | Suchort |
| `umkreis` | optional | Suchradius |
| `angebotsart` | optional | Art des Stellenangebots |

Zusätzliche Suchparameter können übergeben werden.

Beispiel:

`was=Pflege&wo=Köln&umkreis=20&angebotsart=4`

Erwartetes Ergebnis: Ausbildungsplätze im Pflegebereich im Umkreis von 20 km um Köln.

Die tatsächlichen API-Parameter und deren Bedeutung müssen anhand der offiziellen API-Dokumentation geprüft werden.

### 8.3 Angebotsarten

Die Anwendung unterstützt die folgenden Angebotsarten:

| Parameter | Angebotsart | Beschreibung |
|---|---|---|
| `angebotsart=1` | Reguläre Arbeit | Normale Arbeitsplätze für Fachkräfte, Vollzeit, Teilzeit, befristet oder unbefristet |
| `angebotsart=4` | Ausbildung | Ausbildungsstellen, beispielsweise Pflegefachmann/-frau oder MFA |
| `angebotsart=34` | Praktikum / Trainee | Praktikumsplätze, Praxissemester und Hospitationen |
| `angebotsart=2` | Selbstständigkeit | Freiberufliche Tätigkeiten bzw. Niederlassungen |

Die Angebotsarten sollen über die Benutzeroberfläche auswählbar sein und über MCP als Suchparameter verwendet werden können.

### 8.4 Stellenangebot-Details

Die Detailinformationen eines Stellenangebots werden über folgenden Endpunkt abgerufen:

`GET /pc/v4/jobdetails/{base64(refnr)}`

Die Referenznummer (`refnr`) wird vor dem Aufruf mit `base64_encode()` kodiert.

Die Detailinformationen werden über Laravel Cache für sechs Stunden zwischengespeichert.

Der Cache-Key lautet:

`jobsuche.details.{refnr}`

Wiederholte Aufrufe desselben Stellenangebots sollen dadurch unnötige API-Anfragen vermeiden.

### 8.5 Fehlerbehandlung und Wiederholungen

- HTTP-Status 429 (Too Many Requests) führt zu einem einmaligen Wiederholungsversuch nach 500 Millisekunden.
- Netzwerk- und HTTP-Fehler werden zentral behandelt.
- Fehlerhafte Requests dürfen keine unkontrollierten Exceptions verursachen.
- Bei Fehlern wird `null` zurückgegeben.
- Fehler werden über Laravel Logging protokolliert.
- Zugangsdaten und Tokens dürfen niemals in Logs erscheinen.

### 8.6 Datenverarbeitung

- API-Antworten werden als JSON verarbeitet.
- Die Ergebnisse werden als Laravel-übliche Arrays zurückgegeben.
- DTOs werden nicht verwendet.
- API-Antworten müssen robust auf fehlende oder ungültige Daten geprüft werden.

## 9. Arbeitsregeln für KI-Entwicklungsagenten

### Laravel Boost und MCP-Werkzeuge

- Laravel Boost ist als Entwicklungswerkzeug vorgesehen.
- Agenten sollen die tatsächlich verfügbaren Laravel-Boost-MCP-Werkzeuge aktiv für Projektanalyse, Code-Inspektion, Datenbankanalyse und Fehlerdiagnose nutzen, soweit die jeweilige Aufgabe davon profitiert.
- Vor der Nutzung prüfen Agenten, welche MCP-Tools in ihrer Umgebung tatsächlich verfügbar sind.
- Keine nicht vorhandenen Tools oder Fähigkeiten voraussetzen.
- Laravel Boost dient der Entwicklung und Analyse. Es ersetzt nicht den produktiven MCP-Server der Anwendung.

### Vorgehen

1. Zuerst vorhandene Projektstruktur, relevante Dateien und bestehende Implementierungen untersuchen.
2. Anforderungen und Auswirkungen verstehen, bevor Änderungen vorgenommen werden.
3. Bestehende Laravel-Konventionen und Projektentscheidungen beachten.
4. Änderungen auf den notwendigen Umfang begrenzen; keine unnötigen Refactorings.
5. Sicherheits- und Mandantentrennung bei jeder betroffenen Funktion berücksichtigen.
6. Lesbaren, verständlichen und wartbaren Code schreiben.
7. Tests ergänzen bzw. ausführen, soweit vorhanden und für die Änderung relevant.
8. Ergebnisse, Änderungen und offene Punkte knapp und nachvollziehbar berichten.

## 10. UI/UX und Design

### 10.1 Designrichtung

Das visuelle Erscheinungsbild von Nexoma basiert auf einer Kombination aus:

- Swiss Modernism
- Editorial Design
- Pastellfarben

Die Benutzeroberfläche soll modern, hochwertig, klar strukturiert und professionell wirken.

### 10.2 Designprinzipien

- Klare typografische Hierarchie.
- Hohe Lesbarkeit und gute Informationsarchitektur.
- Reduziertes, funktionales Layout.
- Dezente Pastellfarben.
- Ausreichend Weißraum.
- Stellenangebote und deren relevante Informationen stehen im Mittelpunkt.
- Keine unnötigen visuellen Effekte oder überladenen Dashboard-Elemente.
- Einheitliche Gestaltung über alle sechs Sprachversionen hinweg.
- Responsive Darstellung für Desktop, Tablet und Smartphone.

### 10.3 Zielsetzung

Das Design soll Nexoma von klassischen Jobportalen unterscheiden und sowohl für menschliche Benutzer als auch für KI-gestützte Arbeitsabläufe eine klare, verständliche und effiziente Nutzung ermöglichen.

Diese Punkte dürfen nicht ohne Prüfung als bereits entschieden behandelt werden.
