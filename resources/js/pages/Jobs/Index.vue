<script setup lang="ts">
import { Deferred, Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import EmptyState from '@/components/EmptyState.vue';
import JobListItem from '@/components/JobListItem.vue';
import PageHeader from '@/components/PageHeader.vue';
import SelectField from '@/components/SelectField.vue';
import Skeleton from '@/components/Skeleton.vue';
import TextField from '@/components/TextField.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslate } from '@/lib/translate';
import { index } from '@/routes/jobs';
import type { JobFilters, SearchResults } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    filters: JobFilters;
    fieldErrors: Record<string, string>;
    results?: SearchResults | null;
}>();

const { t } = useTranslate();
const page = usePage();

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

const formattedTotal = (total: number) =>
    new Intl.NumberFormat(page.props.locale).format(total);
</script>

<template>
    <div class="flex flex-col gap-10">
        <Head :title="t('jobs.title')" />
        <PageHeader :title="t('jobs.title')" :lead="t('jobs.lead')" />

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
                        :value="props.filters.was ?? undefined"
                        :error="props.fieldErrors.was"
                    />
                    <TextField
                        name="wo"
                        :label="t('jobs.field.wo')"
                        :value="props.filters.wo ?? undefined"
                        :required="false"
                        :error="props.fieldErrors.wo"
                    />
                    <SelectField
                        name="umkreis"
                        :label="t('jobs.field.umkreis')"
                        :options="radiusOptions"
                        :value="props.filters.umkreis ?? '25'"
                        :error="props.fieldErrors.umkreis"
                    />
                    <SelectField
                        name="angebotsart"
                        :label="t('jobs.field.angebotsart')"
                        :options="offerTypeOptions"
                        :value="props.filters.angebotsart ?? ''"
                        :error="props.fieldErrors.angebotsart"
                    />
                    <TextField
                        name="berufsfeld"
                        :label="t('jobs.field.berufsfeld')"
                        :value="props.filters.berufsfeld ?? undefined"
                        :required="false"
                        :error="props.fieldErrors.berufsfeld"
                    />
                    <SelectField
                        name="veroeffentlichtseit"
                        :label="t('jobs.field.veroeffentlichtseit')"
                        :options="publishedOptions"
                        :value="props.filters.veroeffentlichtseit ?? '100'"
                        :error="props.fieldErrors.veroeffentlichtseit"
                    />
                </div>
                <div>
                    <Button type="submit" :processing="processing">
                        {{ t('jobs.search') }}
                    </Button>
                </div>
            </Form>
        </Card>

        <section aria-labelledby="results-heading">
            <h2 id="results-heading" class="sr-only">
                {{ t('jobs.results') }}
            </h2>

            <p v-if="props.results === null" class="max-w-prose text-muted">
                {{ t('jobs.hint') }}
            </p>

            <Deferred v-else data="results">
                <template #fallback>
                    <Skeleton :lines="6" />
                </template>

                <template v-if="props.results">
                    <EmptyState
                        v-if="props.results.status === 'unavailable'"
                        :title="t('jobs.unavailable.title')"
                        :description="t('jobs.unavailable.text')"
                    />
                    <EmptyState
                        v-else-if="props.results.status === 'empty'"
                        :title="t('jobs.empty.title')"
                        :description="t('jobs.empty.text')"
                    />
                    <div v-else class="flex flex-col gap-6">
                        <p class="text-lg font-medium" role="status">
                            {{
                                t('jobs.total', {
                                    count: formattedTotal(props.results.total),
                                })
                            }}
                        </p>
                        <ul class="flex flex-col gap-4">
                            <JobListItem
                                v-for="job in props.results.jobs"
                                :key="job.refnr"
                                :job="job"
                            />
                        </ul>
                        <nav
                            :aria-label="t('jobs.pagination')"
                            class="flex flex-wrap items-center justify-between gap-4"
                        >
                            <Button
                                v-if="props.results.previousUrl"
                                :href="props.results.previousUrl"
                                variant="secondary"
                            >
                                {{ t('jobs.previous') }}
                            </Button>
                            <span v-else />
                            <p class="text-sm text-muted">
                                {{
                                    t('jobs.page_of', {
                                        page: String(props.results.page),
                                        pages: String(props.results.pages),
                                    })
                                }}
                            </p>
                            <Button
                                v-if="props.results.nextUrl"
                                :href="props.results.nextUrl"
                                variant="secondary"
                            >
                                {{ t('jobs.next') }}
                            </Button>
                            <span v-else />
                        </nav>
                    </div>
                </template>
            </Deferred>
        </section>
    </div>
</template>
