<script setup lang="ts">
import { watch, onMounted, onUnmounted } from 'vue';
import Icon from './Icon.vue';
import IconButton from './IconButton.vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        description?: string;
        maxWidth?: 'sm' | 'md' | 'lg' | 'xl';
    }>(),
    {
        maxWidth: 'md',
    }
);

const emit = defineEmits<{
    (e: 'close'): void;
}>();

function onKeyDown(e: KeyboardEvent) {
    if (e.key === 'Escape' && props.open) {
        emit('close');
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (typeof document !== 'undefined') {
            if (isOpen) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }
    }
);

onMounted(() => {
    window.addEventListener('keydown', onKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeyDown);
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});

const maxWidthClass = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
                role="dialog"
                aria-modal="true"
            >
                <!-- Backdrop -->
                <div
                    class="fixed inset-0 bg-black/50 backdrop-blur-xs transition-opacity"
                    @click="emit('close')"
                    aria-hidden="true"
                />

                <!-- Dialog Content -->
                <div
                    :class="[
                        'relative w-full rounded-2xl bg-surface border border-border-subtle p-6 shadow-2xl z-10 transition-transform transform',
                        maxWidthClass[maxWidth],
                    ]"
                >
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div>
                            <h3 v-if="title" class="text-lg font-semibold text-content-primary tracking-tight">
                                {{ title }}
                            </h3>
                            <p v-if="description" class="text-xs text-content-secondary mt-1">
                                {{ description }}
                            </p>
                        </div>
                        <IconButton
                            icon="x"
                            label="Close dialog"
                            size="sm"
                            @click="emit('close')"
                            class="-mr-2 -mt-2"
                        />
                    </div>

                    <div class="space-y-4">
                        <slot />
                    </div>

                    <div
                        v-if="$slots.actions"
                        class="mt-6 pt-4 border-t border-border-subtle flex items-center justify-end gap-3"
                    >
                        <slot name="actions" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
