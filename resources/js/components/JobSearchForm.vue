<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import SelectField from '@/components/SelectField.vue';
import TextField from '@/components/TextField.vue';
import { useTranslate } from '@/lib/translate';
import { index } from '@/routes/jobs';
import type { JobFilters } from '@/types';

const props = withDefaults(
    defineProps<{
        filters?: JobFilters;
        fieldErrors?: Record<string, string>;
    }>(),
    {
        filters: () => ({
            was: null,
            wo: null,
            umkreis: null,
            angebotsart: null,
            berufsfeld: null,
            veroeffentlichtseit: null,
        }),
        fieldErrors: () => ({}),
    },
);

const { t } = useTranslate();

const radiusOptions = computed(() =>
    ['5', '10', '25', '50', '100', '200'].map((km) => ({
        value: km,
        label: t('jobs.distance', { km }),
    })),
);

const offerTypeOptions = computed(() => [
    { value: '', label: t('jobs.offer_type.all') },
    { value: '1', label: t('jobs.offer_type.ARBEIT') },
    { value: '4', label: t('jobs.offer_type.AUSBILDUNG') },
    { value: '34', label: t('jobs.offer_type.PRAKTIKUM_TRAINEE') },
    { value: '2', label: t('jobs.offer_type.SELBSTAENDIGKEIT') },
]);

const publishedOptions = computed(() =>
    ['7', '14', '30', '100'].map((days) => ({
        value: days,
        label: t('jobs.published_within', { days }),
    })),
);

const hasAdvancedFilters = computed(
    () =>
        Boolean(props.filters.angebotsart) ||
        Boolean(props.filters.berufsfeld) ||
        (props.filters.veroeffentlichtseit !== null &&
            props.filters.veroeffentlichtseit !== undefined &&
            props.filters.veroeffentlichtseit !== '100') ||
        ['angebotsart', 'berufsfeld', 'veroeffentlichtseit'].some(
            (field) => props.fieldErrors[field] !== undefined,
        ),
);
</script>

<template>
    <Card>
        <Form
            v-bind="index.form()"
            class="flex flex-col gap-5"
            #default="{ processing }"
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <TextField
                    name="was"
                    :label="t('jobs.field.was')"
                    :value="filters.was ?? undefined"
                    :error="fieldErrors.was"
                />
                <TextField
                    name="wo"
                    :label="t('jobs.field.wo')"
                    :value="filters.wo ?? undefined"
                    :required="false"
                    :error="fieldErrors.wo"
                />
                <SelectField
                    name="umkreis"
                    :label="t('jobs.field.umkreis')"
                    :options="radiusOptions"
                    :value="filters.umkreis ?? '25'"
                    :error="fieldErrors.umkreis"
                />
            </div>
            <details
                :open="hasAdvancedFilters"
                class="rounded-md border border-line px-4"
            >
                <summary
                    class="flex min-h-11 cursor-pointer items-center text-sm font-medium"
                >
                    {{ t('jobs.more_filters') }}
                </summary>
                <div class="grid gap-5 pt-2 pb-4 sm:grid-cols-2">
                    <SelectField
                        name="angebotsart"
                        :label="t('jobs.field.angebotsart')"
                        :options="offerTypeOptions"
                        :value="filters.angebotsart ?? ''"
                        :error="fieldErrors.angebotsart"
                    />
                    <TextField
                        name="berufsfeld"
                        :label="t('jobs.field.berufsfeld')"
                        :value="filters.berufsfeld ?? undefined"
                        :required="false"
                        :error="fieldErrors.berufsfeld"
                    />
                    <SelectField
                        name="veroeffentlichtseit"
                        :label="t('jobs.field.veroeffentlichtseit')"
                        :options="publishedOptions"
                        :value="filters.veroeffentlichtseit ?? '100'"
                        :error="fieldErrors.veroeffentlichtseit"
                    />
                </div>
            </details>
            <div>
                <Button type="submit" :processing="processing">
                    {{ t('jobs.search') }}
                </Button>
            </div>
        </Form>
    </Card>
</template>
