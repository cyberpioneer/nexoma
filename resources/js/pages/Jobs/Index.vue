<script setup lang="ts">
import { Deferred, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from '@/components/Button.vue';
import EmptyState from '@/components/EmptyState.vue';
import JobListItem from '@/components/JobListItem.vue';
import JobSearchForm from '@/components/JobSearchForm.vue';
import PageHeader from '@/components/PageHeader.vue';
import Skeleton from '@/components/Skeleton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslate } from '@/lib/translate';
import type { JobFilters, SavedJobRef, SearchResults } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    filters: JobFilters;
    fieldErrors: Record<string, string>;
    results?: SearchResults | null;
    saved: SavedJobRef[];
}>();

const { t } = useTranslate();

const savedByRefnr = computed(
    () => new Map(props.saved.map((entry) => [entry.refnr, entry])),
);
const page = usePage();

const formattedTotal = (total: number) =>
    new Intl.NumberFormat(page.props.locale).format(total);
</script>

<template>
    <div class="flex flex-col gap-10">
        <Head :title="t('jobs.title')" />
        <PageHeader :title="t('jobs.title')" :lead="t('jobs.lead')" />

        <JobSearchForm
            :filters="props.filters"
            :field-errors="props.fieldErrors"
        />

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
                        class="motion-arrive"
                        :title="t('jobs.unavailable.title')"
                        :description="t('jobs.unavailable.text')"
                    />
                    <EmptyState
                        v-else-if="props.results.status === 'empty'"
                        class="motion-arrive"
                        :title="t('jobs.empty.title')"
                        :description="t('jobs.empty.text')"
                    />
                    <div v-else class="flex motion-arrive flex-col gap-6">
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
                                :saved="savedByRefnr.get(job.refnr) ?? null"
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
