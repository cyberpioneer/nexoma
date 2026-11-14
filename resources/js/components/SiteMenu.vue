<script setup lang="ts">
import { Form, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { destroy } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
import Button from '@/components/Button.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import ThemeSwitch from '@/components/ThemeSwitch.vue';
import { useTranslate } from '@/lib/translate';
import { dashboard } from '@/routes';
import { upgrade } from '@/routes/billing';
import { index as connectedAppsIndex } from '@/routes/connected-apps';
import { index as invoicesIndex } from '@/routes/invoices';
import { index as jobsIndex } from '@/routes/jobs';
import { index as savedJobsIndex } from '@/routes/saved-jobs';

const { t } = useTranslate();
const page = usePage();

const panel = ref<HTMLDialogElement | null>(null);
let stopListening: (() => void) | undefined;

const isSignedIn = computed(() => Boolean(page.props.auth.user));

const links = computed(() => [
    { label: t('dashboard.title'), href: dashboard(), prefix: dashboard().url },
    { label: t('jobs.nav'), href: jobsIndex(), prefix: jobsIndex().url },
    {
        label: t('saved.nav'),
        href: savedJobsIndex(),
        prefix: savedJobsIndex().url,
    },
    {
        label: t('connected_apps.nav'),
        href: connectedAppsIndex(),
        prefix: connectedAppsIndex().url,
    },
    { label: t('billing.nav'), href: upgrade(), prefix: upgrade().url },
    {
        label: t('invoices.nav'),
        href: invoicesIndex(),
        prefix: invoicesIndex().url,
    },
]);

function open(): void {
    panel.value?.showModal();
}

function close(): void {
    panel.value?.close();
}

function closeOnBackdrop(event: MouseEvent): void {
    if (event.target === panel.value) {
        close();
    }
}

onMounted(() => {
    stopListening = router.on('navigate', close);
});

onBeforeUnmount(() => stopListening?.());
</script>

<template>
    <div>
        <button
            type="button"
            aria-haspopup="dialog"
            :aria-label="t('nav.menu')"
            class="group inline-flex size-12 items-center justify-center rounded-full transition-[background-color,transform] hover:bg-surface active:scale-95"
            @click="open"
        >
            <span
                aria-hidden="true"
                class="flex w-7 flex-col items-end gap-[7px]"
            >
                <span class="h-0.5 w-full rounded-full bg-ink" />
                <span
                    class="h-0.5 w-full origin-right rounded-full bg-ink transition-transform duration-200 group-hover:scale-x-75"
                />
                <span
                    class="h-0.5 w-full origin-right rounded-full bg-ink transition-transform duration-200 group-hover:scale-x-50"
                />
            </span>
        </button>

        <dialog
            ref="panel"
            :aria-label="t('nav.menu')"
            class="fixed inset-y-0 right-0 left-auto m-0 h-dvh max-h-none w-full max-w-sm border-l border-line bg-paper p-0 text-ink backdrop:bg-[rgb(0_0_0/0.5)] open:motion-drawer"
            @click="closeOnBackdrop"
        >
            <div
                class="flex h-full flex-col gap-8 overflow-y-auto overscroll-contain p-5"
            >
                <div class="flex items-center justify-between">
                    <p class="font-serif text-2xl font-semibold">Nexoma</p>
                    <button
                        type="button"
                        :aria-label="t('nav.menu_close')"
                        class="inline-flex size-11 items-center justify-center rounded-md hover:bg-surface"
                        @click="close"
                    >
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 20 20"
                            class="size-5"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.75"
                            stroke-linecap="round"
                        >
                            <path d="m5 5 10 10M15 5 5 15" />
                        </svg>
                    </button>
                </div>

                <nav v-if="isSignedIn" :aria-label="t('nav.main')">
                    <ul class="flex flex-col">
                        <li v-for="link in links" :key="link.prefix">
                            <Link
                                :href="link.href"
                                :aria-current="
                                    page.url.startsWith(link.prefix)
                                        ? 'page'
                                        : undefined
                                "
                                class="flex min-h-11 w-full items-center rounded-md px-3 hover:bg-surface aria-[current=page]:bg-surface aria-[current=page]:font-semibold"
                            >
                                {{ link.label }}
                            </Link>
                        </li>
                    </ul>
                </nav>

                <section aria-labelledby="menu-language">
                    <h2
                        id="menu-language"
                        class="mb-1 px-3 font-sans text-sm font-medium text-muted"
                    >
                        {{ t('locale.label') }}
                    </h2>
                    <LocaleSwitcher />
                </section>

                <ThemeSwitch />

                <Form v-if="isSignedIn" v-bind="destroy.form()" class="mt-auto">
                    <Button type="submit" variant="secondary" class="w-full">
                        {{ t('nav.logout') }}
                    </Button>
                </Form>
            </div>
        </dialog>
    </div>
</template>
