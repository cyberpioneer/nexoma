<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { useTranslate } from '@/lib/translate';
import { update } from '@/routes/locale';

const page = usePage();
const { t } = useTranslate();
</script>

<template>
    <Form
        v-bind="update.form()"
        :options="{ preserveScroll: true }"
        class="flex items-center gap-2 text-sm"
    >
        <label for="locale-select">{{ t('locale.label') }}</label>
        <select
            id="locale-select"
            name="locale"
            :value="page.props.locale"
            class="rounded-md border border-gray-300 px-2 py-1 dark:border-gray-600"
        >
            <option
                v-for="option in page.props.availableLocales"
                :key="option.code"
                :value="option.code"
                :lang="option.code"
            >
                {{ option.name }}
            </option>
        </select>
        <button type="submit" class="underline">
            {{ t('locale.submit') }}
        </button>
    </Form>
</template>
