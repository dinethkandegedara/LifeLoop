<script setup lang="ts">
import { ref, computed } from 'vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import Icon from '@/Components/ui/Icon.vue';

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
}

interface WorkSession {
    id: number;
    user_id: number;
    task_id: number;
    schedule_occurrence_id: number | null;
    started_at: string;
    duration_minutes: number;
    notes: string | null;
    task?: {
        id: number;
        title: string;
    };
}

const props = defineProps<{
    occurrences: ScheduleOccurrence[];
    rangeWorkSessions: WorkSession[];
    selectedDate: string;
    todayDate: string;
    rangeStart: string;
    rangeEnd: string;
    actionLoadingIds: Set<number>;
}>();

const emit = defineEmits<{
    (e: 'navigate-month', offsetMonths: number): void;
    (e: 'go-to-today'): void;
    (e: 'drill-to-day', date: string): void;
    (e: 'toggle-occurrence', occ: ScheduleOccurrence): void;
    (e: 'log-work-for-occurrence', occ: ScheduleOccurrence): void;
    (e: 'log-work-ad-hoc-date', date: string): void;
}>();

// Active inspecting day inside Month View
const inspectingDate = ref<string>(props.selectedDate || props.todayDate);

interface MonthCellItem {
    date: string;
    dayNum: number;
    isCurrentMonth: boolean;
    isToday: boolean;
    isSelected: boolean;
    occurrences: ScheduleOccurrence[];
    totalPlannedMinutes: number;
    completedCount: number;
    pendingCount: number;
}

const weekdayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

// Month Grid Computation
const monthGridDays = computed<MonthCellItem[]>(() => {
    if (!props.rangeStart || !props.rangeEnd) return [];

    const startParts = props.rangeStart.split('-').map(Number);
    const endParts = props.rangeEnd.split('-').map(Number);
    const curMonthPart = props.selectedDate ? Number(props.selectedDate.split('-')[1]) - 1 : new Date().getMonth();

    const startDate = new Date(startParts[0], startParts[1] - 1, startParts[2]);
    const endDate = new Date(endParts[0], endParts[1] - 1, endParts[2]);

    const cells: MonthCellItem[] = [];
    const curr = new Date(startDate);

    while (curr <= endDate) {
        const yyyy = curr.getFullYear();
        const mm = String(curr.getMonth() + 1).padStart(2, '0');
        const dd = String(curr.getDate()).padStart(2, '0');
        const dateStr = `${yyyy}-${mm}-${dd}`;

        const isCurrentMonth = curr.getMonth() === curMonthPart;
        const dayOccurrences = props.occurrences
            .filter((occ) => occ.scheduled_date === dateStr)
            .sort((a, b) => a.start_time.localeCompare(b.start_time));

        const totalPlanned = dayOccurrences.reduce((sum, occ) => sum + (occ.duration_minutes || 0), 0);
        const completed = dayOccurrences.filter((occ) => occ.status === 'completed').length;
        const pending = dayOccurrences.filter((occ) => occ.status !== 'completed').length;

        cells.push({
            date: dateStr,
            dayNum: curr.getDate(),
            isCurrentMonth,
            isToday: dateStr === props.todayDate,
            isSelected: dateStr === inspectingDate.value,
            occurrences: dayOccurrences,
            totalPlannedMinutes: totalPlanned,
            completedCount: completed,
            pendingCount: pending,
        });

        curr.setDate(curr.getDate() + 1);
    }

    return cells;
});

// Current Inspecting Day details
const inspectingDayData = computed(() => {
    const cell = monthGridDays.value.find((c) => c.date === inspectingDate.value);
    const sessions = props.rangeWorkSessions.filter((ws) => {
        return ws.started_at.substring(0, 10) === inspectingDate.value;
    });
    return {
        cell,
        workSessions: sessions,
    };
});

// Month Title
const formattedMonthTitle = computed(() => {
    if (!props.selectedDate) return '';
    const parts = props.selectedDate.split('-').map(Number);
    const d = new Date(parts[0], parts[1] - 1, parts[2]);
    return d.toLocaleString('en-US', { month: 'long', year: 'numeric' });
});

// Month Stats
const monthTotalPlannedHours = computed(() => {
    const mins = props.occurrences.reduce((sum, occ) => sum + (occ.duration_minutes || 0), 0);
    return (mins / 60).toFixed(1);
});

const monthTotalSessions = computed(() => props.occurrences.length);

function formatTime12Hour(timeStr: string): string {
    if (!timeStr) return '';
    const clean = timeStr.substring(0, 5);
    const [h, m] = clean.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${String(m).padStart(2, '0')} ${ampm}`;
}

function selectDayForInspection(date: string) {
    inspectingDate.value = date;
}
</script>

<template>
    <div class="space-y-6">
        <!-- MONTH HEADER & NAVIGATION -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 rounded-2xl bg-surface border border-border-subtle shadow-xs">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1 bg-surface-subdued border border-border-subtle/80 rounded-xl p-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="emit('navigate-month', -1)"
                        title="Previous Month"
                    >
                        &larr; Prev
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="emit('go-to-today')"
                        title="Jump to current month"
                    >
                        This Month
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="emit('navigate-month', 1)"
                        title="Next Month"
                    >
                        Next &rarr;
                    </Button>
                </div>

                <h2 class="text-base sm:text-lg font-bold text-content-primary">
                    {{ formattedMonthTitle }}
                </h2>
            </div>

            <!-- Month Summary Stats -->
            <div class="flex items-center gap-3 text-xs">
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary/10 text-primary border border-primary/20 font-medium">
                    <Icon name="clock" :size="13" />
                    <span><strong>{{ monthTotalPlannedHours }}</strong> hrs planned</span>
                </div>
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-surface-subdued text-content-secondary border border-border-subtle font-medium">
                    <Icon name="calendar" :size="13" />
                    <span><strong>{{ monthTotalSessions }}</strong> sessions</span>
                </div>
            </div>
        </div>

        <!-- MAIN LAYOUT: MONTH GRID + INSPECTOR DRAWER -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
            <!-- MONTH CALENDAR GRID (7 columns) -->
            <div class="lg:col-span-8 rounded-2xl bg-surface border border-border-subtle overflow-hidden shadow-xs">
                <!-- Weekday Headers -->
                <div class="grid grid-cols-7 border-b border-border-subtle/80 bg-surface-subdued/60 text-center text-[11px] font-bold text-content-muted py-2.5">
                    <span v-for="w in weekdayNames" :key="w">{{ w }}</span>
                </div>

                <!-- Grid Cells -->
                <div class="grid grid-cols-7 divide-x divide-y divide-border-subtle/60">
                    <div
                        v-for="cell in monthGridDays"
                        :key="cell.date"
                        :class="[
                            'min-h-[92px] sm:min-h-[105px] p-1.5 sm:p-2 transition-all cursor-pointer flex flex-col justify-between group relative',
                            cell.isCurrentMonth ? 'bg-surface' : 'bg-surface-subdued/30 text-content-muted/50',
                            cell.isSelected ? 'ring-2 ring-primary ring-inset z-10 bg-primary/5' : 'hover:bg-surface-hover/80',
                        ]"
                        @click="selectDayForInspection(cell.date)"
                    >
                        <!-- Top Row: Day Number + Daily Total Badge -->
                        <div class="flex items-center justify-between gap-1">
                            <span
                                :class="[
                                    'w-6 h-6 rounded-full flex items-center justify-center text-xs font-semibold transition-all',
                                    cell.isToday
                                        ? 'bg-primary text-primary-text font-bold shadow-xs'
                                        : cell.isCurrentMonth
                                            ? 'text-content-primary'
                                            : 'text-content-muted/60',
                                ]"
                            >
                                {{ cell.dayNum }}
                            </span>

                            <span
                                v-if="cell.totalPlannedMinutes > 0"
                                class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded-md bg-primary/10 text-primary border border-primary/15"
                            >
                                {{ (cell.totalPlannedMinutes / 60).toFixed(1) }}h
                            </span>
                        </div>

                        <!-- Mid: Compact Task Pills (Max 2 to avoid cell overcrowding) -->
                        <div class="space-y-1 my-1 flex-1 overflow-hidden">
                            <div
                                v-for="occ in cell.occurrences.slice(0, 2)"
                                :key="occ.id"
                                :class="[
                                    'text-[10px] px-1.5 py-0.5 rounded-md truncate font-medium flex items-center gap-1 border transition-colors',
                                    occ.status === 'completed'
                                        ? 'bg-status-success/10 text-status-success border-status-success/20 line-through'
                                        : 'bg-surface-subdued text-content-secondary border-border-subtle/70 group-hover:border-primary/30',
                                ]"
                                :title="`${formatTime12Hour(occ.start_time)} - ${occ.task?.title}`"
                            >
                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="occ.status === 'completed' ? 'bg-status-success' : 'bg-primary'" />
                                <span class="truncate">{{ occ.task?.title }}</span>
                            </div>

                            <!-- Truncation Pill if > 2 occurrences -->
                            <div
                                v-if="cell.occurrences.length > 2"
                                class="text-[9px] font-medium text-content-muted px-1 rounded hover:text-primary transition-colors"
                            >
                                +{{ cell.occurrences.length - 2 }} more
                            </div>
                        </div>

                        <!-- Bottom: Status dot strip -->
                        <div v-if="cell.occurrences.length > 0" class="flex items-center gap-1 pt-0.5">
                            <span
                                v-if="cell.completedCount > 0"
                                class="w-1.5 h-1.5 rounded-full bg-status-success"
                                title="Completed sessions"
                            />
                            <span
                                v-if="cell.pendingCount > 0"
                                class="w-1.5 h-1.5 rounded-full bg-primary"
                                title="Pending sessions"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT / BOTTOM: DAY DRILL-DOWN INSPECTOR PANEL -->
            <div class="lg:col-span-4 rounded-2xl bg-surface border border-border-subtle p-4 space-y-4 shadow-xs sticky top-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <div>
                        <span class="text-[10px] font-bold tracking-wider text-content-muted uppercase block">
                            Day Details
                        </span>
                        <h3 class="text-sm font-bold text-content-primary">
                            {{ inspectingDate }}
                        </h3>
                    </div>

                    <Button
                        variant="secondary"
                        size="sm"
                        @click="emit('drill-to-day', inspectingDate)"
                        title="Open full Today/Day focus view"
                    >
                        Open Day View &rarr;
                    </Button>
                </div>

                <!-- Inspecting Day Occurrences List -->
                <div class="space-y-2.5 max-h-[380px] overflow-y-auto pr-1">
                    <div
                        v-if="!inspectingDayData.cell || inspectingDayData.cell.occurrences.length === 0"
                        class="p-6 text-center text-xs text-content-muted space-y-2 rounded-xl bg-surface-subdued/40 border border-dashed border-border-subtle"
                    >
                        <Icon name="calendar" :size="24" class="mx-auto opacity-40" />
                        <p>No planned occurrences for this day.</p>
                        <Button
                            variant="primary"
                            size="sm"
                            icon="plus"
                            @click="emit('log-work-ad-hoc-date', inspectingDate)"
                        >
                            Log Work Session
                        </Button>
                    </div>

                    <div
                        v-for="occ in inspectingDayData.cell?.occurrences"
                        :key="occ.id"
                        :class="[
                            'p-3 rounded-xl border text-xs space-y-2 transition-all',
                            occ.status === 'completed'
                                ? 'bg-surface-subdued/60 border-status-success/30'
                                : 'bg-surface border-border-subtle hover:border-primary/40',
                        ]"
                    >
                        <div class="flex items-start gap-2.5">
                            <Checkbox
                                :id="`month-chk-${occ.id}`"
                                :model-value="occ.status === 'completed'"
                                :disabled="actionLoadingIds.has(occ.id)"
                                @update:model-value="emit('toggle-occurrence', occ)"
                            />

                            <div class="min-w-0 flex-1">
                                <span
                                    :class="[
                                        'font-semibold text-xs leading-snug block truncate',
                                        occ.status === 'completed' ? 'line-through text-content-muted' : 'text-content-primary',
                                    ]"
                                >
                                    {{ occ.task?.title }}
                                </span>

                                <div class="flex items-center gap-1.5 text-[11px] text-content-muted mt-0.5">
                                    <span class="font-mono">{{ formatTime12Hour(occ.start_time) }}</span>
                                    <span>•</span>
                                    <span class="font-mono">{{ (occ.duration_minutes / 60).toFixed(1) }} hrs</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1 border-t border-border-subtle/50 text-[11px]">
                            <Badge
                                v-if="occ.status === 'completed'"
                                variant="completed"
                                size="sm"
                            >
                                Completed
                            </Badge>
                            <Badge
                                v-else
                                variant="planned"
                                size="sm"
                            >
                                Planned
                            </Badge>

                            <button
                                type="button"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium text-primary hover:bg-primary/10 transition-colors cursor-pointer"
                                @click="emit('log-work-for-occurrence', occ)"
                            >
                                <Icon name="plus" :size="11" />
                                <span>Log Work</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quick Action at Bottom of Drawer -->
                <div class="pt-2 border-t border-border-subtle flex items-center justify-between">
                    <span class="text-xs text-content-muted">
                        Planned: <strong>{{ ((inspectingDayData.cell?.totalPlannedMinutes || 0) / 60).toFixed(1) }}h</strong>
                    </span>
                    <Button
                        variant="ghost"
                        size="sm"
                        icon="plus"
                        @click="emit('log-work-ad-hoc-date', inspectingDate)"
                    >
                        Log Extra Work
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
