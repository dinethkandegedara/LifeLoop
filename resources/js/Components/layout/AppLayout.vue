<script setup lang="ts">
import { ref } from 'vue';
import AppSidebar from './AppSidebar.vue';
import AppHeader from './AppHeader.vue';
import ToastContainer from '@/Components/ui/ToastContainer.vue';
import Icon from '@/Components/ui/Icon.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';

const props = withDefaults(
    defineProps<{
        currentTab?: string;
    }>(),
    {
        currentTab: 'today',
    }
);

const emit = defineEmits<{
    (e: 'navigate', tab: string): void;
}>();

const mobileMenuOpen = ref(false);

function handleNavigate(tab: string) {
    emit('navigate', tab);
    mobileMenuOpen.value = false;
}
</script>

<template>
    <div class="min-h-screen bg-app text-content-primary flex flex-col md:flex-row antialiased selection:bg-primary/20 selection:text-primary">
        <!-- Desktop Sidebar -->
        <AppSidebar :current-tab="currentTab" @navigate="handleNavigate" />

        <!-- Mobile Drawer Overlay -->
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
                    v-if="mobileMenuOpen"
                    class="fixed inset-0 z-50 flex md:hidden"
                >
                    <!-- Backdrop -->
                    <div
                        class="fixed inset-0 bg-black/50 backdrop-blur-xs"
                        @click="mobileMenuOpen = false"
                        aria-hidden="true"
                    />

                    <!-- Drawer panel -->
                    <div class="relative w-72 max-w-[80vw] bg-surface border-r border-border-subtle h-full p-4 flex flex-col shadow-2xl z-10">
                        <div class="flex items-center justify-between pb-4 border-b border-border-subtle mb-4">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-lg bg-primary-subdued text-primary border border-primary/25 flex items-center justify-center font-bold text-xs">
                                    LL
                                </div>
                                <span class="font-bold text-sm text-content-primary">LifeLoop</span>
                            </div>
                            <IconButton
                                icon="x"
                                label="Close menu"
                                size="sm"
                                @click="mobileMenuOpen = false"
                            />
                        </div>

                        <!-- Mobile navigation items -->
                        <nav class="flex-1 space-y-1">
                            <button
                                v-for="item in [
                                    { id: 'today', label: 'Today', icon: 'clock' },
                                    { id: 'week', label: 'Week', icon: 'calendar' },
                                    { id: 'month', label: 'Month', icon: 'calendar' },
                                    { id: 'tasks', label: 'All Tasks', icon: 'tasks' },
                                ]"
                                :key="item.id"
                                type="button"
                                @click="handleNavigate(item.id)"
                                :class="[
                                    'w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors cursor-pointer text-left',
                                    currentTab === item.id
                                        ? 'bg-primary-subdued text-primary font-semibold'
                                        : 'text-content-secondary hover:text-content-primary hover:bg-surface-hover',
                                ]"
                            >
                                <Icon :name="item.icon" :size="18" />
                                <span>{{ item.label }}</span>
                            </button>
                        </nav>

                        <div class="pt-4 border-t border-border-subtle flex flex-col gap-3">
                            <span class="text-xs font-medium text-content-secondary">Theme</span>
                            <ThemeToggle variant="segmented" />
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Mobile Header -->
            <AppHeader @toggle-menu="mobileMenuOpen = !mobileMenuOpen" />

            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-6xl w-full mx-auto">
                <slot />
            </main>
        </div>

        <!-- Global Toasts -->
        <ToastContainer />
    </div>
</template>
