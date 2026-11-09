<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslate } from '@/lib/translate';

defineOptions({ layout: AppLayout });

defineProps<{ status: number }>();

const { t } = useTranslate();
</script>

<template>
    <div class="flex max-w-2xl flex-col gap-8">
        <Head :title="t('oauth.error.title')" />

        <PageHeader :title="t('oauth.error.title')" />

        <Card tone="peach">
            <p role="alert">{{ t('oauth.error.text') }}</p>
        </Card>

        <div>
            <Button href="/" variant="secondary">
                {{ t('oauth.error.back') }}
            </Button>
        </div>
    </div>
</template>
