<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    destroy,
    update,
} from '@/actions/App/Http/Controllers/CareerEntryController';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import CareerEntryFields from '@/components/CareerEntryFields.vue';
import { formatDate } from '@/lib/dates';
import { showToast } from '@/lib/toast';
import { useTranslate } from '@/lib/translate';
import type { CareerEntry } from '@/types';

const props = defineProps<{ entry: CareerEntry }>();

const { t } = useTranslate();
const page = usePage();

const editing = ref(false);
const confirmingDelete = ref(false);

function period(): string {
    return t('profile.entry.period', {
        from: formatDate(props.entry.from_date, page.props.locale),
        to: props.entry.to_date
            ? formatDate(props.entry.to_date, page.props.locale)
            : t('profile.entry.ongoing'),
    });
}

function onSaved(): void {
    editing.value = false;
    showToast(t('profile.toast.entry_saved'), 'success');
}

function onDeleted(): void {
    showToast(t('profile.toast.entry_deleted'), 'success');
}
</script>

<template>
    <Card as="li">
        <Form
            v-if="editing"
            v-bind="update.form(entry.id)"
            :options="{ preserveScroll: true }"
            class="flex flex-col gap-5"
            #default="{ errors, processing }"
            @success="onSaved"
        >
            <CareerEntryFields
                :errors="errors"
                :entry="entry"
                :id-prefix="`entry-${entry.id}`"
            />
            <div class="flex flex-wrap gap-3">
                <Button type="submit" :processing="processing">
                    {{ t('profile.entry.save') }}
                </Button>
                <Button variant="secondary" @click="editing = false">
                    {{ t('profile.entry.cancel') }}
                </Button>
            </div>
        </Form>

        <div v-else class="flex flex-col gap-4">
            <div>
                <h3 class="font-sans text-lg font-semibold break-words">
                    {{ entry.what }}
                </h3>
                <p class="break-words">{{ entry.where }}</p>
                <p class="text-sm text-muted">{{ period() }}</p>
                <p class="mt-1 break-words">
                    {{ entry.qualification_or_role }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <template v-if="!confirmingDelete">
                    <Button variant="secondary" @click="editing = true">
                        {{ t('profile.entry.edit') }}
                    </Button>
                    <Button variant="ghost" @click="confirmingDelete = true">
                        {{ t('profile.entry.delete') }}
                    </Button>
                </template>
                <Form
                    v-else
                    v-bind="destroy.form(entry.id)"
                    :options="{ preserveScroll: true }"
                    class="flex motion-arrive flex-wrap items-center gap-3"
                    #default="{ processing }"
                    @success="onDeleted"
                >
                    <p class="text-sm" role="alert">
                        {{ t('profile.entry.delete_confirm') }}
                    </p>
                    <Button type="submit" :processing="processing">
                        {{ t('profile.entry.delete_yes') }}
                    </Button>
                    <Button
                        variant="secondary"
                        @click="confirmingDelete = false"
                    >
                        {{ t('profile.entry.cancel') }}
                    </Button>
                </Form>
            </div>
        </div>
    </Card>
</template>
