<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-start gap-3 rounded-lg border border-dashed border-line-strong p-8"
    >
        <h2 class="text-xl">{{ title }}</h2>
        <p v-if="description" class="max-w-prose text-muted">
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-2">
            <slot />
        </div>
    </div>
</template>
