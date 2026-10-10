<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Components/layout/AppLayout.vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import Badge from '@/Components/ui/Badge.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import Icon from '@/Components/ui/Icon.vue';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';
import { useToast } from '@/composables/useToast';

interface ScheduledTask {
    id: string;
    title: string;
    category: string;
    startTime: string;
    endTime: string;
    plannedHours: number;
    actualHours?: number;
    completed: boolean;
    isExtra?: boolean;
}

const page = usePage();
const toast = useToast();

const currentNav = ref('today');
const activeView = ref<'schedule' | 'empty' | 'loading'>('schedule');

watch(currentNav, (nav) => {
    if (nav === 'tasks') {
        router.visit('/tasks');
    }
});
const userName = computed(() => {
    const user = (page.props.auth as any)?.user;
    if (user?.name) {
        return user.name.trim().split(/\s+/)[0];
    }
    return 'Friend';
});

const todayFormatted = computed(() => {
    return new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
    }).format(new Date());
});

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
});

// Mock Scheduled Tasks
const tasks = ref<ScheduledTask[]>([
    {
        id: '1',
        title: 'Core Architecture & Tenant Boundary Spec',
        category: 'Architecture',
        startTime: '09:00',
        endTime: '11:30',
        plannedHours: 2.5,
        actualHours: 2.5,
        completed: true,
    },
    {
        id: '2',
        title: 'Sanctum Session & CSRF Security Audit',
        category: 'Security',
        startTime: '11:45',
        endTime: '12:45',
        plannedHours: 1.0,
        actualHours: 1.0,
        completed: true,
    },
    {
        id: '3',
        title: 'Stakeholder Demo & Milestone Review',
        category: 'Review',
        startTime: '14:00',
        endTime: '15:00',
        plannedHours: 1.0,
        completed: false,
    },
    {
        id: '4',
        title: 'Work Tracking Model & Variance Engine Tests',
        category: 'Engineering',
        startTime: '15:30',
        endTime: '16:30',
        plannedHours: 1.0,
        completed: false,
    },
]);

// Summary Calculations
const totalPlanned = computed(() =>
    tasks.value.reduce((acc, t) => acc + (t.isExtra ? 0 : t.plannedHours), 0)
);

const totalCompleted = computed(() =>
    tasks.value
        .filter((t) => t.completed)
        .reduce((acc, t) => acc + (t.actualHours ?? t.plannedHours), 0)
);

const totalExtra = computed(() =>
    tasks.value
        .filter((t) => t.isExtra)
        .reduce((acc, t) => acc + (t.actualHours ?? t.plannedHours), 0)
);

const completionPercentage = computed(() => {
    if (totalPlanned.value === 0) return 0;
    return Math.min(100, Math.round((totalCompleted.value / totalPlanned.value) * 100));
});

function toggleTask(id: string) {
    const task = tasks.value.find((t) => t.id === id);
    if (!task) return;

    task.completed = !task.completed;
    if (task.completed) {
        if (!task.actualHours) {
            task.actualHours = task.plannedHours;
        }
        toast.success(`Completed: ${task.title}`);
    } else {
        toast.info(`Reopened: ${task.title}`);
    }
}

// Log Extra Work Dialog State
const logModalOpen = ref(false);
const newWorkTitle = ref('');
const newWorkCategory = ref('Ad-hoc Support');
const newWorkMinutes = ref(30);
const newWorkNotes = ref('');

const categoryOptions = [
    { label: 'Ad-hoc Support', value: 'Ad-hoc Support' },
    { label: 'Unplanned Meeting', value: 'Unplanned Meeting' },
    { label: 'Urgent Bug Fix', value: 'Urgent Bug Fix' },
    { label: 'Code Review & Mentoring', value: 'Code Review' },
    { label: 'Research & Spike', value: 'Research' },
];

function submitExtraWork() {
    if (!newWorkTitle.value.trim()) {
        toast.danger('Please enter a task title');
        return;
    }

    const hours = Number((newWorkMinutes.value / 60).toFixed(2));
    const newTask: ScheduledTask = {
        id: `extra-${Date.now()}`,
        title: newWorkTitle.value.trim(),
        category: newWorkCategory.value,
        startTime: 'Logged',
        endTime: `${newWorkMinutes.value}m`,
        plannedHours: 0,
        actualHours: hours,
        completed: true,
        isExtra: true,
    };

    tasks.value.unshift(newTask);
    logModalOpen.value = false;

    toast.success('Extra work logged successfully', `${newTask.title} (+${newWorkMinutes.value} mins)`);

    // Reset form
    newWorkTitle.value = '';
    newWorkMinutes.value = 30;
    newWorkNotes.value = '';
}
</script>

<template>
    <Head title="Today - LifeLoop" />

    <AppLayout :current-tab="currentNav" @navigate="currentNav = $event">
        <!-- Top Navigation & Controls Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <SegmentedControl
                v-model="currentNav"
                :options="[
                    { label: 'Today', value: 'today' },
                    { label: 'Week', value: 'week' },
                    { label: 'Month', value: 'month' },
                    { label: 'Tasks', value: 'tasks' },
                ]"
            />

            <div class="flex items-center gap-2 self-start sm:self-auto">
                <!-- State Preview Toggle -->
                <SegmentedControl
                    v-model="activeView"
                    size="sm"
                    :options="[
                        { label: 'Live Data', value: 'schedule' },
                        { label: 'Empty State', value: 'empty' },
                        { label: 'Skeleton', value: 'loading' },
                    ]"
                />
                <ThemeToggle variant="button" />
            </div>
        </div>

        <!-- Page Header -->
        <PageHeader
            :title="`${greeting}, ${userName}`"
            :subtitle="`${todayFormatted} • Focus Mode`"
        >
            <template #actions>
                <Button
                    variant="primary"
                    icon="plus"
                    @click="logModalOpen = true"
                >
                    Log Extra Work
                </Button>
            </template>
        </PageHeader>

        <!-- SKELETON STATE PREVIEW -->
        <div v-if="activeView === 'loading'" class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <Skeleton v-for="n in 4" :key="n" height="h-24" />
            </div>
            <div class="space-y-3">
                <Skeleton v-for="n in 4" :key="n" height="h-18" />
            </div>
        </div>

        <!-- EMPTY STATE PREVIEW -->
        <div v-else-if="activeView === 'empty'" class="my-10">
            <EmptyState
                title="No tasks scheduled for today"
                description="Your day is wide open. Enjoy the calm, or create your first scheduled block to start tracking actual focus time."
                icon="calendar"
            >
                <template #action>
                    <Button variant="primary" icon="plus" @click="activeView = 'schedule'">
                        Schedule First Task
                    </Button>
                </template>
            </EmptyState>
        </div>

        <!-- LIVE SCHEDULE VIEW -->
        <div v-else class="space-y-8">
            <!-- Summary Metrics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Planned Hours -->
                <Card compact>
                    <div class="flex items-center justify-between text-xs text-content-secondary">
                        <span>Planned Hours</span>
                        <Icon name="clock" :size="15" class="text-accent" />
                    </div>
                    <p class="text-2xl font-bold tracking-tight text-content-primary mt-2">
                        {{ totalPlanned.toFixed(1) }} <span class="text-sm font-normal text-content-muted">hrs</span>
                    </p>
                    <div class="mt-2 text-[11px] text-content-muted">
                        {{ tasks.filter((t) => !t.isExtra).length }} scheduled blocks
                    </div>
                </Card>

                <!-- Completed Hours -->
                <Card compact>
                    <div class="flex items-center justify-between text-xs text-content-secondary">
                        <span>Completed Work</span>
                        <Icon name="check-circle" :size="15" class="text-status-success" />
                    </div>
                    <p class="text-2xl font-bold tracking-tight text-content-primary mt-2">
                        {{ totalCompleted.toFixed(1) }} <span class="text-sm font-normal text-content-muted">hrs</span>
                    </p>
                    <div class="mt-2 text-[11px] text-status-success flex items-center gap-1 font-medium">
                        <span>{{ completionPercentage }}% of planned time</span>
                    </div>
                </Card>

                <!-- Remaining Hours -->
                <Card compact>
                    <div class="flex items-center justify-between text-xs text-content-secondary">
                        <span>Remaining Focus</span>
                        <Icon name="calendar" :size="15" class="text-content-muted" />
                    </div>
                    <p class="text-2xl font-bold tracking-tight text-content-primary mt-2">
                        {{ Math.max(0, totalPlanned - totalCompleted).toFixed(1) }}
                        <span class="text-sm font-normal text-content-muted">hrs</span>
                    </p>
                    <div class="mt-2 text-[11px] text-content-muted">
                        {{ tasks.filter((t) => !t.completed && !t.isExtra).length }} sessions pending
                    </div>
                </Card>

                <!-- Extra Work Logged -->
                <Card compact>
                    <div class="flex items-center justify-between text-xs text-content-secondary">
                        <span>Extra Work</span>
                        <Icon name="flame" :size="15" class="text-primary" />
                    </div>
                    <p class="text-2xl font-bold tracking-tight text-primary mt-2">
                        {{ totalExtra.toFixed(1) }} <span class="text-sm font-normal text-content-muted">hrs</span>
                    </p>
                    <div class="mt-2 text-[11px] text-content-muted">
                        {{ tasks.filter((t) => t.isExtra).length }} ad-hoc items
                    </div>
                </Card>
            </div>

            <!-- Progress Meter -->
            <Card compact class="bg-surface">
                <div class="flex items-center justify-between text-xs font-medium text-content-secondary mb-2">
                    <span>Daily Progress</span>
                    <span class="text-primary font-semibold">{{ completionPercentage }}%</span>
                </div>
                <div class="w-full h-2 rounded-full bg-surface-subdued overflow-hidden">
                    <div
                        class="h-full bg-primary rounded-full transition-all duration-300 ease-out"
                        :style="{ width: `${completionPercentage}%` }"
                    />
                </div>
            </Card>

            <!-- Task List Section -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-content-primary tracking-tight">
                            Today's Schedule
                        </h2>
                        <p class="text-xs text-content-secondary">
                            Tick tasks to mark them completed or log ad-hoc work
                        </p>
                    </div>

                    <span class="text-xs font-medium text-content-muted">
                        {{ tasks.filter((t) => t.completed).length }}/{{ tasks.length }} Completed
                    </span>
                </div>

                <div class="space-y-2.5">
                    <div
                        v-for="task in tasks"
                        :key="task.id"
                        :class="[
                            'group rounded-xl border p-4 transition-all flex items-center justify-between gap-4',
                            'bg-surface hover:border-border-strong',
                            task.completed
                                ? 'border-border-subtle bg-surface-subdued/30 opacity-80'
                                : 'border-border-subtle shadow-xs',
                            task.isExtra ? 'border-primary/30 bg-primary-subdued/20' : '',
                        ]"
                    >
                        <div class="flex items-center gap-3.5 min-w-0">
                            <!-- Completion Toggle -->
                            <Checkbox
                                :model-value="task.completed"
                                @update:model-value="toggleTask(task.id)"
                            />

                            <!-- Task Details -->
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span
                                        :class="[
                                            'text-sm font-semibold tracking-tight transition-colors',
                                            task.completed
                                                ? 'line-through text-content-muted'
                                                : 'text-content-primary',
                                        ]"
                                    >
                                        {{ task.title }}
                                    </span>

                                    <Badge
                                        v-if="task.isExtra"
                                        variant="extra"
                                        size="sm"
                                        dot
                                    >
                                        Extra Work
                                    </Badge>

                                    <Badge
                                        v-else-if="task.completed"
                                        variant="completed"
                                        size="sm"
                                    >
                                        Done
                                    </Badge>

                                    <Badge
                                        v-else
                                        variant="planned"
                                        size="sm"
                                    >
                                        Planned
                                    </Badge>
                                </div>

                                <div class="flex items-center gap-3 text-xs text-content-muted mt-1">
                                    <span class="flex items-center gap-1 font-mono">
                                        <Icon name="clock" :size="12" />
                                        {{ task.startTime }} - {{ task.endTime }}
                                    </span>
                                    <span>&bull;</span>
                                    <span>{{ task.category }}</span>
                                    <span>&bull;</span>
                                    <span>{{ (task.actualHours ?? task.plannedHours).toFixed(1) }} hrs</span>
                                </div>
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <div class="flex items-center gap-1 shrink-0 opacity-80 group-hover:opacity-100">
                            <IconButton
                                icon="more-vertical"
                                label="Task options"
                                size="sm"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LOG EXTRA WORK DIALOG -->
        <Dialog
            :open="logModalOpen"
            title="Log Extra Work"
            description="Record unplanned time or ad-hoc tasks that occurred outside the regular schedule."
            @close="logModalOpen = false"
        >
            <div class="space-y-4">
                <Input
                    v-model="newWorkTitle"
                    label="What did you work on?"
                    placeholder="e.g., Critical hotfix, client phone sync"
                    required
                />

                <Select
                    v-model="newWorkCategory"
                    label="Category"
                    :options="categoryOptions"
                />

                <Input
                    v-model="newWorkMinutes"
                    type="number"
                    label="Duration (minutes)"
                    placeholder="30"
                    required
                />

                <Input
                    v-model="newWorkNotes"
                    label="Optional Notes"
                    placeholder="Any relevant context, tickets, or links..."
                />
            </div>

            <template #actions>
                <Button
                    variant="ghost"
                    @click="logModalOpen = false"
                >
                    Cancel
                </Button>
                <Button
                    variant="primary"
                    @click="submitExtraWork"
                >
                    Save Work Entry
                </Button>
            </template>
        </Dialog>
    </AppLayout>
</template>
