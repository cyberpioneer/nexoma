<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import PageHeader from '@/components/PageHeader.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { useTranslate } from '@/lib/translate';
import { dashboard, login, register } from '@/routes';

defineOptions({ layout: GuestLayout });

const { t } = useTranslate();
const page = usePage();
</script>

<template>
    <div class="flex flex-col gap-16 sm:gap-24">
        <Head :title="t('home.title')" />
        <section class="max-w-4xl">
            <PageHeader
                size="display"
                :eyebrow="t('home.eyebrow')"
                :title="t('home.title')"
                :lead="t('home.lead')"
            >
                <template v-if="page.props.auth.user">
                    <Button :href="dashboard()" size="lg">
                        {{ t('home.cta.dashboard') }}
                    </Button>
                </template>
                <template v-else>
                    <Button :href="register()" size="lg">
                        {{ t('home.cta.register') }}
                    </Button>
                    <Button :href="login()" variant="secondary" size="lg">
                        {{ t('home.cta.login') }}
                    </Button>
                </template>
            </PageHeader>
        </section>
        <section>
            <ul class="grid gap-4 md:grid-cols-3">
                <Card as="li" tone="sage" class="flex flex-col gap-3">
                    <h2 class="text-xl">{{ t('home.point1.title') }}</h2>
                    <p>{{ t('home.point1.text') }}</p>
                </Card>
                <Card as="li" tone="sky" class="flex flex-col gap-3">
                    <h2 class="text-xl">{{ t('home.point2.title') }}</h2>
                    <p>{{ t('home.point2.text') }}</p>
                </Card>
                <Card as="li" tone="peach" class="flex flex-col gap-3">
                    <h2 class="text-xl">{{ t('home.point3.title') }}</h2>
                    <p>{{ t('home.point3.text') }}</p>
                </Card>
            </ul>
        </section>
    </div>
</template>
