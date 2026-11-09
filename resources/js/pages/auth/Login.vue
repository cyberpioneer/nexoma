<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { store } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import PageHeader from '@/components/PageHeader.vue';
import TextField from '@/components/TextField.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { useTranslate } from '@/lib/translate';
import { register } from '@/routes';
import { request } from '@/routes/password';

defineOptions({ layout: GuestLayout });

const { t } = useTranslate();
const page = usePage();
</script>

<template>
    <div class="mx-auto flex w-full max-w-md flex-col gap-8">
        <Head :title="t('auth.login.title')" />
        <PageHeader :title="t('auth.login.title')" />
        <p v-if="page.props.status" class="text-sm text-success">
            {{ page.props.status }}
        </p>
        <Card>
            <Form
                v-bind="store.form()"
                :reset-on-error="['password']"
                class="flex flex-col gap-5"
                #default="{ errors, processing }"
            >
                <TextField
                    name="email"
                    type="email"
                    autocomplete="username"
                    :label="t('auth.login.email')"
                    :error="errors.email"
                />
                <TextField
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    :label="t('auth.login.password')"
                    :error="errors.password"
                />
                <label class="flex min-h-11 items-center gap-3 text-sm">
                    <input
                        type="checkbox"
                        name="remember"
                        class="size-5 accent-ink"
                    />
                    {{ t('auth.login.remember') }}
                </label>
                <Button type="submit" :processing="processing">
                    {{ t('auth.login.submit') }}
                </Button>
            </Form>
        </Card>
        <ul class="flex flex-col text-sm">
            <li>
                <Link
                    :href="request()"
                    class="inline-flex min-h-11 items-center text-link underline underline-offset-4"
                >
                    {{ t('auth.login.forgot_link') }}
                </Link>
            </li>
            <li>
                <Link
                    :href="register()"
                    class="inline-flex min-h-11 items-center text-link underline underline-offset-4"
                >
                    {{ t('auth.login.register_link') }}
                </Link>
            </li>
        </ul>
    </div>
</template>
