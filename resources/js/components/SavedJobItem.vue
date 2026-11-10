<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import TextareaField from '@/components/TextareaField.vue';
import { formatDate } from '@/lib/dates';
import { removeSavedJob, updateSavedJobNote } from '@/lib/savedJobs';
import { useTranslate } from '@/lib/translate';
import { show } from '@/routes/jobs';
import type { SavedJob } from '@/types';

const props = defineProps<{ job: SavedJob }>();

const { t } = useTranslate();
const page = usePage();

const NOTE_MAX_LENGTH = 1000;
const editing = ref(false);
const note = ref('');
const processing = ref(false);

function displayTitle(): string {
    return props.job.title ?? t('jobs.untitled');
}

function startEditing(): void {
    note.value = props.job.note ?? '';
    editing.value = true;
}

function saveNote(): void {
    processing.value = true;
    updateSavedJobNote(props.job.id, note.value, {
        successMessage: t('saved.toast.note_saved'),
        onSuccess: () => {
            editing.value = false;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

function remove(): void {
    processing.value = true;
    removeSavedJob(props.job.id, {
        successMessage: t('saved.toast.removed'),
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Card as="li" class="flex flex-col gap-4">
        <h2 class="text-xl break-words">
            <Link
                :href="show(job.refnr)"
                class="text-ink underline-offset-4 hover:underline"
            >
                {{ displayTitle() }}
            </Link>
        </h2>
        <dl class="grid gap-x-8 gap-y-1 text-sm sm:grid-cols-2">
            <div v-if="job.employer">
                <dt class="sr-only">{{ t('jobs.employer') }}</dt>
                <dd class="break-words">{{ job.employer }}</dd>
            </div>
            <div v-if="job.location">
                <dt class="sr-only">{{ t('jobs.location') }}</dt>
                <dd class="break-words">{{ job.location }}</dd>
            </div>
            <div v-if="job.savedAt" class="text-muted">
                <dt class="inline">{{ t('saved.saved_on') }}:</dt>
                <dd class="inline">
                    {{ formatDate(job.savedAt, page.props.locale) }}
                </dd>
            </div>
        </dl>

        <form
            v-if="editing"
            class="flex max-w-prose flex-col gap-3"
            @submit.prevent="saveNote"
        >
            <TextareaField
                v-model="note"
                :name="`note-${job.id}`"
                :label="t('saved.note.label')"
                :hint="t('saved.note.hint', { max: String(NOTE_MAX_LENGTH) })"
                :maxlength="NOTE_MAX_LENGTH"
            />
            <div class="flex flex-wrap gap-3">
                <Button
                    type="submit"
                    :processing="processing"
                    :aria-label="
                        t('saved.note.edit_label', { title: displayTitle() })
                    "
                >
                    {{ t('saved.note.save') }}
                </Button>
                <Button
                    variant="ghost"
                    :disabled="processing"
                    @click="editing = false"
                >
                    {{ t('saved.cancel') }}
                </Button>
            </div>
        </form>

        <template v-else>
            <p
                v-if="job.note"
                class="max-w-prose rounded-md bg-surface p-4 break-words whitespace-pre-line"
            >
                {{ job.note }}
            </p>
            <p v-else class="text-sm text-muted">{{ t('saved.note.none') }}</p>
            <div class="flex flex-wrap gap-3">
                <Button
                    variant="secondary"
                    :disabled="processing"
                    :aria-label="
                        t('saved.note.edit_label', { title: displayTitle() })
                    "
                    @click="startEditing"
                >
                    {{ job.note ? t('saved.note.edit') : t('saved.note.add') }}
                </Button>
                <Button
                    variant="ghost"
                    :processing="processing"
                    :aria-label="
                        t('saved.remove_label', { title: displayTitle() })
                    "
                    @click="remove"
                >
                    {{ t('saved.remove') }}
                </Button>
            </div>
        </template>
    </Card>
</template>
