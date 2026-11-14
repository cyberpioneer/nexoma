<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import Badge from '@/components/Badge.vue';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/dates';
import { useTranslate } from '@/lib/translate';
import { upgrade } from '@/routes/billing';
import type { InvoiceSummary } from '@/types';

defineOptions({ layout: AppLayout });

defineProps<{ invoices: InvoiceSummary[]; unavailable: boolean }>();

const { t } = useTranslate();
const page = usePage();

const statusTones = {
    paid: 'sage',
    open: 'peach',
    void: 'neutral',
    uncollectible: 'neutral',
} as const;

/** Betrag in der Währung der Rechnung, formatiert nach der Sprache der Oberfläche. */
function formatAmount(invoice: InvoiceSummary): string {
    try {
        return new Intl.NumberFormat(page.props.locale, {
            style: 'currency',
            currency: invoice.currency,
        }).format(invoice.total / 100);
    } catch {
        return `${(invoice.total / 100).toFixed(2)} ${invoice.currency}`;
    }
}

function label(invoice: InvoiceSummary): string {
    return invoice.number ?? t('invoices.untitled');
}
</script>

<template>
    <div class="flex flex-col gap-8">
        <Head :title="t('invoices.title')" />
        <PageHeader :title="t('invoices.title')" :lead="t('invoices.lead')" />

        <p v-if="unavailable" role="alert" class="max-w-2xl text-danger">
            {{ t('invoices.unavailable') }}
        </p>

        <EmptyState
            v-else-if="invoices.length === 0"
            :title="t('invoices.empty_title')"
            :description="t('invoices.empty_text')"
        >
            <Button :href="upgrade()">{{ t('invoices.empty_action') }}</Button>
        </EmptyState>

        <template v-else>
            <ul
                :aria-label="t('invoices.list_label')"
                class="flex flex-col gap-4"
            >
                <Card
                    v-for="(invoice, index) in invoices"
                    :key="`${index}-${invoice.number ?? ''}`"
                    as="li"
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex flex-col gap-1">
                        <h2 class="text-xl break-words">
                            {{ label(invoice) }}
                        </h2>
                        <p class="text-sm text-muted">
                            <span v-if="invoice.date">
                                {{
                                    formatDate(invoice.date, page.props.locale)
                                }}
                                ·
                            </span>
                            {{ formatAmount(invoice) }}
                        </p>
                        <div>
                            <Badge :tone="statusTones[invoice.status]">
                                {{ t(`invoices.status_${invoice.status}`) }}
                            </Badge>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a
                            v-if="invoice.viewUrl"
                            :href="invoice.viewUrl"
                            :aria-label="
                                t('invoices.view_label', {
                                    number: label(invoice),
                                })
                            "
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-11 items-center justify-center rounded-md border border-line-strong bg-white px-4 text-sm font-medium text-ink transition-colors hover:bg-surface"
                        >
                            {{ t('invoices.view') }}
                        </a>
                        <a
                            v-if="invoice.pdfUrl"
                            :href="invoice.pdfUrl"
                            :aria-label="
                                t('invoices.pdf_label', {
                                    number: label(invoice),
                                })
                            "
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-11 items-center justify-center rounded-md bg-ink px-4 text-sm font-medium text-white transition-colors hover:bg-ink-soft"
                        >
                            {{ t('invoices.pdf') }}
                        </a>
                    </div>
                </Card>
            </ul>
            <p class="max-w-2xl text-sm text-muted">
                {{ t('invoices.note') }}
            </p>
        </template>
    </div>
</template>
