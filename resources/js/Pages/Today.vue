<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Components/layout/AppLayout.vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Input from '@/Components/ui/Input.vue';
import Select, { type SelectOption } from '@/Components/ui/Select.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Icon from '@/Components/ui/Icon.vue';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';
import TodayView from '@/Components/calendar/TodayView.vue';
import WeekView from '@/Components/calendar/WeekView.vue';
import MonthView from '@/Components/calendar/MonthView.vue';
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

const props = withDefaults(
    defineProps<{
        tasks: Task[];
        occurrences: ScheduleOccurrence[];
        todayWorkSessions: WorkSession[];
        recentWorkSessions: WorkSession[];
        todayDate: string;
        selectedDate: string;
        userTimezone: string;
        currentView?: 'today' | 'week' | 'month';
        rangeStart?: string;
        rangeEnd?: string;
        rangeWorkSessions?: WorkSession[];
        overdueOccurrences?: ScheduleOccurrence[];
    }>(),
    {
        currentView: 'today',
        rangeStart: '',
        rangeEnd: '',
        rangeWorkSessions: () => [],
        overdueOccurrences: () => [],
    }
);

const page = usePage();
const toast = useToast();

// Active calendar view ('today' | 'week' | 'month')
const calendarView = ref<'today' | 'week' | 'month'>(props.currentView || 'today');
watch(
    () => props.currentView,
    (v) => {
        if (v && v !== calendarView.value) {
            calendarView.value = v;
        }
    }
);

const userName = computed(() => {
    const user = (page.props.auth as any)?.user;
    if (user?.name) {
        return user.name.trim().split(/\s+/)[0];
    }
    return 'Friend';
});

const isToday = computed(() => props.selectedDate === props.todayDate);

const formattedSelectedDate = computed(() => {
    if (!props.selectedDate) return '';
    const parts = props.selectedDate.split('-').map(Number);
    const dateObj = new Date(parts[0], parts[1] - 1, parts[2]);
    return new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(dateObj);
});

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
});

// ==========================================
// NAVIGATION & CONTEXT PRESERVATION
// ==========================================
function switchCalendarView(newView: 'today' | 'week' | 'month') {
    if (calendarView.value === newView) return;
    calendarView.value = newView;
    router.get(
        '/',
        { view: newView, date: props.selectedDate },
        { preserveState: true, preserveScroll: true }
    );
}

function handleSidebarNavigate(tab: string) {
    if (tab === 'tasks') {
        router.visit('/tasks');
    } else if (['today', 'week', 'month'].includes(tab)) {
        switchCalendarView(tab as any);
    }
}

function drillToDay(dateStr: string) {
    calendarView.value = 'today';
    router.get(
        '/',
        { view: 'today', date: dateStr },
        { preserveState: true, preserveScroll: true }
    );
}

function navigateDay(offsetDays: number) {
    const parts = props.selectedDate.split('-').map(Number);
    const d = new Date(parts[0], parts[1] - 1, parts[2]);
    d.setDate(d.getDate() + offsetDays);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const targetDate = `${yyyy}-${mm}-${dd}`;
    router.get(
        '/',
        { view: 'today', date: targetDate },
        { preserveState: true, preserveScroll: true }
    );
}

function navigateWeek(offsetWeeks: number) {
    const parts = props.selectedDate.split('-').map(Number);
    const d = new Date(parts[0], parts[1] - 1, parts[2]);
    d.setDate(d.getDate() + offsetWeeks * 7);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const targetDate = `${yyyy}-${mm}-${dd}`;
    router.get(
        '/',
        { view: 'week', date: targetDate },
        { preserveState: true, preserveScroll: true }
    );
}

function navigateMonth(offsetMonths: number) {
    const parts = props.selectedDate.split('-').map(Number);
    const d = new Date(parts[0], parts[1] - 1, 1);
    d.setMonth(d.getMonth() + offsetMonths);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const targetDate = `${yyyy}-${mm}-${dd}`;
    router.get(
        '/',
        { view: 'month', date: targetDate },
        { preserveState: true, preserveScroll: true }
    );
}

function goToToday() {
    router.get(
        '/',
        { view: calendarView.value, date: props.todayDate },
        { preserveState: true, preserveScroll: true }
    );
}

function goToSpecificDate(dateStr: string) {
    if (!dateStr) return;
    router.get(
        '/',
        { view: calendarView.value, date: dateStr },
        { preserveState: true, preserveScroll: true }
    );
}

// ==========================================
// OPTIMISTIC OCCURRENCE STATUS TOGGLE
// ==========================================
const actionLoadingIds = ref<Set<number>>(new Set());

async function toggleOccurrenceStatus(occ: ScheduleOccurrence) {
    if (actionLoadingIds.value.has(occ.id)) return; // Prevent duplicate clicks

    const originalStatus = occ.status;
    const originalCompletedAt = occ.completed_at;
    const isNowCompleting = originalStatus !== 'completed';

    // 1. Optimistic Mutation
    actionLoadingIds.value.add(occ.id);
    occ.status = isNowCompleting ? 'completed' : 'pending';
    occ.completed_at = isNowCompleting ? new Date().toISOString() : null;

    try {
        const endpoint = isNowCompleting
            ? `/occurrences/${occ.id}/complete`
            : `/occurrences/${occ.id}/reopen`;

        await axios.patch(endpoint, {}, {
            headers: { Accept: 'application/json' },
        });

        toast.success(isNowCompleting ? 'Occurrence marked completed!' : 'Occurrence reopened.');
    } catch (err: any) {
        // Rollback state on network/server error
        occ.status = originalStatus;
        occ.completed_at = originalCompletedAt;
        const msg = err.response?.data?.message || 'Failed to update occurrence status.';
        toast.danger(msg);
    } finally {
        actionLoadingIds.value.delete(occ.id);
    }
}

// ==========================================
// RECORD WORK SESSION MODAL (ACCESSIBLE IN ALL VIEWS)
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
            label: `[#${occ.id}] ${occ.scheduled_date} at ${timeFormatted} (${(occ.duration_minutes / 60).toFixed(1)}h planned)`,
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

// Edit & Delete Work Session State
const isEditingWorkSession = ref(false);
const editingWorkSessionId = ref<number | null>(null);

const deleteWorkModalOpen = ref(false);
const sessionToDelete = ref<WorkSession | null>(null);
const isDeletingWork = ref(false);

function openWorkModalForOccurrence(occ: ScheduleOccurrence) {
    isEditingWorkSession.value = false;
    editingWorkSessionId.value = null;
    workTaskId.value = occ.task_id;
    workOccurrenceId.value = occ.id;
    workDate.value = occ.scheduled_date;
    workTime.value = occ.start_time.substring(0, 5);
    workDurationMinutes.value = occ.duration_minutes || 120;
    workNotes.value = `Completed session for ${occ.task.title}`;
    workModalOpen.value = true;
}

function openWorkModalAdHoc(dateStr?: string, presetDuration = 60) {
    isEditingWorkSession.value = false;
    editingWorkSessionId.value = null;
    workTaskId.value = props.tasks.length > 0 ? props.tasks[0].id : '';
    workOccurrenceId.value = '';
    workDate.value = dateStr || props.selectedDate;
    workTime.value = '14:00';
    workDurationMinutes.value = presetDuration;
    workNotes.value = '';
    workModalOpen.value = true;
}

function openEditWorkSessionModal(ws: WorkSession) {
    isEditingWorkSession.value = true;
    editingWorkSessionId.value = ws.id;
    workTaskId.value = ws.task_id;
    workOccurrenceId.value = ws.schedule_occurrence_id || '';
    workDate.value = ws.started_at ? ws.started_at.substring(0, 10) : props.selectedDate;
    workTime.value = ws.started_at ? ws.started_at.substring(11, 16) : '10:00';
    workDurationMinutes.value = ws.duration_minutes || 60;
    workNotes.value = ws.notes || '';
    workModalOpen.value = true;
}

function openDeleteWorkModal(ws: WorkSession) {
    sessionToDelete.value = ws;
    deleteWorkModalOpen.value = true;
}

function confirmDeleteWorkSession() {
    if (!sessionToDelete.value) return;

    isDeletingWork.value = true;
    router.delete(`/work-sessions/${sessionToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteWorkModalOpen.value = false;
            sessionToDelete.value = null;
            toast.success('Work session deleted successfully!');
        },
        onError: (errors) => {
            const first = Object.values(errors)[0] as string;
            toast.danger(first || 'Failed to delete work session.');
        },
        onFinish: () => {
            isDeletingWork.value = false;
        },
    });
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
    const payload = {
        task_id: Number(workTaskId.value),
        schedule_occurrence_id: workOccurrenceId.value ? Number(workOccurrenceId.value) : null,
        started_at: startedAt,
        duration_minutes: Number(workDurationMinutes.value),
        notes: workNotes.value.trim() || null,
    };

    if (isEditingWorkSession.value && editingWorkSessionId.value) {
        router.put(
            `/work-sessions/${editingWorkSessionId.value}`,
            payload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    workModalOpen.value = false;
                    isEditingWorkSession.value = false;
                    editingWorkSessionId.value = null;
                    toast.success('Work session updated successfully!');
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0] as string;
                    toast.danger(first || 'Failed to update work session.');
                },
                onFinish: () => {
                    workSubmitting.value = false;
                },
            }
        );
    } else {
        router.post(
            '/work-sessions',
            payload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    workModalOpen.value = false;
                    toast.success('Work session recorded successfully!');
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
}
</script>

<template>
    <Head :title="`${calendarView.toUpperCase()} - LifeLoop`" />

    <AppLayout current-tab="schedule" @navigate="handleSidebarNavigate">
        <!-- TOP PAGE HEADER WITH TITLE, GREETING & PRIMARY ACTIONS -->
        <PageHeader
            :title="calendarView === 'today' ? `${greeting}, ${userName}` : calendarView === 'week' ? 'Weekly Calendar' : 'Monthly Overview'"
            :subtitle="
                calendarView === 'today'
                    ? 'Track scheduled occurrences and focus sessions'
                    : calendarView === 'week'
                    ? `7-day commitment overview in ${userTimezone} timezone`
                    : `Monthly distribution in ${userTimezone} timezone`
            "
        >
            <template #actions>
                <Button
                    variant="secondary"
                    size="sm"
                    icon="tasks"
                    @click="router.visit('/tasks')"
                >
                    Manage Tasks
                </Button>
                <Button
                    variant="primary"
                    size="sm"
                    icon="plus"
                    @click="openWorkModalAdHoc()"
                >
                    Log Work
                </Button>
                <ThemeToggle variant="button" />
            </template>
        </PageHeader>

        <!-- CONSOLIDATED DASHBOARD CONTROL TOOLBAR -->
        <div class="p-3 sm:p-3.5 rounded-2xl bg-surface border border-border-subtle shadow-xs mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <!-- Left: Today / Week / Month Switcher -->
            <div class="flex items-center">
                <SegmentedControl
                    :model-value="calendarView"
                    @update:model-value="switchCalendarView($event as any)"
                    :options="[
                        { label: 'Today', value: 'today' },
                        { label: 'Week', value: 'week' },
                        { label: 'Month', value: 'month' },
                    ]"
                />
            </div>

            <!-- Right: Contextual Date Navigator (In Today View) -->
            <div v-if="calendarView === 'today'" class="flex flex-wrap items-center gap-2">
                <!-- Day Stepper: Prev / Today / Next -->
                <div class="flex items-center gap-1 bg-surface-subdued border border-border-subtle/80 rounded-xl p-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="navigateDay(-1)"
                        title="Previous Day"
                        class="px-2"
                    >
                        <Icon name="chevron-left" :size="16" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="isToday ? 'bg-primary/10 text-primary font-semibold' : 'text-content-secondary'"
                        @click="goToToday"
                    >
                        Today
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="navigateDay(1)"
                        title="Next Day"
                        class="px-2"
                    >
                        <Icon name="chevron-right" :size="16" />
                    </Button>
                </div>

                <!-- Single Interactive Date Pill (Clicking anywhere opens native picker) -->
                <div class="relative flex items-center">
                    <label
                        class="group flex items-center gap-2 px-3 py-1.5 rounded-xl bg-surface-subdued hover:bg-surface-hover border border-border-subtle hover:border-primary/40 text-xs font-medium text-content-primary cursor-pointer transition-all shadow-2xs"
                        title="Click to jump to date"
                    >
                        <Icon name="calendar" :size="14" class="text-primary group-hover:scale-110 transition-transform" />
                        <span class="font-semibold">{{ formattedSelectedDate }}</span>
                        <Badge v-if="isToday" variant="extra" size="sm">Today</Badge>
                        <Badge v-else variant="neutral" size="sm">Selected</Badge>

                        <!-- Native hidden date input covering the label -->
                        <input
                            type="date"
                            :value="selectedDate"
                            class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                            @change="goToSpecificDate(($event.target as HTMLInputElement).value)"
                        />
                    </label>
                </div>
            </div>
        </div>

        <!-- VIEW 1: TODAY WORKSPACE -->
        <div v-if="calendarView === 'today'">
            <TodayView
                :occurrences="occurrences"
                :today-work-sessions="todayWorkSessions"
                :overdue-occurrences="overdueOccurrences"
                :selected-date="selectedDate"
                :today-date="todayDate"
                :action-loading-ids="actionLoadingIds"
                @toggle-occurrence="toggleOccurrenceStatus"
                @log-work-for-occurrence="openWorkModalForOccurrence"
                @log-work-ad-hoc="openWorkModalAdHoc()"
                @manage-tasks="router.visit('/tasks')"
                @edit-work-session="openEditWorkSessionModal"
                @delete-work-session="openDeleteWorkModal"
            />
        </div>

        <!-- VIEW 2: WEEK CALENDAR -->
        <div v-else-if="calendarView === 'week'">
            <WeekView
                :occurrences="occurrences"
                :selected-date="selectedDate"
                :today-date="todayDate"
                :range-start="rangeStart"
                :range-end="rangeEnd"
                :action-loading-ids="actionLoadingIds"
                @navigate-week="navigateWeek"
                @go-to-today="goToToday"
                @drill-to-day="drillToDay"
                @toggle-occurrence="toggleOccurrenceStatus"
                @log-work-for-occurrence="openWorkModalForOccurrence"
            />
        </div>

        <!-- VIEW 3: MONTH CALENDAR -->
        <div v-else-if="calendarView === 'month'">
            <MonthView
                :occurrences="occurrences"
                :range-work-sessions="rangeWorkSessions"
                :selected-date="selectedDate"
                :today-date="todayDate"
                :range-start="rangeStart"
                :range-end="rangeEnd"
                :action-loading-ids="actionLoadingIds"
                @navigate-month="navigateMonth"
                @go-to-today="goToToday"
                @drill-to-day="drillToDay"
                @toggle-occurrence="toggleOccurrenceStatus"
                @log-work-for-occurrence="openWorkModalForOccurrence"
                @log-work-ad-hoc-date="openWorkModalAdHoc($event)"
            />
        </div>

        <!-- UNIFIED RECORD / EDIT WORK SESSION MODAL -->
        <Dialog
            :open="workModalOpen"
            :title="isEditingWorkSession ? 'Edit Work Session' : 'Log Actual Work Session'"
            @close="workModalOpen = false"
        >
            <form @submit.prevent="submitWorkSession" class="space-y-4">
                <p class="text-xs text-content-secondary">
                    {{ isEditingWorkSession ? 'Update your recorded focus time or notes for this work session.' : 'Record time actually spent on a task. Work records are preserved in your audit trail.' }}
                </p>

                <!-- Task Select -->
                <div>
                    <label class="block text-xs font-semibold text-content-primary mb-1">
                        Task *
                    </label>
                    <Select
                        v-model="workTaskId"
                        :options="taskOptions"
                        placeholder="Choose a task..."
                        required
                    />
                </div>

                <!-- Occurrence Link (Optional) -->
                <div>
                    <label class="block text-xs font-semibold text-content-primary mb-1">
                        Linked Planned Occurrence (Optional)
                    </label>
                    <Select
                        v-model="workOccurrenceId"
                        :options="occurrenceOptions"
                    />
                </div>

                <!-- Date & Time -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-content-primary mb-1">
                            Started Date *
                        </label>
                        <Input
                            v-model="workDate"
                            type="date"
                            required
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-content-primary mb-1">
                            Started Time *
                        </label>
                        <Input
                            v-model="workTime"
                            type="time"
                            required
                        />
                    </div>
                </div>

                <!-- Duration in Minutes with Quick Chips -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-semibold text-content-primary">
                            Duration (Minutes) *
                        </label>
                        <span class="text-xs font-mono font-medium text-primary">
                            {{ (workDurationMinutes / 60).toFixed(1) }} hrs
                        </span>
                    </div>
                    <Input
                        v-model.number="workDurationMinutes"
                        type="number"
                        min="1"
                        step="1"
                        required
                    />

                    <!-- Quick Preset Chips -->
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        <button
                            v-for="mins in [30, 60, 90, 120, 180]"
                            :key="mins"
                            type="button"
                            :class="[
                                'px-2 py-0.5 text-xs rounded-full border transition-all cursor-pointer',
                                workDurationMinutes === mins
                                    ? 'bg-primary text-primary-text border-primary font-bold shadow-xs'
                                    : 'bg-surface text-content-secondary border-border-subtle hover:bg-surface-hover',
                            ]"
                            @click="workDurationMinutes = mins"
                        >
                            {{ mins / 60 }} {{ mins === 60 ? 'hr' : 'hrs' }}
                        </button>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-xs font-semibold text-content-primary mb-1">
                        Session Notes / Accomplishments
                    </label>
                    <textarea
                        v-model="workNotes"
                        rows="3"
                        class="w-full text-xs bg-surface text-content-primary border border-border-subtle rounded-xl p-2.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none"
                        placeholder="What did you work on or finish?"
                    />
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border-subtle">
                    <Button
                        variant="ghost"
                        type="button"
                        @click="workModalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="primary"
                        type="submit"
                        :disabled="workSubmitting"
                    >
                        {{ workSubmitting ? (isEditingWorkSession ? 'Updating...' : 'Recording...') : (isEditingWorkSession ? 'Update Work Record' : 'Save Work Record') }}
                    </Button>
                </div>
            </form>
        </Dialog>

        <!-- DELETE WORK SESSION CONFIRMATION DIALOG -->
        <Dialog
            :open="deleteWorkModalOpen"
            title="Delete Work Session"
            @close="deleteWorkModalOpen = false"
        >
            <div class="space-y-4">
                <p class="text-xs text-content-secondary">
                    Are you sure you want to delete this recorded work session?
                </p>

                <div v-if="sessionToDelete" class="p-3.5 rounded-2xl bg-surface-subdued border border-border-subtle text-xs space-y-1.5">
                    <div class="font-bold text-content-primary text-sm">
                        {{ sessionToDelete.task?.title || 'Focus Session' }}
                    </div>
                    <div class="text-[11px] text-content-muted flex items-center gap-2">
                        <span>Started: {{ sessionToDelete.started_at ? sessionToDelete.started_at.substring(0, 16) : '' }}</span>
                        <span>•</span>
                        <span class="font-bold text-primary">{{ (sessionToDelete.duration_minutes / 60).toFixed(1) }} hrs</span>
                    </div>
                    <p v-if="sessionToDelete.notes" class="text-[11px] text-content-secondary italic pt-0.5">
                        "{{ sessionToDelete.notes }}"
                    </p>
                </div>

                <p class="text-[11px] text-status-danger font-medium">
                    This action will remove this focus record from your schedule statistics and audit trail.
                </p>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border-subtle">
                    <Button
                        variant="ghost"
                        type="button"
                        @click="deleteWorkModalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="danger"
                        type="button"
                        :disabled="isDeletingWork"
                        @click="confirmDeleteWorkSession"
                    >
                        {{ isDeletingWork ? 'Deleting...' : 'Delete Work Record' }}
                    </Button>
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
