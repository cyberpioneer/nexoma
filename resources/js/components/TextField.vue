<script setup lang="ts">
withDefaults(
    defineProps<{
        name: string;
        label: string;
        type?: string;
        autocomplete?: string;
        error?: string;
        value?: string;
        required?: boolean;
    }>(),
    {
        type: 'text',
        autocomplete: 'off',
        error: undefined,
        value: undefined,
        required: true,
    },
);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label :for="name" class="text-sm font-medium">{{ label }}</label>
        <input
            :id="name"
            :name="name"
            :type="type"
            :value="value"
            :autocomplete="autocomplete"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? `${name}-error` : undefined"
            :required="required"
            class="field-control w-full"
        />
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
