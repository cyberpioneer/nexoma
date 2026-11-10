<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import Badge from '@/components/Badge.vue';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import SaveJobControl from '@/components/SaveJobControl.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/dates';
import { useTranslate } from '@/lib/translate';
import { index } from '@/routes/jobs';
import type { JobDetails, SavedState } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    job: JobDetails | null;
    saved: SavedState | null;
}>();

const { t } = useTranslate();
const page = usePage();
</script>

<template>
    <div class="flex flex-col gap-8">
        <Head :title="props.job?.title ?? t('jobs.detail.title')" />

        <template v-if="props.job">
            <PageHeader
                :title="props.job.title ?? t('jobs.untitled')"
                :lead="props.job.employer ?? undefined"
                :eyebrow="props.job.profession ?? undefined"
            >
                <Badge v-if="props.job.offerType" tone="sky">
                    {{ t(`jobs.offer_type.${props.job.offerType}`) }}
                </Badge>
            </PageHeader>

            <Card tone="sage">
                <dl class="grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2">
                    <div v-if="props.job.locations.length > 0">
                        <dt class="font-medium">{{ t('jobs.location') }}</dt>
                        <dd v-for="place in props.job.locations" :key="place">
                            {{ place }}
                        </dd>
                    </div>
                    <div v-if="props.job.publishedAt">
                        <dt class="font-medium">{{ t('jobs.published') }}</dt>
                        <dd>
                            {{
                                formatDate(
                                    props.job.publishedAt,
                                    page.props.locale,
                                )
                            }}
                        </dd>
                    </div>
                    <div v-if="props.job.startDate">
                        <dt class="font-medium">{{ t('jobs.start') }}</dt>
                        <dd>
                            {{
                                formatDate(
                                    props.job.startDate,
                                    page.props.locale,
                                )
                            }}
                        </dd>
                    </div>
                    <div v-if="props.job.contractDuration">
                        <dt class="font-medium">
                            {{ t('jobs.contract_label') }}
                        </dt>
                        <dd>
                            {{
                                t(`jobs.contract.${props.job.contractDuration}`)
                            }}
                        </dd>
                    </div>
                    <div
                        v-if="
                            props.job.workingTimes.length > 0 ||
                            props.job.homeOffice
                        "
                    >
                        <dt class="font-medium">
                            {{ t('jobs.working_time_label') }}
                        </dt>
                        <dd v-for="time in props.job.workingTimes" :key="time">
                            {{ t(`jobs.working_time.${time}`) }}
                        </dd>
                        <dd v-if="props.job.homeOffice">
                            {{ t('jobs.home_office') }}
                        </dd>
                    </div>
                </dl>
            </Card>

            <section aria-labelledby="description-heading">
                <h2 id="description-heading" class="mb-4 text-2xl">
                    {{ t('jobs.description') }}
                </h2>
                <p
                    v-if="props.job.description"
                    class="max-w-prose whitespace-pre-line"
                >
                    {{ props.job.description }}
                </p>
                <p v-else class="text-muted">{{ t('jobs.no_description') }}</p>
            </section>

            <SaveJobControl
                :refnr="props.job.refnr"
                :title="props.job.title"
                :saved="props.saved"
                field-id="detail"
            />

            <div class="flex flex-wrap gap-3">
                <a
                    v-if="props.job.applicationUrl"
                    :href="props.job.applicationUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-11 items-center justify-center rounded-md bg-ink px-4 text-sm font-medium text-white transition-colors hover:bg-ink-soft"
                >
                    {{ t('jobs.apply_external') }}
                </a>
                <p
                    v-if="props.job.applicationHost"
                    class="flex min-h-11 items-center text-sm text-muted"
                >
                    {{
                        t('jobs.apply_target', {
                            host: props.job.applicationHost,
                        })
                    }}
                </p>
                <a
                    :href="props.job.officialUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-11 items-center justify-center rounded-md border border-line-strong bg-white px-4 text-sm font-medium text-ink transition-colors hover:bg-surface"
                >
                    {{ t('jobs.open_official') }}
                </a>
                <Button :href="index()" variant="ghost">
                    {{ t('jobs.back') }}
                </Button>
            </div>
            <p class="max-w-prose text-sm text-muted">
                {{ t('jobs.external_note') }}
            </p>
        </template>

        <template v-else>
            <PageHeader :title="t('jobs.detail.title')" />
            <EmptyState
                :title="t('jobs.detail.unavailable.title')"
                :description="t('jobs.detail.unavailable.text')"
            >
                <Button :href="index()">{{ t('jobs.back') }}</Button>
            </EmptyState>
        </template>
    </div>
</template>
