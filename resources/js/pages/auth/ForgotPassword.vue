<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import SubmitButton from '@/components/SubmitButton.vue';
import TextField from '@/components/TextField.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { useTranslate } from '@/lib/translate';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({ layout: GuestLayout });

const { t } = useTranslate();
const page = usePage();
</script>

<template>
    <Head :title="t('auth.forgot.title')" />
    <h1 class="text-xl font-semibold">{{ t('auth.forgot.title') }}</h1>
    <p class="text-sm">{{ t('auth.forgot.intro') }}</p>
    <p
        v-if="page.props.status"
        class="text-sm text-green-700 dark:text-green-400"
    >
        {{ page.props.status }}
    </p>
    <Form
        v-bind="email.form()"
        class="flex flex-col gap-4"
        #default="{ errors, processing }"
    >
        <TextField
            name="email"
            type="email"
            autocomplete="username"
            :label="t('auth.forgot.email')"
            :error="errors.email"
        />
        <SubmitButton :processing="processing">{{
            t('auth.forgot.submit')
        }}</SubmitButton>
    </Form>
    <Link :href="login()" class="text-sm underline">{{
        t('auth.forgot.login_link')
    }}</Link>
</template>
