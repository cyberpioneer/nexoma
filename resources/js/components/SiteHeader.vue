<script setup lang="ts">
import SiteMenu from '@/components/SiteMenu.vue';
import Wordmark from '@/components/Wordmark.vue';
</script>

<template>
    <header class="fixed inset-x-0 top-0 z-40 bg-white shadow-header">
        <div
            class="layout-container flex h-18 items-center justify-between gap-6"
        >
            <Wordmark />
            <SiteMenu />
        </div>
    </header>
</template>
