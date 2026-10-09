<script setup lang="ts">
export interface SegmentOption {
    label: string;
    value: string;
    icon?: string;
}

const props = defineProps<{
    modelValue: string;
    options: SegmentOption[];
    size?: 'sm' | 'md';
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();
</script>

<template>
    <div
        :class="[
            'inline-flex items-center p-1 rounded-xl bg-surface-subdued border border-border-subtle select-none',
            size === 'sm' ? 'text-xs' : 'text-sm',
        ]"
        role="tablist"
    >
        <button
            v-for="opt in options"
            :key="opt.value"
            type="button"
            role="tab"
            :aria-selected="modelValue === opt.value"
            @click="emit('update:modelValue', opt.value)"
            :class="[
                'inline-flex items-center justify-center font-medium rounded-lg transition-all cursor-pointer gap-1.5',
                size === 'sm' ? 'px-2.5 py-1 min-h-[28px]' : 'px-3.5 py-1.5 min-h-[34px]',
                modelValue === opt.value
                    ? 'bg-surface text-content-primary shadow-xs font-semibold'
                    : 'text-content-secondary hover:text-content-primary',
            ]"
        >
            <span v-if="opt.icon" class="text-current">
                <!-- Inline icon if provided -->
            </span>
            <span>{{ opt.label }}</span>
        </button>
    </div>
</template>
