<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { store } from '@/actions/Laravel/Fortify/Http/Controllers/RegisteredUserController';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import PageHeader from '@/components/PageHeader.vue';
import TextField from '@/components/TextField.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { showToast } from '@/lib/toast';
import { useTranslate } from '@/lib/translate';
import { login } from '@/routes';

defineOptions({ layout: GuestLayout });

const { t } = useTranslate();

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
                <TextField
                    name="name"
                    autocomplete="name"
                    :label="t('auth.register.name')"
                    :error="errors.name"
                />
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
