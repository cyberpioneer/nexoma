<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, watch } from 'vue';
import { checkout, portal } from '@/routes/billing';
import { show as profileShow } from '@/routes/profile';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { showToast } from '@/lib/toast';
import { useTranslate } from '@/lib/translate';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    isSubscribed: boolean;
    canManage: boolean;
    awaitingConfirmation: boolean;
}>();

const { t } = useTranslate();
const page = usePage();

/**
 * Wartezeiten (ms) zwischen den Nachfragen nach der Rückkehr von Stripe, bis der Webhook angekommen ist. Insgesamt
 * 6 Nachfragen in 30 s: zusammen mit der Rückkehr- und der Upgrade-Anfrage bleibt das unter dem Limit der
 * Billing-Routen (10 pro Minute und Benutzer).
 */
const POLL_DELAYS_MS = [3000, 3000, 4000, 5000, 7000, 8000];

let pollTimer: ReturnType<typeof setTimeout> | null = null;

function stopPolling(): void {
    if (pollTimer !== null) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
}

/** Fragt nur den Status ab (keine Freischaltung durch den Client): Der Webhook setzt den Status serverseitig. */
function schedulePoll(attempt: number): void {
    if (attempt >= POLL_DELAYS_MS.length || props.isSubscribed) {
        return;
    }

    pollTimer = setTimeout(() => {
        router.reload({
            only: ['isSubscribed', 'canManage'],
            onSuccess: () => schedulePoll(attempt + 1),
        });
    }, POLL_DELAYS_MS[attempt]);
}

watch(
    () => props.isSubscribed,
    (subscribed) => {
        if (subscribed) {
            stopPolling();
            showToast(t('billing.success_active'), 'success');
        }
    },
);

/** Rückkehr von Stripe (Erfolg/Abbruch): die Meldung des Servers einmalig als Toast zeigen. */
onMounted(() => {
    if (page.props.status) {
        showToast(page.props.status, 'success');
    }

    if (props.awaitingConfirmation && !props.isSubscribed) {
        schedulePoll(0);
    }
});

onBeforeUnmount(stopPolling);

/** Fehler des Servers (z. B. Zahlungsabwicklung nicht verfügbar) als Toast zeigen. */
function notifyFailure(errors: Record<string, string>): void {
    const message = errors.billing ?? Object.values(errors)[0];

    if (message) {
        showToast(message);
    }
}

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
                    @error="notifyFailure"
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
                    @error="notifyFailure"
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
