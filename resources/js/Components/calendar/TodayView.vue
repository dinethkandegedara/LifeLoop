<script setup lang="ts">
import { computed } from 'vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Icon from '@/Components/ui/Icon.vue';

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
    occurrences: ScheduleOccurrence[];
    todayWorkSessions: WorkSession[];
    overdueOccurrences: ScheduleOccurrence[];
    selectedDate: string;
    todayDate: string;
    actionLoadingIds: Set<number>;
}>();

const emit = defineEmits<{
    (e: 'toggle-occurrence', occ: ScheduleOccurrence): void;
    (e: 'log-work-for-occurrence', occ: ScheduleOccurrence): void;
    (e: 'log-work-ad-hoc'): void;
    (e: 'manage-tasks'): void;
    (e: 'edit-work-session', ws: WorkSession): void;
    (e: 'delete-work-session', ws: WorkSession): void;
}>();

// Chronological sorted occurrences
const sortedOccurrences = computed(() => {
    return [...props.occurrences].sort((a, b) => {
        return a.start_time.localeCompare(b.start_time);
    });
});

// 4-Metric Summary Calculations
const plannedMinutes = computed(() => {
    return props.occurrences.reduce((sum, occ) => sum + (occ.duration_minutes || 0), 0);
});

const completedMinutes = computed(() => {
    return props.occurrences
        .filter((occ) => occ.status === 'completed')
        .reduce((sum, occ) => sum + (occ.duration_minutes || 0), 0);
});

const remainingMinutes = computed(() => {
    return Math.max(0, plannedMinutes.value - completedMinutes.value);
});

const actualMinutes = computed(() => {
    return props.todayWorkSessions.reduce((sum, ws) => sum + (ws.duration_minutes || 0), 0);
});

const plannedHoursStr = computed(() => (plannedMinutes.value / 60).toFixed(1));
const completedHoursStr = computed(() => (completedMinutes.value / 60).toFixed(1));
const remainingHoursStr = computed(() => (remainingMinutes.value / 60).toFixed(1));
const actualHoursStr = computed(() => (actualMinutes.value / 60).toFixed(1));

function formatTime12Hour(timeStr: string): string {
    if (!timeStr) return '';
    const clean = timeStr.substring(0, 5);
    const [h, m] = clean.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${String(m).padStart(2, '0')} ${ampm}`;
}

function formatMinutes(minutes: number): string {
    const hours = (minutes / 60).toFixed(1);
    const unit = Number(hours) === 1 ? 'hr' : 'hrs';
    return `${hours} ${unit} (${minutes}m)`;
}
</script>

<template>
    <div class="space-y-6">
        <!-- 4-METRIC SUMMARY ROW (Planned, Completed, Remaining, Actual) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <!-- 1. Planned Hours -->
            <Card compact class="bg-surface border-border-subtle hover:border-primary/30 transition-colors">
                <div class="flex items-center justify-between text-xs text-content-secondary">
                    <span class="font-medium">Planned Time</span>
                    <Icon name="clock" :size="15" class="text-primary" />
                </div>
                <p class="text-2xl font-bold tracking-tight text-content-primary mt-2 font-mono">
                    {{ plannedHoursStr }} <span class="text-xs font-normal text-content-muted">hrs</span>
                </p>
                <div class="mt-1.5 text-[11px] text-content-muted flex items-center gap-1">
                    <span>{{ occurrences.length }} scheduled task{{ occurrences.length === 1 ? '' : 's' }}</span>
                </div>
            </Card>

            <!-- 2. Completed Hours -->
            <Card compact class="bg-surface border-border-subtle hover:border-status-success/30 transition-colors">
                <div class="flex items-center justify-between text-xs text-content-secondary">
                    <span class="font-medium">Completed</span>
                    <Icon name="check-circle" :size="15" class="text-status-success" />
                </div>
                <p class="text-2xl font-bold tracking-tight text-status-success mt-2 font-mono">
                    {{ completedHoursStr }} <span class="text-xs font-normal text-content-muted">hrs</span>
                </p>
                <div class="mt-1.5 text-[11px] text-content-muted">
                    {{ occurrences.filter(o => o.status === 'completed').length }} of {{ occurrences.length }} done
                </div>
            </Card>

            <!-- 3. Remaining Hours -->
            <Card compact class="bg-surface border-border-subtle hover:border-accent/30 transition-colors">
                <div class="flex items-center justify-between text-xs text-content-secondary">
                    <span class="font-medium">Remaining</span>
                    <Icon name="calendar" :size="15" class="text-accent" />
                </div>
                <p class="text-2xl font-bold tracking-tight text-content-primary mt-2 font-mono">
                    {{ remainingHoursStr }} <span class="text-xs font-normal text-content-muted">hrs</span>
                </p>
                <div class="mt-1.5 text-[11px] text-content-muted">
                    {{ occurrences.filter(o => o.status !== 'completed').length }} left to execute
                </div>
            </Card>

            <!-- 4. Actual Logged Hours -->
            <Card compact class="bg-surface border-border-subtle hover:border-primary/30 transition-colors">
                <div class="flex items-center justify-between text-xs text-content-secondary">
                    <span class="font-medium">Actual Worked</span>
                    <Icon name="flame" :size="15" class="text-primary" />
                </div>
                <p class="text-2xl font-bold tracking-tight text-content-primary mt-2 font-mono">
                    {{ actualHoursStr }} <span class="text-xs font-normal text-content-muted">hrs</span>
                </p>
                <div class="mt-1.5 text-[11px] text-content-muted">
                    {{ todayWorkSessions.length }} focus session{{ todayWorkSessions.length === 1 ? '' : 's' }} recorded
                </div>
            </Card>
        </div>

        <!-- OVERDUE TASKS BANNER (IF ANY) -->
        <div
            v-if="overdueOccurrences.length > 0"
            class="rounded-2xl border border-status-danger/30 bg-status-danger-subdued/40 p-4 space-y-3 shadow-xs"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-status-danger/15 text-status-danger flex items-center justify-center font-bold">
                        <Icon name="alert-circle" :size="16" />
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-status-danger uppercase tracking-wider">
                            Overdue Tasks ({{ overdueOccurrences.length }})
                        </h3>
                        <p class="text-[11px] text-content-secondary">
                            Pending tasks from previous dates needing your attention.
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-2">
                <div
                    v-for="overdue in overdueOccurrences"
                    :key="`overdue-${overdue.id}`"
                    class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl bg-surface border border-status-danger/20 hover:border-status-danger/40 transition-colors"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <Checkbox
                            :model-value="overdue.status === 'completed'"
                            :disabled="actionLoadingIds.has(overdue.id)"
                            @update:model-value="emit('toggle-occurrence', overdue)"
                        />
                        <div class="min-w-0">
                            <span class="font-semibold text-xs text-content-primary truncate block">
                                {{ overdue.task?.title || 'Untitled Task' }}
                            </span>
                            <div class="flex items-center gap-2 text-[11px] text-content-muted flex-wrap">
                                <span class="text-status-danger font-medium">Scheduled {{ overdue.scheduled_date }}</span>
                                <span>•</span>
                                <span>{{ formatTime12Hour(overdue.start_time) }}</span>
                                <span>•</span>
                                <span>{{ (overdue.duration_minutes / 60).toFixed(1) }}h</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <Button
                            variant="secondary"
                            size="sm"
                            icon="plus"
                            @click="emit('log-work-for-occurrence', overdue)"
                        >
                            Log Work
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TODAY'S SCHEDULED OCCURRENCES SECTION -->
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-border-subtle pb-3">
                <div class="flex items-center gap-2.5">
                    <h2 class="text-base font-semibold text-content-primary tracking-tight">
                        Scheduled Sessions
                    </h2>
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-surface-subdued border border-border-subtle text-content-muted">
                        {{ sortedOccurrences.length }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        icon="tasks"
                        @click="emit('manage-tasks')"
                    >
                        Edit Schedules &rarr;
                    </Button>
                    <Button
                        variant="primary"
                        size="sm"
                        icon="plus"
                        @click="emit('log-work-ad-hoc')"
                    >
                        Log Work Session
                    </Button>
                </div>
            </div>

            <!-- EMPTY STATE -->
            <div v-if="sortedOccurrences.length === 0">
                <EmptyState
                    title="No work scheduled for this day"
                    description="You have no planned work blocks for this date. You can add recurring schedules in Manage Tasks, or log an ad-hoc focus session directly."
                    icon="calendar"
                >
                    <template #action>
                        <div class="flex items-center gap-2 mt-2">
                            <Button
                                variant="primary"
                                icon="plus"
                                size="sm"
                                @click="emit('log-work-ad-hoc')"
                            >
                                Log Work Session
                            </Button>
                            <Button
                                variant="secondary"
                                icon="tasks"
                                size="sm"
                                @click="emit('manage-tasks')"
                            >
                                Manage Tasks & Schedules
                            </Button>
                        </div>
                    </template>
                </EmptyState>
            </div>

            <!-- CHRONOLOGICAL LIST OF OCCURRENCES -->
            <div v-else class="space-y-2.5">
                <div
                    v-for="occ in sortedOccurrences"
                    :key="occ.id"
                    :id="`occurrence-card-${occ.id}`"
                    :class="[
                        'group rounded-2xl border p-4 transition-all flex flex-col md:flex-row md:items-center justify-between gap-4',
                        'bg-surface hover:border-border-strong',
                        occ.status === 'completed'
                            ? 'border-status-success/30 bg-surface-subdued/30 opacity-90'
                            : 'border-border-subtle shadow-xs',
                    ]"
                >
                    <!-- Left: Checkbox + Title + Time + Badges -->
                    <div class="flex items-start md:items-center gap-3.5 min-w-0">
                        <!-- Direct Completion Checkbox -->
                        <div class="pt-0.5 md:pt-0">
                            <Checkbox
                                :id="`checkbox-occ-${occ.id}`"
                                :model-value="occ.status === 'completed'"
                                :disabled="actionLoadingIds.has(occ.id)"
                                @update:model-value="emit('toggle-occurrence', occ)"
                            />
                        </div>

                        <!-- Occurrence Info -->
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

                                <Badge
                                    v-if="occ.status === 'completed'"
                                    variant="completed"
                                    size="sm"
                                    dot
                                >
                                    Completed
                                </Badge>
                                <Badge
                                    v-else
                                    variant="planned"
                                    size="sm"
                                    dot
                                >
                                    Planned
                                </Badge>

                                <span
                                    v-if="occ.is_exception"
                                    class="text-[10px] font-medium px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-500 border border-amber-500/20"
                                >
                                    Exception
                                </span>
                            </div>

                            <!-- Meta details row -->
                            <div class="flex items-center gap-2.5 text-xs text-content-secondary mt-1 flex-wrap">
                                <span class="flex items-center gap-1 font-mono font-medium text-content-primary">
                                    <Icon name="clock" :size="12" class="text-primary opacity-80" />
                                    {{ formatTime12Hour(occ.start_time) }}
                                </span>
                                <span class="text-content-muted">•</span>
                                <span class="font-mono">{{ (occ.duration_minutes / 60).toFixed(1) }} hrs planned</span>
                                <span v-if="occ.recurring_schedule" class="text-content-muted">•</span>
                                <span v-if="occ.recurring_schedule" class="capitalize text-content-muted">
                                    {{ occ.recurring_schedule.type.replace('_', ' ') }}
                                </span>
                            </div>

                            <p v-if="occ.notes" class="text-xs text-content-muted mt-1 italic">
                                "{{ occ.notes }}"
                            </p>
                        </div>
                    </div>

                    <!-- Right: Quick Actions -->
                    <div class="flex items-center gap-2 shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-border-subtle/50">
                        <Button
                            variant="secondary"
                            size="sm"
                            icon="plus"
                            @click="emit('log-work-for-occurrence', occ)"
                            title="Record actual time spent on this task occurrence"
                        >
                            Log Work
                        </Button>
                        <Button
                            v-if="occ.status === 'completed'"
                            variant="ghost"
                            size="sm"
                            :disabled="actionLoadingIds.has(occ.id)"
                            @click="emit('toggle-occurrence', occ)"
                        >
                            Reopen
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- RECORDED WORK SESSIONS ON THIS DAY -->
        <div v-if="todayWorkSessions.length > 0" class="pt-4 border-t border-border-subtle space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-content-primary flex items-center gap-2">
                    <Icon name="check-circle" :size="16" class="text-status-success" />
                    <span>Actual Work Recorded on This Day</span>
                </h3>
                <span class="text-xs font-mono text-content-muted font-medium">
                    Total: {{ actualHoursStr }} hrs
                </span>
            </div>

            <div class="space-y-2">
                <div
                    v-for="ws in todayWorkSessions"
                    :key="`work-${ws.id}`"
                    class="group flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-2xl bg-surface border border-border-subtle/80 hover:border-border-strong text-xs transition-all shadow-2xs"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-xl bg-status-success/15 text-status-success flex items-center justify-center shrink-0">
                            <Icon name="check-circle" :size="16" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-xs text-content-primary truncate block">
                                    {{ ws.task?.title || 'Focus Session' }}
                                </span>
                                <span v-if="ws.occurrence" class="text-[10px] px-2 py-0.5 rounded-md bg-surface-subdued text-content-muted border border-border-subtle/60">
                                    Occurrence #{{ ws.occurrence.id }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 text-[11px] text-content-muted mt-0.5 flex-wrap">
                                <span class="font-mono text-content-secondary font-medium">
                                    {{ ws.started_at ? ws.started_at.substring(11, 16) : '' }}
                                </span>
                                <span v-if="ws.notes">•</span>
                                <span v-if="ws.notes" class="italic text-content-secondary truncate max-w-sm">{{ ws.notes }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5 shrink-0 self-end sm:self-center">
                        <span class="font-mono font-bold text-content-primary px-2.5 py-1 rounded-xl bg-surface-subdued border border-border-subtle/60">
                            {{ (ws.duration_minutes / 60).toFixed(1) }} hrs
                        </span>

                        <!-- Action Buttons: Edit & Delete -->
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                class="p-1.5 rounded-lg text-content-muted hover:text-content-primary hover:bg-surface-subdued transition-colors cursor-pointer"
                                title="Edit this work session"
                                @click="emit('edit-work-session', ws)"
                            >
                                <Icon name="edit" :size="14" />
                            </button>
                            <button
                                type="button"
                                class="p-1.5 rounded-lg text-content-muted hover:text-status-danger hover:bg-status-danger/10 transition-colors cursor-pointer"
                                title="Delete this work session"
                                @click="emit('delete-work-session', ws)"
                            >
                                <Icon name="trash" :size="14" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
