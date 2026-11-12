/** Neuestes fertiges Audio-Briefing (nur Whitelist-Felder; die Datei kommt ueber die geschuetzte Stream-Route). */
export type AudioBriefingSummary = {
    id: number;
    durationMs: number;
    createdAt: string | null;
};

/** Audio-Bereich der Uebersicht; `null` fuer Benutzer ohne Abo. */
export type DashboardAudio = {
    count: number;
    latest: AudioBriefingSummary | null;
};
