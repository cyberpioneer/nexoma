<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import JobSearchForm from '@/components/JobSearchForm.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslate } from '@/lib/translate';

defineOptions({ layout: AppLayout });

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
        <section aria-labelledby="dashboard-search">
            <h2 id="dashboard-search" class="mb-4 text-2xl">
                {{ t('dashboard.search.title') }}
            </h2>
            <JobSearchForm />
        </section>
    </div>
</template>
