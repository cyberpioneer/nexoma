<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import PageHeader from '@/components/PageHeader.vue';
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
    <div class="mx-auto flex w-full max-w-md flex-col gap-8">
        <Head :title="t('auth.forgot.title')" />
        <PageHeader
            :title="t('auth.forgot.title')"
            :lead="t('auth.forgot.intro')"
        />
        <p v-if="page.props.status" class="text-sm text-success">
            {{ page.props.status }}
        </p>
        <Card>
            <Form
                v-bind="email.form()"
                class="flex flex-col gap-5"
                #default="{ errors, processing }"
            >
                <TextField
                    name="email"
                    type="email"
                    autocomplete="username"
                    :label="t('auth.forgot.email')"
                    :error="errors.email"
                />
                <Button type="submit" :processing="processing">
                    {{ t('auth.forgot.submit') }}
                </Button>
            </Form>
        </Card>
        <Link
            :href="login()"
            class="inline-flex min-h-11 items-center text-sm text-link underline underline-offset-4"
        >
            {{ t('auth.forgot.login_link') }}
        </Link>
    </div>
</template>
