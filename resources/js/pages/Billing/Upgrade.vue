<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { checkout, portal } from '@/routes/billing';
import { show as profileShow } from '@/routes/profile';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslate } from '@/lib/translate';

defineOptions({ layout: AppLayout });

defineProps<{ isSubscribed: boolean; canManage: boolean }>();

const { t } = useTranslate();
const page = usePage();

const features = [
    'feature_profile',
    'feature_matcher',
    'feature_audio',
] as const;
</script>

<template>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-8">
        <Head :title="t('billing.upgrade.title')" />

        <PageHeader
            :title="
                isSubscribed
                    ? t('billing.upgrade.active_title')
                    : t('billing.upgrade.title')
            "
            :lead="
                isSubscribed
                    ? t('billing.upgrade.active_lead')
                    : t('billing.upgrade.lead')
            "
        />

        <p
            v-if="page.props.status"
            role="status"
            class="rounded-md bg-sage p-4 text-sm"
        >
            {{ page.props.status }}
        </p>
        <p
            v-if="page.props.errors.billing"
            role="alert"
            class="rounded-md bg-peach p-4 text-sm text-danger"
        >
            {{ page.props.errors.billing }}
        </p>

        <Card as="section" aria-labelledby="billing-features">
            <h2 id="billing-features" class="mb-4 text-2xl">
                {{ t('billing.upgrade.features_title') }}
            </h2>
            <ul class="flex flex-col gap-3">
                <li
                    v-for="feature in features"
                    :key="feature"
                    class="flex gap-3"
                >
                    <span aria-hidden="true" class="text-success">✓</span>
                    <span>{{ t(`billing.upgrade.${feature}`) }}</span>
                </li>
            </ul>

            <div class="mt-8 flex flex-col gap-3">
                <Form
                    v-if="!isSubscribed"
                    v-bind="checkout.form()"
                    #default="{ processing }"
                >
                    <Button type="submit" size="lg" :processing="processing">
                        {{ t('billing.upgrade.subscribe') }}
                    </Button>
                </Form>
                <div v-if="isSubscribed">
                    <Button :href="profileShow()" size="lg">
                        {{ t('billing.upgrade.profile_link') }}
                    </Button>
                </div>
                <Form
                    v-if="canManage"
                    v-bind="portal.form()"
                    #default="{ processing }"
                >
                    <Button
                        type="submit"
                        :variant="isSubscribed ? 'primary' : 'secondary'"
                        :processing="processing"
                    >
                        {{ t('billing.upgrade.manage') }}
                    </Button>
                </Form>
                <p class="text-sm text-muted">
                    {{ t('billing.upgrade.payment_note') }}
                </p>
            </div>
        </Card>
    </div>
</template>
