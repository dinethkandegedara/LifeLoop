<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

const props = withDefaults(
    defineProps<{
        align?: 'left' | 'right';
        width?: 'auto' | 'w-48' | 'w-56' | 'w-64';
    }>(),
    {
        align: 'right',
        width: 'w-48',
    }
);

const open = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

function toggle() {
    open.value = !open.value;
}

function close() {
    open.value = false;
}

function handleClickOutside(e: MouseEvent) {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
        close();
    }
}

function handleKeyDown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        close();
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    document.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <div class="relative inline-block text-left" ref="dropdownRef">
        <div @click="toggle" class="cursor-pointer">
            <slot name="trigger" :open="open" />
        </div>

        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <div
                v-if="open"
                :class="[
                    'absolute z-50 mt-1.5 rounded-xl border border-border-subtle bg-surface p-1 shadow-lg ring-1 ring-black/5',
                    align === 'right' ? 'right-0' : 'left-0',
                    width,
                ]"
                role="menu"
            >
                <div @click="close">
                    <slot :close="close" />
                </div>
            </div>
        </Transition>
    </div>
</template>
