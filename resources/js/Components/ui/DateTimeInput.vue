<script setup lang="ts">
import { computed } from 'vue';
import Icon from './Icon.vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        type?: 'date' | 'time' | 'datetime-local';
        label?: string;
        id?: string;
        error?: string;
        disabled?: boolean;
        required?: boolean;
    }>(),
    {
        modelValue: '',
        type: 'date',
        disabled: false,
        required: false,
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const inputId = computed(() => props.id || `datetime-${Math.random().toString(36).substring(2, 7)}`);

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
            <span class="absolute left-3 flex items-center pointer-events-none text-content-muted">
                <Icon :name="type === 'time' ? 'clock' : 'calendar'" :size="16" />
            </span>

            <input
                :id="inputId"
                :type="type"
                :value="modelValue"
                :disabled="disabled"
                :required="required"
                @input="onInput"
                :class="[
                    'w-full h-10 rounded-lg text-sm transition-colors pl-9 pr-3',
                    'bg-surface text-content-primary',
                    'border border-border-subtle focus:border-primary',
                    'focus:outline-none focus:ring-2 focus:ring-primary/20',
                    'disabled:opacity-50 disabled:bg-surface-subdued disabled:cursor-not-allowed',
                    error ? 'border-status-danger focus:border-status-danger focus:ring-status-danger/20' : '',
                ]"
            />
        </div>

        <p v-if="error" class="text-xs text-status-danger">
            {{ error }}
        </p>
    </div>
</template>
