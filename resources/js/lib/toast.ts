import { reactive } from 'vue';

export type ToastTone = 'error' | 'success';

export type Toast = {
    id: number;
    tone: ToastTone;
    message: string;
};

const DISMISS_AFTER_MS = 7000;

let nextId = 1;

export const toasts = reactive<Toast[]>([]);

export function dismissToast(id: number): void {
    const index = toasts.findIndex((toast) => toast.id === id);

    if (index !== -1) {
        toasts.splice(index, 1);
    }
}

export function showToast(message: string, tone: ToastTone = 'error'): void {
    // Gleiche Meldung nicht stapeln (z. B. bei wiederholten Fehlversuchen).
    const existing = toasts.find(
        (toast) => toast.message === message && toast.tone === tone,
    );

    if (existing) {
        dismissToast(existing.id);
    }

    const id = nextId++;
    toasts.push({ id, tone, message });
    setTimeout(() => dismissToast(id), DISMISS_AFTER_MS);
}
