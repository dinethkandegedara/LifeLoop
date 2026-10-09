<script setup lang="ts">
import { computed } from 'vue';
import Icon from './Icon.vue';

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'subtle';
export type ButtonSize = 'sm' | 'md' | 'lg';

const props = withDefaults(
    defineProps<{
        variant?: ButtonVariant;
        size?: ButtonSize;
        disabled?: boolean;
        loading?: boolean;
        type?: 'button' | 'submit' | 'reset';
        icon?: string;
        iconRight?: string;
    }>(),
    {
        variant: 'primary',
        size: 'md',
        disabled: false,
        loading: false,
        type: 'button',
    }
);

const classes = computed(() => {
    const base = [
        'inline-flex items-center justify-center font-medium transition-colors select-none rounded-lg cursor-pointer',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary',
        'disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none',
    ];

    // Sizes
    const sizes = {
        sm: 'h-8 px-3 text-xs gap-1.5 min-h-[32px]',
        md: 'h-10 px-4 text-sm gap-2 min-h-[40px]',
        lg: 'h-11 px-5 text-base gap-2.5 min-h-[44px]',
    };

    // Variants
    const variants = {
        primary:
            'bg-primary text-primary-text hover:bg-primary-hover active:bg-primary-active shadow-sm font-semibold',
        secondary:
            'bg-surface text-content-primary border border-border-subtle hover:bg-surface-hover active:bg-surface-active shadow-sm',
        subtle:
            'bg-primary-subdued text-primary hover:bg-primary/20 active:bg-primary/25',
        ghost:
            'text-content-secondary hover:text-content-primary hover:bg-surface-hover active:bg-surface-active',
        danger:
            'bg-status-danger text-white hover:bg-status-danger/90 active:bg-status-danger/80 shadow-sm font-semibold',
    };

    return [...base, sizes[props.size], variants[props.variant]].join(' ');
});
</script>

<template>
    <button :type="type" :class="classes" :disabled="disabled || loading">
        <Icon
            v-if="loading"
            name="loader"
            :size="size === 'sm' ? 14 : 16"
            class="animate-spin"
        />
        <Icon
            v-else-if="icon"
            :name="icon"
            :size="size === 'sm' ? 14 : 16"
        />
        
        <slot />

        <Icon
            v-if="iconRight && !loading"
            :name="iconRight"
            :size="size === 'sm' ? 14 : 16"
        />
    </button>
</template>
