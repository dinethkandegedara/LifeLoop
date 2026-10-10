<script setup lang="ts">
import { computed } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/ui/Icon.vue';
import AppLogo from '@/Components/ui/AppLogo.vue';

const props = defineProps<{
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
    { id: 'schedule', label: 'Schedule', icon: 'calendar', href: '/' },
    { id: 'tasks', label: 'All Tasks', icon: 'tasks', href: '/tasks' },
    { id: 'reports', label: 'Reports', icon: 'chart', href: '/reports' },
    { id: 'settings', label: 'Settings', icon: 'settings', href: '/settings' },
];

function isItemActive(item: (typeof navItems)[number]): boolean {
    if (item.id === 'schedule') {
        return props.currentTab === 'schedule' || ['today', 'week', 'month'].includes(props.currentTab);
    }
    return props.currentTab === item.id;
}

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
            <Link href="/" class="group">
                <AppLogo variant="icon-text" size="md" show-subtitle />
            </Link>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 px-3 py-4 space-y-1">
            <Link
                v-for="item in navItems"
                :key="item.id"
                :href="item.href"
                :class="[
                    'w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors cursor-pointer group',
                    isItemActive(item)
                        ? 'bg-primary-subdued text-primary font-semibold'
                        : 'text-content-secondary hover:text-content-primary hover:bg-surface-hover',
                ]"
            >
                <div class="flex items-center gap-3">
                    <Icon
                        :name="item.icon"
                        :size="18"
                        :class="isItemActive(item) ? 'text-primary' : 'text-content-muted group-hover:text-content-secondary'"
                    />
                    <span>{{ item.label }}</span>
                </div>
            </Link>
        </nav>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-border-subtle">
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
