<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { store } from '@/actions/Laravel/Fortify/Http/Controllers/RegisteredUserController';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import PageHeader from '@/components/PageHeader.vue';
import TextField from '@/components/TextField.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { showToast } from '@/lib/toast';
import { useTranslate } from '@/lib/translate';
import { login } from '@/routes';
import { disclaimer, privacy } from '@/routes/legal';

defineOptions({ layout: GuestLayout });

const { t } = useTranslate();

type ConsentPart = { text: string; href?: string };

/**
 * Zerlegt die Beschriftung an den Platzhaltern :privacy und :disclaimer,
 * damit die Links in jeder Sprache an der grammatisch passenden Stelle stehen.
 */
const consentParts = computed<ConsentPart[]>(() =>
    t('auth.register.consent')
        .split(/(:privacy|:disclaimer)/)
        .filter((part) => part !== '')
        .map((part) => {
            if (part === ':privacy') {
                return {
                    text: t('auth.register.consent_privacy'),
                    href: privacy().url,
                };
            }

            if (part === ':disclaimer') {
                return {
                    text: t('auth.register.consent_disclaimer'),
                    href: disclaimer().url,
                };
            }

            return { text: part };
        }),
);

function notifyFailure(errors: Record<string, string>): void {
    const message = Object.values(errors)[0];

    if (message) {
        showToast(message);
    }
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-md flex-col gap-8">
        <Head :title="t('auth.register.title')" />
        <PageHeader :title="t('auth.register.title')" />
        <Card>
            <Form
                v-bind="store.form()"
                :reset-on-error="['password', 'password_confirmation']"
                @error="notifyFailure"
                class="flex flex-col gap-5"
                #default="{ errors, processing }"
            >
                <div class="grid gap-5 sm:grid-cols-2">
                    <TextField
                        name="first_name"
                        autocomplete="given-name"
                        :label="t('auth.register.first_name')"
                        :error="errors.first_name"
                    />
                    <TextField
                        name="last_name"
                        autocomplete="family-name"
                        :label="t('auth.register.last_name')"
                        :error="errors.last_name"
                    />
                </div>
                <TextField
                    name="email"
                    type="email"
                    autocomplete="username"
                    :label="t('auth.register.email')"
                    :error="errors.email"
                />
                <TextField
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    :label="t('auth.register.password')"
                    :error="errors.password"
                />
                <TextField
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    :label="t('auth.register.password_confirmation')"
                    :error="errors.password_confirmation"
                />
                <div class="flex flex-col gap-1">
                    <label class="flex min-h-11 items-start gap-3 py-2 text-sm">
                        <input
                            type="checkbox"
                            name="consent"
                            value="1"
                            required
                            class="mt-0.5 size-5 shrink-0 accent-ink"
                            :aria-invalid="errors.consent ? 'true' : undefined"
                            :aria-describedby="
                                errors.consent ? 'consent-error' : undefined
                            "
                        />
                        <span>
                            <template
                                v-for="(part, index) in consentParts"
                                :key="index"
                            >
                                <a
                                    v-if="part.href"
                                    :href="part.href"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-link underline underline-offset-4"
                                >
                                    {{ part.text }}
                                </a>
                                <template v-else>{{ part.text }}</template>
                            </template>
                        </span>
                    </label>
                    <p
                        v-if="errors.consent"
                        id="consent-error"
                        class="text-sm text-danger"
                    >
                        {{ errors.consent }}
                    </p>
                </div>
                <Button type="submit" :processing="processing">
                    {{ t('auth.register.submit') }}
                </Button>
            </Form>
        </Card>
        <Link
            :href="login()"
            class="inline-flex min-h-11 items-center text-sm text-link underline underline-offset-4"
        >
            {{ t('auth.register.login_link') }}
        </Link>
    </div>
</template>
