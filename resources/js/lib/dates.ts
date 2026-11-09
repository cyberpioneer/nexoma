/**
 * Formatiert ein ISO-Datum (JJJJ-MM-TT) passend zur Oberflaechensprache.
 * Das Datum wird als UTC behandelt, damit die Zeitzone den Tag nicht verschiebt.
 */
export function formatDate(isoDate: string | null, locale: string): string {
    if (isoDate === null) {
        return '';
    }

    const date = new Date(`${isoDate}T00:00:00Z`);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(date);
}
