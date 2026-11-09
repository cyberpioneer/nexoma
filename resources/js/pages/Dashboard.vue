<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslate } from '@/lib/translate';

defineOptions({ layout: AppLayout });

const { t } = useTranslate();
const page = usePage();
</script>

<template>
    <div>
        <Head :title="t('dashboard.title')" />
        <PageHeader
            :title="t('dashboard.title')"
            :lead="
                t('dashboard.welcome', {
                    name: page.props.auth.user?.name ?? '',
                })
            "
        />
    </div>
</template>
