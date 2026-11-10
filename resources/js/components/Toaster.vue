<script setup lang="ts">
import { dismissToast, toasts } from '@/lib/toast';
import { useTranslate } from '@/lib/translate';

const { t } = useTranslate();
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-4 top-22 z-50 flex flex-col items-center gap-3 sm:inset-x-auto sm:right-6"
    >
        <div
            v-for="toast in toasts"
            :key="toast.id"
            :role="toast.tone === 'error' ? 'alert' : 'status'"
            class="bg-black pointer-events-auto flex min-h-14 w-full max-w-md motion-arrive items-center gap-3 rounded-md border border-white/20 py-1 pr-1 pl-4 text-sm text-white shadow-header sm:w-96 dark:bg-[#eee] dark:text-[#888]"
        >
            <p class="flex-1">{{ toast.message }}</p>
            <button
                type="button"
                class="inline-flex size-11 shrink-0 items-center justify-center rounded-sm text-white hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                :aria-label="t('toast.close')"
                @click="dismissToast(toast.id)"
            >
                <svg
                    viewBox="0 0 20 20"
                    class="size-5"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    aria-hidden="true"
                >
                    <path d="M5 5l10 10M15 5L5 15" />
                </svg>
            </button>
        </div>
    </div>
</template>
