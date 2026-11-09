<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { update } from '@/routes/locale';

const page = usePage();
</script>

<template>
    <ul class="flex flex-col">
        <li v-for="option in page.props.availableLocales" :key="option.code">
            <Form v-bind="update.form()" :options="{ preserveScroll: true }">
                <input type="hidden" name="locale" :value="option.code" />
                <button
                    type="submit"
                    :lang="option.code"
                    :aria-current="
                        option.code === page.props.locale ? 'true' : undefined
                    "
                    class="flex min-h-11 w-full items-center justify-between gap-3 rounded-md px-3 text-left hover:bg-surface aria-[current=true]:bg-surface aria-[current=true]:font-semibold"
                >
                    {{ option.name }}
                    <svg
                        v-if="option.code === page.props.locale"
                        aria-hidden="true"
                        viewBox="0 0 20 20"
                        class="size-4 shrink-0"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="m4 10.5 4 4 8-9" />
                    </svg>
                </button>
            </Form>
        </li>
    </ul>
</template>
