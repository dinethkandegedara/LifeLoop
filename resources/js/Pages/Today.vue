<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Components/layout/AppLayout.vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Input from '@/Components/ui/Input.vue';
import Select, { type SelectOption } from '@/Components/ui/Select.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Icon from '@/Components/ui/Icon.vue';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';
import { useToast } from '@/composables/useToast';

interface Task {
    id: number;
    title: string;
    description: string | null;
    status: 'active' | 'archived';
}

interface ScheduleOccurrence {
    id: number;
    task_id: number;
    recurring_schedule_id: number | null;
    scheduled_date: string;
    start_time: string;
    duration_minutes: number;
    status: 'pending' | 'completed' | 'skipped' | 'cancelled';
    is_exception: boolean;
    completed_at: string | null;
    notes: string | null;
    task: {
        id: number;
        title: string;
        description: string | null;
    };
    recurring_schedule?: {
        id: number;
        type: string;
    } | null;
    work_sessions?: WorkSession[];
}

interface WorkSession {
    id: number;
    user_id: number;
    task_id: number;
    schedule_occurrence_id: number | null;
    started_at: string;
    ended_at: string | null;
    duration_minutes: number;
    notes: string | null;
    is_manual: boolean;
    task?: {
        id: number;
        title: string;
    };
    occurrence?: {
        id: number;
        scheduled_date: string;
        start_time: string;
    } | null;
}

const props = defineProps<{
    tasks: Task[];
    occurrences: ScheduleOccurrence[];
    todayWorkSessions: WorkSession[];
    recentWorkSessions: WorkSession[];
    todayDate: string;
    selectedDate: string;
    userTimezone: string;
}>();

const page = usePage();
const toast = useToast();

const currentNav = ref('today');
const activeHistoryTab = ref<'selected' | 'all'>('selected');

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

const formattedSelectedDate = computed(() => {
    if (!props.selectedDate) return '';
    const parts = props.selectedDate.split('-');
    const dateObj = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    return new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(dateObj);
});

const isToday = computed(() => props.selectedDate === props.todayDate);

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
});

// Summary calculations
const totalPlannedHours = computed(() => {
    const mins = props.occurrences.reduce((acc, occ) => acc + (occ.duration_minutes || 0), 0);
    return (mins / 60).toFixed(1);
});

const totalWorkedSelectedDateHours = computed(() => {
    const mins = props.todayWorkSessions.reduce((acc, ws) => acc + (ws.duration_minutes || 0), 0);
    return (mins / 60).toFixed(1);
});

const totalAuditWorkedHours = computed(() => {
    const mins = props.recentWorkSessions.reduce((acc, ws) => acc + (ws.duration_minutes || 0), 0);
    return (mins / 60).toFixed(1);
});

const completedOccurrencesCount = computed(() => {
    return props.occurrences.filter((o) => o.status === 'completed').length;
});

const completionPercentage = computed(() => {
    if (props.occurrences.length === 0) return 0;
    return Math.round((completedOccurrencesCount.value / props.occurrences.length) * 100);
});

// Date Navigation
function navigateDate(offsetDays: number) {
    const parts = props.selectedDate.split('-');
    const d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    d.setDate(d.getDate() + offsetDays);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const targetDate = `${yyyy}-${mm}-${dd}`;
    goToDate(targetDate);
}

function goToToday() {
    goToDate(props.todayDate);
}

function goToDate(dateString: string) {
    router.get(
        '/',
        { date: dateString },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
}

// Occurrence actions
const actionLoadingId = ref<number | null>(null);

function markOccurrenceComplete(occ: ScheduleOccurrence) {
    actionLoadingId.value = occ.id;
    router.patch(
        `/occurrences/${occ.id}/complete`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Occurrence marked completed!');
            },
            onError: () => {
                toast.danger('Failed to complete occurrence.');
            },
            onFinish: () => {
                actionLoadingId.value = null;
            },
        }
    );
}

function reopenOccurrence(occ: ScheduleOccurrence) {
    actionLoadingId.value = occ.id;
    router.patch(
        `/occurrences/${occ.id}/reopen`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.info('Occurrence reopened. All recorded work sessions remain completely intact.');
            },
            onError: () => {
                toast.danger('Failed to reopen occurrence.');
            },
            onFinish: () => {
                actionLoadingId.value = null;
            },
        }
    );
}

// ==========================================
// RECORD WORK SESSION MODAL
// ==========================================
const workModalOpen = ref(false);
const workTaskId = ref<number | ''>('');
const workOccurrenceId = ref<number | ''>('');
const workDate = ref(props.selectedDate);
const workTime = ref('10:00');
const workDurationMinutes = ref(120);
const workNotes = ref('');
const workSubmitting = ref(false);

const taskOptions = computed<SelectOption[]>(() => {
    return props.tasks.map((t) => ({
        label: t.title,
        value: t.id,
    }));
});

const occurrenceOptions = computed<SelectOption[]>(() => {
    const list: SelectOption[] = [
        { label: 'None (Unscheduled / Ad-hoc Work)', value: '' },
    ];
    const relevantOccurrences = workTaskId.value
        ? props.occurrences.filter((o) => o.task_id === Number(workTaskId.value))
        : props.occurrences;

    relevantOccurrences.forEach((occ) => {
        const timeFormatted = occ.start_time.substring(0, 5);
        list.push({
            label: `[Occurrence #${occ.id}] ${occ.scheduled_date} at ${timeFormatted} (${(occ.duration_minutes / 60).toFixed(1)} hrs planned)`,
            value: occ.id,
        });
    });

    return list;
});

watch(workOccurrenceId, (newOccId) => {
    if (newOccId) {
        const occ = props.occurrences.find((o) => o.id === Number(newOccId));
        if (occ) {
            workTaskId.value = occ.task_id;
            workDate.value = occ.scheduled_date;
            workTime.value = occ.start_time.substring(0, 5);
        }
    }
});

function openWorkModalForOccurrence(occ: ScheduleOccurrence) {
    workTaskId.value = occ.task_id;
    workOccurrenceId.value = occ.id;
    workDate.value = occ.scheduled_date;
    workTime.value = occ.start_time.substring(0, 5);
    workDurationMinutes.value = occ.duration_minutes || 120;
    workNotes.value = `Completed scheduled session for ${occ.task.title}`;
    workModalOpen.value = true;
}

function openWorkModalAdHoc(presetDuration = 60) {
    workTaskId.value = props.tasks.length > 0 ? props.tasks[0].id : '';
    workOccurrenceId.value = '';
    workDate.value = props.selectedDate;
    workTime.value = '14:00';
    workDurationMinutes.value = presetDuration;
    workNotes.value = 'Ad-hoc work session on unscheduled day';
    workModalOpen.value = true;
}

function submitWorkSession() {
    if (!workTaskId.value) {
        toast.danger('Please select a task.');
        return;
    }
    if (!workDate.value || !workTime.value) {
        toast.danger('Please specify started date and time.');
        return;
    }
    if (!workDurationMinutes.value || workDurationMinutes.value <= 0) {
        toast.danger('Duration must be at least 1 minute.');
        return;
    }

    const startedAt = `${workDate.value} ${workTime.value}:00`;

    workSubmitting.value = true;
    router.post(
        '/work-sessions',
        {
            task_id: Number(workTaskId.value),
            schedule_occurrence_id: workOccurrenceId.value ? Number(workOccurrenceId.value) : null,
            started_at: startedAt,
            duration_minutes: Number(workDurationMinutes.value),
            notes: workNotes.value.trim() || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                workModalOpen.value = false;
                activeHistoryTab.value = 'all'; // Switch to show all sessions so unscheduled entries are immediately visible
                toast.success('Actual work session recorded successfully!');
            },
            onError: (errors) => {
                const first = Object.values(errors)[0] as string;
                toast.danger(first || 'Failed to record work session.');
            },
            onFinish: () => {
                workSubmitting.value = false;
            },
        }
    );
}

// Helpers
function formatMinutes(minutes: number): string {
    const hours = (minutes / 60).toFixed(1);
    const unit = Number(hours) === 1 ? 'hr' : 'hrs';
    return `${hours} ${unit} (${minutes}m)`;
}

function formatTimeOnly(timeStr: string): string {
    if (!timeStr) return '';
    return timeStr.substring(0, 5);
}
</script>

<template>
    <Head title="Today - LifeLoop" />

    <AppLayout :current-tab="currentNav" @navigate="currentNav = $event">
        <!-- Top Navigation Bar & Action Row -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <SegmentedControl
                v-model="currentNav"
                :options="[
                    { label: 'Schedule & Focus', value: 'today' },
                    { label: 'Tasks', value: 'tasks' },
                ]"
            />

            <div class="flex items-center gap-3">
                <ThemeToggle variant="button" />
            </div>
        </div>

        <!-- Page Header with Primary Actions -->
        <PageHeader
            :title="`${greeting}, ${userName}`"
            :subtitle="`${formattedSelectedDate} • Focus & Daily Execution`"
        >
            <template #actions>
                <div class="flex items-center gap-2">
                    <Button
                        id="btn-goto-manage-tasks"
                        variant="secondary"
                        icon="tasks"
                        @click="router.visit('/tasks')"
                        title="Configure recurrence rules and task occurrences in Manage Tasks"
                    >
                        Manage Tasks & Schedules
                    </Button>
                    <Button
                        id="btn-log-work"
                        variant="primary"
                        icon="plus"
                        @click="openWorkModalAdHoc(60)"
                    >
                        Log Work Session
                    </Button>
                </div>
            </template>
        </PageHeader>

        <!-- Date Navigator Bar -->
        <Card compact class="mb-6 bg-surface border-border-subtle">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="navigateDate(-1)"
                    >
                        &larr; Prev Day
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        :class="isToday ? 'border-primary/40 text-primary font-semibold' : ''"
                        @click="goToToday"
                    >
                        Today
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="navigateDate(1)"
                    >
                        Next Day &rarr;
                    </Button>
                </div>

                <div class="flex items-center gap-2 text-xs text-content-secondary">
                    <span class="font-medium text-content-primary">{{ formattedSelectedDate }}</span>
                    <Badge v-if="isToday" variant="extra" size="sm">Today</Badge>
                    <Badge v-else variant="neutral" size="sm">Viewing Date</Badge>
                </div>

                <div class="flex items-center gap-2">
                    <input
                        type="date"
                        :value="selectedDate"
                        class="text-xs bg-surface-subdued text-content-primary border border-border-subtle rounded px-2.5 py-1 focus:ring-1 focus:ring-primary outline-none"
                        @change="goToDate(($event.target as HTMLInputElement).value)"
                    />
                </div>
            </div>
        </Card>

        <!-- Summary Metrics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <!-- Planned Hours -->
            <Card compact>
                <div class="flex items-center justify-between text-xs text-content-secondary">
                    <span>Planned Time</span>
                    <Icon name="clock" :size="15" class="text-accent" />
                </div>
                <p class="text-2xl font-bold tracking-tight text-content-primary mt-2">
                    {{ totalPlannedHours }} <span class="text-sm font-normal text-content-muted">hrs</span>
                </p>
                <div class="mt-2 text-[11px] text-content-muted">
                    {{ occurrences.length }} scheduled occurrence{{ occurrences.length === 1 ? '' : 's' }}
                </div>
            </Card>

            <!-- Actual Worked Today -->
            <Card compact>
                <div class="flex items-center justify-between text-xs text-content-secondary">
                    <span>Actual Work (Selected Day)</span>
                    <Icon name="check-circle" :size="15" class="text-status-success" />
                </div>
                <p class="text-2xl font-bold tracking-tight text-content-primary mt-2">
                    {{ totalWorkedSelectedDateHours }} <span class="text-sm font-normal text-content-muted">hrs</span>
                </p>
                <div class="mt-2 text-[11px] text-status-success flex items-center gap-1 font-medium">
                    <span>{{ todayWorkSessions.length }} session{{ todayWorkSessions.length === 1 ? '' : 's' }} recorded</span>
                </div>
            </Card>

            <!-- Completion Status -->
            <Card compact>
                <div class="flex items-center justify-between text-xs text-content-secondary">
                    <span>Completion Rate</span>
                    <Icon name="tasks" :size="15" class="text-primary" />
                </div>
                <p class="text-2xl font-bold tracking-tight text-content-primary mt-2">
                    {{ completionPercentage }}%
                </p>
                <div class="mt-2 text-[11px] text-content-muted">
                    {{ completedOccurrencesCount }} of {{ occurrences.length }} completed
                </div>
            </Card>

            <!-- Total Audit Log Intact -->
            <Card compact>
                <div class="flex items-center justify-between text-xs text-content-secondary">
                    <span>Total Audit Sessions</span>
                    <Icon name="flame" :size="15" class="text-primary" />
                </div>
                <p class="text-2xl font-bold tracking-tight text-primary mt-2">
                    {{ totalAuditWorkedHours }} <span class="text-sm font-normal text-content-muted">hrs</span>
                </p>
                <div class="mt-2 text-[11px] text-content-muted">
                    {{ recentWorkSessions.length }} sessions intact in audit trail
                </div>
            </Card>
        </div>

        <!-- MAIN LAYOUT: Occurrences & Audit History -->
        <div class="space-y-8">
            <!-- SECTION 1: SCHEDULED OCCURRENCES FOR THIS DAY -->
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-border-subtle pb-3">
                    <div>
                        <h2 class="text-base font-semibold text-content-primary tracking-tight flex items-center gap-2">
                            <span>Today's Planned Occurrences</span>
                            <Badge variant="neutral" size="sm">{{ occurrences.length }}</Badge>
                        </h2>
                        <p class="text-xs text-content-secondary">
                            Execute your scheduled blocks: mark complete, reopen, or record actual focus work. (To configure rules, visit Manage Tasks &gt; Edit Task).
                        </p>
                    </div>

                    <Button
                        id="btn-edit-schedules"
                        variant="ghost"
                        size="sm"
                        icon="edit"
                        @click="router.visit('/tasks')"
                    >
                        Edit Schedules in Tasks &rarr;
                    </Button>
                </div>

                <!-- EMPTY STATE FOR OCCURRENCES -->
                <div v-if="occurrences.length === 0">
                    <EmptyState
                        title="No occurrences scheduled for this day"
                        description="Schedules and recurring rules are configured per-task. Go to Manage Tasks > Edit Task to plan occurrences for this day."
                        icon="calendar"
                    >
                        <template #action>
                            <Button
                                id="btn-goto-tasks-empty"
                                variant="primary"
                                icon="tasks"
                                @click="router.visit('/tasks')"
                            >
                                Go to Manage Tasks &rarr;
                            </Button>
                        </template>
                    </EmptyState>
                </div>

                <!-- LIST OF OCCURRENCES -->
                <div v-else class="space-y-3">
                    <div
                        v-for="occ in occurrences"
                        :key="occ.id"
                        :id="`occurrence-card-${occ.id}`"
                        :class="[
                            'group rounded-xl border p-4.5 transition-all flex flex-col md:flex-row md:items-center justify-between gap-4',
                            'bg-surface hover:border-border-strong',
                            occ.status === 'completed'
                                ? 'border-status-success/30 bg-surface-subdued/40'
                                : 'border-border-subtle shadow-xs',
                        ]"
                    >
                        <div class="flex items-start md:items-center gap-3.5 min-w-0">
                            <!-- Status Indicator Icon -->
                            <div
                                :class="[
                                    'w-9 h-9 rounded-full flex items-center justify-center shrink-0 transition-colors',
                                    occ.status === 'completed'
                                        ? 'bg-status-success-subdued text-status-success'
                                        : 'bg-primary-subdued text-primary',
                                ]"
                            >
                                <Icon
                                    :name="occ.status === 'completed' ? 'check-circle' : 'clock'"
                                    :size="18"
                                />
                            </div>

                            <!-- Occurrence Details -->
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3
                                        :class="[
                                            'text-sm font-semibold tracking-tight transition-colors',
                                            occ.status === 'completed'
                                                ? 'text-content-secondary line-through'
                                                : 'text-content-primary',
                                        ]"
                                    >
                                        {{ occ.task?.title || 'Untitled Task' }}
                                    </h3>

                                    <!-- Status Badges -->
                                    <Badge
                                        v-if="occ.status === 'completed'"
                                        variant="completed"
                                        size="sm"
                                        dot
                                    >
                                        Completed
                                    </Badge>
                                    <Badge
                                        v-else-if="occ.status === 'pending'"
                                        variant="planned"
                                        size="sm"
                                        dot
                                    >
                                        Pending
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="neutral"
                                        size="sm"
                                    >
                                        {{ occ.status }}
                                    </Badge>

                                    <!-- Recorded Work Indicator -->
                                    <span
                                        v-if="occ.work_sessions && occ.work_sessions.length > 0"
                                        class="text-xs bg-primary-subdued/70 text-primary border border-primary/20 px-2 py-0.5 rounded-full font-medium flex items-center gap-1"
                                    >
                                        <Icon name="check" :size="12" />
                                        {{ occ.work_sessions.length }} session{{ occ.work_sessions.length === 1 ? '' : 's' }} recorded ({{ formatMinutes(occ.work_sessions.reduce((s, w) => s + w.duration_minutes, 0)) }})
                                    </span>
                                </div>

                                <div class="flex flex-wrap items-center gap-3 text-xs text-content-muted mt-1.5">
                                    <span class="flex items-center gap-1 font-mono text-content-secondary">
                                        <Icon name="clock" :size="12" />
                                        {{ formatTimeOnly(occ.start_time) }}
                                    </span>
                                    <span>&bull;</span>
                                    <span>Planned: {{ formatMinutes(occ.duration_minutes) }}</span>
                                    <span v-if="occ.recurring_schedule">&bull;</span>
                                    <span v-if="occ.recurring_schedule" class="capitalize">
                                        {{ occ.recurring_schedule.type.replace('_', ' ') }}
                                    </span>
                                    <span v-if="occ.completed_at">&bull;</span>
                                    <span v-if="occ.completed_at" class="text-status-success">
                                        Done at {{ occ.completed_at.substring(11, 16) }}
                                    </span>
                                </div>

                                <p v-if="occ.notes" class="text-xs text-content-secondary mt-1">
                                    {{ occ.notes }}
                                </p>
                            </div>
                        </div>

                        <!-- Action Controls -->
                        <div class="flex items-center gap-2 shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-border-subtle">
                            <!-- Record Actual Work Button -->
                            <Button
                                :id="`btn-record-work-${occ.id}`"
                                variant="subtle"
                                size="sm"
                                icon="clock"
                                @click="openWorkModalForOccurrence(occ)"
                            >
                                Record Work
                            </Button>

                            <!-- Complete / Reopen Action -->
                            <Button
                                v-if="occ.status === 'pending'"
                                :id="`btn-complete-${occ.id}`"
                                variant="secondary"
                                size="sm"
                                icon="check"
                                :loading="actionLoadingId === occ.id"
                                @click="markOccurrenceComplete(occ)"
                            >
                                Mark Complete
                            </Button>

                            <Button
                                v-else-if="occ.status === 'completed'"
                                :id="`btn-reopen-${occ.id}`"
                                variant="secondary"
                                size="sm"
                                icon="restore"
                                :loading="actionLoadingId === occ.id"
                                class="text-accent hover:text-accent font-medium"
                                @click="reopenOccurrence(occ)"
                            >
                                Reopen
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: RECORDED WORK SESSIONS & AUDIT TRAIL -->
            <div class="space-y-4 pt-4 border-t border-border-subtle">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-content-primary tracking-tight flex items-center gap-2">
                            <span>Recorded Work Sessions & Audit History</span>
                            <Badge variant="extra" size="sm">
                                {{ recentWorkSessions.length }} Total Intact
                            </Badge>
                        </h2>
                        <p class="text-xs text-content-secondary">
                            Immutable audit trail of actual time spent. Preserved permanently even when occurrences are completed or reopened.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <SegmentedControl
                            v-model="activeHistoryTab"
                            size="sm"
                            :options="[
                                { label: 'Selected Day (' + todayWorkSessions.length + ')', value: 'selected' },
                                { label: 'All Sessions (' + recentWorkSessions.length + ')', value: 'all' },
                            ]"
                        />

                        <Button
                            id="btn-log-adhoc-work"
                            variant="primary"
                            size="sm"
                            icon="plus"
                            @click="openWorkModalAdHoc(60)"
                        >
                            Log Unscheduled Work (1h)
                        </Button>
                    </div>
                </div>

                <!-- INTEGRITY GUARANTEE CALLOUT BANNER -->
                <div class="rounded-lg bg-surface-subdued/50 border border-primary/20 p-3.5 flex items-start gap-3">
                    <Icon name="sparkles" :size="18" class="text-primary shrink-0 mt-0.5" />
                    <div class="text-xs space-y-0.5 text-content-secondary">
                        <p class="font-medium text-content-primary">
                            Historical Integrity Protection Active
                        </p>
                        <p>
                            Work session records are independent entities. When you reopen or modify a schedule occurrence, all recorded work sessions, timestamps, durations, and notes remain <strong>completely intact and unmodified</strong>.
                        </p>
                    </div>
                </div>

                <!-- WORK SESSIONS LIST -->
                <div v-if="(activeHistoryTab === 'selected' ? todayWorkSessions : recentWorkSessions).length === 0">
                    <EmptyState
                        title="No work sessions recorded yet"
                        description="Use 'Record Work' on an occurrence or 'Log Unscheduled Work' to start building your work history."
                        icon="clock"
                    >
                        <template #action>
                            <Button
                                id="btn-empty-log-work"
                                variant="secondary"
                                icon="plus"
                                @click="openWorkModalAdHoc(60)"
                            >
                                Log 1 Hour Unscheduled Work
                            </Button>
                        </template>
                    </EmptyState>
                </div>

                <div v-else class="space-y-2.5">
                    <div
                        v-for="ws in (activeHistoryTab === 'selected' ? todayWorkSessions : recentWorkSessions)"
                        :key="ws.id"
                        :id="`work-session-card-${ws.id}`"
                        class="rounded-xl border border-border-subtle bg-surface p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:border-border-strong transition-colors"
                    >
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                <Icon name="clock" :size="16" />
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-semibold text-content-primary">
                                        {{ ws.task?.title || 'Task #' + ws.task_id }}
                                    </span>

                                    <!-- Duration Pill -->
                                    <Badge variant="planned" size="sm">
                                        {{ formatMinutes(ws.duration_minutes) }}
                                    </Badge>

                                    <!-- Occurrence Linkage Status -->
                                    <Badge
                                        v-if="ws.schedule_occurrence_id"
                                        variant="extra"
                                        size="sm"
                                        dot
                                    >
                                        Occurrence #{{ ws.schedule_occurrence_id }}
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="neutral"
                                        size="sm"
                                    >
                                        Unscheduled / Ad-hoc
                                    </Badge>
                                </div>

                                <div class="flex items-center gap-2 text-xs text-content-muted mt-1">
                                    <span>Started: {{ ws.started_at }}</span>
                                    <span v-if="ws.notes">&bull;</span>
                                    <span v-if="ws.notes" class="text-content-secondary italic">"{{ ws.notes }}"</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 text-xs font-mono text-content-muted self-end sm:self-auto">
                            <span class="text-status-success font-medium">✓ Intact</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- DIALOG: RECORD ACTUAL WORK SESSION         -->
        <!-- ========================================== -->
        <Dialog
            :open="workModalOpen"
            title="Record Work Session"
            description="Log actual time spent on a task. Work records are preserved permanently in your history."
            @close="workModalOpen = false"
        >
            <div class="space-y-4">
                <Select
                    v-model="workTaskId"
                    label="Task"
                    placeholder="Select task"
                    :options="taskOptions"
                    required
                />

                <Select
                    v-model="workOccurrenceId"
                    label="Link to Schedule Occurrence (Optional)"
                    :options="occurrenceOptions"
                    hint="Choose an occurrence to link this work session, or 'None' for ad-hoc unscheduled work."
                />

                <div class="grid grid-cols-2 gap-3">
                    <Input
                        v-model="workDate"
                        type="date"
                        label="Date"
                        required
                    />

                    <Input
                        v-model="workTime"
                        type="time"
                        label="Start Time"
                        required
                    />
                </div>

                <div>
                    <Input
                        v-model="workDurationMinutes"
                        type="number"
                        label="Actual Duration (minutes)"
                        placeholder="120"
                        required
                    />
                    <div class="flex items-center gap-2 mt-2">
                        <span class="text-xs text-content-muted">Quick Presets:</span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-xs py-0.5 px-2 h-6"
                            @click="workDurationMinutes = 60"
                        >
                            1 hr (60m)
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-xs py-0.5 px-2 h-6 font-semibold text-primary"
                            @click="workDurationMinutes = 120"
                        >
                            2 hrs (120m)
                        </Button>
                    </div>
                </div>

                <Input
                    v-model="workNotes"
                    label="Notes / Accomplishments"
                    placeholder="What did you achieve during this session?"
                />
            </div>

            <template #actions>
                <Button
                    variant="ghost"
                    :disabled="workSubmitting"
                    @click="workModalOpen = false"
                >
                    Cancel
                </Button>
                <Button
                    id="btn-submit-work"
                    variant="primary"
                    :loading="workSubmitting"
                    @click="submitWorkSession"
                >
                    Save Work Session ({{ (workDurationMinutes / 60).toFixed(1) }} hrs)
                </Button>
            </template>
        </Dialog>
    </AppLayout>
</template>
