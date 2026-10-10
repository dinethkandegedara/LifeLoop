<script setup lang="ts">
import { ref, computed } from 'vue';
import Card from '@/Components/ui/Card.vue';
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

const props = defineProps<{
    occurrences: ScheduleOccurrence[];
    selectedDate: string;
    todayDate: string;
    rangeStart: string;
    rangeEnd: string;
    actionLoadingIds: Set<number>;
}>();

const emit = defineEmits<{
    (e: 'navigate-week', offsetWeeks: number): void;
    (e: 'go-to-today'): void;
    (e: 'drill-to-day', date: string): void;
    (e: 'toggle-occurrence', occ: ScheduleOccurrence): void;
    (e: 'log-work-for-occurrence', occ: ScheduleOccurrence): void;
}>();

// Mobile active day selector tab
const mobileSelectedDay = ref(props.selectedDate || props.todayDate);

// Week calendar days generation (7 days: Monday to Sunday)
interface WeekDayItem {
    date: string;
    dayNum: number; // 1 = Mon, 7 = Sun
    dayLabel: string; // "Mon", "Tue"
    fullLabel: string; // "Monday", etc.
    monthDay: number; // 1..31
    isToday: boolean;
    isSelected: boolean;
    occurrences: ScheduleOccurrence[];
    totalPlannedMinutes: number;
}

const weekdayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const fullWeekdayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

const weekDays = computed<WeekDayItem[]>(() => {
    if (!props.rangeStart) return [];

    const startParts = props.rangeStart.split('-').map(Number);
    const startDate = new Date(startParts[0], startParts[1] - 1, startParts[2]);

    const days: WeekDayItem[] = [];

    for (let i = 0; i < 7; i++) {
        const d = new Date(startDate);
        d.setDate(startDate.getDate() + i);

        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        const dateStr = `${yyyy}-${mm}-${dd}`;

        const dayOccurrences = props.occurrences
            .filter((occ) => occ.scheduled_date === dateStr)
            .sort((a, b) => a.start_time.localeCompare(b.start_time));

        const totalPlanned = dayOccurrences.reduce((sum, occ) => sum + (occ.duration_minutes || 0), 0);

        days.push({
            date: dateStr,
            dayNum: i + 1,
            dayLabel: weekdayNames[i],
            fullLabel: fullWeekdayNames[i],
            monthDay: d.getDate(),
            isToday: dateStr === props.todayDate,
            isSelected: dateStr === props.selectedDate,
            occurrences: dayOccurrences,
            totalPlannedMinutes: totalPlanned,
        });
    }

    return days;
});

// Week header summary
const totalWeekPlannedMinutes = computed(() => {
    return props.occurrences.reduce((sum, occ) => sum + (occ.duration_minutes || 0), 0);
});

const totalWeekCompletedMinutes = computed(() => {
    return props.occurrences
        .filter((occ) => occ.status === 'completed')
        .reduce((sum, occ) => sum + (occ.duration_minutes || 0), 0);
});

const totalWeekPlannedHours = computed(() => (totalWeekPlannedMinutes.value / 60).toFixed(1));
const totalWeekCompletedHours = computed(() => (totalWeekCompletedMinutes.value / 60).toFixed(1));

const formattedWeekRangeTitle = computed(() => {
    if (!props.rangeStart || !props.rangeEnd) return '';
    const startParts = props.rangeStart.split('-').map(Number);
    const endParts = props.rangeEnd.split('-').map(Number);
    const d1 = new Date(startParts[0], startParts[1] - 1, startParts[2]);
    const d2 = new Date(endParts[0], endParts[1] - 1, endParts[2]);

    const m1 = d1.toLocaleString('en-US', { month: 'short' });
    const m2 = d2.toLocaleString('en-US', { month: 'short' });
    const y1 = d1.getFullYear();
    const y2 = d2.getFullYear();

    if (m1 === m2 && y1 === y2) {
        return `${m1} ${d1.getDate()} – ${d2.getDate()}, ${y1}`;
    }
    return `${m1} ${d1.getDate()} – ${m2} ${d2.getDate()}, ${y2}`;
});

function formatTime12Hour(timeStr: string): string {
    if (!timeStr) return '';
    const clean = timeStr.substring(0, 5);
    const [h, m] = clean.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${String(m).padStart(2, '0')} ${ampm}`;
}
</script>

<template>
    <div class="space-y-6">
        <!-- WEEK HEADER & NAVIGATION CONTROLS -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 rounded-2xl bg-surface border border-border-subtle shadow-xs">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1 bg-surface-subdued border border-border-subtle/80 rounded-xl p-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="emit('navigate-week', -1)"
                        title="Previous Week"
                    >
                        &larr; Prev
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="emit('go-to-today')"
                        title="Jump to current week"
                    >
                        This Week
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="emit('navigate-week', 1)"
                        title="Next Week"
                    >
                        Next &rarr;
                    </Button>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-sm sm:text-base font-bold text-content-primary">
                        {{ formattedWeekRangeTitle }}
                    </span>
                </div>
            </div>

            <!-- Week Summary Badges -->
            <div class="flex items-center gap-3 text-xs">
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary/10 text-primary border border-primary/20 font-medium">
                    <Icon name="clock" :size="13" />
                    <span><strong>{{ totalWeekPlannedHours }}</strong> hrs planned</span>
                </div>
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-status-success/10 text-status-success border border-status-success/20 font-medium">
                    <Icon name="check-circle" :size="13" />
                    <span><strong>{{ totalWeekCompletedHours }}</strong> hrs completed</span>
                </div>
            </div>
        </div>

        <!-- MOBILE DAY SELECTOR STRIP (< md screens) -->
        <div class="md:hidden">
            <div class="flex items-center gap-1 overflow-x-auto pb-2 scrollbar-none">
                <button
                    v-for="day in weekDays"
                    :key="`tab-${day.date}`"
                    type="button"
                    :class="[
                        'flex-1 min-w-[48px] py-2 px-1 rounded-xl text-center transition-all border cursor-pointer flex flex-col items-center gap-0.5',
                        mobileSelectedDay === day.date
                            ? 'bg-primary text-primary-text border-primary font-bold shadow-xs'
                            : day.isToday
                                ? 'bg-surface border-primary text-primary'
                                : 'bg-surface border-border-subtle text-content-secondary hover:bg-surface-hover',
                    ]"
                    @click="mobileSelectedDay = day.date"
                >
                    <span class="text-[10px] uppercase font-bold">{{ day.dayLabel }}</span>
                    <span class="text-sm font-bold">{{ day.monthDay }}</span>
                    <span v-if="day.occurrences.length > 0" class="w-1.5 h-1.5 rounded-full bg-current opacity-80" />
                </button>
            </div>
        </div>

        <!-- 7-COLUMN WEEK BOARD (DESKTOP) / ACTIVE DAY (MOBILE) -->
        <div class="grid grid-cols-1 md:grid-cols-7 gap-3">
            <div
                v-for="day in weekDays"
                :key="day.date"
                :class="[
                    'flex flex-col rounded-2xl border transition-all min-h-[420px]',
                    'md:flex',
                    mobileSelectedDay === day.date ? 'flex' : 'hidden md:flex',
                    day.isToday
                        ? 'border-primary/40 bg-surface shadow-xs'
                        : 'border-border-subtle bg-surface/70',
                ]"
            >
                <!-- Day Header -->
                <div
                    class="p-3 border-b border-border-subtle/80 flex items-center justify-between cursor-pointer hover:bg-surface-hover transition-colors rounded-t-2xl"
                    @click="emit('drill-to-day', day.date)"
                    title="Click to view full Day focus"
                >
                    <div class="flex items-center gap-2">
                        <div
                            :class="[
                                'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all',
                                day.isToday
                                    ? 'bg-primary text-primary-text shadow-xs'
                                    : 'bg-surface-subdued text-content-primary border border-border-subtle',
                            ]"
                        >
                            {{ day.monthDay }}
                        </div>
                        <div>
                            <span class="text-xs font-bold text-content-primary block leading-tight">
                                {{ day.dayLabel }}
                            </span>
                            <span class="text-[10px] text-content-muted leading-none">
                                {{ day.occurrences.length }} {{ day.occurrences.length === 1 ? 'task' : 'tasks' }}
                            </span>
                        </div>
                    </div>

                    <span
                        v-if="day.totalPlannedMinutes > 0"
                        class="text-[10px] font-mono font-semibold px-2 py-0.5 rounded-full bg-primary/10 text-primary border border-primary/15"
                    >
                        {{ (day.totalPlannedMinutes / 60).toFixed(1) }}h
                    </span>
                </div>

                <!-- Occurrences Column Body -->
                <div class="p-2.5 flex-1 space-y-2 overflow-y-auto max-h-[600px]">
                    <div v-if="day.occurrences.length === 0" class="h-32 flex flex-col items-center justify-center text-center p-3 text-content-muted/60">
                        <Icon name="calendar" :size="20" class="opacity-40 mb-1" />
                        <span class="text-[11px]">No sessions</span>
                    </div>

                    <div
                        v-for="occ in day.occurrences"
                        :key="occ.id"
                        :class="[
                            'p-2.5 rounded-xl border transition-all text-xs space-y-1.5 shadow-2xs group',
                            occ.status === 'completed'
                                ? 'bg-surface-subdued/60 border-status-success/30 text-content-secondary'
                                : 'bg-surface border-border-subtle hover:border-primary/40',
                        ]"
                    >
                        <!-- Top Row: Checkbox + Title -->
                        <div class="flex items-start gap-2">
                            <Checkbox
                                :id="`week-chk-${occ.id}`"
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
                                    :title="occ.task?.title"
                                >
                                    {{ occ.task?.title || 'Untitled Task' }}
                                </span>

                                <div class="flex items-center gap-1.5 text-[10px] text-content-muted mt-0.5">
                                    <span class="font-mono">{{ formatTime12Hour(occ.start_time) }}</span>
                                    <span>•</span>
                                    <span class="font-mono">{{ (occ.duration_minutes / 60).toFixed(1) }}h</span>
                                </div>
                            </div>
                        </div>

                        <!-- Card Action Strip -->
                        <div class="flex items-center justify-between pt-1 border-t border-border-subtle/50 text-[10px]">
                            <Badge
                                v-if="occ.status === 'completed'"
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

                            <button
                                type="button"
                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium text-primary hover:bg-primary/10 transition-colors cursor-pointer"
                                @click="emit('log-work-for-occurrence', occ)"
                                title="Log actual work spent"
                            >
                                <Icon name="plus" :size="10" />
                                <span>Log</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Day Column Footer: Drill-down shortcut -->
                <div class="p-2 border-t border-border-subtle/60 text-center">
                    <button
                        type="button"
                        class="w-full py-1 text-[11px] font-medium text-content-muted hover:text-primary transition-colors cursor-pointer rounded-lg hover:bg-surface-hover"
                        @click="emit('drill-to-day', day.date)"
                    >
                        View {{ day.dayLabel }} &rarr;
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
