<script setup lang="ts">
import { ref } from 'vue';

withDefaults(
    defineProps<{
        text: string;
        position?: 'top' | 'bottom' | 'left' | 'right';
    }>(),
    {
        position: 'top',
    }
);

const visible = ref(false);
</script>

<template>
    <div
        class="relative inline-flex"
        @mouseenter="visible = true"
        @mouseleave="visible = false"
        @focusin="visible = true"
        @focusout="visible = false"
    >
        <slot />

        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="visible"
                role="tooltip"
                :class="[
                    'absolute z-50 pointer-events-none whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium',
                    'bg-content-primary text-surface shadow-md',
                    position === 'top' ? 'bottom-full left-1/2 -translate-x-1/2 mb-1.5' : '',
                    position === 'bottom' ? 'top-full left-1/2 -translate-x-1/2 mt-1.5' : '',
                    position === 'left' ? 'right-full top-1/2 -translate-y-1/2 mr-1.5' : '',
                    position === 'right' ? 'left-full top-1/2 -translate-y-1/2 ml-1.5' : '',
                ]"
            >
                {{ text }}
            </div>
        </Transition>
    </div>
</template>
