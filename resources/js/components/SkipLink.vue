<script setup lang="ts">
import { useTranslate } from '@/lib/translate';

const { t } = useTranslate();
</script>

<template>
    <a
        href="#main"
        class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:bg-ink focus:px-4 focus:py-3 focus:text-sm focus:font-medium focus:text-white"
    >
        {{ t('skip.link') }}
    </a>
</template>
