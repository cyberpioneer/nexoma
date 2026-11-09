<script setup lang="ts">
export type SelectOption = { value: string; label: string };

withDefaults(
    defineProps<{
        name: string;
        label: string;
        options: SelectOption[];
        value?: string;
        error?: string;
    }>(),
    { value: undefined, error: undefined },
);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label :for="name" class="text-sm font-medium">{{ label }}</label>
        <select
            :id="name"
            :name="name"
            :value="value"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? `${name}-error` : undefined"
            class="field-control w-full"
        >
            <option
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </select>
        <p
            v-if="error"
            :id="`${name}-error`"
            role="alert"
            class="text-sm text-danger"
        >
            {{ error }}
        </p>
    </div>
</template>
