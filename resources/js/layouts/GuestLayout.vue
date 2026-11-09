<script setup lang="ts">
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
</script>

<template>
    <main class="flex min-h-screen items-center justify-center p-4">
        <div class="flex w-full max-w-sm flex-col gap-6">
            <slot />
            <LocaleSwitcher />
        </div>
    </main>
</template>
