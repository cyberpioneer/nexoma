<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import Button from '@/components/Button.vue';
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
        <label for="locale-select" class="text-muted">{{
            t('locale.label')
        }}</label>
        <select
            id="locale-select"
            name="locale"
            :value="page.props.locale"
            class="field-control"
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
        <Button type="submit" variant="secondary">
            {{ t('locale.submit') }}
        </Button>
    </Form>
</template>
