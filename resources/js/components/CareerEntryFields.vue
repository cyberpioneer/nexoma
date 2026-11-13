<script setup lang="ts">
import TextField from '@/components/TextField.vue';
import { useTranslate } from '@/lib/translate';
import type { CareerEntry } from '@/types';

/** Gemeinsame Felder zum Anlegen und Bearbeiten eines Werdegang-Eintrags (innerhalb eines Inertia-`<Form>`). */
defineProps<{
    errors: Record<string, string>;
    entry?: CareerEntry;
    /** Eindeutiges Präfix für die Feld-IDs, damit mehrere Formulare auf einer Seite gültig bleiben. */
    idPrefix: string;
}>();

const { t } = useTranslate();
const today = new Date().toLocaleDateString('sv-SE');
</script>

<template>
    <div class="grid gap-5 sm:grid-cols-2">
        <TextField
            name="what"
            :id-prefix="idPrefix"
            :label="t('profile.entry.what')"
            :value="entry?.what"
            :error="errors.what"
        />
        <TextField
            name="where"
            :id-prefix="idPrefix"
            :label="t('profile.entry.where')"
            :value="entry?.where"
            :error="errors.where"
        />
        <TextField
            name="from_date"
            type="date"
            :id-prefix="idPrefix"
            :max="today"
            :label="t('profile.entry.from')"
            :value="entry?.from_date"
            :error="errors.from_date"
        />
        <TextField
            name="to_date"
            type="date"
            :id-prefix="idPrefix"
            :max="today"
            :required="false"
            :label="t('profile.entry.to')"
            :hint="t('profile.entry.to_hint')"
            :value="entry?.to_date ?? undefined"
            :error="errors.to_date"
        />
        <div class="sm:col-span-2">
            <TextField
                name="qualification_or_role"
                :id-prefix="idPrefix"
                :label="t('profile.entry.qualification')"
                :value="entry?.qualification_or_role"
                :error="errors.qualification_or_role"
            />
        </div>
    </div>
</template>
