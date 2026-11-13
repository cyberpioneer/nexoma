<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        name: string;
        label: string;
        type?: string;
        autocomplete?: string;
        error?: string;
        value?: string;
        required?: boolean;
        /** Optionaler Hilfetext unter dem Feld. */
        hint?: string;
        /** Obergrenze für Datumsfelder (JJJJ-MM-TT). */
        max?: string;
        /** Präfix für die Feld-ID, wenn derselbe Feldname mehrfach auf einer Seite vorkommt. */
        idPrefix?: string;
    }>(),
    {
        type: 'text',
        autocomplete: 'off',
        error: undefined,
        value: undefined,
        required: true,
        hint: undefined,
        max: undefined,
        idPrefix: undefined,
    },
);

const id = computed(() =>
    props.idPrefix ? `${props.idPrefix}-${props.name}` : props.name,
);

const describedBy = computed(
    () =>
        [
            props.hint ? `${id.value}-hint` : null,
            props.error ? `${id.value}-error` : null,
        ]
            .filter(Boolean)
            .join(' ') || undefined,
);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label :for="id" class="text-sm font-medium">{{ label }}</label>
        <input
            :id="id"
            :name="name"
            :type="type"
            :value="value"
            :max="max"
            :autocomplete="autocomplete"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
            :required="required"
            class="field-control w-full"
        />
        <p v-if="hint" :id="`${id}-hint`" class="text-sm text-muted">
            {{ hint }}
        </p>
        <p
            v-if="error"
            :id="`${id}-error`"
            role="alert"
            class="text-sm text-danger"
        >
            {{ error }}
        </p>
    </div>
</template>
