<script setup lang="ts">
import Icon from '@/Components/ui/Icon.vue';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';

defineProps<{
    currentTab: string;
}>();

const emit = defineEmits<{
    (e: 'navigate', tab: string): void;
}>();

const navItems = [
    { id: 'today', label: 'Today', icon: 'clock', badge: '3' },
    { id: 'week', label: 'Week', icon: 'calendar' },
    { id: 'month', label: 'Month', icon: 'calendar' },
    { id: 'tasks', label: 'All Tasks', icon: 'tasks' },
];
</script>

<template>
    <aside
        class="hidden md:flex flex-col w-64 bg-surface border-r border-border-subtle h-screen sticky top-0 shrink-0 select-none transition-colors"
    >
        <!-- App Logo & Branding -->
        <div class="h-16 px-6 flex items-center gap-3 border-b border-border-subtle">
            <div
                class="w-8 h-8 rounded-lg bg-primary-subdued text-primary border border-primary/25 flex items-center justify-center font-bold text-sm tracking-tight shadow-xs"
            >
                LL
            </div>
            <div>
                <span class="font-bold text-base tracking-tight text-content-primary">LifeLoop</span>
                <span class="block text-[10px] text-content-muted leading-none font-medium">Productivity & Flow</span>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 px-3 py-4 space-y-1">
            <button
                v-for="item in navItems"
                :key="item.id"
                type="button"
                @click="emit('navigate', item.id)"
                :class="[
                    'w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors cursor-pointer group',
                    currentTab === item.id
                        ? 'bg-primary-subdued text-primary font-semibold'
                        : 'text-content-secondary hover:text-content-primary hover:bg-surface-hover',
                ]"
            >
                <div class="flex items-center gap-3">
                    <Icon
                        :name="item.icon"
                        :size="18"
                        :class="currentTab === item.id ? 'text-primary' : 'text-content-muted group-hover:text-content-secondary'"
                    />
                    <span>{{ item.label }}</span>
                </div>

                <span
                    v-if="item.badge"
                    :class="[
                        'text-xs font-medium px-2 py-0.5 rounded-full',
                        currentTab === item.id
                            ? 'bg-primary/20 text-primary'
                            : 'bg-surface-subdued text-content-muted',
                    ]"
                >
                    {{ item.badge }}
                </span>
            </button>
        </nav>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-border-subtle space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-content-secondary">Theme</span>
                <ThemeToggle variant="segmented" />
            </div>

            <!-- User Profile Avatar Card -->
            <div class="flex items-center gap-3 p-2 rounded-lg bg-surface-subdued border border-border-subtle">
                <div
                    class="w-8 h-8 rounded-full bg-accent-subdued text-accent border border-accent/20 flex items-center justify-center text-xs font-semibold"
                >
                    AM
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-content-primary truncate">Alex Morgan</p>
                    <p class="text-[10px] text-content-muted truncate">alex@lifeloop.app</p>
                </div>
            </div>
        </div>
    </aside>
</template>
