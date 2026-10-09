<script setup lang="ts">
withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        compact?: boolean;
        hoverable?: boolean;
    }>(),
    {
        compact: false,
        hoverable: false,
    }
);
</script>

<template>
    <div
        :class="[
            'rounded-xl border border-border-subtle bg-surface text-content-primary transition-all shadow-sm',
            hoverable ? 'hover:border-border-strong hover:shadow-md' : '',
            compact ? 'p-4' : 'p-5 sm:p-6',
        ]"
    >
        <div
            v-if="title || $slots.header || $slots.actions"
            class="flex items-start justify-between gap-4 mb-4"
        >
            <div>
                <slot name="header">
                    <h3 v-if="title" class="text-base font-semibold tracking-tight text-content-primary">
                        {{ title }}
                    </h3>
                    <p v-if="description" class="text-xs text-content-secondary mt-0.5">
                        {{ description }}
                    </p>
                </slot>
            </div>
            <div v-if="$slots.actions" class="flex items-center gap-2 shrink-0">
                <slot name="actions" />
            </div>
        </div>

        <slot />

        <div
            v-if="$slots.footer"
            class="mt-4 pt-4 border-t border-border-subtle flex items-center justify-between"
        >
            <slot name="footer" />
        </div>
    </div>
</template>
