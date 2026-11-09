<script setup lang="ts">
import { Form, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { destroy } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
import Button from '@/components/Button.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import SiteFooter from '@/components/SiteFooter.vue';
import SkipLink from '@/components/SkipLink.vue';
import Wordmark from '@/components/Wordmark.vue';
import { useTranslate } from '@/lib/translate';
import { dashboard } from '@/routes';
import { index as connectedAppsIndex } from '@/routes/connected-apps';
import { index as jobsIndex } from '@/routes/jobs';

const { t } = useTranslate();
const page = usePage();

const isDashboardCurrent = computed(() => page.url.startsWith(dashboard().url));
const isJobsCurrent = computed(() => page.url.startsWith(jobsIndex().url));
const isConnectedAppsCurrent = computed(() =>
    page.url.startsWith(connectedAppsIndex().url),
);
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <SkipLink />
        <header class="border-b border-line">
            <div
                class="layout-container flex flex-wrap items-center justify-between gap-x-6 gap-y-2 py-3"
            >
                <Wordmark />
                <nav
                    :aria-label="t('nav.main')"
                    class="order-last sm:order-none"
                >
                    <ul class="flex items-center gap-1">
                        <li>
                            <Link
                                :href="dashboard()"
                                :aria-current="
                                    isDashboardCurrent ? 'page' : undefined
                                "
                                class="inline-flex min-h-11 items-center rounded-md px-3 text-sm font-medium hover:bg-surface aria-[current=page]:underline aria-[current=page]:underline-offset-8"
                            >
                                {{ t('dashboard.title') }}
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="jobsIndex()"
                                :aria-current="
                                    isJobsCurrent ? 'page' : undefined
                                "
                                class="inline-flex min-h-11 items-center rounded-md px-3 text-sm font-medium hover:bg-surface aria-[current=page]:underline aria-[current=page]:underline-offset-8"
                            >
                                {{ t('jobs.nav') }}
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="connectedAppsIndex()"
                                :aria-current="
                                    isConnectedAppsCurrent ? 'page' : undefined
                                "
                                class="inline-flex min-h-11 items-center rounded-md px-3 text-sm font-medium hover:bg-surface aria-[current=page]:underline aria-[current=page]:underline-offset-8"
                            >
                                {{ t('connected_apps.nav') }}
                            </Link>
                        </li>
                    </ul>
                </nav>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <LocaleSwitcher />
                    <Form v-bind="destroy.form()">
                        <Button type="submit" variant="ghost">
                            {{ t('nav.logout') }}
                        </Button>
                    </Form>
                </div>
            </div>
        </header>
        <main
            id="main"
            tabindex="-1"
            class="layout-container flex-1 py-10 focus-visible:outline-none sm:py-16"
        >
            <slot />
        </main>
        <SiteFooter />
    </div>
</template>
