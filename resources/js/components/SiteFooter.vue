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
            <p>
                <span class="font-semibold text-ink">Nexoma</span>
                · {{ t('footer.note') }}
            </p>
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
        </div>
    </footer>
</template>
