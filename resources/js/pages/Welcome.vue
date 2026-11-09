<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import PageHeader from '@/components/PageHeader.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { useTranslate } from '@/lib/translate';
import { dashboard, login, register } from '@/routes';
import { index as connectedAppsIndex } from '@/routes/connected-apps';

defineOptions({ layout: GuestLayout });

const { t } = useTranslate();
const page = usePage();

const steps = ['step1', 'step2', 'step3'] as const;
</script>

<template>
    <div class="flex flex-col gap-16 sm:gap-24">
        <Head :title="t('home.title')" />
        <section class="max-w-4xl">
            <PageHeader
                size="display"
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
        <section aria-labelledby="mcp-heading">
            <Card
                tone="lilac"
                class="grid gap-10 md:grid-cols-2 md:items-center lg:gap-16"
            >
                <div class="flex flex-col gap-6">
                    <h2 id="mcp-heading" class="text-3xl sm:text-4xl">
                        {{ t('home.mcp.title') }}
                    </h2>
                    <p class="text-lg">{{ t('home.mcp.lead') }}</p>
                    <ol class="flex flex-col gap-4">
                        <li
                            v-for="(step, index) in steps"
                            :key="step"
                            class="flex gap-4"
                        >
                            <span
                                aria-hidden="true"
                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-ink text-sm font-semibold text-white"
                            >
                                {{ index + 1 }}
                            </span>
                            <div>
                                <h3 class="font-sans text-base font-semibold">
                                    {{ t(`home.mcp.${step}.title`) }}
                                </h3>
                                <p>{{ t(`home.mcp.${step}.text`) }}</p>
                            </div>
                        </li>
                    </ol>
                    <p class="font-medium">{{ t('home.mcp.safe') }}</p>
                    <div>
                        <Button
                            v-if="page.props.auth.user"
                            :href="connectedAppsIndex()"
                            size="lg"
                        >
                            {{ t('home.mcp.cta_user') }}
                        </Button>
                        <Button v-else :href="register()" size="lg">
                            {{ t('home.cta.register') }}
                        </Button>
                    </div>
                    <p class="text-sm text-muted">{{ t('home.mcp.tech') }}</p>
                </div>

                <div
                    role="group"
                    :aria-label="t('home.mcp.example')"
                    class="flex flex-col gap-4"
                >
                    <p class="text-sm font-medium text-muted">
                        {{ t('home.mcp.example') }}
                    </p>
                    <p
                        class="ml-auto max-w-[90%] rounded-2xl rounded-br-sm bg-ink px-5 py-3 text-white"
                    >
                        {{ t('home.mcp.question') }}
                    </p>
                    <p
                        class="max-w-[90%] rounded-2xl rounded-bl-sm border border-line bg-white px-5 py-3"
                    >
                        {{ t('home.mcp.answer') }}
                    </p>
                </div>
            </Card>
        </section>
    </div>
</template>
