<script setup lang="ts">
import Icon from './Icon.vue';
import IconButton from './IconButton.vue';

withDefaults(
    defineProps<{
        title: string;
        message?: string;
        dismissible?: boolean;
    }>(),
    {
        dismissible: false,
    }
);

const emit = defineEmits<{
    (e: 'dismiss'): void;
}>();
</script>

<template>
    <div
        class="rounded-xl border border-status-danger/30 bg-status-danger-subdued p-4 flex items-start gap-3 text-status-danger"
        role="alert"
    >
        <Icon name="alert-circle" :size="18" class="mt-0.5 shrink-0" />

        <div class="flex-1 min-w-0">
            <h5 class="text-xs font-semibold uppercase tracking-wider">
                {{ title }}
            </h5>
            <p v-if="message" class="text-xs text-content-primary mt-1 leading-relaxed">
                {{ message }}
            </p>
        </div>

        <IconButton
            v-if="dismissible"
            icon="x"
            label="Dismiss error"
            size="sm"
            @click="emit('dismiss')"
            class="-mr-1 -mt-1 text-status-danger hover:bg-status-danger/20"
        />
    </div>
</template>
