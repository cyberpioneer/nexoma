<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import { useTranslate } from '@/lib/translate';

type LinkTarget = string | { url: string; method: 'get' };

const props = withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'ghost';
        size?: 'md' | 'lg';
        type?: 'button' | 'submit' | 'reset';
        /** Wenn gesetzt, wird ein Link statt eines Buttons ausgegeben. */
        href?: LinkTarget;
        disabled?: boolean;
        processing?: boolean;
    }>(),
    {
        variant: 'primary',
        size: 'md',
        type: 'button',
        href: undefined,
        disabled: false,
        processing: false,
    },
);

const { t } = useTranslate();

const variantClasses = {
    primary: 'bg-ink text-white hover:bg-ink-soft',
    secondary: 'border border-line-strong bg-white text-ink hover:bg-surface',
    ghost: 'text-ink hover:bg-surface',
};

const sizeClasses = {
    md: 'min-h-11 px-4 text-sm',
    lg: 'min-h-12 px-6 text-base',
};

const classes = computed(() =>
    cn(
        'inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors',
        'disabled:cursor-not-allowed disabled:opacity-50',
        variantClasses[props.variant],
        sizeClasses[props.size],
    ),
);
</script>

<template>
    <Link v-if="href" :href="href" :class="classes">
        <slot />
    </Link>
    <button
        v-else
        :type="type"
        :disabled="disabled || processing"
        :aria-busy="processing ? 'true' : undefined"
        :class="classes"
    >
        <template v-if="processing">{{ t('common.processing') }}</template>
        <slot v-else />
    </button>
</template>
