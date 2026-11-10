<script setup lang="ts">
const model = defineModel<string>({ required: true });

withDefaults(
    defineProps<{
        name: string;
        label: string;
        hint?: string;
        error?: string;
        maxlength?: number;
        rows?: number;
    }>(),
    { hint: undefined, error: undefined, maxlength: undefined, rows: 3 },
);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label :for="name" class="text-sm font-medium">{{ label }}</label>
        <textarea
            :id="name"
            v-model="model"
            :name="name"
            :rows="rows"
            :maxlength="maxlength"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="
                [hint ? `${name}-hint` : null, error ? `${name}-error` : null]
                    .filter(Boolean)
                    .join(' ') || undefined
            "
            class="field-control w-full resize-y"
        />
        <p v-if="hint" :id="`${name}-hint`" class="text-sm text-muted">
            {{ hint }}
        </p>
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
