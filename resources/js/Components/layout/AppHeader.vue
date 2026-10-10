<script setup lang="ts">
import Icon from '@/Components/ui/Icon.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';
import AppLogo from '@/Components/ui/AppLogo.vue';

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
            <AppLogo variant="icon-text" size="sm" />
        </div>

        <div class="flex items-center gap-2">
            <ThemeToggle variant="button" />
        </div>
    </header>
</template>
