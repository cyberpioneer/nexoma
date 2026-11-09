<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { useTranslate } from '@/lib/translate';

type LegalBlock =
    | { type: 'h1' | 'h2'; text: string }
    | { type: 'p'; lines: string[] }
    | { type: 'ul'; items: string[] };

const props = defineProps<{
    document: 'imprint' | 'privacy' | 'disclaimer';
    blocks: LegalBlock[];
}>();

defineOptions({ layout: GuestLayout });

const { t } = useTranslate();
const title = computed(() => t(`footer.${props.document}`));
</script>

<template>
    <article class="max-w-3xl">
        <Head :title="title" />
        <p class="mb-6 text-sm text-muted">{{ t('legal.german_only') }}</p>
        <div lang="de" class="flex flex-col gap-4">
            <template v-for="(block, index) in blocks" :key="index">
                <h1 v-if="block.type === 'h1'" class="text-3xl sm:text-4xl">
                    {{ block.text }}
                </h1>
                <h2 v-else-if="block.type === 'h2'" class="mt-4 text-xl">
                    {{ block.text }}
                </h2>
                <p v-else-if="block.type === 'p'">
                    <template
                        v-for="(line, lineIndex) in block.lines"
                        :key="lineIndex"
                    >
                        <br v-if="lineIndex > 0" />
                        {{ line }}
                    </template>
                </p>
                <ul
                    v-else-if="block.type === 'ul'"
                    class="list-disc space-y-1 pl-6"
                >
                    <li
                        v-for="(item, itemIndex) in block.items"
                        :key="itemIndex"
                    >
                        {{ item }}
                    </li>
                </ul>
            </template>
        </div>
    </article>
</template>
