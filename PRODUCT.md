# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Mehrsprachige Jobsuchende in Deutschland. Sie suchen Stellen der Bundesagentur für Arbeit (BA) und brauchen die Bedienung in ihrer eigenen Sprache: Deutsch, Englisch, Russisch, Ukrainisch, Spanisch oder Italienisch. Sie suchen entweder direkt im Browser oder über ihren KI-Assistenten (ChatGPT, Claude, Gemini), den sie mit ihrem Konto verbinden.

Zweite Rolle derselben Personen: Sie verwalten ihr Konto, erteilen KI-Clients die Zustimmung und widerrufen sie unter „Verbundene Apps“.

## Product Purpose

Nexoma macht die Stellensuche der Bundesagentur für Arbeit mehrsprachig zugänglich: im Browser und über einen MCP-Zugang für KI-Assistenten. Erfolg heißt, dass eine Person in ihrer Sprache zu passenden Stellen und zum offiziellen Bewerbungsweg kommt, ohne dass Nexoma Daten sammelt, die dafür nicht nötig sind.

## Positioning

Drei Dinge zusammen, die ein Nachbarprodukt nicht wahrheitsgemäß behaupten könnte:

- Die Oberfläche und alle Hinweise gibt es in sechs Sprachen (Stellentexte bleiben in der Originalsprache der BA).
- Verifizierte Konten verbinden KI-Assistenten per OAuth mit der Stellensuche, mit ausdrücklicher Zustimmung und Widerruf (MCP: suchen, ansehen und auf Wunsch in der eigenen Liste speichern, kein Löschen, keine Bewerbung).
- Sicherer, schlanker Zugang: keine Werbung, kein Tracking, selbst gehostete Schriften, API-Schlüssel der BA bleiben serverseitig.

## Operating Context

Datenquelle ist die Jobsuche-API der Bundesagentur für Arbeit (serverseitig, öffentlicher Schlüssel). Stellentexte sind Fremddaten und werden als unvertrauenswürdig behandelt. Der Betrieb läuft als eine Laravel-Anwendung (Laravel 13, Inertia, Vue 3, PostgreSQL) unter der Domain nexoma.me. Betreiber ist NOMNIX // Slawa Mironow (Freiberufler, Bad Mergentheim); Rechtstexte (Impressum, Datenschutz, Haftungsausschluss) sind Entwürfe und müssen vor Veröffentlichung geprüft werden.

## Capabilities and Constraints

- Release 1: Registrierung mit E-Mail-Verifizierung, Anmeldung, Stellensuche und Details, „Verbundene Apps“, MCP-Server mit drei lesenden Tools (`job_search`, `job_details`, `job_search_options`) und dem Tool `save_job` zum Speichern einer Stelle, optional mit Vermerk, Rechtsseiten.
- Nicht in Release 1: Favoriten, Suchprofile, Suchverlauf (Favoriten sind der erste Ausbau danach). Kontolöschung nur auf Anfrage.
- Konten sind getrennt (Single-Tenant mit Daten je Benutzer). Browser-Auth: Fortify mit Session, OAuth für KI-Clients: Passport; kein Sanctum.
- Texte der Oberfläche liegen zentral in `lang/*/ui.php` und werden über `t()` ausgegeben; neue Texte brauchen alle sechs Sprachen. Übersetzungen sind maschinell erzeugt und nicht von Muttersprachlern geprüft. Die Anrede (du/Sie) ist uneinheitlich und offen.
- Rechtstexte erscheinen nur auf Deutsch.

## Brand Commitments

Name: Nexoma. Die vorhandenen Design-Tokens in `resources/css/app.css` (Farben `paper`, `surface`, `line`, `ink`, `muted`, `link`, `danger`, `success`, `focus`, `sage`, `sky`, `peach`, `lilac`) und die Schriften Inter und Source Serif 4 sind bindend. Es gibt kein Logo und keine weiteren Bildmarken.

Hinweis zur Abstimmung: Bei „Vorgaben“ wurde zusätzlich „Keine besonderen Vorgaben“ gewählt. Als verbindlich gelten die beiden konkret gewählten Vorgaben (Tokens und Schriften, WCAG); falls die Gestaltung doch neu gedacht werden darf, bitte `init` erneut aufrufen.

## Evidence on Hand

Keine Kundenstimmen, Fallstudien, Zahlen oder Pressestimmen. Nicht erfinden. Vorhanden sind: die laufende Anwendung mit sechs Sprachen, die Rechtstexte unter `resources/legal/`, die Deployment-Checkliste in `ROADMAP.md`.

## Product Principles

1. Die Sprache der Person kommt zuerst: jede Oberfläche, jeder Fehler und jeder Hinweis ist in allen sechs Sprachen vorhanden.
2. Fremdtext bleibt Fremdtext: Stellenangebote werden nie als Markup oder Anweisung behandelt, Bewerbungslinks gehen nur zu geprüften https-Adressen.
3. Zustimmung ist sichtbar und widerrufbar: KI-Zugriff entsteht nur durch eine bewusste Zustimmung und lässt sich jederzeit beenden.
4. So wenig Daten wie möglich: kein Tracking, kein Suchverlauf je Benutzer in Release 1, keine Suchtexte im Audit-Log.
5. Ruhe vor Wirkung: eine Aufgabe pro Seite, klare Sprache, vorhersehbare Bedienung.

## Accessibility & Inclusion

WCAG 2.2 AA als Mindeststandard: ausreichende Kontraste, vollständige Tastaturbedienung, sichtbarer Fokus, Skip-Link, Labels an allen Feldern. Mehrsprachigkeit schließt Kyrillisch ein (Schriften enthalten die Teilmengen `cyrillic` und `cyrillic-ext`).
