import { router } from '@inertiajs/vue3';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/SavedJobController';
import { showToast } from '@/lib/toast';

type Options = {
    /** Props, die nach dem Speichern neu geladen werden (alle, wenn leer). */
    only?: string[];
    successMessage: string;
    onFinish?: () => void;
    onSuccess?: () => void;
};

function visitOptions(options: Options) {
    return {
        preserveScroll: true,
        ...(options.only ? { only: options.only } : {}),
        onSuccess: () => {
            showToast(options.successMessage, 'success');
            options.onSuccess?.();
        },
        onError: (errors: Record<string, string>) => {
            const message =
                errors.refnr ?? errors.note ?? Object.values(errors)[0];

            if (message) {
                showToast(message);
            }
        },
        onFinish: () => options.onFinish?.(),
    };
}

/** Speichert eine Stelle; `note` nur mitsenden, wenn der Benutzer einen Vermerk eingibt. */
export function saveJob(
    refnr: string,
    note: string | null,
    options: Options,
): void {
    router.post(
        store().url,
        note === null ? { refnr } : { refnr, note },
        visitOptions(options),
    );
}

export function updateSavedJobNote(
    id: number,
    note: string,
    options: Options,
): void {
    router.patch(update(id).url, { note }, visitOptions(options));
}

export function removeSavedJob(id: number, options: Options): void {
    router.delete(destroy(id).url, visitOptions(options));
}
