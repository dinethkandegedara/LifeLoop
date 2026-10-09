<script setup lang="ts">
import { useToast } from '@/composables/useToast';
import Toast from './Toast.vue';

const { toasts, dismiss } = useToast();
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none px-4 sm:px-0"
            aria-live="polite"
        >
            <TransitionGroup
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="transform translate-y-3 opacity-0"
                enter-to-class="transform translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="transform translate-y-0 opacity-100"
                leave-to-class="transform translate-y-2 opacity-0"
            >
                <Toast
                    v-for="toast in toasts"
                    :key="toast.id"
                    :toast="toast"
                    @dismiss="dismiss"
                />
            </TransitionGroup>
        </div>
    </Teleport>
</template>
