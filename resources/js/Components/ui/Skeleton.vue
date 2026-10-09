<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?: 'text' | 'circle' | 'rectangle';
        width?: string;
        height?: string;
        className?: string;
    }>(),
    {
        variant: 'rectangle',
        width: 'w-full',
        height: 'h-4',
        className: '',
    }
);
</script>

<template>
    <div
        :class="[
            'animate-pulse bg-surface-subdued border border-border-subtle/50',
            variant === 'circle' ? 'rounded-full' : 'rounded-lg',
            width,
            height,
            className,
        ]"
        aria-hidden="true"
    />
</template>
