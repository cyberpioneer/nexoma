<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Button from '@/components/Button.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import SavedJobItem from '@/components/SavedJobItem.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslate } from '@/lib/translate';
import { index } from '@/routes/jobs';
import type { SavedJob } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{ savedJobs: SavedJob[] }>();

const { t } = useTranslate();
</script>

<template>
    <div class="flex flex-col gap-8">
        <Head :title="t('saved.title')" />
        <PageHeader :title="t('saved.title')" :lead="t('saved.lead')" />

        <ul
            v-if="props.savedJobs.length > 0"
            :aria-label="t('saved.list_label')"
            class="flex flex-col gap-4"
        >
            <SavedJobItem
                v-for="job in props.savedJobs"
                :key="job.id"
                :job="job"
            />
        </ul>

        <EmptyState
            v-else
            :title="t('saved.empty.title')"
            :description="t('saved.empty.text')"
        >
            <Button :href="index()">{{ t('saved.empty.action') }}</Button>
        </EmptyState>
    </div>
</template>
