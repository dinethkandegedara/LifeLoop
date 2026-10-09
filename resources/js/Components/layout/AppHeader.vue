<script setup lang="ts">
import Icon from '@/Components/ui/Icon.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';

defineProps<{
    currentTitle?: string;
}>();

const emit = defineEmits<{
    (e: 'toggle-menu'): void;
}>();
</script>

<template>
    <header
        class="md:hidden h-14 bg-surface border-b border-border-subtle px-4 flex items-center justify-between sticky top-0 z-30 select-none transition-colors"
    >
        <div class="flex items-center gap-3">
            <IconButton
                icon="menu"
                label="Toggle navigation menu"
                size="sm"
                @click="emit('toggle-menu')"
            />
            <div class="flex items-center gap-2">
                <div
                    class="w-6 h-6 rounded-md bg-primary-subdued text-primary border border-primary/25 flex items-center justify-center font-bold text-xs"
                >
                    LL
                </div>
                <span class="font-bold text-sm text-content-primary">LifeLoop</span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <ThemeToggle variant="button" />
        </div>
    </header>
</template>
