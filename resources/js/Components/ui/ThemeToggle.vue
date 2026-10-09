<script setup lang="ts">
import { useTheme } from '@/composables/useTheme';
import Icon from './Icon.vue';

const { theme, resolvedTheme, setTheme, toggleTheme } = useTheme();

withDefaults(
    defineProps<{
        variant?: 'segmented' | 'button';
    }>(),
    {
        variant: 'button',
    }
);
</script>

<template>
    <!-- Segmented Control for settings or full control -->
    <div
        v-if="variant === 'segmented'"
        class="inline-flex items-center p-1 rounded-xl bg-surface-subdued border border-border-subtle text-xs"
        role="group"
        aria-label="Theme selector"
    >
        <button
            type="button"
            @click="setTheme('light')"
            :class="[
                'flex items-center gap-1.5 px-2.5 py-1 rounded-lg font-medium transition-colors cursor-pointer',
                theme === 'light'
                    ? 'bg-surface text-content-primary shadow-xs font-semibold'
                    : 'text-content-secondary hover:text-content-primary',
            ]"
            title="Light Theme"
        >
            <Icon name="sun" :size="13" />
            <span>Light</span>
        </button>

        <button
            type="button"
            @click="setTheme('dark')"
            :class="[
                'flex items-center gap-1.5 px-2.5 py-1 rounded-lg font-medium transition-colors cursor-pointer',
                theme === 'dark'
                    ? 'bg-surface text-content-primary shadow-xs font-semibold'
                    : 'text-content-secondary hover:text-content-primary',
            ]"
            title="Dark Theme"
        >
            <Icon name="moon" :size="13" />
            <span>Dark</span>
        </button>

        <button
            type="button"
            @click="setTheme('system')"
            :class="[
                'flex items-center gap-1.5 px-2.5 py-1 rounded-lg font-medium transition-colors cursor-pointer',
                theme === 'system'
                    ? 'bg-surface text-content-primary shadow-xs font-semibold'
                    : 'text-content-secondary hover:text-content-primary',
            ]"
            title="System Theme"
        >
            <Icon name="monitor" :size="13" />
            <span>System</span>
        </button>
    </div>

    <!-- Quick Single Button Toggle -->
    <button
        v-else
        type="button"
        @click="toggleTheme"
        class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-border-subtle bg-surface text-content-secondary hover:text-content-primary hover:bg-surface-hover transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary shadow-xs"
        :aria-label="resolvedTheme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'"
        :title="resolvedTheme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'"
    >
        <Icon v-if="resolvedTheme === 'dark'" name="sun" :size="16" class="text-amber-400" />
        <Icon v-else name="moon" :size="16" class="text-indigo-400" />
    </button>
</template>
