<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { destroy } from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
import { useTranslate } from '@/lib/translate';

const { t } = useTranslate();
</script>

<template>
    <div class="min-h-screen">
        <header
            class="flex items-center justify-end border-b border-gray-200 p-4 dark:border-gray-700"
        >
            <Form v-bind="destroy.form()">
                <button type="submit" class="text-sm underline">
                    {{ t('nav.logout') }}
                </button>
            </Form>
        </header>
        <main class="mx-auto max-w-3xl p-4">
            <slot />
        </main>
    </div>
</template>
