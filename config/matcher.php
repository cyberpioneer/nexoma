<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automatische Vakanzensuche (AI Matcher)
    |--------------------------------------------------------------------------
    |
    | enabled: Hauptschalter der automatischen Suche (JOB_MATCHER_ENABLED=false stoppt Planer, Kommando und bereits
    |          eingereihte Jobs; Abo, Profil und Daten bleiben unberuehrt).
    | interval_minutes: Abstand der Laeufe je Benutzer in Minuten; 0 schaltet den Matcher aus.
    | max_runs_per_week: hoechstens so viele geplante Laeufe je Benutzer in 7 Tagen (Kostenbremse, 0 = unbegrenzt).
    |          Die Laeufe werden gleichmaessig verteilt (Mindestabstand = 7 Tage / Anzahl).
    | published_within_days: Parameter `veroeffentlichtseit` der BA-Suche (0 bis 100 Tage).
    | Jeder Lauf verursacht Kosten beim KI-Dienst (hoechstens ein Lauf je Benutzer und Intervall).
    |
    */

    'enabled' => (bool) env('JOB_MATCHER_ENABLED', true),

    'max_runs_per_week' => max(0, (int) env('JOB_MATCHER_MAX_RUNS_PER_WEEK', 2)),

    'interval_minutes' => (int) env('JOB_MATCHER_INTERVAL_MINUTES', 360),

    'published_within_days' => (int) env('JOB_MATCHER_PUBLISHED_WITHIN_DAYS', 100),

];
