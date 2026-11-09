<script setup lang="ts">
import { useTranslate } from '@/lib/translate';
import { useTheme } from '@/lib/theme';

const { t } = useTranslate();
const { isDark, toggle } = useTheme();
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="isDark"
        class="flex min-h-11 w-full items-center justify-between gap-4 rounded-md px-3 text-left hover:bg-surface"
        @click="toggle"
    >
        <span>{{ t('theme.dark') }}</span>
        <span
            aria-hidden="true"
            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full border border-line-strong transition-colors"
            :class="isDark ? 'bg-ink' : 'bg-surface'"
        >
            <span
                class="absolute left-0.5 size-4 rounded-full border border-line-strong bg-white transition-transform"
                :class="isDark ? 'translate-x-5' : 'translate-x-0'"
            />
        </span>
    </button>
</template>
