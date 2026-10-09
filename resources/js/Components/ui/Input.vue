<script setup lang="ts">
import { computed } from 'vue';
import Icon from './Icon.vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string | number;
        id?: string;
        label?: string;
        type?: string;
        placeholder?: string;
        error?: string;
        hint?: string;
        disabled?: boolean;
        required?: boolean;
        icon?: string;
    }>(),
    {
        modelValue: '',
        type: 'text',
        placeholder: '',
        disabled: false,
        required: false,
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string | number): void;
}>();

const inputId = computed(() => props.id || `input-${Math.random().toString(36).substring(2, 7)}`);

function onInput(event: Event) {
    const target = event.target as HTMLInputElement;
    emit('update:modelValue', target.value);
}
</script>

<template>
    <div class="w-full space-y-1.5">
        <label
            v-if="label"
            :for="inputId"
            class="block text-xs font-medium text-content-secondary"
        >
            {{ label }}
            <span v-if="required" class="text-status-danger">*</span>
        </label>

        <div class="relative flex items-center">
            <span
                v-if="icon"
                class="absolute left-3 flex items-center pointer-events-none text-content-muted"
            >
                <Icon :name="icon" :size="16" />
            </span>

            <input
                :id="inputId"
                :type="type"
                :value="modelValue"
                :placeholder="placeholder"
                :disabled="disabled"
                :required="required"
                @input="onInput"
                :class="[
                    'w-full h-10 rounded-lg text-sm transition-colors',
                    'bg-surface text-content-primary placeholder:text-content-muted',
                    'border border-border-subtle focus:border-primary',
                    'focus:outline-none focus:ring-2 focus:ring-primary/20',
                    'disabled:opacity-50 disabled:bg-surface-subdued disabled:cursor-not-allowed',
                    icon ? 'pl-9 pr-3' : 'px-3',
                    error ? 'border-status-danger focus:border-status-danger focus:ring-status-danger/20' : '',
                ]"
            />
        </div>

        <p v-if="error" class="text-xs text-status-danger">
            {{ error }}
        </p>
        <p v-else-if="hint" class="text-xs text-content-muted">
            {{ hint }}
        </p>
    </div>
</template>
