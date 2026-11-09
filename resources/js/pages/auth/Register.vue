<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { store } from '@/actions/Laravel/Fortify/Http/Controllers/RegisteredUserController';
import SubmitButton from '@/components/SubmitButton.vue';
import TextField from '@/components/TextField.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { useTranslate } from '@/lib/translate';
import { login } from '@/routes';

defineOptions({ layout: GuestLayout });

const { t } = useTranslate();
</script>

<template>
    <Head :title="t('auth.register.title')" />
    <h1 class="text-xl font-semibold">{{ t('auth.register.title') }}</h1>
    <Form
        v-bind="store.form()"
        :reset-on-error="['password', 'password_confirmation']"
        class="flex flex-col gap-4"
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
        <SubmitButton :processing="processing">{{
            t('auth.register.submit')
        }}</SubmitButton>
    </Form>
    <Link :href="login()" class="text-sm underline">{{
        t('auth.register.login_link')
    }}</Link>
</template>
