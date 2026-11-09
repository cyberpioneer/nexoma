import { onMounted, ref } from 'vue';

const COOKIE_NAME = 'nexoma-theme';
const ONE_YEAR_SECONDS = 60 * 60 * 24 * 365;

/** Aktueller Zustand; der Startwert wird vor dem ersten Rendern per Inline-Skript in app.blade.php gesetzt. */
const isDark = ref(false);

function syncTheme(): void {
    isDark.value = document.documentElement.dataset.theme === 'dark';
}

function setTheme(dark: boolean): void {
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    isDark.value = dark;

    // Reines Anzeige-Cookie (kein Personenbezug); Cookies gesperrt: die Wahl gilt nur fuer diese Seite.
    document.cookie = `${COOKIE_NAME}=${dark ? 'dark' : 'light'}; path=/; max-age=${ONE_YEAR_SECONDS}; SameSite=Lax`;
}

export function useTheme() {
    onMounted(syncTheme);

    return {
        isDark,
        toggle: (): void => setTheme(!isDark.value),
    };
}
