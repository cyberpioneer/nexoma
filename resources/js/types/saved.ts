/** Zuordnung einer Stelle der Trefferliste zu einem gespeicherten Eintrag (nur eigene Daten). */
export type SavedJobRef = {
    id: number;
    refnr: string;
    note: string | null;
};

/** Gespeicherter Eintrag der aktuellen Detailseite. */
export type SavedState = {
    id: number;
    note: string | null;
};

/** KI-Angaben eines Vorschlags der automatischen Suche (nur bei Matcher-Einträgen vorhanden). */
export type SavedJobAi = {
    summary: string | null;
    temperature: number | null;
    distanceKm: number | null;
};

export type SavedJob = {
    id: number;
    refnr: string;
    title: string | null;
    employer: string | null;
    location: string | null;
    note: string | null;
    savedAt: string | null;
    /** Nur bei Vorschlägen der automatischen Suche. */
    ai?: SavedJobAi;
};
