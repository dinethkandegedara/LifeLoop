<script setup lang="ts">
import { computed } from 'vue';

export type BadgeVariant =
    | 'planned'
    | 'in_progress'
    | 'completed'
    | 'overdue'
    | 'extra'
    | 'neutral';

const props = withDefaults(
    defineProps<{
        variant?: BadgeVariant;
        size?: 'sm' | 'md';
        dot?: boolean;
    }>(),
    {
        variant: 'neutral',
        size: 'sm',
        dot: false,
    }
);

const badgeClasses = computed(() => {
    const variants = {
        planned:
            'bg-accent-subdued text-accent border border-accent/20',
        in_progress:
            'bg-status-warning-subdued text-status-warning border border-status-warning/20',
        completed:
            'bg-status-success-subdued text-status-success border border-status-success/20',
        overdue:
            'bg-status-danger-subdued text-status-danger border border-status-danger/20',
        extra:
            'bg-primary-subdued text-primary border border-primary/20',
        neutral:
            'bg-surface-subdued text-content-secondary border border-border-subtle',
    };

    const sizes = {
        sm: 'px-2 py-0.5 text-xs',
        md: 'px-2.5 py-1 text-xs',
    };

    return [
        'inline-flex items-center gap-1.5 font-medium rounded-full select-none tracking-tight',
        variants[props.variant],
        sizes[props.size],
    ].join(' ');
});

const dotColor = computed(() => {
    const colors = {
        planned: 'bg-accent',
        in_progress: 'bg-status-warning animate-pulse',
        completed: 'bg-status-success',
        overdue: 'bg-status-danger',
        extra: 'bg-primary',
        neutral: 'bg-content-muted',
    };
    return colors[props.variant];
});
</script>

<template>
    <span :class="badgeClasses">
        <span
            v-if="dot"
            :class="['w-1.5 h-1.5 rounded-full shrink-0', dotColor]"
            aria-hidden="true"
        />
        <slot />
    </span>
</template>
