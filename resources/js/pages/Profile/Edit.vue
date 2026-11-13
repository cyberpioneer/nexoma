<script setup lang="ts">
import { Form, Head, useHttp, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { store } from '@/actions/App/Http/Controllers/CareerEntryController';
import {
    optimizeBio,
    update,
} from '@/actions/App/Http/Controllers/CareerProfileController';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import CareerEntryFields from '@/components/CareerEntryFields.vue';
import CareerEntryItem from '@/components/CareerEntryItem.vue';
import PageHeader from '@/components/PageHeader.vue';
import SelectField from '@/components/SelectField.vue';
import TextField from '@/components/TextField.vue';
import TextareaField from '@/components/TextareaField.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { showToast } from '@/lib/toast';
import { useTranslate } from '@/lib/translate';
import type { CareerEntry, CareerProfile } from '@/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    profile: CareerProfile;
    careerEntries: CareerEntry[];
    maxCareerEntries: number;
    maxBioLength: number;
    searchRadii: number[];
}>();

const { t } = useTranslate();
const page = usePage();

const bio = ref(props.profile.bio ?? '');

const languageOptions = computed(() =>
    page.props.availableLocales.map((locale) => ({
        value: locale.code,
        label: locale.name,
    })),
);

const radiusOptions = computed(() => [
    { value: '', label: t('profile.radius.none') },
    ...props.searchRadii.map((km) => ({
        value: String(km),
        label: t('profile.radius.option', { km: String(km) }),
    })),
]);

const selectedLanguage = computed(
    () => props.profile.preferred_language ?? page.props.locale,
);

const canAddEntry = computed(
    () => props.careerEntries.length < props.maxCareerEntries,
);

/** Gewählte Sprache; das Auswahlfeld meldet Änderungen über das Browser-Ereignis `change`. */
const language = ref(selectedLanguage.value);

function rememberLanguage(event: Event): void {
    language.value = (event.target as HTMLSelectElement).value;
}

const MIN_BIO_LENGTH_FOR_AI = 10;

/** KI-Optimierung: sendet Text und Sprache, übernimmt die Antwort nur ins Formularfeld (gespeichert wird erst über „Profil speichern“). */
const optimizer = useHttp<
    { bio: string; preferred_language: string },
    { bio: string }
>(optimizeBio(), { bio: '', preferred_language: '' });

async function optimizeWithAi(): Promise<void> {
    if (bio.value.trim().length < MIN_BIO_LENGTH_FOR_AI) {
        showToast(t('profile.ai.too_short'));

        return;
    }

    optimizer.bio = bio.value;
    optimizer.preferred_language = language.value;

    try {
        await optimizer.submit({
            onSuccess: (response) => {
                bio.value = response.bio;
                showToast(t('profile.ai.done'), 'success');
            },
            onError: (errors) => {
                showToast(Object.values(errors)[0] ?? t('profile.ai.error'));
            },
            onHttpException: () => {
                showToast(t('profile.ai.error'));
            },
            onNetworkError: () => {
                showToast(t('profile.ai.error'));
            },
        });
    } catch {
        // Fehler wurden bereits als Meldung angezeigt.
    }
}

/** Das Formular für einen neuen Eintrag wird nach dem Speichern zurückgesetzt und neu aufgebaut. */
const newEntryKey = ref(0);

function onProfileSaved(): void {
    showToast(t('profile.toast.saved'), 'success');
}

function onEntryAdded(): void {
    newEntryKey.value++;
    showToast(t('profile.toast.entry_saved'), 'success');
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-10">
        <Head :title="t('profile.title')" />

        <PageHeader :title="t('profile.title')" :lead="t('profile.lead')" />

        <Card as="section" aria-labelledby="profile-heading">
            <h2 id="profile-heading" class="mb-6 text-2xl">
                {{ t('profile.section.profile') }}
            </h2>
            <Form
                v-bind="update.form()"
                :options="{ preserveScroll: true }"
                class="flex flex-col gap-5"
                #default="{ errors, processing }"
                @success="onProfileSaved"
            >
                <TextareaField
                    v-model="bio"
                    name="bio"
                    :rows="5"
                    :maxlength="maxBioLength"
                    :label="t('profile.bio.label')"
                    :hint="
                        t('profile.bio.hint') +
                        ' ' +
                        t('profile.bio.counter', {
                            count: String(bio.length),
                            max: String(maxBioLength),
                        })
                    "
                    :error="errors.bio"
                />
                <div class="flex flex-col gap-2">
                    <div>
                        <Button
                            variant="secondary"
                            :processing="optimizer.processing"
                            @click="optimizeWithAi"
                        >
                            {{ t('profile.ai.optimize') }}
                        </Button>
                    </div>
                    <p class="text-sm text-muted">
                        {{ t('profile.ai.notice') }}
                    </p>
                </div>
                <SelectField
                    name="preferred_language"
                    :label="t('profile.language.label')"
                    :options="languageOptions"
                    :value="selectedLanguage"
                    :error="errors.preferred_language"
                    @change="rememberLanguage"
                />
                <div class="grid gap-5 sm:grid-cols-2">
                    <TextField
                        name="search_location"
                        autocomplete="address-level2"
                        :required="false"
                        :label="t('profile.location.label')"
                        :value="profile.search_location ?? undefined"
                        :error="errors.search_location"
                    />
                    <SelectField
                        name="search_radius"
                        :label="t('profile.radius.label')"
                        :options="radiusOptions"
                        :value="
                            profile.search_radius === null
                                ? ''
                                : String(profile.search_radius)
                        "
                        :error="errors.search_radius"
                    />
                </div>
                <div>
                    <Button type="submit" :processing="processing">
                        {{ t('profile.save') }}
                    </Button>
                </div>
            </Form>
        </Card>

        <section aria-labelledby="entries-heading" class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <h2 id="entries-heading" class="text-2xl">
                    {{ t('profile.entries.title') }}
                </h2>
                <p class="max-w-2xl text-muted">
                    {{ t('profile.entries.lead') }}
                </p>
            </div>

            <ul v-if="careerEntries.length > 0" class="flex flex-col gap-4">
                <CareerEntryItem
                    v-for="entry in careerEntries"
                    :key="entry.id"
                    :entry="entry"
                />
            </ul>
            <p v-else class="text-muted">{{ t('profile.entries.empty') }}</p>

            <Card
                v-if="canAddEntry"
                as="section"
                aria-labelledby="new-entry-heading"
            >
                <h3
                    id="new-entry-heading"
                    class="mb-6 font-sans text-lg font-semibold"
                >
                    {{ t('profile.entry.add_title') }}
                </h3>
                <Form
                    :key="newEntryKey"
                    v-bind="store.form()"
                    :options="{ preserveScroll: true }"
                    class="flex flex-col gap-5"
                    #default="{ errors, processing }"
                    @success="onEntryAdded"
                >
                    <CareerEntryFields :errors="errors" id-prefix="new-entry" />
                    <div>
                        <Button type="submit" :processing="processing">
                            {{ t('profile.entry.add') }}
                        </Button>
                    </div>
                </Form>
            </Card>
            <p v-else class="text-sm text-muted">
                {{
                    t('profile.entry.limit', { max: String(maxCareerEntries) })
                }}
            </p>
        </section>
    </div>
</template>
