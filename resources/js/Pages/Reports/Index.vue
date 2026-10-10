<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Components/layout/AppLayout.vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Select, { type SelectOption } from '@/Components/ui/Select.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Icon from '@/Components/ui/Icon.vue';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';

interface TaskSummary {
    id: number;
    title: string;
    scheduled_hours: number;
    completed_planned_hours: number;
    remaining_scheduled_hours: number;
    actual_hours: number;
    occurrence_completion_rate: number;
    planned_hour_completion_rate: number;
    scheduled_occurrences_count: number;
    completed_occurrences_count: number;
    overdue_count: number;
}

interface DailyTrend {
    date: string;
    day_name: string;
    day_num: string;
    scheduled_hours: number;
    completed_hours: number;
    actual_hours: number;
    completion_rate: number;
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
    overdue_occurrences: Array<{
        id: number;
        scheduled_date: string;
        start_time: string;
        duration_minutes: number;
        task?: { id: number; title: string };
    }>;
}

const props = defineProps<{
    period: 'today' | 'week' | 'month' | 'custom';
    period_label: string;
    start_date: string;
    end_date: string;
    metrics: Metrics;
    daily_trends: DailyTrend[];
    task_summaries: TaskSummary[];
    tasks: Array<{ id: number; title: string; description: string | null }>;
    selected_task_id: number | null;
}>();

const selectedPeriod = ref(props.period);
const selectedTaskId = ref<number | ''>(props.selected_task_id || '');
const customFrom = ref(props.start_date);
const customTo = ref(props.end_date);
const showCustomRange = ref(props.period === 'custom');

const taskFilterOptions = computed<SelectOption[]>(() => {
    return [
        { label: 'All Tasks (Aggregated)', value: '' },
        ...props.tasks.map((t) => ({ label: t.title, value: t.id })),
    ];
});

function applyFilters() {
    const params: Record<string, any> = {
        period: selectedPeriod.value,
    };
    if (selectedTaskId.value) {
        params.task_id = selectedTaskId.value;
    }
    if (selectedPeriod.value === 'custom') {
        params.from = customFrom.value;
        params.to = customTo.value;
    }

    router.get('/reports', params, {
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
        applyFilters();
    }
}

function handleTaskFilterChange() {
    applyFilters();
}

// Max value helper for trend charts
const maxTrendValue = computed(() => {
    let max = 1;
    props.daily_trends.forEach((d) => {
        if (d.scheduled_hours > max) max = d.scheduled_hours;
        if (d.actual_hours > max) max = d.actual_hours;
    });
    return max;
});
</script>

<template>
    <Head title="Performance Reports - LifeLoop" />

    <AppLayout current-tab="reports">
        <!-- TOP CONTROLS & HEADER -->
        <div class="space-y-6 mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3 flex-wrap">
                    <SegmentedControl
                        :model-value="selectedPeriod"
                        @update:model-value="handlePeriodChange($event as string)"
                        :options="[
                            { label: 'Today', value: 'today' },
                            { label: 'Week', value: 'week' },
                            { label: 'Month', value: 'month' },
                            { label: 'Custom', value: 'custom' },
                        ]"
                    />

                    <!-- Task Filter Dropdown -->
                    <div class="w-56">
                        <Select
                            v-model="selectedTaskId"
                            :options="taskFilterOptions"
                            size="sm"
                            @update:model-value="handleTaskFilterChange"
                        />
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <ThemeToggle variant="button" />
                </div>
            </div>

            <!-- Custom Date Range Bar (if custom selected) -->
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

            <!-- Page Header -->
            <PageHeader
                title="Performance & Schedule Reports"
                :subtitle="`${period_label} • Comprehensive metrics and execution analytics`"
            />
        </div>

        <!-- 4-METRIC PRIMARY SUMMARY CARDS -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-8">
            <!-- 1. Scheduled Hours -->
            <Card compact class="bg-surface border-border-subtle hover:border-primary/30 transition-colors">
                <div class="flex items-center justify-between text-xs text-content-secondary">
                    <span class="font-medium">Scheduled Hours</span>
                    <Icon name="clock" :size="15" class="text-primary" />
                </div>
                <p class="text-2xl font-bold tracking-tight text-content-primary mt-2 font-mono">
                    {{ metrics.scheduled_hours }} <span class="text-xs font-normal text-content-muted">hrs</span>
                </p>
                <div class="mt-1.5 text-[11px] text-content-muted">
                    {{ metrics.scheduled_occurrences_count }} planned occurrence{{ metrics.scheduled_occurrences_count === 1 ? '' : 's' }}
                </div>
            </Card>

            <!-- 2. Completed Planned Hours -->
            <Card compact class="bg-surface border-border-subtle hover:border-status-success/30 transition-colors">
                <div class="flex items-center justify-between text-xs text-content-secondary">
                    <span class="font-medium">Completed Planned</span>
                    <Icon name="check-circle" :size="15" class="text-status-success" />
                </div>
                <p class="text-2xl font-bold tracking-tight text-status-success mt-2 font-mono">
                    {{ metrics.completed_planned_hours }} <span class="text-xs font-normal text-content-muted">hrs</span>
                </p>
                <div class="mt-1.5 text-[11px] text-content-muted">
                    {{ metrics.completed_occurrences_count }} of {{ metrics.scheduled_occurrences_count }} done ({{ metrics.planned_hour_completion_rate }}%)
                </div>
            </Card>

            <!-- 3. Remaining Scheduled Hours -->
            <Card compact class="bg-surface border-border-subtle hover:border-accent/30 transition-colors">
                <div class="flex items-center justify-between text-xs text-content-secondary">
                    <span class="font-medium">Remaining Scheduled</span>
                    <Icon name="calendar" :size="15" class="text-accent" />
                </div>
                <p class="text-2xl font-bold tracking-tight text-content-primary mt-2 font-mono">
                    {{ metrics.remaining_scheduled_hours }} <span class="text-xs font-normal text-content-muted">hrs</span>
                </p>
                <div class="mt-1.5 text-[11px] text-content-muted">
                    {{ metrics.incomplete_occurrences_count }} pending occurrence{{ metrics.incomplete_occurrences_count === 1 ? '' : 's' }}
                </div>
            </Card>

            <!-- 4. Actual Worked Hours -->
            <Card compact class="bg-surface border-border-subtle hover:border-primary/30 transition-colors">
                <div class="flex items-center justify-between text-xs text-content-secondary">
                    <span class="font-medium">Actual Worked</span>
                    <Icon name="flame" :size="15" class="text-primary" />
                </div>
                <p class="text-2xl font-bold tracking-tight text-content-primary mt-2 font-mono">
                    {{ metrics.actual_hours }} <span class="text-xs font-normal text-content-muted">hrs</span>
                </p>
                <div class="mt-1.5 text-[11px] text-content-muted flex items-center justify-between">
                    <span>{{ metrics.work_session_count }} sessions</span>
                    <span v-if="metrics.unscheduled_actual_hours > 0" class="text-primary font-medium">
                        +{{ metrics.unscheduled_actual_hours }}h extra
                    </span>
                </div>
            </Card>
        </div>

        <!-- COMPLETION PERFORMANCE & OVERDUE BANNER -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <!-- Occurrence Completion Rate -->
            <Card compact class="bg-surface border-border-subtle p-4 space-y-2">
                <span class="text-xs font-semibold text-content-secondary block">
                    Occurrence Completion Rate
                </span>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-content-primary font-mono">
                        {{ metrics.occurrence_completion_rate }}%
                    </span>
                    <span class="text-xs text-content-muted">
                        ({{ metrics.completed_occurrences_count }}/{{ metrics.scheduled_occurrences_count }} tasks)
                    </span>
                </div>
                <!-- Progress bar -->
                <div class="w-full h-2 rounded-full bg-surface-subdued overflow-hidden border border-border-subtle/40">
                    <div
                        class="h-full bg-status-success rounded-full transition-all duration-500"
                        :style="{ width: `${Math.min(100, metrics.occurrence_completion_rate)}%` }"
                    />
                </div>
            </Card>

            <!-- Planned Hour Completion Rate -->
            <Card compact class="bg-surface border-border-subtle p-4 space-y-2">
                <span class="text-xs font-semibold text-content-secondary block">
                    Planned Hours Execution Rate
                </span>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-content-primary font-mono">
                        {{ metrics.planned_hour_completion_rate }}%
                    </span>
                    <span class="text-xs text-content-muted">
                        ({{ metrics.completed_planned_hours }}/{{ metrics.scheduled_hours }} hrs)
                    </span>
                </div>
                <!-- Progress bar -->
                <div class="w-full h-2 rounded-full bg-surface-subdued overflow-hidden border border-border-subtle/40">
                    <div
                        class="h-full bg-primary rounded-full transition-all duration-500"
                        :style="{ width: `${Math.min(100, metrics.planned_hour_completion_rate)}%` }"
                    />
                </div>
            </Card>

            <!-- Overdue Incomplete Tasks Count -->
            <Card compact class="bg-surface border-border-subtle p-4 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-content-secondary">
                        Overdue Incomplete Tasks
                    </span>
                    <Badge v-if="metrics.overdue_incomplete_count > 0" variant="danger" size="sm">
                        Attention
                    </Badge>
                </div>
                <div class="flex items-baseline gap-2">
                    <span
                        :class="[
                            'text-3xl font-extrabold font-mono',
                            metrics.overdue_incomplete_count > 0 ? 'text-status-danger' : 'text-status-success',
                        ]"
                    >
                        {{ metrics.overdue_incomplete_count }}
                    </span>
                    <span class="text-xs text-content-muted">
                        {{ metrics.overdue_incomplete_count === 1 ? 'task' : 'tasks' }} needing resolution
                    </span>
                </div>
                <p class="text-[11px] text-content-muted">
                    Future incomplete tasks count toward remaining work, not overdue.
                </p>
            </Card>
        </div>

        <!-- OVERDUE OCCURRENCES DETAILS (IF ANY) -->
        <div
            v-if="metrics.overdue_incomplete_count > 0 && metrics.overdue_occurrences.length > 0"
            class="mb-8 p-4 rounded-2xl bg-status-danger-subdued/40 border border-status-danger/30 space-y-3"
        >
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-bold text-status-danger uppercase tracking-wider flex items-center gap-1.5">
                    <Icon name="alert-circle" :size="15" />
                    <span>Overdue Incomplete Occurrences</span>
                </h3>
                <span class="text-xs text-status-danger font-medium font-mono">
                    {{ metrics.overdue_incomplete_count }} overdue
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                <div
                    v-for="overdue in metrics.overdue_occurrences"
                    :key="overdue.id"
                    class="p-3 rounded-xl bg-surface border border-status-danger/20 text-xs space-y-1"
                >
                    <div class="font-bold text-content-primary truncate">
                        {{ overdue.task?.title || 'Untitled Task' }}
                    </div>
                    <div class="text-[11px] text-content-muted flex items-center gap-2">
                        <span class="text-status-danger font-semibold">{{ overdue.scheduled_date }}</span>
                        <span>•</span>
                        <span>{{ overdue.start_time.substring(0, 5) }}</span>
                        <span>•</span>
                        <span>{{ (overdue.duration_minutes / 60).toFixed(1) }}h</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- DAILY EXECUTION TRENDS BARS (WEEK REPORTS ONLY) -->
        <div v-if="period === 'week' && daily_trends.length > 0" class="mb-8 space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-content-primary flex items-center gap-2">
                    <Icon name="chart" :size="16" class="text-primary" />
                    <span>Daily Execution Rhythm</span>
                </h3>
                <div class="flex items-center gap-3 text-xs text-content-muted">
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
            </div>

            <Card compact class="p-4 bg-surface border-border-subtle overflow-x-auto">
                <div class="flex items-end gap-2 sm:gap-3 min-w-[500px] h-48 pt-6 pb-2">
                    <div
                        v-for="day in daily_trends"
                        :key="day.date"
                        class="flex-1 flex flex-col items-center gap-1 h-full justify-end group"
                    >
                        <!-- Comparison Triple-Bar Column -->
                        <div class="w-full flex items-end justify-center gap-0.5 h-36">
                            <!-- Scheduled Bar -->
                            <div
                                class="w-2 sm:w-2.5 bg-primary/30 rounded-t-xs transition-all duration-300"
                                :style="{ height: `${Math.max(4, (day.scheduled_hours / maxTrendValue) * 100)}%` }"
                                :title="`Scheduled: ${day.scheduled_hours}h`"
                            />
                            <!-- Completed Planned Bar -->
                            <div
                                class="w-2 sm:w-2.5 bg-status-success rounded-t-xs transition-all duration-300"
                                :style="{ height: `${Math.max(4, (day.completed_hours / maxTrendValue) * 100)}%` }"
                                :title="`Completed Planned: ${day.completed_hours}h`"
                            />
                            <!-- Actual Worked Bar -->
                            <div
                                class="w-2 sm:w-2.5 bg-amber-500 rounded-t-xs transition-all duration-300"
                                :style="{ height: `${Math.max(4, (day.actual_hours / maxTrendValue) * 100)}%` }"
                                :title="`Actual Worked: ${day.actual_hours}h`"
                            />
                        </div>

                        <!-- Date Labels -->
                        <div class="text-center pt-1 border-t border-border-subtle/50 w-full">
                            <span class="text-[10px] font-bold text-content-primary block">{{ day.day_num }}</span>
                            <span class="text-[9px] text-content-muted block">{{ day.day_name }}</span>
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <!-- TASK PERFORMANCE BREAKDOWN TABLE -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-content-primary">
                        Task Performance Breakdown
                    </h3>
                    <p class="text-xs text-content-secondary">
                        Detailed execution metrics and individual task report links.
                    </p>
                </div>
                <span class="text-xs font-semibold text-content-muted">
                    {{ task_summaries.length }} task{{ task_summaries.length === 1 ? '' : 's' }}
                </span>
            </div>

            <div class="rounded-2xl border border-border-subtle bg-surface overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-surface-subdued/80 border-b border-border-subtle text-[11px] font-bold uppercase tracking-wider text-content-muted">
                            <tr>
                                <th class="p-3.5">Task</th>
                                <th class="p-3.5 text-right">Scheduled</th>
                                <th class="p-3.5 text-right">Completed Planned</th>
                                <th class="p-3.5 text-right">Remaining</th>
                                <th class="p-3.5 text-right">Actual Worked</th>
                                <th class="p-3.5 text-center">Completion Rate</th>
                                <th class="p-3.5 text-center">Overdue</th>
                                <th class="p-3.5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle/60">
                            <tr
                                v-if="task_summaries.length === 0"
                                class="text-center text-content-muted"
                            >
                                <td colspan="8" class="p-8">No tasks recorded.</td>
                            </tr>

                            <tr
                                v-for="t in task_summaries"
                                :key="t.id"
                                class="hover:bg-surface-hover/80 transition-colors"
                            >
                                <td class="p-3.5 font-semibold text-content-primary">
                                    {{ t.title }}
                                </td>
                                <td class="p-3.5 text-right font-mono text-content-secondary">
                                    {{ t.scheduled_hours }}h
                                </td>
                                <td class="p-3.5 text-right font-mono font-semibold text-status-success">
                                    {{ t.completed_planned_hours }}h
                                </td>
                                <td class="p-3.5 text-right font-mono text-content-muted">
                                    {{ t.remaining_scheduled_hours }}h
                                </td>
                                <td class="p-3.5 text-right font-mono font-bold text-content-primary">
                                    {{ t.actual_hours }}h
                                </td>
                                <td class="p-3.5 text-center">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 rounded-full font-mono text-[11px] font-bold',
                                            t.occurrence_completion_rate >= 80
                                                ? 'bg-status-success/10 text-status-success'
                                                : t.occurrence_completion_rate >= 40
                                                    ? 'bg-primary/10 text-primary'
                                                    : 'bg-surface-subdued text-content-muted',
                                        ]"
                                    >
                                        {{ t.occurrence_completion_rate }}%
                                    </span>
                                </td>
                                <td class="p-3.5 text-center">
                                    <span
                                        v-if="t.overdue_count > 0"
                                        class="px-2 py-0.5 rounded-full bg-status-danger/10 text-status-danger font-bold text-[11px] font-mono"
                                    >
                                        {{ t.overdue_count }}
                                    </span>
                                    <span v-else class="text-content-muted/50 font-mono">—</span>
                                </td>
                                <td class="p-3.5 text-right">
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        @click="router.visit(`/tasks/${t.id}/report`)"
                                    >
                                        View Report &rarr;
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
