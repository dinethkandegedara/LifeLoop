<script setup lang="ts">
import Icon from './Icon.vue';

export interface TabItem {
    id: string;
    label: string;
    icon?: string;
    count?: number;
}

defineProps<{
    modelValue: string;
    tabs: TabItem[];
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', id: string): void;
}>();
</script>

<template>
    <div class="border-b border-border-subtle">
        <nav class="flex space-x-6 -mb-px overflow-x-auto no-scrollbar" aria-label="Tabs">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                @click="emit('update:modelValue', tab.id)"
                :class="[
                    'group inline-flex items-center gap-2 py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors cursor-pointer',
                    modelValue === tab.id
                        ? 'border-primary text-primary font-semibold'
                        : 'border-transparent text-content-secondary hover:text-content-primary hover:border-border-strong',
                ]"
            >
                <Icon
                    v-if="tab.icon"
                    :name="tab.icon"
                    :size="16"
                    :class="modelValue === tab.id ? 'text-primary' : 'text-content-muted group-hover:text-content-secondary'"
                />
                <span>{{ tab.label }}</span>
                <span
                    v-if="tab.count !== undefined"
                    :class="[
                        'rounded-full px-2 py-0.5 text-xs font-semibold',
                        modelValue === tab.id
                            ? 'bg-primary-subdued text-primary'
                            : 'bg-surface-subdued text-content-secondary',
                    ]"
                >
                    {{ tab.count }}
                </span>
            </button>
        </nav>
    </div>
</template>
