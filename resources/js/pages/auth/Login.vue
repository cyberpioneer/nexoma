<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { store } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
import SubmitButton from '@/components/SubmitButton.vue';
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
    <Head :title="t('auth.login.title')" />
    <h1 class="text-xl font-semibold">{{ t('auth.login.title') }}</h1>
    <p
        v-if="page.props.status"
        class="text-sm text-green-700 dark:text-green-400"
    >
        {{ page.props.status }}
    </p>
    <Form
        v-bind="store.form()"
        :reset-on-error="['password']"
        class="flex flex-col gap-4"
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
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" />
            {{ t('auth.login.remember') }}
        </label>
        <SubmitButton :processing="processing">{{
            t('auth.login.submit')
        }}</SubmitButton>
    </Form>
    <div class="flex flex-col gap-1 text-sm">
        <Link :href="request()" class="underline">{{
            t('auth.login.forgot_link')
        }}</Link>
        <Link :href="register()" class="underline">{{
            t('auth.login.register_link')
        }}</Link>
    </div>
</template>
