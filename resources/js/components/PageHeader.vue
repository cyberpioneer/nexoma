<script setup lang="ts">
withDefaults(
    defineProps<{
        title: string;
        eyebrow?: string;
        lead?: string;
        size?: 'default' | 'display';
    }>(),
    { eyebrow: undefined, lead: undefined, size: 'default' },
);
</script>

<template>
    <header class="flex flex-col gap-4">
        <p
            v-if="eyebrow"
            class="text-xs font-medium tracking-widest text-muted uppercase"
        >
            {{ eyebrow }}
        </p>
        <h1
            :class="
                size === 'display' ? 'text-display' : 'text-3xl sm:text-4xl'
            "
        >
            {{ title }}
        </h1>
        <p v-if="lead" class="max-w-2xl text-lg text-muted">{{ lead }}</p>
        <div v-if="$slots.default" class="mt-2 flex flex-wrap gap-3">
            <slot />
        </div>
    </header>
</template>
