<script setup lang="ts">
import { ref } from 'vue';
import Badge from '@/components/Badge.vue';
import Button from '@/components/Button.vue';
import TextareaField from '@/components/TextareaField.vue';
import { removeSavedJob, saveJob } from '@/lib/savedJobs';
import { useTranslate } from '@/lib/translate';
import type { SavedState } from '@/types';

const props = defineProps<{
    refnr: string;
    title: string | null;
    saved: SavedState | null;
    /** Eindeutiger Teil der Feld-ID, damit mehrere Eintraege einer Liste unterscheidbar bleiben. */
    fieldId: string;
}>();

const { t } = useTranslate();

const NOTE_MAX_LENGTH = 1000;
const noteOpen = ref(false);
const note = ref('');
const processing = ref(false);

function displayTitle(): string {
    return props.title ?? t('jobs.untitled');
}

function save(withNote: boolean): void {
    processing.value = true;
    saveJob(props.refnr, withNote ? note.value : null, {
        only: ['saved'],
        successMessage: t('saved.toast.saved'),
        onSuccess: () => {
            noteOpen.value = false;
            note.value = '';
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

function remove(): void {
    if (!props.saved) {
        return;
    }

    processing.value = true;
    removeSavedJob(props.saved.id, {
        only: ['saved'],
        successMessage: t('saved.toast.removed'),
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <template v-if="props.saved">
            <div class="flex flex-wrap items-center gap-3">
                <Badge tone="sage">{{ t('saved.saved') }}</Badge>
                <Button
                    variant="secondary"
                    :processing="processing"
                    :aria-label="
                        t('saved.remove_label', { title: displayTitle() })
                    "
                    @click="remove"
                >
                    {{ t('saved.remove') }}
                </Button>
            </div>
            <p
                v-if="props.saved.note"
                class="max-w-prose text-sm break-words whitespace-pre-line"
            >
                {{ props.saved.note }}
            </p>
        </template>

        <form
            v-else-if="noteOpen"
            class="flex max-w-prose flex-col gap-3"
            @submit.prevent="save(true)"
        >
            <TextareaField
                v-model="note"
                :name="`note-${fieldId}`"
                :label="t('saved.note.label')"
                :hint="t('saved.note.hint', { max: String(NOTE_MAX_LENGTH) })"
                :maxlength="NOTE_MAX_LENGTH"
            />
            <div class="flex flex-wrap gap-3">
                <Button
                    type="submit"
                    :processing="processing"
                    :aria-label="
                        t('saved.save_with_note_label', {
                            title: displayTitle(),
                        })
                    "
                >
                    {{ t('saved.save') }}
                </Button>
                <Button
                    variant="ghost"
                    :disabled="processing"
                    @click="noteOpen = false"
                >
                    {{ t('saved.cancel') }}
                </Button>
            </div>
        </form>

        <div v-else class="flex flex-wrap items-center gap-3">
            <Button
                variant="secondary"
                :processing="processing"
                :aria-label="t('saved.save_label', { title: displayTitle() })"
                @click="save(false)"
            >
                {{ t('saved.save') }}
            </Button>
            <Button
                variant="ghost"
                :disabled="processing"
                :aria-label="
                    t('saved.save_with_note_label', { title: displayTitle() })
                "
                @click="noteOpen = true"
            >
                {{ t('saved.save_with_note') }}
            </Button>
        </div>
    </div>
</template>
