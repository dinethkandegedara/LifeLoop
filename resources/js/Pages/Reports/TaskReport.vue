<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Components/layout/AppLayout.vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Icon from '@/Components/ui/Icon.vue';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';

interface Task {
    id: number;
    title: string;
    description: string | null;
    status: 'active' | 'archived';
}

interface Metrics {
    start_date: string;
    end_date: string;
    scheduled_hours: number;
    completed_planned_hours: number;
    remaining_scheduled_hours: number;
    skipped_hours: number;
    scheduled_occurrences_count: number;
    completed_occurrences_count: number;
    incomplete_occurrences_count: number;
    skipped_occurrences_count: number;
    total_occurrences_count: number;
    occurrence_completion_rate: number;
    planned_hour_completion_rate: number;
    actual_hours: number;
    unscheduled_actual_hours: number;
    scheduled_actual_hours: number;
    work_session_count: number;
    overdue_incomplete_count: number;
}

interface TrendItem {
    date?: string;
    day_name?: string;
    day_num?: string;
    label?: string;
    week_number?: number;
    month_key?: string;
    scheduled_hours: number;
    completed_hours: number;
    actual_hours: number;
    completion_rate: number;
}

interface ScheduleOccurrence {
    id: number;
    scheduled_date: string;
    start_time: string;
    duration_minutes: number;
    status: 'pending' | 'completed' | 'skipped' | 'cancelled';
    completed_at: string | null;
    is_exception: boolean;
    notes: string | null;
}

interface WorkSession {
    id: number;
    started_at: string;
    duration_minutes: number;
    schedule_occurrence_id: number | null;
    notes: string | null;
    occurrence?: { id: number; scheduled_date: string; start_time: string } | null;
}

const props = defineProps<{
    task: Task;
    period: 'today' | 'week' | 'month' | 'all' | 'custom';
    period_label: string;
    start_date: string;
    end_date: string;
    anchor_date?: string;
    is_current?: boolean;
    prev_date?: string | null;
    next_date?: string | null;
    can_go_next?: boolean;
    status_filter: string;
    metrics: Metrics;
    daily_trends: TrendItem[];
    weekly_trends: TrendItem[];
    monthly_trends: TrendItem[];
    occurrences_history: ScheduleOccurrence[];
    work_sessions_history: WorkSession[];
}>();

const selectedPeriod = ref(props.period);
const selectedStatusFilter = ref(props.status_filter || 'all');
const activeHistoryTab = ref<'occurrences' | 'work_sessions'>('occurrences');
const activeTrendTab = ref<'daily' | 'weekly' | 'monthly'>('weekly');

const customFrom = ref(props.start_date);
const customTo = ref(props.end_date);
const showCustomRange = ref(props.period === 'custom');

function applyFilters(targetDate?: string | null) {
    const params: Record<string, any> = {
        period: selectedPeriod.value,
        status: selectedStatusFilter.value !== 'all' ? selectedStatusFilter.value : undefined,
    };
    if (targetDate) {
        params.date = targetDate;
    } else if (props.anchor_date && !['custom', 'all'].includes(selectedPeriod.value)) {
        params.date = props.anchor_date;
    }
    if (selectedPeriod.value === 'custom') {
        params.from = customFrom.value;
        params.to = customTo.value;
    }

    router.get(`/tasks/${props.task.id}/report`, params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function handlePeriodChange(p: string) {
    selectedPeriod.value = p as any;
    if (p === 'custom') {
        showCustomRange.value = true;
    } else {
        showCustomRange.value = false;
        applyFilters(props.anchor_date || null);
    }
}

function setStatusFilter(status: string) {
    selectedStatusFilter.value = status;
    applyFilters(props.anchor_date || null);
}

function navigatePeriod(targetDate: string | null | undefined) {
    if (!targetDate) return;
    applyFilters(targetDate);
}

function handleDirectDateChange(dateStr: string) {
    if (!dateStr) return;
    applyFilters(dateStr);
}

function resetToCurrent() {
    const params: Record<string, any> = {
        period: selectedPeriod.value,
        status: selectedStatusFilter.value !== 'all' ? selectedStatusFilter.value : undefined,
    };
    router.get(`/tasks/${props.task.id}/report`, params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function formatTime12Hour(timeStr: string): string {
    if (!timeStr) return '';
    const clean = timeStr.substring(0, 5);
    const [h, m] = clean.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${String(m).padStart(2, '0')} ${ampm}`;
}

const currentTrendList = computed(() => {
    if (activeTrendTab.value === 'daily') return props.daily_trends;
    if (activeTrendTab.value === 'weekly') return props.weekly_trends;
    return props.monthly_trends;
});

const maxTrendValue = computed(() => {
    let max = 1;
    currentTrendList.value.forEach((t) => {
        if (t.scheduled_hours > max) max = t.scheduled_hours;
        if (t.actual_hours > max) max = t.actual_hours;
    });
    return max;
});
</script>

<template>
    <Head :title="`${task.title} - Performance Report - LifeLoop`" />

    <AppLayout current-tab="reports">
        <!-- HEADER & BREADCRUMBS -->
        <div class="space-y-6 mb-8">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-2 text-xs">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="router.visit('/reports')"
                    >
                        &larr; All Reports
                    </Button>
                    <span class="text-content-muted">/</span>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="router.visit('/tasks')"
                    >
                        Manage Tasks
                    </Button>
                </div>

                <div class="flex items-center gap-3">
                    <ThemeToggle variant="button" />
                </div>
            </div>

            <!-- Page Title with Status Badge -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border-subtle pb-4">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-content-primary">
                            {{ task.title }}
                        </h1>
                        <Badge :variant="task.status === 'active' ? 'planned' : 'neutral'" size="sm">
                            {{ task.status }}
                        </Badge>
                    </div>
                    <p v-if="task.description" class="text-xs text-content-secondary mt-1">
                        {{ task.description }}
                    </p>
                    <p class="text-xs text-content-muted mt-1 font-medium">
                        Period: {{ period_label }} ({{ start_date }} to {{ end_date }})
                    </p>
                </div>

                <!-- Period Controls -->
                <div class="flex items-center gap-2 flex-wrap">
                    <SegmentedControl
                        :model-value="selectedPeriod"
                        @update:model-value="handlePeriodChange($event as string)"
                        :options="[
                            { label: 'Week', value: 'week' },
                            { label: 'Month', value: 'month' },
                            { label: 'All Time', value: 'all' },
                            { label: 'Custom', value: 'custom' },
                        ]"
                    />
                </div>
            </div>

            <!-- Previous / Next Period Navigation Bar (For Week & Month) -->
            <div
                v-if="!['custom', 'all'].includes(selectedPeriod)"
                class="flex flex-wrap items-center justify-between gap-3 p-3 rounded-2xl bg-surface border border-border-subtle shadow-xs"
            >
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="navigatePeriod(props.prev_date)"
                        :disabled="!props.prev_date"
                        class="p-1.5 rounded-lg border border-border-subtle bg-surface-subdued text-content-secondary hover:text-content-primary hover:border-border-strong disabled:opacity-30 disabled:cursor-not-allowed transition-all cursor-pointer"
                        :title="'Previous ' + (selectedPeriod === 'week' ? 'Week' : 'Month')"
                    >
                        <Icon name="chevron-left" :size="16" />
                    </button>

                    <button
                        type="button"
                        @click="navigatePeriod(props.next_date)"
                        :disabled="!props.can_go_next || !props.next_date"
                        class="p-1.5 rounded-lg border border-border-subtle bg-surface-subdued text-content-secondary hover:text-content-primary hover:border-border-strong disabled:opacity-30 disabled:cursor-not-allowed transition-all cursor-pointer"
                        :title="'Next ' + (selectedPeriod === 'week' ? 'Week' : 'Month')"
                    >
                        <Icon name="chevron-right" :size="16" />
                    </button>

                    <button
                        v-if="!props.is_current"
                        type="button"
                        @click="resetToCurrent"
                        class="px-2.5 py-1 text-xs font-semibold rounded-lg border border-primary/20 bg-primary/10 text-primary hover:bg-primary/20 transition-all cursor-pointer"
                    >
                        Jump to Current
                    </button>
                </div>

                <!-- Active Period Display & Direct Jump Picker -->
                <div class="flex items-center gap-2.5">
                    <span class="text-xs sm:text-sm font-semibold text-content-primary">
                        {{ props.period_label }}
                    </span>
                    <Badge v-if="props.is_current" variant="primary" size="sm">Current</Badge>
                    <Badge v-else variant="neutral" size="sm">Past Archive</Badge>

                    <!-- Direct Date Jump Input -->
                    <div class="relative ml-1">
                        <input
                            type="date"
                            :value="props.anchor_date || props.start_date"
                            @change="handleDirectDateChange(($event.target as HTMLInputElement).value)"
                            class="w-8 h-8 rounded-lg bg-surface-subdued text-transparent border border-border-subtle hover:border-border-strong cursor-pointer outline-none transition-colors"
                            title="Jump directly to specific date"
                        />
                        <span class="absolute inset-0 flex items-center justify-center pointer-events-none text-content-muted">
                            <Icon name="calendar" :size="14" />
                        </span>
                    </div>
                </div>
            </div>

            <!-- Custom Date Range Bar -->
            <div
                v-if="selectedPeriod === 'custom' || showCustomRange"
                class="flex flex-wrap items-center gap-3 p-3.5 rounded-2xl bg-surface border border-border-subtle text-xs"
            >
                <div class="flex items-center gap-2">
                    <span class="text-content-muted font-medium">From:</span>
                    <input
                        v-model="customFrom"
                        type="date"
                        class="bg-surface-subdued text-content-primary border border-border-subtle rounded-xl px-2.5 py-1 text-xs outline-none focus:border-primary"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-content-muted font-medium">To:</span>
                    <input
                        v-model="customTo"
                        type="date"
                        class="bg-surface-subdued text-content-primary border border-border-subtle rounded-xl px-2.5 py-1 text-xs outline-none focus:border-primary"
                    />
                </div>
                <Button
                    variant="primary"
                    size="sm"
                    @click="applyFilters"
                >
                    Apply Range
                </Button>
            </div>
        </div>

        <!-- 4-METRIC PRIMARY CARDS -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-8">
            <!-- 1. Scheduled vs Completed Planned Hours -->
            <Card compact class="bg-surface border-border-subtle hover:border-primary/30 transition-colors">
                <div class="flex items-center justify-between text-xs text-content-secondary">
                    <span class="font-medium">Scheduled vs Completed</span>
                    <Icon name="check-circle" :size="15" class="text-status-success" />
                </div>
                <p class="text-2xl font-bold tracking-tight text-content-primary mt-2 font-mono">
                    {{ metrics.completed_planned_hours }} <span class="text-xs font-normal text-content-muted">/ {{ metrics.scheduled_hours }} hrs</span>
                </p>
                <div class="mt-1.5 text-[11px] text-content-muted">
                    {{ metrics.completed_occurrences_count }} of {{ metrics.scheduled_occurrences_count }} completed ({{ metrics.planned_hour_completion_rate }}%)
                </div>
            </Card>

            <!-- 2. Actual Hours Worked -->
            <Card compact class="bg-surface border-border-subtle hover:border-primary/30 transition-colors">
                <div class="flex items-center justify-between text-xs text-content-secondary">
                    <span class="font-medium">Actual Worked</span>
                    <Icon name="flame" :size="15" class="text-primary" />
                </div>
                <p class="text-2xl font-bold tracking-tight text-content-primary mt-2 font-mono">
                    {{ metrics.actual_hours }} <span class="text-xs font-normal text-content-muted">hrs</span>
                </p>
                <div class="mt-1.5 text-[11px] text-content-muted flex items-center justify-between">
                    <span>{{ metrics.work_session_count }} focus sessions</span>
                    <span v-if="metrics.unscheduled_actual_hours > 0" class="text-primary font-medium">
                        +{{ metrics.unscheduled_actual_hours }}h unscheduled
                    </span>
                </div>
            </Card>

            <!-- 3. Completion Rate & Remaining Work -->
            <Card compact class="bg-surface border-border-subtle hover:border-accent/30 transition-colors">
                <div class="flex items-center justify-between text-xs text-content-secondary">
                    <span class="font-medium">Completion Rate</span>
                    <Icon name="tasks" :size="15" class="text-accent" />
                </div>
                <p class="text-2xl font-bold tracking-tight text-content-primary mt-2 font-mono">
                    {{ metrics.occurrence_completion_rate }}%
                </p>
                <div class="mt-1.5 text-[11px] text-content-muted">
                    {{ metrics.remaining_scheduled_hours }} hrs remaining ({{ metrics.incomplete_occurrences_count }} pending)
                </div>
            </Card>

            <!-- 4. Overdue Incomplete Occurrences -->
            <Card compact class="bg-surface border-border-subtle hover:border-status-danger/30 transition-colors">
                <div class="flex items-center justify-between text-xs text-content-secondary">
                    <span class="font-medium">Overdue Incomplete</span>
                    <Icon name="alert-circle" :size="15" :class="metrics.overdue_incomplete_count > 0 ? 'text-status-danger' : 'text-status-success'" />
                </div>
                <p
                    :class="[
                        'text-2xl font-bold tracking-tight mt-2 font-mono',
                        metrics.overdue_incomplete_count > 0 ? 'text-status-danger' : 'text-status-success',
                    ]"
                >
                    {{ metrics.overdue_incomplete_count }}
                </p>
                <div class="mt-1.5 text-[11px] text-content-muted">
                    {{ metrics.overdue_incomplete_count > 0 ? 'Overdue occurrences needing completion' : 'Zero overdue tasks!' }}
                </div>
            </Card>
        </div>

        <!-- TRENDS SECTION (DAILY, WEEKLY, MONTHLY) -->
        <div class="mb-10 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold text-content-primary flex items-center gap-2">
                        <Icon name="chart" :size="18" class="text-primary" />
                        <span>Execution Trends</span>
                    </h3>
                    <p class="text-xs text-content-secondary">
                        Observe your consistency across daily, weekly, and monthly intervals.
                    </p>
                </div>

                <!-- Trend Granularity Tabs -->
                <div class="flex items-center gap-1 bg-surface-subdued p-1 rounded-xl border border-border-subtle">
                    <button
                        type="button"
                        v-for="tab in [
                            { id: 'daily', label: 'Daily' },
                            { id: 'weekly', label: 'Weekly (8 wks)' },
                            { id: 'monthly', label: 'Monthly (6 mos)' },
                        ]"
                        :key="tab.id"
                        :class="[
                            'px-3 py-1 text-xs rounded-lg font-medium transition-colors cursor-pointer',
                            activeTrendTab === tab.id
                                ? 'bg-surface text-content-primary shadow-xs font-semibold'
                                : 'text-content-muted hover:text-content-primary',
                        ]"
                        @click="activeTrendTab = tab.id as any"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <!-- Visual Comparison Triple-Bar Strip -->
            <Card compact class="p-4 bg-surface border-border-subtle overflow-x-auto">
                <div class="flex items-center justify-end gap-3 text-xs text-content-muted mb-3">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-xs bg-primary/30 border border-primary/50" /> Scheduled
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-xs bg-status-success" /> Completed
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-xs bg-amber-500" /> Actual Worked
                    </span>
                </div>

                <div class="flex items-end gap-2 sm:gap-4 min-w-[500px] h-48 pt-4 pb-2">
                    <div
                        v-for="(item, idx) in currentTrendList"
                        :key="idx"
                        class="flex-1 flex flex-col items-center gap-1 h-full justify-end group"
                    >
                        <!-- Triple Bar Column -->
                        <div class="w-full flex items-end justify-center gap-1 h-36">
                            <div
                                class="w-2 sm:w-3 bg-primary/30 rounded-t-xs transition-all duration-300"
                                :style="{ height: `${Math.max(4, (item.scheduled_hours / maxTrendValue) * 100)}%` }"
                                :title="`Scheduled: ${item.scheduled_hours}h`"
                            />
                            <div
                                class="w-2 sm:w-3 bg-status-success rounded-t-xs transition-all duration-300"
                                :style="{ height: `${Math.max(4, (item.completed_hours / maxTrendValue) * 100)}%` }"
                                :title="`Completed: ${item.completed_hours}h`"
                            />
                            <div
                                class="w-2 sm:w-3 bg-amber-500 rounded-t-xs transition-all duration-300"
                                :style="{ height: `${Math.max(4, (item.actual_hours / maxTrendValue) * 100)}%` }"
                                :title="`Actual Worked: ${item.actual_hours}h`"
                            />
                        </div>

                        <!-- Label -->
                        <div class="text-center pt-1 border-t border-border-subtle/50 w-full truncate">
                            <span class="text-[10px] font-bold text-content-primary block truncate">
                                {{ item.day_num ? item.day_num + ' ' + item.day_name : item.label }}
                            </span>
                            <span class="text-[9px] text-content-muted font-mono block">
                                {{ item.actual_hours }}h
                            </span>
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <!-- CHRONOLOGICAL HISTORY SECTION WITH FILTERS -->
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-border-subtle pb-3">
                <!-- Tab Switcher between Occurrences and Work Sessions -->
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :class="[
                            'px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-colors cursor-pointer border',
                            activeHistoryTab === 'occurrences'
                                ? 'bg-primary text-primary-text border-primary shadow-xs'
                                : 'bg-surface text-content-secondary border-border-subtle hover:bg-surface-hover',
                        ]"
                        @click="activeHistoryTab = 'occurrences'"
                    >
                        Scheduled Occurrences History ({{ occurrences_history.length }})
                    </button>
                    <button
                        type="button"
                        :class="[
                            'px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-colors cursor-pointer border',
                            activeHistoryTab === 'work_sessions'
                                ? 'bg-primary text-primary-text border-primary shadow-xs'
                                : 'bg-surface text-content-secondary border-border-subtle hover:bg-surface-hover',
                        ]"
                        @click="activeHistoryTab = 'work_sessions'"
                    >
                        Work Sessions History ({{ work_sessions_history.length }})
                    </button>
                </div>

                <!-- Occurrence Status Filter (if occurrences tab active) -->
                <div v-if="activeHistoryTab === 'occurrences'" class="flex items-center gap-1 text-xs">
                    <span class="text-content-muted font-medium mr-1">Status:</span>
                    <button
                        v-for="st in ['all', 'completed', 'pending', 'skipped']"
                        :key="st"
                        type="button"
                        :class="[
                            'px-2 py-0.5 rounded-lg capitalize transition-colors cursor-pointer',
                            selectedStatusFilter === st
                                ? 'bg-surface-subdued text-content-primary font-bold border border-border-subtle'
                                : 'text-content-muted hover:text-content-primary',
                        ]"
                        @click="setStatusFilter(st)"
                    >
                        {{ st }}
                    </button>
                </div>
            </div>

            <!-- TAB 1: SCHEDULED OCCURRENCES TABLE -->
            <div v-if="activeHistoryTab === 'occurrences'" class="rounded-2xl border border-border-subtle bg-surface overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-surface-subdued/80 border-b border-border-subtle text-[11px] font-bold uppercase tracking-wider text-content-muted">
                            <tr>
                                <th class="p-3.5">Date</th>
                                <th class="p-3.5">Start Time</th>
                                <th class="p-3.5">Planned Duration</th>
                                <th class="p-3.5 text-center">Status</th>
                                <th class="p-3.5">Completed At</th>
                                <th class="p-3.5">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle/60">
                            <tr v-if="occurrences_history.length === 0" class="text-center text-content-muted">
                                <td colspan="6" class="p-8">No scheduled occurrences match the selected filter.</td>
                            </tr>
                            <tr
                                v-for="occ in occurrences_history"
                                :key="occ.id"
                                class="hover:bg-surface-hover/80 transition-colors"
                            >
                                <td class="p-3.5 font-semibold text-content-primary">
                                    {{ occ.scheduled_date }}
                                </td>
                                <td class="p-3.5 font-mono text-content-secondary">
                                    {{ formatTime12Hour(occ.start_time) }}
                                </td>
                                <td class="p-3.5 font-mono text-content-secondary">
                                    {{ (occ.duration_minutes / 60).toFixed(1) }} hrs ({{ occ.duration_minutes }}m)
                                </td>
                                <td class="p-3.5 text-center">
                                    <Badge
                                        :variant="occ.status === 'completed' ? 'completed' : occ.status === 'skipped' ? 'neutral' : 'planned'"
                                        size="sm"
                                    >
                                        {{ occ.status }}
                                    </Badge>
                                </td>
                                <td class="p-3.5 text-content-muted font-mono text-[11px]">
                                    {{ occ.completed_at ? occ.completed_at.substring(0, 16) : '—' }}
                                </td>
                                <td class="p-3.5 text-content-secondary italic max-w-xs truncate">
                                    {{ occ.notes || '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: WORK SESSIONS HISTORY TABLE -->
            <div v-else class="rounded-2xl border border-border-subtle bg-surface overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-surface-subdued/80 border-b border-border-subtle text-[11px] font-bold uppercase tracking-wider text-content-muted">
                            <tr>
                                <th class="p-3.5">Started At</th>
                                <th class="p-3.5">Duration</th>
                                <th class="p-3.5">Session Type</th>
                                <th class="p-3.5">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle/60">
                            <tr v-if="work_sessions_history.length === 0" class="text-center text-content-muted">
                                <td colspan="4" class="p-8">No actual work sessions recorded in this period.</td>
                            </tr>
                            <tr
                                v-for="ws in work_sessions_history"
                                :key="ws.id"
                                class="hover:bg-surface-hover/80 transition-colors"
                            >
                                <td class="p-3.5 font-semibold text-content-primary font-mono">
                                    {{ ws.started_at ? ws.started_at.substring(0, 16) : '—' }}
                                </td>
                                <td class="p-3.5 font-mono font-bold text-primary">
                                    {{ (ws.duration_minutes / 60).toFixed(1) }} hrs
                                </td>
                                <td class="p-3.5">
                                    <span
                                        v-if="ws.schedule_occurrence_id"
                                        class="px-2 py-0.5 rounded-md bg-surface-subdued border border-border-subtle/80 text-[11px] font-medium text-content-secondary"
                                    >
                                        Scheduled (Occurrence #{{ ws.schedule_occurrence_id }})
                                    </span>
                                    <span
                                        v-else
                                        class="px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-500 border border-amber-500/20 text-[11px] font-bold"
                                    >
                                        Unscheduled / Ad-hoc
                                    </span>
                                </td>
                                <td class="p-3.5 text-content-secondary italic max-w-sm truncate">
                                    {{ ws.notes || '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
