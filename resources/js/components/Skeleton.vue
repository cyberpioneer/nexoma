<script setup lang="ts">
import { useTranslate } from '@/lib/translate';

withDefaults(defineProps<{ lines?: number }>(), { lines: 3 });

const { t } = useTranslate();
</script>

<template>
    <div class="flex flex-col gap-3" role="status" aria-busy="true">
        <span class="sr-only">{{ t('common.loading') }}</span>
        <div
            v-for="line in lines"
            :key="line"
            aria-hidden="true"
            class="h-4 rounded-sm bg-surface motion-safe:animate-pulse"
            :class="line === lines && lines > 1 ? 'w-2/3' : 'w-full'"
        />
    </div>
</template>
