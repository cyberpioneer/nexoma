<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AudioBriefingPlayer from '@/components/AudioBriefingPlayer.vue';
import Card from '@/components/Card.vue';
import JobSearchForm from '@/components/JobSearchForm.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/dates';
import { useTranslate } from '@/lib/translate';
import { index as savedBriefingsIndex } from '@/routes/saved-briefings';
import { index as savedJobsIndex } from '@/routes/saved-jobs';
import type { DashboardAudio } from '@/types';

defineOptions({ layout: AppLayout });

defineProps<{ savedJobsCount: number; audio: DashboardAudio | null }>();

const { t } = useTranslate();
const page = usePage();
</script>

<template>
    <div class="flex flex-col gap-10">
        <Head :title="t('dashboard.title')" />
        <PageHeader
            :title="t('dashboard.title')"
            :lead="
                t('dashboard.welcome', {
                    name: page.props.auth.user?.name ?? '',
                })
            "
        />

        <section aria-labelledby="dashboard-stats">
            <h2 id="dashboard-stats" class="mb-4 text-2xl">
                {{ t('dashboard.stats.title') }}
            </h2>
            <dl class="grid gap-4 sm:grid-cols-2">
                <Card tone="sage" class="flex flex-col gap-1">
                    <dt class="text-sm font-medium">
                        <Link
                            :href="savedJobsIndex()"
                            class="underline-offset-4 hover:underline"
                        >
                            {{ t('dashboard.stats.saved_jobs') }}
                        </Link>
                    </dt>
                    <dd class="text-4xl font-semibold tabular-nums">
                        {{ savedJobsCount }}
                    </dd>
                </Card>
                <Card v-if="audio" tone="sky" class="flex flex-col gap-1">
                    <dt class="text-sm font-medium">
                        <Link
                            :href="savedBriefingsIndex()"
                            class="underline-offset-4 hover:underline"
                        >
                            {{ t('dashboard.stats.briefings') }}
                        </Link>
                    </dt>
                    <dd class="text-4xl font-semibold tabular-nums">
                        {{ audio.count }}
                    </dd>
                </Card>
            </dl>
        </section>

        <section v-if="audio" aria-labelledby="dashboard-briefing">
            <h2 id="dashboard-briefing" class="mb-4 text-2xl">
                {{ t('dashboard.briefing.title') }}
            </h2>
            <Card v-if="audio.latest" class="flex flex-col gap-4">
                <p class="text-sm text-muted">
                    {{
                        t('dashboard.briefing.created', {
                            date: formatDate(
                                audio.latest.createdAt,
                                page.props.locale,
                            ),
                        })
                    }}
                </p>
                <AudioBriefingPlayer :briefing="audio.latest" />
                <p class="text-sm text-muted">
                    {{ t('dashboard.briefing.ai_note') }}
                </p>
            </Card>
            <p v-else class="max-w-2xl text-muted">
                {{ t('dashboard.briefing.empty') }}
            </p>
        </section>

        <section aria-labelledby="dashboard-search">
            <h2 id="dashboard-search" class="mb-4 text-2xl">
                {{ t('dashboard.search.title') }}
            </h2>
            <JobSearchForm />
        </section>
    </div>
</template>
