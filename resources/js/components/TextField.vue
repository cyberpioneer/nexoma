<script setup lang="ts">
withDefaults(
    defineProps<{
        name: string;
        label: string;
        type?: string;
        autocomplete?: string;
        error?: string;
        value?: string;
    }>(),
    { type: 'text', autocomplete: 'off', error: undefined, value: undefined },
);
</script>

<template>
    <div class="flex flex-col gap-1">
        <label :for="name" class="text-sm font-medium">{{ label }}</label>
        <input
            :id="name"
            :name="name"
            :type="type"
            :value="value"
            :autocomplete="autocomplete"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? `${name}-error` : undefined"
            required
            class="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
        />
        <p
            v-if="error"
            :id="`${name}-error`"
            role="alert"
            class="text-sm text-red-600 dark:text-red-400"
        >
            {{ error }}
        </p>
    </div>
</template>
