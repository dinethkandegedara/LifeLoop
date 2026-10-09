<script setup lang="ts">
import { computed } from 'vue';
import Icon from './Icon.vue';

export type IconButtonVariant = 'ghost' | 'secondary' | 'subtle' | 'primary';
export type IconButtonSize = 'sm' | 'md' | 'lg';

const props = withDefaults(
    defineProps<{
        icon: string;
        label: string;
        variant?: IconButtonVariant;
        size?: IconButtonSize;
        disabled?: boolean;
        type?: 'button' | 'submit' | 'reset';
    }>(),
    {
        variant: 'ghost',
        size: 'md',
        disabled: false,
        type: 'button',
    }
);

const classes = computed(() => {
    const base = [
        'inline-flex items-center justify-center rounded-lg transition-colors cursor-pointer select-none',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary',
        'disabled:opacity-50 disabled:cursor-not-allowed',
    ];

    const sizes = {
        sm: 'w-8 h-8 text-xs',
        md: 'w-9 h-9 text-sm',
        lg: 'w-10 h-10 text-base',
    };

    const variants = {
        ghost: 'text-content-secondary hover:text-content-primary hover:bg-surface-hover active:bg-surface-active',
        secondary: 'bg-surface text-content-primary border border-border-subtle hover:bg-surface-hover shadow-sm',
        subtle: 'bg-primary-subdued text-primary hover:bg-primary/20',
        primary: 'bg-primary text-primary-text hover:bg-primary-hover shadow-sm',
    };

    return [...base, sizes[props.size], variants[props.variant]].join(' ');
});
</script>

<template>
    <button
        :type="type"
        :class="classes"
        :disabled="disabled"
        :aria-label="label"
        :title="label"
    >
        <Icon :name="icon" :size="size === 'sm' ? 14 : size === 'lg' ? 20 : 16" />
    </button>
</template>
