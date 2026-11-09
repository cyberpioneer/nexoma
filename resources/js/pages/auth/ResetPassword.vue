<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SubmitButton from '@/components/SubmitButton.vue';
import TextField from '@/components/TextField.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { useTranslate } from '@/lib/translate';
import { update } from '@/routes/password';

defineOptions({ layout: GuestLayout });

defineProps<{ token: string; email: string }>();

const { t } = useTranslate();
</script>

<template>
    <Head :title="t('auth.reset.title')">
        <meta name="referrer" content="no-referrer" />
    </Head>
    <h1 class="text-xl font-semibold">{{ t('auth.reset.title') }}</h1>
    <Form
        v-bind="update.form()"
        :reset-on-error="['password', 'password_confirmation']"
        class="flex flex-col gap-4"
        #default="{ errors, processing }"
    >
        <input type="hidden" name="token" :value="token" />
        <TextField
            name="email"
            type="email"
            autocomplete="username"
            :value="email"
            :label="t('auth.reset.email')"
            :error="errors.email"
        />
        <TextField
            name="password"
            type="password"
            autocomplete="new-password"
            :label="t('auth.reset.password')"
            :error="errors.password"
        />
        <TextField
            name="password_confirmation"
            type="password"
            autocomplete="new-password"
            :label="t('auth.reset.password_confirmation')"
            :error="errors.password_confirmation"
        />
        <SubmitButton :processing="processing">{{
            t('auth.reset.submit')
        }}</SubmitButton>
    </Form>
</template>
