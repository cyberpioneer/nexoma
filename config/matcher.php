<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automatische Vakanzensuche (AI Matcher)
    |--------------------------------------------------------------------------
    |
    | interval_minutes: Abstand der Laeufe je Benutzer in Minuten; 0 schaltet den Matcher aus.
    | published_within_days: Parameter `veroeffentlichtseit` der BA-Suche (0 bis 100 Tage).
    | Jeder Lauf verursacht Kosten beim KI-Dienst (hoechstens ein Lauf je Benutzer und Intervall).
    |
    */

    'interval_minutes' => (int) env('JOB_MATCHER_INTERVAL_MINUTES', 360),

    'published_within_days' => (int) env('JOB_MATCHER_PUBLISHED_WITHIN_DAYS', 100),

];
