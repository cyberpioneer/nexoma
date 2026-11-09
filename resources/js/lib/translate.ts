import { usePage } from '@inertiajs/vue3';

/**
 * Liefert die Uebersetzungsfunktion t(). Die Texte kommen vom Server (Shared Prop `translations`);
 * fehlt ein Schluessel, wird der Schluessel selbst angezeigt. `:name`-Platzhalter werden ersetzt.
 */
export function useTranslate() {
    const page = usePage();

    function t(key: string, replacements: Record<string, string> = {}): string {
        let text = page.props.translations[key] ?? key;

        for (const [name, value] of Object.entries(replacements)) {
            text = text.replaceAll(`:${name}`, () => value);
        }

        return text;
    }

    return { t };
}
