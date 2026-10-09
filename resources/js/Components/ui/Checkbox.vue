<script setup lang="ts">
import { computed } from 'vue';
import Icon from './Icon.vue';

const props = withDefaults(
    defineProps<{
        modelValue?: boolean;
        id?: string;
        label?: string;
        description?: string;
        disabled?: boolean;
    }>(),
    {
        modelValue: false,
        disabled: false,
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
}>();

const checkboxId = computed(() => props.id || `checkbox-${Math.random().toString(36).substring(2, 7)}`);

function toggle() {
    if (!props.disabled) {
        emit('update:modelValue', !props.modelValue);
    }
}
</script>

<template>
    <label
        :for="checkboxId"
        :class="[
            'inline-flex items-start gap-3 select-none cursor-pointer',
            disabled ? 'opacity-50 cursor-not-allowed' : '',
        ]"
    >
        <div class="relative flex items-center pt-0.5">
            <input
                :id="checkboxId"
                type="checkbox"
                :checked="modelValue"
                :disabled="disabled"
                @change="toggle"
                class="sr-only"
            />
            <div
                :class="[
                    'w-5 h-5 rounded-md border flex items-center justify-center transition-all',
                    'focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2',
                    modelValue
                        ? 'bg-primary border-primary text-primary-text'
                        : 'bg-surface border-border-strong hover:border-primary/60 text-transparent',
                ]"
            >
                <Icon name="check" :size="13" class="stroke-[3]" />
            </div>
        </div>

        <div v-if="label || description" class="text-sm">
            <span
                v-if="label"
                :class="[
                    'font-medium text-content-primary',
                    modelValue ? 'line-through text-content-muted' : '',
                ]"
            >
                {{ label }}
            </span>
            <p v-if="description" class="text-xs text-content-muted mt-0.5">
                {{ description }}
            </p>
        </div>
    </label>
</template>
