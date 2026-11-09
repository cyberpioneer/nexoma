<script setup lang="ts">
import { useTranslate } from '@/lib/translate';

defineProps<{ processing: boolean }>();

const { t } = useTranslate();
</script>

<template>
    <button
        type="submit"
        :disabled="processing"
        class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50 dark:bg-gray-100 dark:text-gray-900"
    >
        <template v-if="processing">{{ t('common.processing') }}</template>
        <slot v-else />
    </button>
</template>
