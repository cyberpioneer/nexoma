<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        tone?: 'neutral' | 'sage' | 'sky' | 'peach' | 'lilac';
    }>(),
    { tone: 'neutral' },
);

const toneClasses = {
    neutral: 'bg-surface',
    sage: 'bg-sage',
    sky: 'bg-sky',
    peach: 'bg-peach',
    lilac: 'bg-lilac',
};

const classes = computed(() =>
    cn(
        'inline-flex items-center rounded-sm px-2 py-0.5 text-xs font-medium text-ink',
        toneClasses[props.tone],
    ),
);
</script>

<template>
    <span :class="classes"><slot /></span>
</template>
