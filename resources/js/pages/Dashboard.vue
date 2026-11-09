<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslate } from '@/lib/translate';

defineOptions({ layout: AppLayout });

const { t } = useTranslate();
const page = usePage();
</script>

<template>
    <Head :title="t('dashboard.title')" />
    <h1 class="text-xl font-semibold">{{ t('dashboard.title') }}</h1>
    <p class="mt-2">
        {{ t('dashboard.welcome', { name: page.props.auth.user?.name ?? '' }) }}
    </p>
</template>
