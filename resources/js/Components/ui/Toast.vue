<script setup lang="ts">
import { computed } from 'vue';
import Icon from './Icon.vue';
import IconButton from './IconButton.vue';
import type { ToastItem } from '@/composables/useToast';

const props = defineProps<{
    toast: ToastItem;
}>();

const emit = defineEmits<{
    (e: 'dismiss', id: string): void;
}>();

const iconName = computed(() => {
    switch (props.toast.type) {
        case 'success':
            return 'check-circle';
        case 'warning':
            return 'alert-circle';
        case 'danger':
            return 'alert-circle';
        default:
            return 'sparkles';
    }
});

const iconColor = computed(() => {
    switch (props.toast.type) {
        case 'success':
            return 'text-status-success';
        case 'warning':
            return 'text-status-warning';
        case 'danger':
            return 'text-status-danger';
        default:
            return 'text-primary';
    }
});
</script>

<template>
    <div
        class="w-full max-w-sm rounded-xl border border-border-subtle bg-surface p-3.5 shadow-lg flex items-start gap-3 pointer-events-auto transition-all"
        role="alert"
    >
        <span class="mt-0.5 shrink-0" :class="iconColor">
            <Icon :name="iconName" :size="18" />
        </span>

        <div class="flex-1 min-w-0 pr-1">
            <p class="text-sm font-semibold text-content-primary">
                {{ toast.title }}
            </p>
            <p v-if="toast.description" class="text-xs text-content-secondary mt-0.5 leading-relaxed">
                {{ toast.description }}
            </p>
        </div>

        <IconButton
            icon="x"
            label="Dismiss notification"
            size="sm"
            @click="emit('dismiss', toast.id)"
            class="-mr-1 -mt-1 text-content-muted hover:text-content-primary"
        />
    </div>
</template>
