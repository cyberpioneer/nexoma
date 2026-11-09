<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useTranslate } from '@/lib/translate';
import { home } from '@/routes';

const { t } = useTranslate();
</script>

<template>
    <Link
        :href="home()"
        :aria-label="t('nav.home')"
        class="inline-flex min-h-11 items-center font-serif text-2xl font-semibold tracking-tight"
    >
        Nexoma
    </Link>
</template>
