<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Badge from '@/components/Badge.vue';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslate } from '@/lib/translate';
import type { AuthorizeProps } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<AuthorizeProps>();

const { t } = useTranslate();

function scopeText(id: string, fallback: string): string {
    const key = `oauth.scope.${id}`;
    const text = t(key);

    return text === key ? fallback : text;
}
</script>

<template>
    <div class="flex max-w-2xl flex-col gap-8">
        <Head :title="t('oauth.authorize.title')" />

        <PageHeader
            :title="t('oauth.authorize.title')"
            :lead="t('oauth.authorize.lead', { email: props.user.email })"
        />

        <Card tone="peach">
            <p class="font-medium">{{ t('oauth.authorize.unverified') }}</p>
            <dl class="mt-4 grid gap-4 text-sm">
                <div>
                    <dt class="font-medium">{{ t('oauth.authorize.app') }}</dt>
                    <dd class="text-lg break-words">{{ props.client.name }}</dd>
                </div>
                <div>
                    <dt class="font-medium">
                        {{ t('oauth.authorize.redirect') }}
                    </dt>
                    <dd class="text-lg font-medium break-all">
                        {{ props.redirectHost }}
                    </dd>
                    <dd v-if="props.redirectPunycode" class="mt-1 text-sm">
                        {{ t('oauth.punycode.warning') }}
                    </dd>
                </div>
            </dl>
        </Card>

        <section aria-labelledby="scopes-heading">
            <h2 id="scopes-heading" class="mb-4 text-2xl">
                {{ t('oauth.authorize.scopes') }}
            </h2>
            <ul class="flex flex-col gap-2">
                <li
                    v-for="scope in props.scopes"
                    :key="scope.id"
                    class="flex items-center gap-3"
                >
                    <Badge tone="sky">{{ scope.id }}</Badge>
                    <span>{{ scopeText(scope.id, scope.description) }}</span>
                </li>
            </ul>
        </section>

        <div class="flex flex-wrap gap-3">
            <form method="post" :action="props.approveUrl">
                <input type="hidden" name="_token" :value="props.csrfToken" />
                <input
                    v-if="props.state !== null"
                    type="hidden"
                    name="state"
                    :value="props.state"
                />
                <input type="hidden" name="client_id" :value="props.clientId" />
                <input
                    type="hidden"
                    name="auth_token"
                    :value="props.authToken"
                />
                <Button type="submit">{{
                    t('oauth.authorize.approve')
                }}</Button>
            </form>
            <form method="post" :action="props.denyUrl">
                <input type="hidden" name="_token" :value="props.csrfToken" />
                <input type="hidden" name="_method" value="DELETE" />
                <input
                    v-if="props.state !== null"
                    type="hidden"
                    name="state"
                    :value="props.state"
                />
                <input type="hidden" name="client_id" :value="props.clientId" />
                <input
                    type="hidden"
                    name="auth_token"
                    :value="props.authToken"
                />
                <Button type="submit" variant="secondary">
                    {{ t('oauth.authorize.deny') }}
                </Button>
            </form>
        </div>
    </div>
</template>
