<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import Badge from '@/components/Badge.vue';
import Card from '@/components/Card.vue';
import { formatDate } from '@/lib/dates';
import { useTranslate } from '@/lib/translate';
import { show } from '@/routes/jobs';
import type { JobSummary } from '@/types';

defineProps<{ job: JobSummary }>();

const { t } = useTranslate();
const page = usePage();
</script>

<template>
    <Card as="li" class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <Badge v-if="job.offerType" tone="sky">
                {{ t(`jobs.offer_type.${job.offerType}`) }}
            </Badge>
            <p v-if="job.profession" class="text-sm text-muted">
                {{ job.profession }}
            </p>
        </div>
        <h3 class="text-xl">
            <Link
                :href="show(job.refnr)"
                class="text-ink underline-offset-4 hover:underline"
            >
                {{ job.title ?? t('jobs.untitled') }}
            </Link>
        </h3>
        <dl class="grid gap-x-8 gap-y-1 text-sm sm:grid-cols-2">
            <div v-if="job.employer">
                <dt class="sr-only">{{ t('jobs.employer') }}</dt>
                <dd>{{ job.employer }}</dd>
            </div>
            <div v-if="job.location">
                <dt class="sr-only">{{ t('jobs.location') }}</dt>
                <dd>
                    {{ job.location }}
                    <span v-if="job.distance !== null" class="text-muted">
                        ({{ t('jobs.distance', { km: String(job.distance) }) }})
                    </span>
                </dd>
            </div>
            <div v-if="job.publishedAt" class="text-muted">
                <dt class="inline">{{ t('jobs.published') }}:</dt>
                <dd class="inline">
                    {{ formatDate(job.publishedAt, page.props.locale) }}
                </dd>
            </div>
            <div v-if="job.startDate" class="text-muted">
                <dt class="inline">{{ t('jobs.start') }}:</dt>
                <dd class="inline">
                    {{ formatDate(job.startDate, page.props.locale) }}
                </dd>
            </div>
        </dl>
    </Card>
</template>
