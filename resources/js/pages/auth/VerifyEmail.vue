<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { destroy } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import PageHeader from '@/components/PageHeader.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { useTranslate } from '@/lib/translate';
import { send } from '@/routes/verification';

defineOptions({ layout: GuestLayout });

const { t } = useTranslate();
const page = usePage();
</script>

<template>
    <div class="mx-auto flex w-full max-w-md flex-col gap-8">
        <Head :title="t('auth.verify.title')" />
        <PageHeader
            :title="t('auth.verify.title')"
            :lead="t('auth.verify.intro')"
        />
        <p
            v-if="page.props.status === 'verification-link-sent'"
            class="text-sm text-success"
        >
            {{ t('auth.verify.link_sent') }}
        </p>
        <Card class="flex flex-col gap-3">
            <Form v-bind="send.form()" #default="{ processing }">
                <Button type="submit" :processing="processing">
                    {{ t('auth.verify.resend') }}
                </Button>
            </Form>
            <Form v-bind="destroy.form()">
                <Button type="submit" variant="ghost">
                    {{ t('nav.logout') }}
                </Button>
            </Form>
        </Card>
    </div>
</template>
