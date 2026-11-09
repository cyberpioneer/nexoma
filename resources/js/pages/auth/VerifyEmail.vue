<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { destroy } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
import SubmitButton from '@/components/SubmitButton.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { useTranslate } from '@/lib/translate';
import { send } from '@/routes/verification';

defineOptions({ layout: GuestLayout });

const { t } = useTranslate();
const page = usePage();
</script>

<template>
    <Head :title="t('auth.verify.title')" />
    <h1 class="text-xl font-semibold">{{ t('auth.verify.title') }}</h1>
    <p class="text-sm">{{ t('auth.verify.intro') }}</p>
    <p
        v-if="page.props.status === 'verification-link-sent'"
        class="text-sm text-green-700 dark:text-green-400"
    >
        {{ t('auth.verify.link_sent') }}
    </p>
    <Form v-bind="send.form()" #default="{ processing }">
        <SubmitButton :processing="processing">{{
            t('auth.verify.resend')
        }}</SubmitButton>
    </Form>
    <Form v-bind="destroy.form()">
        <button type="submit" class="text-sm underline">
            {{ t('nav.logout') }}
        </button>
    </Form>
</template>
