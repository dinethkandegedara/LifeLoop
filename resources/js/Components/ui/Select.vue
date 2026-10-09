<script setup lang="ts">
import { computed } from 'vue';
import Icon from './Icon.vue';

export interface SelectOption {
    label: string;
    value: string | number;
    disabled?: boolean;
}

const props = withDefaults(
    defineProps<{
        modelValue?: string | number;
        options: SelectOption[];
        id?: string;
        label?: string;
        placeholder?: string;
        error?: string;
        hint?: string;
        disabled?: boolean;
        required?: boolean;
    }>(),
    {
        modelValue: '',
        placeholder: 'Select an option',
        disabled: false,
        required: false,
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string | number): void;
}>();

const selectId = computed(() => props.id || `select-${Math.random().toString(36).substring(2, 7)}`);

function onChange(event: Event) {
    const target = event.target as HTMLSelectElement;
    emit('update:modelValue', target.value);
}
</script>

<template>
    <div class="w-full space-y-1.5">
        <label
            v-if="label"
            :for="selectId"
            class="block text-xs font-medium text-content-secondary"
        >
            {{ label }}
            <span v-if="required" class="text-status-danger">*</span>
        </label>

        <div class="relative flex items-center">
            <select
                :id="selectId"
                :value="modelValue"
                :disabled="disabled"
                :required="required"
                @change="onChange"
                :class="[
                    'w-full h-10 rounded-lg text-sm transition-colors appearance-none pr-9 pl-3 cursor-pointer',
                    'bg-surface text-content-primary',
                    'border border-border-subtle focus:border-primary',
                    'focus:outline-none focus:ring-2 focus:ring-primary/20',
                    'disabled:opacity-50 disabled:bg-surface-subdued disabled:cursor-not-allowed',
                    error ? 'border-status-danger focus:border-status-danger focus:ring-status-danger/20' : '',
                ]"
            >
                <option v-if="placeholder" value="" disabled :selected="!modelValue">
                    {{ placeholder }}
                </option>
                <option
                    v-for="opt in options"
                    :key="opt.value"
                    :value="opt.value"
                    :disabled="opt.disabled"
                >
                    {{ opt.label }}
                </option>
            </select>

            <span class="absolute right-3 pointer-events-none text-content-muted">
                <Icon name="chevron-down" :size="14" />
            </span>
        </div>

        <p v-if="error" class="text-xs text-status-danger">
            {{ error }}
        </p>
        <p v-else-if="hint" class="text-xs text-content-muted">
            {{ hint }}
        </p>
    </div>
</template>
