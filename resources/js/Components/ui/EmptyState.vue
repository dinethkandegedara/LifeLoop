<script setup lang="ts">
import Icon from './Icon.vue';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: string;
    }>(),
    {
        icon: 'calendar',
    }
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center text-center p-8 sm:p-12 rounded-2xl border border-dashed border-border-subtle bg-surface-subdued/50"
    >
        <div
            class="w-12 h-12 rounded-2xl bg-surface border border-border-subtle flex items-center justify-center text-content-muted shadow-xs mb-3"
        >
            <Icon :name="icon" :size="22" />
        </div>

        <h4 class="text-sm font-semibold text-content-primary">
            {{ title }}
        </h4>
        <p v-if="description" class="text-xs text-content-secondary mt-1 max-w-sm leading-relaxed">
            {{ description }}
        </p>

        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>
