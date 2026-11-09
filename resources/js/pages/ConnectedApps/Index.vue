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

const props = defineProps<{ apps: ConnectedApp[] }>();

const { t } = useTranslate();
const page = usePage();

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
                                <Badge tone="sky">{{ scope }}</Badge>
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
                        <template v-else>
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
                        </template>
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
