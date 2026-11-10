<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useTranslate } from '@/lib/translate';
import { disclaimer, imprint, privacy } from '@/routes/legal';

const { t } = useTranslate();
</script>

<template>
    <footer class="mt-16 border-t border-line">
        <div
            class="layout-container flex flex-col gap-3 py-8 text-sm text-muted"
        >
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase">Nexoma</p>
                <span class="text-xs font-semibold text-[#555d66]">{{
                    new Date().getFullYear()
                }}</span>
            </div>

            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <nav :aria-label="t('footer.legal')">
                    <ul class="flex flex-wrap gap-x-6">
                        <li>
                            <Link
                                :href="imprint()"
                                class="inline-flex min-h-11 items-center underline-offset-4 hover:underline"
                            >
                                {{ t('footer.imprint') }}
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="privacy()"
                                class="inline-flex min-h-11 items-center underline-offset-4 hover:underline"
                            >
                                {{ t('footer.privacy') }}
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="disclaimer()"
                                class="inline-flex min-h-11 items-center underline-offset-4 hover:underline"
                            >
                                {{ t('footer.disclaimer') }}
                            </Link>
                        </li>
                    </ul>
                </nav>

                <span class="main-in"
                    >Made with <span class="text-[#fde2e4]">&#x2764;</span> in
                    Germany</span
                >
            </div>
        </div>
    </footer>
</template>
