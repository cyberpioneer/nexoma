<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AudioBriefingPlayer from '@/components/AudioBriefingPlayer.vue';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/dates';
import { useTranslate } from '@/lib/translate';
import { dashboard } from '@/routes';
import type { AudioBriefingSummary, BriefingPagination } from '@/types';

defineOptions({ layout: AppLayout });

defineProps<{
    briefings: AudioBriefingSummary[];
    pagination: BriefingPagination;
}>();

const { t } = useTranslate();
const page = usePage();

/** Dauer als m:ss (z. B. 83000 ms -> 1:23). */
function formatDuration(ms: number): string {
    const seconds = Math.max(0, Math.round(ms / 1000));

    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}
</script>

<template>
    <div class="flex flex-col gap-8">
        <Head :title="t('briefings.title')" />
        <PageHeader :title="t('briefings.title')" :lead="t('briefings.lead')" />

        <template v-if="briefings.length > 0">
            <p class="max-w-2xl text-sm text-muted">
                {{ t('dashboard.briefing.ai_note') }}
            </p>

            <ul
                :aria-label="t('briefings.list_label')"
                class="flex flex-col gap-4"
            >
                <Card
                    v-for="briefing in briefings"
                    :key="briefing.id"
                    as="li"
                    class="flex flex-col gap-4"
                >
                    <div>
                        <h2 class="text-xl">
                            {{
                                t('briefings.item', {
                                    date: formatDate(
                                        briefing.createdAt,
                                        page.props.locale,
                                    ),
                                })
                            }}
                        </h2>
                        <p class="text-sm text-muted">
                            {{
                                t('briefings.duration', {
                                    duration: formatDuration(
                                        briefing.durationMs,
                                    ),
                                })
                            }}
                        </p>
                    </div>
                    <AudioBriefingPlayer :briefing="briefing" />
                </Card>
            </ul>

            <nav
                v-if="pagination.lastPage > 1"
                :aria-label="t('briefings.pagination.label')"
                class="flex flex-wrap items-center gap-4"
            >
                <Button
                    v-if="pagination.previousUrl"
                    :href="pagination.previousUrl"
                    variant="secondary"
                >
                    {{ t('briefings.pagination.previous') }}
                </Button>
                <span class="text-sm" aria-live="polite">
                    {{
                        t('briefings.pagination.status', {
                            page: String(pagination.currentPage),
                            last: String(pagination.lastPage),
                        })
                    }}
                </span>
                <Button
                    v-if="pagination.nextUrl"
                    :href="pagination.nextUrl"
                    variant="secondary"
                >
                    {{ t('briefings.pagination.next') }}
                </Button>
            </nav>
        </template>

        <EmptyState
            v-else
            :title="t('briefings.empty.title')"
            :description="t('briefings.empty.text')"
        >
            <Button :href="dashboard()">{{ t('briefings.back') }}</Button>
        </EmptyState>
    </div>
</template>
