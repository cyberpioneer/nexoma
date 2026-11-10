<script setup lang="ts">
import SiteFooter from '@/components/SiteFooter.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import SkipLink from '@/components/SkipLink.vue';
import Toaster from '@/components/Toaster.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col pt-18">
        <SkipLink />
        <SiteHeader />
        <main
            id="main"
            tabindex="-1"
            class="layout-container flex-1 py-10 focus-visible:outline-none sm:py-16"
        >
            <slot />
        </main>
        <SiteFooter />
        <Toaster />
    </div>
</template>
