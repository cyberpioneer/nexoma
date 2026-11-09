<script setup lang="ts">
import { useTranslate } from '@/lib/translate';

const { t } = useTranslate();
</script>

<template>
    <footer class="mt-16 border-t border-line">
        <div class="layout-container py-8 text-sm text-muted">
            <p>
                <span class="font-semibold text-ink">Nexora</span>
                · {{ t('footer.note') }}
            </p>
        </div>
    </footer>
</template>
