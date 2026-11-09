<script setup lang="ts">
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import SiteFooter from '@/components/SiteFooter.vue';
import SkipLink from '@/components/SkipLink.vue';
import Wordmark from '@/components/Wordmark.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <SkipLink />
        <header class="border-b border-line">
            <div
                class="layout-container flex flex-wrap items-center justify-between gap-x-6 gap-y-2 py-3"
            >
                <Wordmark />
                <LocaleSwitcher />
            </div>
        </header>
        <main
            id="main"
            tabindex="-1"
            class="layout-container flex-1 py-10 focus-visible:outline-none sm:py-16"
        >
            <slot />
        </main>
        <SiteFooter />
    </div>
</template>
