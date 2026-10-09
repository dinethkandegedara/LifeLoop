<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/ui/Icon.vue';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';

defineProps<{
    currentTab: string;
}>();

const emit = defineEmits<{
    (e: 'navigate', tab: string): void;
}>();

const page = usePage();
const logoutForm = useForm({});

const user = computed(() => (page.props.auth as any)?.user);

const initials = computed(() => {
    if (!user.value?.name) return 'LL';
    const parts = user.value.name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
});

const navItems = [
    { id: 'today', label: 'Today', icon: 'clock', badge: '3', href: '/' },
    { id: 'week', label: 'Week', icon: 'calendar', href: '/' },
    { id: 'month', label: 'Month', icon: 'calendar', href: '/' },
    { id: 'tasks', label: 'All Tasks', icon: 'tasks', href: '/' },
];

function logout() {
    logoutForm.post('/logout');
}
</script>

<template>
    <aside
        class="hidden md:flex flex-col w-64 bg-surface border-r border-border-subtle h-screen sticky top-0 shrink-0 select-none transition-colors"
    >
        <!-- App Logo & Branding -->
        <div class="h-16 px-6 flex items-center gap-3 border-b border-border-subtle">
            <Link href="/" class="flex items-center gap-3 group">
                <div
                    class="w-8 h-8 rounded-lg bg-primary-subdued text-primary border border-primary/25 flex items-center justify-center font-bold text-sm tracking-tight shadow-xs group-hover:scale-105 transition-transform"
                >
                    LL
                </div>
                <div>
                    <span class="font-bold text-base tracking-tight text-content-primary">LifeLoop</span>
                    <span class="block text-[10px] text-content-muted leading-none font-medium">Productivity & Flow</span>
                </div>
            </Link>
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

            <!-- Settings Link -->
            <Link
                href="/settings"
                :class="[
                    'w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors cursor-pointer group',
                    currentTab === 'settings'
                        ? 'bg-primary-subdued text-primary font-semibold'
                        : 'text-content-secondary hover:text-content-primary hover:bg-surface-hover',
                ]"
            >
                <Icon
                    name="settings"
                    :size="18"
                    :class="currentTab === 'settings' ? 'text-primary' : 'text-content-muted group-hover:text-content-secondary'"
                />
                <span>Settings</span>
            </Link>
        </nav>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-border-subtle space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-content-secondary">Theme</span>
                <ThemeToggle variant="segmented" />
            </div>

            <!-- User Profile Avatar Card with Logout -->
            <div v-if="user" class="p-2.5 rounded-xl bg-surface-subdued border border-border-subtle space-y-2">
                <div class="flex items-center gap-2.5">
                    <div
                        class="w-8 h-8 rounded-full bg-accent-subdued text-accent border border-accent/20 flex items-center justify-center text-xs font-bold shrink-0"
                    >
                        {{ initials }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-content-primary truncate">{{ user.name }}</p>
                        <p class="text-[10px] text-content-muted truncate">{{ user.email }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-1 border-t border-border-subtle/60 text-[11px]">
                    <span class="text-content-muted">{{ user.timezone }}</span>
                    <button
                        type="button"
                        @click="logout"
                        :disabled="logoutForm.processing"
                        class="text-status-danger hover:underline font-medium cursor-pointer"
                    >
                        Sign out
                    </button>
                </div>
            </div>

            <!-- Guest Sign In / Register Prompt -->
            <div v-else class="space-y-2">
                <Link
                    href="/login"
                    class="w-full flex items-center justify-center h-9 px-3 rounded-lg text-xs font-medium bg-primary text-primary-text hover:bg-primary-hover shadow-xs"
                >
                    Sign In
                </Link>
            </div>
        </div>
    </aside>
</template>
