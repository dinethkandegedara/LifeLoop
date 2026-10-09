<script setup lang="ts">
defineProps<{
    title: string;
    subtitle?: string;
}>();
</script>

<template>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 pb-2">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-content-primary">
                {{ title }}
            </h1>
            <p v-if="subtitle" class="text-sm text-content-secondary mt-1">
                {{ subtitle }}
            </p>
        </div>

        <div v-if="$slots.actions" class="flex items-center gap-3 shrink-0">
            <slot name="actions" />
        </div>
    </div>
</template>
