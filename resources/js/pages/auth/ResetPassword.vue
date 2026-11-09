<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import PageHeader from '@/components/PageHeader.vue';
import TextField from '@/components/TextField.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { useTranslate } from '@/lib/translate';
import { update } from '@/routes/password';

defineOptions({ layout: GuestLayout });

defineProps<{ token: string; email: string }>();

const { t } = useTranslate();
</script>

<template>
    <div class="mx-auto flex w-full max-w-md flex-col gap-8">
        <Head :title="t('auth.reset.title')">
            <meta name="referrer" content="no-referrer" />
        </Head>
        <PageHeader :title="t('auth.reset.title')" />
        <Card>
            <Form
                v-bind="update.form()"
                :reset-on-error="['password', 'password_confirmation']"
                class="flex flex-col gap-5"
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
                <Button type="submit" :processing="processing">
                    {{ t('auth.reset.submit') }}
                </Button>
            </Form>
        </Card>
    </div>
</template>
