/** Profilfelder des angemeldeten Benutzers (Whitelist des Servers). */
export type CareerProfile = {
    preferred_language: string | null;
    bio: string | null;
    search_location: string | null;
    search_radius: number | null;
};

/** Eintrag im Berufs- oder Ausbildungswerdegang; Daten als JJJJ-MM-TT, `to_date` null = laufend. */
export type CareerEntry = {
    id: number;
    what: string;
    where: string;
    from_date: string;
    to_date: string | null;
    qualification_or_role: string;
};
