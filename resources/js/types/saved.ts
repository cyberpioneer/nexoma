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

export type SavedJob = {
    id: number;
    refnr: string;
    title: string | null;
    employer: string | null;
    location: string | null;
    note: string | null;
    savedAt: string | null;
};
