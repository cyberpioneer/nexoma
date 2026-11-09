<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        as?: 'div' | 'article' | 'section' | 'li';
        tone?: 'plain' | 'sage' | 'sky' | 'peach' | 'lilac';
    }>(),
    { as: 'div', tone: 'plain' },
);

const toneClasses = {
    plain: 'border border-line bg-white shadow-card',
    sage: 'bg-sage',
    sky: 'bg-sky',
    peach: 'bg-peach',
    lilac: 'bg-lilac',
};

const classes = computed(() =>
    cn('rounded-lg p-6 sm:p-8', toneClasses[props.tone]),
);
</script>

<template>
    <component :is="as" :class="classes">
        <slot />
    </component>
</template>
