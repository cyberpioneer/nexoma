<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslate } from '@/lib/translate';

defineOptions({ layout: AppLayout });

const props = defineProps<{ status: number }>();

const { t } = useTranslate();
const isServerError = computed(() => props.status >= 500);
</script>

<template>
    <div class="flex max-w-2xl flex-col gap-8">
        <Head
            :title="
                t(
                    isServerError
                        ? 'oauth.error.server_title'
                        : 'oauth.error.title',
                )
            "
        />

        <PageHeader
            :title="
                t(
                    isServerError
                        ? 'oauth.error.server_title'
                        : 'oauth.error.title',
                )
            "
        />

        <Card tone="peach">
            <p role="alert">
                {{
                    t(
                        isServerError
                            ? 'oauth.error.server_text'
                            : 'oauth.error.text',
                    )
                }}
            </p>
        </Card>

        <div>
            <Button href="/" variant="secondary">
                {{ t('oauth.error.back') }}
            </Button>
        </div>
    </div>
</template>
