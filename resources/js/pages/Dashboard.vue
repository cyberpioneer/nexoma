<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import Button from '@/components/Button.vue';
import Card from '@/components/Card.vue';
import PageHeader from '@/components/PageHeader.vue';
import TextField from '@/components/TextField.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslate } from '@/lib/translate';
import { index } from '@/routes/jobs';

defineOptions({ layout: AppLayout });

const { t } = useTranslate();
const page = usePage();
</script>

<template>
    <div class="flex flex-col gap-10">
        <Head :title="t('dashboard.title')" />
        <PageHeader
            :title="t('dashboard.title')"
            :lead="
                t('dashboard.welcome', {
                    name: page.props.auth.user?.name ?? '',
                })
            "
        />
        <Card tone="sky" class="max-w-xl">
            <h2 class="mb-4 text-2xl">{{ t('dashboard.search.title') }}</h2>
            <Form
                v-bind="index.form()"
                class="flex flex-col gap-5"
                #default="{ processing }"
            >
                <TextField name="was" :label="t('jobs.field.was')" />
                <div>
                    <Button type="submit" :processing="processing">
                        {{ t('jobs.search') }}
                    </Button>
                </div>
            </Form>
        </Card>
    </div>
</template>
