<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { destroy } from '@/actions/App/Http/Controllers/ConnectedAppController';
import Badge from '@/components/Badge.vue';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/dates';
import { useTranslate } from '@/lib/translate';
import type { ConnectedApp } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{ apps: ConnectedApp[]; mcpUrl: string }>();

const { t } = useTranslate();
const page = usePage();

const copied = ref(false);
const guideSteps = ['step1', 'step2', 'step3', 'step4'] as const;

async function copyAddress(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.mcpUrl);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2500);
    } catch {
        // Zwischenablage nicht verfuegbar: die Adresse kann im Feld markiert werden.
    }
}

const confirmingId = ref<string | null>(null);
const processingId = ref<string | null>(null);

function revoke(app: ConnectedApp): void {
    router.delete(destroy(app.id).url, {
        preserveScroll: true,
        onStart: () => {
            processingId.value = app.id;
        },
        onFinish: () => {
            processingId.value = null;
            confirmingId.value = null;
        },
    });
}

function scopeText(id: string): string {
    const key = `oauth.scope.${id}`;
    const text = t(key);

    return text === key ? id : text;
}
</script>

<template>
    <div class="flex flex-col gap-8">
        <Head :title="t('connected_apps.title')" />

        <PageHeader
            :title="t('connected_apps.title')"
            :lead="t('connected_apps.lead')"
        />

        <Card as="section" aria-labelledby="guide-heading">
            <div class="flex flex-col gap-6">
                <h2 id="guide-heading" class="text-2xl">
                    {{ t('connected_apps.guide.title') }}
                </h2>
                <p class="max-w-2xl">{{ t('connected_apps.guide.intro') }}</p>

                <div class="flex max-w-2xl flex-col gap-2">
                    <label for="mcp-address" class="text-sm font-medium">
                        {{ t('connected_apps.guide.address_label') }}
                    </label>
                    <div class="flex flex-wrap items-center gap-3">
                        <input
                            id="mcp-address"
                            type="text"
                            readonly
                            :value="props.mcpUrl"
                            class="field-control min-w-0 flex-1 font-mono text-sm"
                            @focus="
                                ($event.target as HTMLInputElement).select()
                            "
                        />
                        <Button variant="secondary" @click="copyAddress">
                            {{ t('connected_apps.guide.copy') }}
                        </Button>
                        <span class="text-sm text-success" aria-live="polite">
                            {{ copied ? t('connected_apps.guide.copied') : '' }}
                        </span>
                    </div>
                </div>

                <ol class="flex max-w-2xl flex-col gap-3">
                    <li
                        v-for="(step, index) in guideSteps"
                        :key="step"
                        class="flex gap-4"
                    >
                        <span
                            aria-hidden="true"
                            class="flex size-7 shrink-0 items-center justify-center rounded-full bg-surface text-sm font-semibold"
                        >
                            {{ index + 1 }}
                        </span>
                        <p>{{ t(`connected_apps.guide.${step}`) }}</p>
                    </li>
                </ol>

                <div class="max-w-2xl rounded-md bg-surface p-4">
                    <h3 class="font-sans text-base font-semibold">
                        {{ t('connected_apps.guide.allowed_title') }}
                    </h3>
                    <p class="mt-1">
                        {{ t('connected_apps.guide.allowed_text') }}
                    </p>
                    <p class="mt-2">
                        {{ t('connected_apps.guide.revoke_text') }}
                    </p>
                </div>

                <p class="text-sm text-muted">
                    {{ t('connected_apps.guide.note') }}
                </p>
            </div>
        </Card>

        <ul v-if="props.apps.length > 0" class="flex flex-col gap-4">
            <Card v-for="app in props.apps" :key="app.id" as="li">
                <div class="flex flex-col gap-4">
                    <h2 class="text-xl break-words">{{ app.name }}</h2>
                    <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="font-medium">
                                {{ t('connected_apps.redirect') }}
                            </dt>
                            <dd
                                v-for="redirect in app.redirectHosts"
                                :key="redirect.host"
                                class="break-all"
                            >
                                {{ redirect.host }}
                                <Badge v-if="redirect.punycode" tone="peach">{{
                                    t('oauth.punycode.badge')
                                }}</Badge>
                            </dd>
                        </div>
                        <div v-if="app.connectedAt">
                            <dt class="font-medium">
                                {{ t('connected_apps.since') }}
                            </dt>
                            <dd>
                                {{
                                    formatDate(
                                        app.connectedAt,
                                        page.props.locale,
                                    )
                                }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="font-medium">
                                {{ t('connected_apps.access') }}
                            </dt>
                            <dd
                                v-for="scope in app.scopes"
                                :key="scope"
                                class="flex items-center gap-2"
                            >
                                {{ scopeText(scope) }}
                            </dd>
                        </div>
                    </dl>
                    <div class="flex flex-wrap items-center gap-3">
                        <Button
                            v-if="confirmingId !== app.id"
                            variant="secondary"
                            @click="confirmingId = app.id"
                        >
                            {{ t('connected_apps.revoke') }}
                        </Button>
                        <div
                            v-else
                            class="flex motion-arrive flex-wrap items-center gap-3"
                        >
                            <p class="text-sm" role="alert">
                                {{
                                    t('connected_apps.confirm', {
                                        name: app.name,
                                    })
                                }}
                            </p>
                            <Button
                                :processing="processingId === app.id"
                                @click="revoke(app)"
                            >
                                {{ t('connected_apps.confirm_yes') }}
                            </Button>
                            <Button
                                variant="ghost"
                                :disabled="processingId === app.id"
                                @click="confirmingId = null"
                            >
                                {{ t('connected_apps.confirm_no') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </Card>
        </ul>

        <EmptyState
            v-else
            :title="t('connected_apps.empty.title')"
            :description="t('connected_apps.empty.text')"
        />
    </div>
</template>
