<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Components/layout/AppLayout.vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import Badge from '@/Components/ui/Badge.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Input from '@/Components/ui/Input.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import Icon from '@/Components/ui/Icon.vue';
import { useToast } from '@/composables/useToast';

interface TaskSchedule {
    id: number;
    type: string;
    start_date: string;
    end_date: string | null;
    start_time: string;
    duration_minutes: number;
    interval?: number;
    weekdays?: number[] | null;
    month_day?: number | null;
    month_week?: number | null;
    month_weekday?: number | null;
    is_active: boolean;
}

interface TaskOccurrence {
    id: number;
    recurring_schedule_id: number | null;
    scheduled_date: string;
    start_time: string;
    duration_minutes: number;
    status: 'pending' | 'completed' | 'skipped' | 'cancelled';
    is_exception: boolean;
    completed_at: string | null;
    notes: string | null;
}

interface TaskItem {
    id: number;
    title: string;
    description: string | null;
    status: 'active' | 'archived';
    is_archived: boolean;
    has_history: boolean;
    created_at: string;
    created_at_human: string;
    archived_at: string | null;
    schedules?: TaskSchedule[];
    occurrences?: TaskOccurrence[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTasks {
    data: TaskItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    tasks: PaginatedTasks;
    filters: {
        search: string;
        status: 'active' | 'archived' | 'all';
    };
    counts: {
        active: number;
        archived: number;
        total: number;
    };
    userTimezone?: string;
}>();

const toast = useToast();

// Local Search and Filter State
const searchInput = ref(props.filters.search || '');
const currentStatus = ref<'active' | 'archived' | 'all'>(props.filters.status || 'active');
const isSearching = ref(false);

let searchDebounceTimeout: ReturnType<typeof setTimeout> | null = null;

function applyFilters() {
    isSearching.value = true;
    router.get(
        '/tasks',
        {
            search: searchInput.value || undefined,
            status: currentStatus.value !== 'active' ? currentStatus.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                isSearching.value = false;
            },
        }
    );
}

// Watch search input with debounce
watch(searchInput, () => {
    if (searchDebounceTimeout) {
        clearTimeout(searchDebounceTimeout);
    }
    searchDebounceTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
});

// Watch status change immediately
watch(currentStatus, () => {
    applyFilters();
});

function clearSearch() {
    searchInput.value = '';
    applyFilters();
}

function resetAllFilters() {
    searchInput.value = '';
    currentStatus.value = 'active';
    applyFilters();
}

// ==========================================
// CREATE TASK MODAL
// ==========================================
const createModalOpen = ref(false);
const createForm = useForm({
    title: '',
    description: '',
});

function openCreateModal() {
    createForm.reset();
    createForm.clearErrors();
    createModalOpen.value = true;
}

function submitCreate() {
    createForm.post('/tasks', {
        preserveScroll: true,
        onSuccess: () => {
            createModalOpen.value = false;
            createForm.reset();
            toast.success('Task created successfully');
        },
        onError: () => {
            toast.danger('Please check the task details');
        },
    });
}

// ==========================================
// EDIT TASK: 2 TABS (TASK INFO & SCHEDULE RULE)
// ==========================================
const editModalOpen = ref(false);
const editTab = ref<'details' | 'schedule'>('details');
const selectedTaskId = ref<number | null>(null);

const selectedTaskForEdit = computed<TaskItem | null>(() => {
    if (!selectedTaskId.value) return null;
    return props.tasks.data.find((t) => t.id === selectedTaskId.value) || null;
});

// Tab 1: Edit Details Form
const editDetailsForm = useForm({
    title: '',
    description: '',
    status: 'active' as 'active' | 'archived',
});

interface ScheduleSlot {
    id?: number;
    weekdays: number[]; // 1=Mon..7=Sun
    start_time: string; // "14:00"
    end_time: string;   // "16:00"
    duration_minutes: number; // 120
}

// Tab 2: Schedule Rule Form (Slots & Pattern)
const scheduleMode = ref<'weekly' | 'daily' | 'monthly' | 'one_time'>('weekly');
const monthlySubMode = ref<'date' | 'weekday'>('date');
const monthlyDayOfMonth = ref<number>(7);
const monthlyWeekPosition = ref<number>(1);
const monthlyWeekday = ref<number>(1);
const scheduleStartDate = ref(new Date().toISOString().substring(0, 10));
const scheduleHasEndDate = ref(false);
const scheduleEndDate = ref('');
const scheduleSlots = ref<ScheduleSlot[]>([
    {
        weekdays: [1],
        start_time: '14:00',
        end_time: '16:00',
        duration_minutes: 120,
    },
]);
const scheduleSubmitting = ref(false);

const weekdayOptions = [
    { label: 'Monday', short: 'Mon', value: 1 },
    { label: 'Tuesday', short: 'Tue', value: 2 },
    { label: 'Wednesday', short: 'Wed', value: 3 },
    { label: 'Thursday', short: 'Thu', value: 4 },
    { label: 'Friday', short: 'Fri', value: 5 },
    { label: 'Saturday', short: 'Sat', value: 6 },
    { label: 'Sunday', short: 'Sun', value: 7 },
];

function toggleSlotWeekday(slot: ScheduleSlot, day: number) {
    if (slot.weekdays.includes(day)) {
        if (slot.weekdays.length > 1) {
            slot.weekdays = slot.weekdays.filter((d) => d !== day);
        } else {
            toast.info('Each slot requires at least one selected day');
        }
    } else {
        slot.weekdays = [...slot.weekdays, day].sort((a, b) => a - b);
    }
}

function addScheduleSlot() {
    const usedDays = new Set(scheduleSlots.value.flatMap((s) => s.weekdays));
    const nextDay = [1, 2, 3, 4, 5, 6, 7].find((d) => !usedDays.has(d)) || 2;
    scheduleSlots.value.push({
        weekdays: [nextDay],
        start_time: '10:00',
        end_time: '12:00',
        duration_minutes: 120,
    });
}

function removeScheduleSlot(index: number) {
    if (scheduleSlots.value.length > 1) {
        scheduleSlots.value.splice(index, 1);
    }
}

// Helper: Calculate duration from start and end times
function calculateDurationFromTimes(start: string, end: string): number {
    if (!start || !end) return 60;
    const [startH, startM] = start.split(':').map(Number);
    const [endH, endM] = end.split(':').map(Number);
    let diff = (endH * 60 + endM) - (startH * 60 + startM);
    if (diff <= 0) {
        diff += 24 * 60;
    }
    return Math.max(1, Math.min(1440, diff));
}

// Helper: Calculate end time from start time and duration
function calculateEndTimeFromDuration(start: string, durationMinutes: number): string {
    if (!start) return '11:00';
    const [startH, startM] = start.split(':').map(Number);
    const totalMinutes = (startH * 60 + startM + durationMinutes) % (24 * 60);
    const endH = Math.floor(totalMinutes / 60);
    const endM = totalMinutes % 60;
    return `${String(endH).padStart(2, '0')}:${String(endM).padStart(2, '0')}`;
}

// Helper: Format 24h time to 12h AM/PM
function formatTime12Hour(timeStr: string): string {
    if (!timeStr) return '';
    const parts = timeStr.split(':');
    let h = parseInt(parts[0], 10);
    const m = parts[1] || '00';
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12;
    if (h === 0) h = 12;
    return `${h}:${m} ${ampm}`;
}

function onSlotStartTimeChange(slot: ScheduleSlot) {
    slot.end_time = calculateEndTimeFromDuration(slot.start_time, slot.duration_minutes);
}

function onSlotEndTimeChange(slot: ScheduleSlot) {
    slot.duration_minutes = calculateDurationFromTimes(slot.start_time, slot.end_time);
}

function setSlotDuration(slot: ScheduleSlot, mins: number) {
    slot.duration_minutes = mins;
    slot.end_time = calculateEndTimeFromDuration(slot.start_time, mins);
}

// Visual Weekly Day Helpers for Apple-style Summary
const weekCalendarDays = [
    { num: 1, label: 'M', name: 'Mon' },
    { num: 2, label: 'T', name: 'Tue' },
    { num: 3, label: 'W', name: 'Wed' },
    { num: 4, label: 'T', name: 'Thu' },
    { num: 5, label: 'F', name: 'Fri' },
    { num: 6, label: 'S', name: 'Sat' },
    { num: 7, label: 'S', name: 'Sun' },
];

function formatDateFriendly(dateStr: string): string {
    if (!dateStr) return '';
    try {
        const [y, m, d] = dateStr.split('-').map(Number);
        const date = new Date(y, m - 1, d);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch {
        return dateStr;
    }
}

function getOrdinalSuffix(n: number): string {
    const j = n % 10;
    const k = n % 100;
    if (j === 1 && k !== 11) return 'st';
    if (j === 2 && k !== 12) return 'nd';
    if (j === 3 && k !== 13) return 'rd';
    return 'th';
}

function formatMonthlyWeekdayPhrase(weekPosition: number, weekday: number): string {
    const posMap: Record<number, string> = {
        1: '1st',
        2: '2nd',
        3: '3rd',
        4: '4th',
        [-1]: 'Last',
    };
    const dayMap: Record<number, string> = {
        1: 'Monday',
        2: 'Tuesday',
        3: 'Wednesday',
        4: 'Thursday',
        5: 'Friday',
        6: 'Saturday',
        7: 'Sunday',
    };
    const pos = posMap[weekPosition] || '1st';
    const day = dayMap[weekday] || 'Monday';
    return `${pos} ${day}`;
}

const activeScheduledDaysSet = computed<Set<number>>(() => {
    const set = new Set<number>();
    if (scheduleMode.value === 'daily') {
        return new Set([1, 2, 3, 4, 5, 6, 7]);
    }
    if (scheduleMode.value === 'one_time') {
        if (scheduleStartDate.value) {
            const [y, m, d] = scheduleStartDate.value.split('-').map(Number);
            const date = new Date(y, m - 1, d);
            const jsDay = date.getDay(); // 0=Sun, 1=Mon...
            const isoDay = jsDay === 0 ? 7 : jsDay;
            set.add(isoDay);
        }
        return set;
    }
    if (scheduleMode.value === 'monthly') {
        if (monthlySubMode.value === 'weekday') {
            set.add(monthlyWeekday.value);
        } else if (scheduleStartDate.value) {
            const [y, m] = scheduleStartDate.value.split('-').map(Number);
            const date = new Date(y, m - 1, monthlyDayOfMonth.value);
            const jsDay = date.getDay();
            const isoDay = jsDay === 0 ? 7 : jsDay;
            set.add(isoDay);
        }
        return set;
    }
    for (const slot of scheduleSlots.value) {
        for (const day of slot.weekdays) {
            set.add(day);
        }
    }
    return set;
});

const activeDaysCount = computed(() => {
    return activeScheduledDaysSet.value.size;
});

const hoursPerWeekday = computed<Record<number, number>>(() => {
    const map: Record<number, number> = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0, 6: 0, 7: 0 };
    if (scheduleMode.value === 'daily') {
        const slot = scheduleSlots.value[0];
        const hrs = slot ? Number((slot.duration_minutes / 60).toFixed(1)) : 0;
        for (let d = 1; d <= 7; d++) {
            map[d] = hrs;
        }
        return map;
    }
    if (scheduleMode.value === 'one_time') {
        if (scheduleStartDate.value) {
            const [y, m, d] = scheduleStartDate.value.split('-').map(Number);
            const date = new Date(y, m - 1, d);
            const jsDay = date.getDay();
            const isoDay = jsDay === 0 ? 7 : jsDay;
            map[isoDay] = Number(((scheduleSlots.value[0]?.duration_minutes || 0) / 60).toFixed(1));
        }
        return map;
    }
    if (scheduleMode.value === 'monthly') {
        const slot = scheduleSlots.value[0];
        const hrs = slot ? Number((slot.duration_minutes / 60).toFixed(1)) : 0;
        if (monthlySubMode.value === 'weekday') {
            map[monthlyWeekday.value] = hrs;
        } else if (scheduleStartDate.value) {
            const [y, m] = scheduleStartDate.value.split('-').map(Number);
            const date = new Date(y, m - 1, monthlyDayOfMonth.value);
            const jsDay = date.getDay();
            const isoDay = jsDay === 0 ? 7 : jsDay;
            map[isoDay] = hrs;
        }
        return map;
    }
    for (const slot of scheduleSlots.value) {
        const hrs = slot.duration_minutes / 60;
        for (const day of slot.weekdays) {
            map[day] = Number(((map[day] || 0) + hrs).toFixed(1));
        }
    }
    return map;
});

const totalPlannedWeeklyMinutes = computed(() => {
    if (scheduleMode.value === 'one_time') {
        return scheduleSlots.value[0]?.duration_minutes || 0;
    }
    if (scheduleMode.value === 'monthly') {
        return scheduleSlots.value[0]?.duration_minutes || 0;
    }
    if (scheduleMode.value === 'daily') {
        return (scheduleSlots.value[0]?.duration_minutes || 0) * 7;
    }
    return scheduleSlots.value.reduce((acc, slot) => {
        return acc + slot.duration_minutes * slot.weekdays.length;
    }, 0);
});

const totalPlannedWeeklyHours = computed(() => {
    return (totalPlannedWeeklyMinutes.value / 60).toFixed(1);
});

const totalWeeklySessionsCount = computed(() => {
    if (scheduleMode.value === 'one_time') return 1;
    if (scheduleMode.value === 'monthly') return 1;
    if (scheduleMode.value === 'daily') return 7;
    return scheduleSlots.value.reduce((acc, slot) => acc + slot.weekdays.length, 0);
});

interface FormattedRoutineSlot {
    daysLabel: string;
    start12: string;
    end12: string;
    durationHours: string;
}

const formattedRoutineSlots = computed<FormattedRoutineSlot[]>(() => {
    const daysMap: Record<number, string> = {
        1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat', 7: 'Sun'
    };

    if (scheduleMode.value === 'one_time') {
        const slot = scheduleSlots.value[0];
        if (!slot) return [];
        return [{
            daysLabel: formatDateFriendly(scheduleStartDate.value) || 'One-time Session',
            start12: formatTime12Hour(slot.start_time),
            end12: formatTime12Hour(slot.end_time),
            durationHours: (slot.duration_minutes / 60).toFixed(1),
        }];
    }

    if (scheduleMode.value === 'monthly') {
        const slot = scheduleSlots.value[0];
        if (!slot) return [];
        const label = monthlySubMode.value === 'date'
            ? `Every ${monthlyDayOfMonth.value}${getOrdinalSuffix(monthlyDayOfMonth.value)} of month`
            : `Every ${formatMonthlyWeekdayPhrase(monthlyWeekPosition.value, monthlyWeekday.value)}`;
        return [{
            daysLabel: label,
            start12: formatTime12Hour(slot.start_time),
            end12: formatTime12Hour(slot.end_time),
            durationHours: (slot.duration_minutes / 60).toFixed(1),
        }];
    }

    if (scheduleMode.value === 'daily') {
        const slot = scheduleSlots.value[0];
        if (!slot) return [];
        return [{
            daysLabel: 'Every Day',
            start12: formatTime12Hour(slot.start_time),
            end12: formatTime12Hour(slot.end_time),
            durationHours: (slot.duration_minutes / 60).toFixed(1),
        }];
    }

    return scheduleSlots.value.map((slot) => {
        const daysLabel = slot.weekdays.map((d) => daysMap[d]).join(', ') || 'No Day';
        return {
            daysLabel,
            start12: formatTime12Hour(slot.start_time),
            end12: formatTime12Hour(slot.end_time),
            durationHours: (slot.duration_minutes / 60).toFixed(1),
        };
    });
});

function openEditModal(task: TaskItem, initialTab: 'details' | 'schedule' = 'details') {
    selectedTaskId.value = task.id;
    editTab.value = initialTab;

    // Fill details
    editDetailsForm.title = task.title;
    editDetailsForm.description = task.description || '';
    editDetailsForm.status = task.status;
    editDetailsForm.clearErrors();

    // Pre-fill or default schedule state
    scheduleStartDate.value = new Date().toISOString().substring(0, 10);
    scheduleHasEndDate.value = false;
    scheduleEndDate.value = '';

    if (task.schedules && task.schedules.length > 0) {
        const first = task.schedules[0];
        scheduleStartDate.value = first.start_date || new Date().toISOString().substring(0, 10);
        if (first.end_date) {
            scheduleHasEndDate.value = true;
            scheduleEndDate.value = first.end_date;
        }

        if (first.type === 'one_time') {
            scheduleMode.value = 'one_time';
            scheduleStartDate.value = first.start_date || new Date().toISOString().substring(0, 10);
            scheduleHasEndDate.value = false;
            scheduleEndDate.value = '';
            scheduleSlots.value = [
                {
                    id: first.id,
                    weekdays: [1],
                    start_time: first.start_time ? first.start_time.substring(0, 5) : '10:00',
                    end_time: calculateEndTimeFromDuration(first.start_time, first.duration_minutes || 60),
                    duration_minutes: first.duration_minutes || 60,
                },
            ];
        } else if (first.type === 'monthly_date') {
            scheduleMode.value = 'monthly';
            monthlySubMode.value = 'date';
            monthlyDayOfMonth.value = first.month_day || 7;
            scheduleStartDate.value = first.start_date || new Date().toISOString().substring(0, 10);
            scheduleHasEndDate.value = Boolean(first.end_date);
            scheduleEndDate.value = first.end_date || '';
            scheduleSlots.value = [
                {
                    id: first.id,
                    weekdays: [1],
                    start_time: first.start_time ? first.start_time.substring(0, 5) : '10:00',
                    end_time: calculateEndTimeFromDuration(first.start_time, first.duration_minutes || 60),
                    duration_minutes: first.duration_minutes || 60,
                },
            ];
        } else if (first.type === 'monthly_day') {
            scheduleMode.value = 'monthly';
            monthlySubMode.value = 'weekday';
            monthlyWeekPosition.value = first.month_week || 1;
            monthlyWeekday.value = first.month_weekday || 1;
            scheduleStartDate.value = first.start_date || new Date().toISOString().substring(0, 10);
            scheduleHasEndDate.value = Boolean(first.end_date);
            scheduleEndDate.value = first.end_date || '';
            scheduleSlots.value = [
                {
                    id: first.id,
                    weekdays: [first.month_weekday || 1],
                    start_time: first.start_time ? first.start_time.substring(0, 5) : '10:00',
                    end_time: calculateEndTimeFromDuration(first.start_time, first.duration_minutes || 60),
                    duration_minutes: first.duration_minutes || 60,
                },
            ];
        } else if (first.type === 'daily') {
            scheduleMode.value = 'daily';
            scheduleSlots.value = [
                {
                    id: first.id,
                    weekdays: [1, 2, 3, 4, 5, 6, 7],
                    start_time: first.start_time ? first.start_time.substring(0, 5) : '17:00',
                    end_time: calculateEndTimeFromDuration(first.start_time, first.duration_minutes || 60),
                    duration_minutes: first.duration_minutes || 60,
                },
            ];
        } else {
            // weekly - map each recurring schedule to an editable slot!
            scheduleMode.value = 'weekly';
            scheduleSlots.value = task.schedules.map((s) => ({
                id: s.id,
                weekdays: s.weekdays && s.weekdays.length > 0 ? [...s.weekdays] : [1],
                start_time: s.start_time ? s.start_time.substring(0, 5) : '14:00',
                end_time: calculateEndTimeFromDuration(s.start_time, s.duration_minutes || 60),
                duration_minutes: s.duration_minutes || 60,
            }));
        }
    } else {
        scheduleMode.value = 'weekly';
        scheduleSlots.value = [
            {
                weekdays: [1],
                start_time: '14:00',
                end_time: '16:00',
                duration_minutes: 120,
            },
        ];
    }

    editModalOpen.value = true;
}

function submitEditDetails() {
    if (!selectedTaskId.value) return;

    editDetailsForm.put(`/tasks/${selectedTaskId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Task details updated successfully');
        },
        onError: () => {
            toast.danger('Please fix errors before saving');
        },
    });
}

function submitScheduleRule() {
    if (!selectedTaskId.value) return;

    scheduleSubmitting.value = true;
    const rulesPayload: any[] = [];

    if (scheduleMode.value === 'one_time') {
        const slot = scheduleSlots.value[0] || { start_time: '10:00', duration_minutes: 60 };
        rulesPayload.push({
            type: 'one_time',
            start_date: scheduleStartDate.value,
            end_date: null,
            start_time: slot.start_time,
            duration_minutes: Number(slot.duration_minutes),
            weekdays: null,
        });
    } else if (scheduleMode.value === 'monthly') {
        const slot = scheduleSlots.value[0] || { start_time: '10:00', duration_minutes: 60 };
        if (monthlySubMode.value === 'date') {
            rulesPayload.push({
                type: 'monthly_date',
                start_date: scheduleStartDate.value,
                end_date: scheduleHasEndDate.value && scheduleEndDate.value ? scheduleEndDate.value : null,
                start_time: slot.start_time,
                duration_minutes: Number(slot.duration_minutes),
                month_day: Number(monthlyDayOfMonth.value),
            });
        } else {
            rulesPayload.push({
                type: 'monthly_day',
                start_date: scheduleStartDate.value,
                end_date: scheduleHasEndDate.value && scheduleEndDate.value ? scheduleEndDate.value : null,
                start_time: slot.start_time,
                duration_minutes: Number(slot.duration_minutes),
                month_week: Number(monthlyWeekPosition.value),
                month_weekday: Number(monthlyWeekday.value),
            });
        }
    } else if (scheduleMode.value === 'daily') {
        const slot = scheduleSlots.value[0] || { start_time: '17:00', duration_minutes: 60 };
        rulesPayload.push({
            type: 'daily',
            start_date: scheduleStartDate.value,
            end_date: scheduleHasEndDate.value && scheduleEndDate.value ? scheduleEndDate.value : null,
            start_time: slot.start_time,
            duration_minutes: Number(slot.duration_minutes),
            weekdays: null,
        });
    } else {
        // weekly slots
        for (const slot of scheduleSlots.value) {
            if (!slot.weekdays || slot.weekdays.length === 0) continue;
            rulesPayload.push({
                type: 'weekly',
                start_date: scheduleStartDate.value,
                end_date: scheduleHasEndDate.value && scheduleEndDate.value ? scheduleEndDate.value : null,
                start_time: slot.start_time,
                duration_minutes: Number(slot.duration_minutes),
                weekdays: slot.weekdays,
            });
        }
    }

    router.post(
        `/tasks/${selectedTaskId.value}/schedules/sync`,
        {
            timezone: props.userTimezone || 'UTC',
            start_date: scheduleStartDate.value,
            end_date: scheduleMode.value === 'one_time' ? null : (scheduleHasEndDate.value && scheduleEndDate.value ? scheduleEndDate.value : null),
            rules: rulesPayload,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Schedule rule saved & occurrences planned!');
            },
            onError: (errors) => {
                const first = Object.values(errors)[0] as string;
                toast.danger(first || 'Failed to save schedule rule');
            },
            onFinish: () => {
                scheduleSubmitting.value = false;
            },
        }
    );
}

function clearSchedule() {
    if (!selectedTaskId.value) return;
    if (!confirm('Are you sure you want to remove all schedule rules for this task? Future pending occurrences will be cancelled.')) return;

    scheduleSubmitting.value = true;
    router.post(
        `/tasks/${selectedTaskId.value}/schedules/sync`,
        {
            timezone: props.userTimezone || 'UTC',
            start_date: scheduleStartDate.value,
            end_date: null,
            rules: [],
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.info('Schedule removed. Task is now unscheduled.');
                scheduleSlots.value = [
                    {
                        weekdays: [1],
                        start_time: '14:00',
                        end_time: '16:00',
                        duration_minutes: 120,
                    },
                ];
            },
            onError: () => {
                toast.danger('Could not remove schedule');
            },
            onFinish: () => {
                scheduleSubmitting.value = false;
            },
        }
    );
}

// Archive / Unarchive Quick Action
function toggleArchive(task: TaskItem) {
    const action = task.is_archived ? 'unarchive' : 'archive';
    router.patch(
        `/tasks/${task.id}/${action}`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(task.is_archived ? 'Task restored to active' : 'Task archived');
            },
        }
    );
}

// Delete Confirmation Modal State
const deleteModalOpen = ref(false);
const taskToDelete = ref<TaskItem | null>(null);
const deleteForm = useForm({});

function openDeleteModal(task: TaskItem) {
    taskToDelete.value = task;
    deleteModalOpen.value = true;
}

function confirmDelete() {
    if (!taskToDelete.value) return;

    deleteForm.delete(`/tasks/${taskToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteModalOpen.value = false;
            toast.success('Task permanently deleted');
        },
        onError: (errors: any) => {
            if (errors?.delete) {
                toast.danger(errors.delete);
            } else {
                toast.danger('Could not delete task');
            }
        },
    });
}

function archiveInsteadOfDelete() {
    if (!taskToDelete.value) return;
    deleteModalOpen.value = false;
    toggleArchive(taskToDelete.value);
}

// Helpers for formatted summaries
function formatScheduleSummary(task: TaskItem): string {
    if (!task.schedules || task.schedules.length === 0) {
        return 'Unscheduled';
    }
    const daysMap: Record<number, string> = {
        1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat', 7: 'Sun'
    };
    if (task.schedules.length === 1) {
        const sched = task.schedules[0];
        const hrs = (sched.duration_minutes / 60).toFixed(1);
        const start12 = formatTime12Hour(sched.start_time);
        const end12 = formatTime12Hour(calculateEndTimeFromDuration(sched.start_time, sched.duration_minutes));

        if (sched.type === 'one_time') {
            return `One-time: ${start12} – ${end12} (${hrs}h)`;
        }
        if (sched.type === 'daily') {
            return `Every day: ${start12} – ${end12} (${hrs}h)`;
        }
        if (sched.type === 'weekly') {
            const days = sched.weekdays
                ? sched.weekdays.map((d) => daysMap[d]).join(', ')
                : 'Weekly';
            return `Every ${days}: ${start12} – ${end12} (${hrs}h)`;
        }
        if (sched.type === 'monthly_date') {
            const day = sched.month_day || 1;
            return `Monthly on the ${day}${getOrdinalSuffix(day)}: ${start12} – ${end12} (${hrs}h)`;
        }
        if (sched.type === 'monthly_day') {
            const pos = sched.month_week || 1;
            const wday = sched.month_weekday || 1;
            return `Monthly on the ${formatMonthlyWeekdayPhrase(pos, wday)}: ${start12} – ${end12} (${hrs}h)`;
        }
        return `${sched.type}: ${start12} – ${end12} (${hrs}h)`;
    }

    // Multiple rules (e.g. Mon 2–4 PM, Tue 10–12 AM)
    const ruleSnippets = task.schedules.map((s) => {
        const days = s.weekdays
            ? s.weekdays.map((d) => daysMap[d]).join(', ')
            : s.type;
        const start12 = formatTime12Hour(s.start_time);
        const end12 = formatTime12Hour(calculateEndTimeFromDuration(s.start_time, s.duration_minutes));
        return `${days} ${start12}–${end12}`;
    });

    const totalWeeklyMins = task.schedules.reduce((acc, s) => {
        const dCount = s.weekdays ? s.weekdays.length : (s.type === 'daily' ? 7 : 1);
        return acc + s.duration_minutes * dCount;
    }, 0);
    const totalWeeklyHrs = (totalWeeklyMins / 60).toFixed(1);

    return `${ruleSnippets.join(', ')} (${totalWeeklyHrs}h/wk)`;
}

function formatSingleRuleDescription(sched: TaskSchedule): string {
    const hrs = (sched.duration_minutes / 60).toFixed(1);
    const start12 = formatTime12Hour(sched.start_time);
    const end12 = formatTime12Hour(calculateEndTimeFromDuration(sched.start_time, sched.duration_minutes));

    if (sched.type === 'one_time') {
        return `One-time on ${sched.start_date}: ${start12} – ${end12} (${hrs}h)`;
    }
    if (sched.type === 'daily') {
        return `Every day: ${start12} – ${end12} (${hrs}h)`;
    }
    if (sched.type === 'weekly') {
        const days = sched.weekdays
            ? sched.weekdays.map((d) => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][d - 1]).join(', ')
            : 'Weekly';
        return `Every ${days}: ${start12} – ${end12} (${hrs}h)`;
    }
    if (sched.type === 'monthly_date') {
        const day = sched.month_day || 1;
        return `Every ${day}${getOrdinalSuffix(day)} of the month: ${start12} – ${end12} (${hrs}h)`;
    }
    if (sched.type === 'monthly_day') {
        const pos = sched.month_week || 1;
        const wday = sched.month_weekday || 1;
        return `Every ${formatMonthlyWeekdayPhrase(pos, wday)} of the month: ${start12} – ${end12} (${hrs}h)`;
    }
    return `${sched.type}: ${start12} – ${end12} (${hrs}h)`;
}
</script>

<template>
    <Head title="Tasks - LifeLoop" />

    <AppLayout current-tab="tasks">
        <!-- Page Header -->
        <PageHeader
            title="Task Management"
            subtitle="Organize, schedule recurrence, and manage task rules with full ownership"
        >
            <template #actions>
                <Button
                    variant="primary"
                    icon="plus"
                    @click="openCreateModal"
                >
                    Create Task
                </Button>
            </template>
        </PageHeader>

        <!-- Search, Filter & Status Toolbar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 mb-6">
            <!-- Search Box with Clear Button -->
            <div class="relative flex-1 max-w-md">
                <Input
                    v-model="searchInput"
                    type="text"
                    placeholder="Search tasks by title or description..."
                    icon="search"
                    class="w-full"
                />
                <button
                    v-if="searchInput"
                    type="button"
                    @click="clearSearch"
                    class="absolute right-3 top-2.5 text-content-muted hover:text-content-primary p-1 rounded-md transition-colors"
                    aria-label="Clear search"
                >
                    <Icon name="x" :size="14" />
                </button>
            </div>

            <!-- Status Filter Control -->
            <div class="flex items-center gap-2 self-start sm:self-auto">
                <SegmentedControl
                    v-model="currentStatus"
                    size="sm"
                    :options="[
                        { label: `Active (${counts.active})`, value: 'active' },
                        { label: `Archived (${counts.archived})`, value: 'archived' },
                        { label: `All (${counts.total})`, value: 'all' },
                    ]"
                />
            </div>
        </div>

        <!-- LOADING SKELETON STATE -->
        <div v-if="isSearching" class="space-y-3">
            <Skeleton v-for="n in 3" :key="n" height="h-24" />
        </div>

        <!-- EMPTY STATES -->
        <div v-else-if="tasks.data.length === 0" class="my-8">
            <EmptyState
                v-if="!searchInput && currentStatus === 'active' && counts.total === 0"
                title="No tasks created yet"
                description="Get started by creating your first task. Tasks organize your daily workload and track progress over time."
                icon="tasks"
            >
                <template #action>
                    <Button variant="primary" icon="plus" @click="openCreateModal">
                        Create Your First Task
                    </Button>
                </template>
            </EmptyState>

            <EmptyState
                v-else-if="!searchInput && currentStatus === 'archived' && counts.archived === 0"
                title="No archived tasks"
                description="When you archive completed or inactive tasks, they will appear here safely preserved for your records."
                icon="archive"
            >
                <template #action>
                    <Button variant="secondary" @click="currentStatus = 'active'">
                        View Active Tasks
                    </Button>
                </template>
            </EmptyState>

            <EmptyState
                v-else
                title="No matching tasks found"
                description="We couldn't find any tasks matching your current search query or filter."
                icon="search"
            >
                <template #action>
                    <Button variant="secondary" icon="x" @click="resetAllFilters">
                        Reset Search & Filters
                    </Button>
                </template>
            </EmptyState>
        </div>

        <!-- TASK LIST VIEW -->
        <div v-else class="space-y-3">
            <div
                v-for="task in tasks.data"
                :key="task.id"
                :class="[
                    'group rounded-xl border p-4 sm:p-5 transition-all flex flex-col lg:flex-row lg:items-center justify-between gap-4',
                    'bg-surface hover:border-border-strong hover:shadow-xs',
                    task.is_archived
                        ? 'border-border-subtle bg-surface-subdued/40 opacity-75'
                        : 'border-border-subtle',
                ]"
            >
                <!-- Task Details -->
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h3
                            :class="[
                                'text-base font-semibold tracking-tight transition-colors cursor-pointer hover:text-primary',
                                task.is_archived
                                    ? 'line-through text-content-muted'
                                    : 'text-content-primary',
                            ]"
                            @click="openEditModal(task, 'details')"
                        >
                            {{ task.title }}
                        </h3>

                        <!-- Status Badge -->
                        <Badge
                            v-if="task.is_archived"
                            variant="neutral"
                            size="sm"
                        >
                            Archived
                        </Badge>
                        <Badge
                            v-else
                            variant="planned"
                            size="sm"
                            dot
                        >
                            Active
                        </Badge>

                        <!-- Schedule Rule Summary Pill -->
                        <Badge
                            v-if="task.schedules && task.schedules.length > 0"
                            variant="extra"
                            size="sm"
                            class="cursor-pointer hover:bg-primary/20"
                            @click="openEditModal(task, 'schedule')"
                            title="Click to view or edit schedule rule"
                        >
                            <Icon name="calendar" :size="11" />
                            <span v-if="task.schedules.length === 1">
                                {{ formatScheduleSummary(task) }}
                            </span>
                            <span v-else>
                                {{ task.schedules.length }} Rules: {{ formatScheduleSummary(task) }}
                            </span>
                        </Badge>
                        <Badge
                            v-else
                            variant="neutral"
                            size="sm"
                            class="cursor-pointer hover:bg-surface-subdued"
                            @click="openEditModal(task, 'schedule')"
                            title="Click to schedule this task"
                        >
                            Unscheduled
                        </Badge>

                        <!-- History Protection Indicator -->
                        <Badge
                            v-if="task.has_history"
                            variant="neutral"
                            size="sm"
                            title="Has historical records — protected from accidental deletion"
                        >
                            Protected History
                        </Badge>
                    </div>

                    <!-- Description -->
                    <p
                        v-if="task.description"
                        class="text-xs text-content-secondary line-clamp-2 leading-relaxed"
                    >
                        {{ task.description }}
                    </p>

                    <!-- Metadata -->
                    <div class="flex items-center gap-3 text-xs text-content-muted pt-0.5">
                        <span class="flex items-center gap-1">
                            <Icon name="calendar" :size="13" />
                            Created {{ task.created_at_human }}
                        </span>
                        <span v-if="task.archived_at" class="flex items-center gap-1">
                            &bull;
                            <Icon name="archive" :size="13" />
                            Archived
                        </span>
                    </div>
                </div>

                <!-- Action Controls -->
                <div class="flex items-center gap-2 shrink-0 pt-2 lg:pt-0 border-t lg:border-t-0 border-border-subtle">
                    <!-- Schedule Rule Action -->
                    <Button
                        variant="subtle"
                        size="sm"
                        icon="calendar"
                        @click="openEditModal(task, 'schedule')"
                        title="Configure recurrence schedule rules"
                    >
                        Schedule Rule
                    </Button>

                    <!-- Edit Task Info Action -->
                    <Button
                        variant="ghost"
                        size="sm"
                        icon="edit"
                        @click="openEditModal(task, 'details')"
                    >
                        Edit
                    </Button>

                    <!-- View Task Report Action -->
                    <Button
                        variant="ghost"
                        size="sm"
                        icon="chart"
                        @click="router.visit(`/tasks/${task.id}/report`)"
                        title="View task execution and performance report"
                    >
                        Report
                    </Button>

                    <!-- Archive / Unarchive Action -->
                    <Button
                        variant="ghost"
                        size="sm"
                        :icon="task.is_archived ? 'restore' : 'archive'"
                        @click="toggleArchive(task)"
                    >
                        {{ task.is_archived ? 'Restore' : 'Archive' }}
                    </Button>

                    <!-- Delete Button -->
                    <IconButton
                        icon="trash"
                        label="Delete task"
                        size="sm"
                        class="text-content-muted hover:text-status-danger"
                        @click="openDeleteModal(task)"
                    />
                </div>
            </div>

            <!-- Pagination Bar -->
            <div
                v-if="tasks.last_page > 1"
                class="flex items-center justify-between pt-4 border-t border-border-subtle text-xs text-content-muted"
            >
                <span>
                    Showing {{ tasks.data.length }} of {{ tasks.total }} tasks
                </span>

                <div class="flex items-center gap-1">
                    <template v-for="(link, i) in tasks.links" :key="i">
                        <button
                            v-if="link.url"
                            type="button"
                            @click="router.visit(link.url, { preserveScroll: true })"
                            :class="[
                                'px-3 py-1.5 rounded-lg font-medium transition-colors cursor-pointer',
                                link.active
                                    ? 'bg-primary text-primary-text font-semibold'
                                    : 'hover:bg-surface-subdued text-content-secondary',
                            ]"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="px-2 py-1.5 opacity-40 select-none"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- DIALOG: CREATE TASK                        -->
        <!-- ========================================== -->
        <Dialog
            :open="createModalOpen"
            title="Create Task"
            description="Add a task to your backlog. You can configure flexible schedule rules once created."
            @close="createModalOpen = false"
        >
            <form @submit.prevent="submitCreate" class="space-y-4">
                <Input
                    v-model="createForm.title"
                    label="Task Title"
                    placeholder="e.g., Deep Focus & Architecture Spec"
                    required
                    :error="createForm.errors.title"
                />

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold tracking-tight text-content-primary">
                        Description (Optional)
                    </label>
                    <textarea
                        v-model="createForm.description"
                        rows="3"
                        placeholder="Add checklist, context, requirements, or links..."
                        class="w-full rounded-xl border border-border-subtle bg-surface px-3 py-2 text-sm text-content-primary placeholder:text-content-muted focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary transition-colors"
                        maxlength="2000"
                    />
                </div>

                <div class="pt-2 flex justify-end">
                    <Button
                        type="submit"
                        variant="primary"
                        :loading="createForm.processing"
                        :disabled="createForm.processing"
                    >
                        Create Task
                    </Button>
                </div>
            </form>
        </Dialog>

        <!-- ======================================================== -->
        <!-- DIALOG: EDIT TASK (ONLY 2 TABS: TASK INFO & SCHEDULE RULE)-->
        <!-- ======================================================== -->
        <Dialog
            :open="editModalOpen"
            :title="`Manage Task: ${selectedTaskForEdit?.title || ''}`"
            description="Update task details or configure flexible recurring schedule rules."
            maxWidth="4xl"
            @close="editModalOpen = false"
        >
            <div class="space-y-4">
                <!-- TOP TAB BAR & STATUS -->
                <div class="flex items-center justify-between border-b border-border-subtle/80 pb-3">
                    <SegmentedControl
                        v-model="editTab"
                        size="sm"
                        :options="[
                            { label: 'Task Info', value: 'details' },
                            { label: 'Schedule Rule', value: 'schedule' },
                        ]"
                    />
                    <div v-if="selectedTaskForEdit" class="text-xs text-content-muted flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-surface-subdued/80 border border-border-subtle/50">
                        <span
                            class="w-2 h-2 rounded-full"
                            :class="selectedTaskForEdit.status === 'active' ? 'bg-status-success' : 'bg-content-muted'"
                        />
                        <span class="capitalize font-medium text-[11px] text-content-secondary">
                            {{ selectedTaskForEdit.status }}
                        </span>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- TAB 1: TASK INFO                           -->
                <!-- ========================================== -->
                <div v-if="editTab === 'details'" class="max-w-lg mx-auto py-2 space-y-4">
                    <div class="p-4 sm:p-5 rounded-2xl bg-surface-subdued/40 border border-border-subtle/80 space-y-4 shadow-xs">
                        <div>
                            <label class="block text-[11px] font-bold tracking-wider text-content-muted uppercase mb-1.5">
                                Task Title
                            </label>
                            <input
                                v-model="editDetailsForm.title"
                                type="text"
                                placeholder="What do you plan to work on?"
                                class="w-full text-sm bg-surface text-content-primary border border-border-subtle rounded-xl px-3 py-2.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all placeholder:text-content-muted"
                                required
                            />
                            <span v-if="editDetailsForm.errors.title" class="text-xs text-status-danger mt-1 block">
                                {{ editDetailsForm.errors.title }}
                            </span>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold tracking-wider text-content-muted uppercase mb-1.5">
                                Description (Optional)
                            </label>
                            <textarea
                                v-model="editDetailsForm.description"
                                rows="3"
                                placeholder="Add checklist, context, notes, or links..."
                                class="w-full text-xs bg-surface text-content-primary border border-border-subtle rounded-xl px-3 py-2.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all placeholder:text-content-muted resize-y"
                                maxlength="2000"
                            />
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold tracking-wider text-content-muted uppercase mb-1.5">
                                Task Status
                            </label>
                            <SegmentedControl
                                v-model="editDetailsForm.status"
                                size="sm"
                                :options="[
                                    { label: 'Active', value: 'active' },
                                    { label: 'Archived', value: 'archived' },
                                ]"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end pt-1">
                        <Button
                            variant="primary"
                            :loading="editDetailsForm.processing"
                            @click="submitEditDetails"
                        >
                            Save Task Info
                        </Button>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- TAB 2: SCHEDULE RULE (2 COLUMNS APPLE-STYLE) -->
                <!-- ========================================== -->
                <div v-else-if="editTab === 'schedule'" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-start">
                        <!-- LEFT COLUMN: Pattern, Date Window & Apple Live Summary -->
                        <div class="space-y-3.5">
                            <!-- Schedule Pattern Selector -->
                            <div class="space-y-1.5">
                                <label class="block text-[11px] font-bold tracking-wider text-content-muted uppercase">
                                    Schedule Pattern
                                </label>
                                <SegmentedControl
                                    v-model="scheduleMode"
                                    size="sm"
                                    class="w-full"
                                    :options="[
                                        { label: 'Weekly Slots', value: 'weekly' },
                                        { label: 'Every Day', value: 'daily' },
                                        { label: 'Monthly', value: 'monthly' },
                                        { label: 'One-Time', value: 'one_time' },
                                    ]"
                                />
                            </div>

                            <!-- Monthly Recurrence Rule Card (Only if Monthly) -->
                            <div v-if="scheduleMode === 'monthly'" class="p-3.5 rounded-2xl bg-surface-subdued/50 border border-border-subtle/80 space-y-2.5">
                                <span class="text-[10px] font-bold tracking-wider text-content-muted uppercase block">
                                    Monthly Recurrence Rule
                                </span>
                                <SegmentedControl
                                    v-model="monthlySubMode"
                                    size="sm"
                                    class="w-full"
                                    :options="[
                                        { label: 'By Date (e.g. 7th)', value: 'date' },
                                        { label: 'By Day (e.g. 1st Mon)', value: 'weekday' },
                                    ]"
                                />

                                <!-- Option 1: By Calendar Date -->
                                <div v-if="monthlySubMode === 'date'" class="pt-1 space-y-1.5">
                                    <label class="block text-[11px] font-semibold text-content-secondary">
                                        Day of the Month
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-content-muted shrink-0">Every</span>
                                        <select
                                            v-model.number="monthlyDayOfMonth"
                                            class="w-full text-xs bg-surface text-content-primary border border-border-subtle rounded-xl px-2.5 py-1.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors cursor-pointer"
                                        >
                                            <option v-for="d in 31" :key="d" :value="d">
                                                {{ d }}{{ getOrdinalSuffix(d) }} of the month
                                            </option>
                                        </select>
                                    </div>
                                    <div class="text-[11px] text-content-secondary px-2.5 py-1 rounded-lg bg-surface/60 border border-border-subtle/60">
                                        Occurs on the <span class="font-bold text-primary">{{ monthlyDayOfMonth }}{{ getOrdinalSuffix(monthlyDayOfMonth) }}</span> of each month.
                                    </div>
                                </div>

                                <!-- Option 2: By Weekday Position -->
                                <div v-else-if="monthlySubMode === 'weekday'" class="pt-1 space-y-2">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-[10px] font-bold tracking-wider text-content-muted uppercase mb-1">
                                                Week Position
                                            </label>
                                            <select
                                                v-model.number="monthlyWeekPosition"
                                                class="w-full text-xs bg-surface text-content-primary border border-border-subtle rounded-xl px-2.5 py-1.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors cursor-pointer"
                                            >
                                                <option :value="1">1st</option>
                                                <option :value="2">2nd</option>
                                                <option :value="3">3rd</option>
                                                <option :value="4">4th</option>
                                                <option :value="-1">Last</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold tracking-wider text-content-muted uppercase mb-1">
                                                Day of Week
                                            </label>
                                            <select
                                                v-model.number="monthlyWeekday"
                                                class="w-full text-xs bg-surface text-content-primary border border-border-subtle rounded-xl px-2.5 py-1.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors cursor-pointer"
                                            >
                                                <option v-for="d in weekdayOptions" :key="d.value" :value="d.value">
                                                    {{ d.label }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="text-[11px] text-content-secondary px-2.5 py-1 rounded-lg bg-surface/60 border border-border-subtle/60">
                                        Occurs on the <span class="font-bold text-primary">{{ formatMonthlyWeekdayPhrase(monthlyWeekPosition, monthlyWeekday) }}</span> of each month.
                                    </div>
                                </div>
                            </div>

                            <!-- One-Time Single Day Picker (NO END DATE) -->
                            <div v-if="scheduleMode === 'one_time'" class="p-3.5 rounded-2xl bg-surface-subdued/50 border border-border-subtle/80 space-y-2">
                                <span class="text-[10px] font-bold tracking-wider text-content-muted uppercase block">
                                    Scheduled Date
                                </span>
                                <div>
                                    <label class="block text-[11px] font-semibold text-content-secondary mb-1">
                                        Choose Date (One-Time)
                                    </label>
                                    <input
                                        v-model="scheduleStartDate"
                                        type="date"
                                        class="w-full text-xs bg-surface text-content-primary border border-border-subtle rounded-xl px-2.5 py-1.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors"
                                        required
                                    />
                                </div>
                                <div class="text-[11px] text-content-muted flex items-center gap-1.5 pt-0.5">
                                    <Icon name="calendar" :size="12" class="text-primary opacity-80" />
                                    <span>Planned for {{ formatDateFriendly(scheduleStartDate) }}</span>
                                </div>
                            </div>

                            <!-- Recurring Date Range Card (Start Date + Ongoing/End Date Toggle) -->
                            <div v-else class="p-3.5 rounded-2xl bg-surface-subdued/50 border border-border-subtle/80 space-y-2.5">
                                <span class="text-[10px] font-bold tracking-wider text-content-muted uppercase block">
                                    Date Range
                                </span>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 items-start">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-content-secondary mb-1">
                                            Start Date
                                        </label>
                                        <input
                                            v-model="scheduleStartDate"
                                            type="date"
                                            class="w-full text-xs bg-surface text-content-primary border border-border-subtle rounded-xl px-2.5 py-1.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors"
                                            required
                                        />
                                    </div>

                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label class="text-[11px] font-semibold text-content-secondary">
                                                End Date
                                            </label>
                                            <!-- Apple-style Toggle Switch -->
                                            <button
                                                type="button"
                                                role="switch"
                                                :aria-checked="scheduleHasEndDate"
                                                @click="scheduleHasEndDate = !scheduleHasEndDate"
                                                class="relative inline-flex h-4 w-7 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out focus:outline-none"
                                                :class="scheduleHasEndDate ? 'bg-primary' : 'bg-surface border border-border-subtle'"
                                                title="Toggle end date"
                                            >
                                                <span
                                                    aria-hidden="true"
                                                    class="pointer-events-none inline-block h-3 w-3 transform rounded-full bg-white shadow-xs transition duration-200 ease-in-out mt-0.5 ml-0.5"
                                                    :class="scheduleHasEndDate ? 'translate-x-3 bg-white' : 'translate-x-0 bg-content-muted'"
                                                />
                                            </button>
                                        </div>
                                        <div v-if="scheduleHasEndDate">
                                            <input
                                                v-model="scheduleEndDate"
                                                type="date"
                                                :min="scheduleStartDate"
                                                class="w-full text-xs bg-surface text-content-primary border border-border-subtle rounded-xl px-2.5 py-1.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors"
                                            />
                                        </div>
                                        <div
                                            v-else
                                            class="text-xs text-content-muted px-2.5 py-1.5 bg-surface/60 border border-border-subtle/70 rounded-xl flex items-center justify-between"
                                        >
                                            <span class="font-medium text-content-secondary text-[11px]">Ongoing</span>
                                            <span class="text-[10px] opacity-70">No end date</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- APPLE-STYLE LIVE SCHEDULE SUMMARY CARD -->
                            <div class="rounded-2xl bg-surface-subdued/70 border border-border-subtle/80 p-3.5 space-y-3 shadow-xs">
                                <!-- Header with Mode & Total Hours Badge -->
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-primary animate-pulse" />
                                        <span class="text-[10px] font-bold tracking-widest text-content-muted uppercase">
                                            Live Schedule Summary
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-primary/10 border border-primary/20 text-primary">
                                        <Icon name="clock" :size="11" />
                                        <span class="text-xs font-bold tracking-tight">
                                            {{ scheduleMode === 'monthly' ? (scheduleSlots[0]?.duration_minutes / 60).toFixed(1) + ' hrs/mo' : scheduleMode === 'one_time' ? (scheduleSlots[0]?.duration_minutes / 60).toFixed(1) + ' hrs' : totalPlannedWeeklyHours + ' hrs/wk' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Apple 3-Metric Summary Bar -->
                                <div v-if="scheduleMode === 'one_time'" class="grid grid-cols-3 gap-1.5 text-center">
                                    <div class="p-2 rounded-xl bg-surface border border-border-subtle/60">
                                        <span class="text-[9px] font-semibold text-content-muted uppercase tracking-wider block">Duration</span>
                                        <span class="text-xs font-bold text-content-primary font-mono">{{ (scheduleSlots[0]?.duration_minutes / 60).toFixed(1) }}h</span>
                                    </div>
                                    <div class="p-2 rounded-xl bg-surface border border-border-subtle/60">
                                        <span class="text-[9px] font-semibold text-content-muted uppercase tracking-wider block">Date</span>
                                        <span class="text-xs font-bold text-content-primary truncate block">{{ formatDateFriendly(scheduleStartDate) }}</span>
                                    </div>
                                    <div class="p-2 rounded-xl bg-surface border border-border-subtle/60">
                                        <span class="text-[9px] font-semibold text-content-muted uppercase tracking-wider block">Type</span>
                                        <span class="text-xs font-bold text-content-primary">One-Time</span>
                                    </div>
                                </div>

                                <div v-else-if="scheduleMode === 'monthly'" class="grid grid-cols-3 gap-1.5 text-center">
                                    <div class="p-2 rounded-xl bg-surface border border-border-subtle/60">
                                        <span class="text-[9px] font-semibold text-content-muted uppercase tracking-wider block">Planned</span>
                                        <span class="text-xs font-bold text-content-primary font-mono">{{ (scheduleSlots[0]?.duration_minutes / 60).toFixed(1) }}h/mo</span>
                                    </div>
                                    <div class="p-2 rounded-xl bg-surface border border-border-subtle/60">
                                        <span class="text-[9px] font-semibold text-content-muted uppercase tracking-wider block">Frequency</span>
                                        <span class="text-xs font-bold text-content-primary">Monthly</span>
                                    </div>
                                    <div class="p-2 rounded-xl bg-surface border border-border-subtle/60">
                                        <span class="text-[9px] font-semibold text-content-muted uppercase tracking-wider block">Rule</span>
                                        <span class="text-xs font-bold text-content-primary truncate block">
                                            {{ monthlySubMode === 'date' ? monthlyDayOfMonth + getOrdinalSuffix(monthlyDayOfMonth) : formatMonthlyWeekdayPhrase(monthlyWeekPosition, monthlyWeekday) }}
                                        </span>
                                    </div>
                                </div>

                                <div v-else class="grid grid-cols-3 gap-1.5 text-center">
                                    <div class="p-2 rounded-xl bg-surface border border-border-subtle/60">
                                        <span class="text-[9px] font-semibold text-content-muted uppercase tracking-wider block">Planned</span>
                                        <span class="text-xs font-bold text-content-primary font-mono">{{ totalPlannedWeeklyHours }}h</span>
                                    </div>
                                    <div class="p-2 rounded-xl bg-surface border border-border-subtle/60">
                                        <span class="text-[9px] font-semibold text-content-muted uppercase tracking-wider block">Active</span>
                                        <span class="text-xs font-bold text-content-primary">{{ activeDaysCount }} {{ activeDaysCount === 1 ? 'day' : 'days' }}</span>
                                    </div>
                                    <div class="p-2 rounded-xl bg-surface border border-border-subtle/60">
                                        <span class="text-[9px] font-semibold text-content-muted uppercase tracking-wider block">Sessions</span>
                                        <span class="text-xs font-bold text-content-primary">{{ totalWeeklySessionsCount }} {{ totalWeeklySessionsCount === 1 ? 'slot' : 'slots' }}</span>
                                    </div>
                                </div>

                                <!-- 7-Day Apple Activity Day Rhythm Strip -->
                                <div class="p-2 rounded-xl bg-surface border border-border-subtle/60">
                                    <div class="flex items-center justify-between gap-0.5">
                                        <div
                                            v-for="day in weekCalendarDays"
                                            :key="day.num"
                                            class="flex-1 flex flex-col items-center gap-1 py-1 rounded-lg transition-colors"
                                            :class="hoursPerWeekday[day.num] > 0 ? 'bg-primary/5' : ''"
                                        >
                                            <span class="text-[10px] font-bold text-content-muted">
                                                {{ day.label }}
                                            </span>
                                            <div
                                                :class="[
                                                    'w-5.5 h-5.5 rounded-full flex items-center justify-center text-[10px] font-bold transition-all',
                                                    hoursPerWeekday[day.num] > 0
                                                        ? 'bg-primary text-primary-text shadow-xs scale-105'
                                                        : 'bg-surface-subdued text-content-muted/30 border border-border-subtle/40',
                                                ]"
                                            >
                                                <span v-if="hoursPerWeekday[day.num] > 0">✓</span>
                                                <span v-else class="w-1 h-1 rounded-full bg-content-muted/30"></span>
                                            </div>
                                            <span
                                                class="text-[9px] font-mono transition-colors"
                                                :class="hoursPerWeekday[day.num] > 0 ? 'text-primary font-bold' : 'text-content-muted/40'"
                                            >
                                                {{ hoursPerWeekday[day.num] > 0 ? `${hoursPerWeekday[day.num]}h` : '—' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Apple-style Structured Session Breakdown -->
                                <div class="space-y-1.5 max-h-32 overflow-y-auto pr-0.5">
                                    <div
                                        v-for="(item, i) in formattedRoutineSlots"
                                        :key="i"
                                        class="flex items-center justify-between px-3 py-1.5 rounded-xl bg-surface border border-border-subtle/60 text-xs transition-colors hover:border-primary/30"
                                    >
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="px-2 py-0.5 rounded-md bg-primary/10 text-primary font-bold text-[11px] shrink-0">
                                                {{ item.daysLabel }}
                                            </span>
                                            <span class="font-medium text-content-primary truncate">
                                                {{ item.start12 }} – {{ item.end12 }}
                                            </span>
                                        </div>
                                        <span class="text-[11px] font-semibold text-content-secondary shrink-0 font-mono bg-surface-subdued px-1.5 py-0.5 rounded-md border border-border-subtle/50">
                                            {{ item.durationHours }}h
                                        </span>
                                    </div>
                                    <div
                                        v-if="formattedRoutineSlots.length === 0"
                                        class="py-2.5 text-center text-xs text-content-muted bg-surface rounded-xl border border-dashed border-border-subtle"
                                    >
                                        No routine slots configured yet.
                                    </div>
                                </div>

                                <!-- Timeline Footer Ribbon -->
                                <div class="pt-2 border-t border-border-subtle/50 flex items-center justify-between text-[11px] text-content-muted">
                                    <span class="flex items-center gap-1.5">
                                        <Icon name="calendar" :size="12" class="text-primary opacity-80" />
                                        <span v-if="scheduleMode === 'one_time'" class="text-content-secondary font-medium">Session on {{ formatDateFriendly(scheduleStartDate) }}</span>
                                        <span v-else class="text-content-secondary font-medium">Starts {{ formatDateFriendly(scheduleStartDate) }}</span>
                                    </span>
                                    <span
                                        v-if="scheduleMode === 'one_time'"
                                        class="font-medium px-2 py-0.5 rounded-full text-[10px] bg-primary/10 text-primary"
                                    >
                                        One-Time Session
                                    </span>
                                    <span
                                        v-else
                                        class="font-medium px-2 py-0.5 rounded-full text-[10px]"
                                        :class="scheduleHasEndDate ? 'bg-surface border border-border-subtle text-content-primary' : 'bg-status-success/10 text-status-success'"
                                    >
                                        {{ scheduleHasEndDate && scheduleEndDate ? 'Ends ' + formatDateFriendly(scheduleEndDate) : 'Ongoing Routine' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: Time Slots Editor -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold tracking-wider text-content-muted uppercase">
                                    {{ scheduleMode === 'weekly' ? `Time Slots (${scheduleSlots.length})` : 'Session Time' }}
                                </span>

                                <button
                                    v-if="scheduleMode === 'weekly'"
                                    type="button"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-primary/10 hover:bg-primary/20 text-primary text-xs font-semibold transition-colors cursor-pointer"
                                    @click="addScheduleSlot"
                                >
                                    <Icon name="plus" :size="12" />
                                    <span>Add Slot</span>
                                </button>
                            </div>

                            <!-- Scrollable Slots Container -->
                            <div class="max-h-[48vh] overflow-y-auto space-y-3 pr-1">
                                <div
                                    v-for="(slot, idx) in scheduleSlots"
                                    :key="idx"
                                    class="p-3.5 rounded-2xl bg-surface-subdued/70 border border-border-subtle/80 space-y-3 shadow-xs transition-all"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[11px] font-bold text-content-primary px-2.5 py-0.5 rounded-md bg-surface border border-border-subtle/80">
                                                {{ scheduleMode === 'weekly' ? `Slot ${idx + 1}` : 'Session' }}
                                            </span>
                                            <span class="text-[11px] text-content-muted font-mono bg-surface/50 px-2 py-0.5 rounded-md border border-border-subtle/40">
                                                {{ (slot.duration_minutes / 60).toFixed(1) }} hrs planned
                                            </span>
                                        </div>

                                        <button
                                            v-if="scheduleMode === 'weekly' && scheduleSlots.length > 1"
                                            type="button"
                                            class="text-[11px] text-content-muted hover:text-status-danger p-1 rounded-lg hover:bg-status-danger/10 transition-colors cursor-pointer flex items-center gap-1"
                                            title="Remove this slot"
                                            @click="removeScheduleSlot(idx)"
                                        >
                                            <Icon name="trash" :size="12" />
                                            <span>Remove</span>
                                        </button>
                                    </div>

                                    <!-- Weekday Selection (Only if Weekly) -->
                                    <div v-if="scheduleMode === 'weekly'" class="space-y-1">
                                        <label class="block text-[10px] font-bold tracking-wider text-content-muted uppercase">
                                            Repeat Days
                                        </label>
                                        <div class="grid grid-cols-7 gap-1">
                                            <button
                                                v-for="day in weekdayOptions"
                                                :key="day.value"
                                                type="button"
                                                :class="[
                                                    'py-1.5 px-0.5 text-xs rounded-xl font-medium transition-all cursor-pointer border text-center flex flex-col items-center justify-center',
                                                    slot.weekdays.includes(day.value)
                                                        ? 'bg-primary text-primary-text border-primary font-bold shadow-xs scale-102'
                                                        : 'bg-surface text-content-secondary border-border-subtle hover:bg-surface-hover',
                                                ]"
                                                @click="toggleSlotWeekday(slot, day.value)"
                                            >
                                                <span class="text-[11px]">{{ day.short }}</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Time Inputs -->
                                    <div class="grid grid-cols-2 gap-2.5">
                                        <div>
                                            <label class="block text-[10px] font-bold tracking-wider text-content-muted uppercase mb-1">
                                                Start Time
                                            </label>
                                            <input
                                                v-model="slot.start_time"
                                                type="time"
                                                class="w-full text-xs bg-surface text-content-primary border border-border-subtle rounded-xl px-2.5 py-1.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors"
                                                @change="onSlotStartTimeChange(slot)"
                                            />
                                            <span class="text-[10px] text-content-muted mt-0.5 block font-mono">
                                                {{ formatTime12Hour(slot.start_time) }}
                                            </span>
                                        </div>

                                        <div>
                                            <label class="block text-[10px] font-bold tracking-wider text-content-muted uppercase mb-1">
                                                End Time
                                            </label>
                                            <input
                                                v-model="slot.end_time"
                                                type="time"
                                                class="w-full text-xs bg-surface text-content-primary border border-border-subtle rounded-xl px-2.5 py-1.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors"
                                                @change="onSlotEndTimeChange(slot)"
                                            />
                                            <span class="text-[10px] text-content-muted mt-0.5 block font-mono">
                                                {{ formatTime12Hour(slot.end_time) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Duration Quick Chips -->
                                    <div>
                                        <span class="text-[10px] font-bold tracking-wider text-content-muted uppercase block mb-1">Duration</span>
                                        <div class="flex flex-wrap gap-1.5">
                                            <button
                                                v-for="mins in [30, 60, 90, 120, 180]"
                                                :key="mins"
                                                type="button"
                                                :class="[
                                                    'px-2.5 py-0.5 text-[11px] rounded-full font-medium transition-all cursor-pointer border',
                                                    slot.duration_minutes === mins
                                                        ? 'bg-primary text-primary-text border-primary font-bold shadow-xs'
                                                        : 'bg-surface text-content-secondary border-border-subtle hover:bg-surface-hover',
                                                ]"
                                                @click="setSlotDuration(slot, mins)"
                                            >
                                                {{ (mins / 60) }} {{ mins === 60 ? 'hr' : 'hrs' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Add Another Slot Button (Apple subtle dashed button) -->
                                <button
                                    v-if="scheduleMode === 'weekly'"
                                    type="button"
                                    class="w-full py-2.5 px-3 rounded-xl border border-dashed border-border-subtle hover:border-primary/50 bg-surface/40 hover:bg-primary/5 text-xs font-semibold text-content-secondary hover:text-primary transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                    @click="addScheduleSlot"
                                >
                                    <Icon name="plus" :size="13" />
                                    <span>Add Another Time Slot</span>
                                </button>
                            </div>

                            <!-- Bottom Action Row -->
                            <div class="pt-2 flex items-center justify-between gap-2 border-t border-border-subtle/50">
                                <Button
                                    v-if="selectedTaskForEdit?.schedules && selectedTaskForEdit.schedules.length > 0"
                                    variant="ghost"
                                    size="sm"
                                    class="text-status-danger hover:bg-status-danger/10 text-xs py-1"
                                    :loading="scheduleSubmitting"
                                    @click="clearSchedule"
                                >
                                    Clear Schedule
                                </Button>
                                <div v-else />

                                <Button
                                    variant="primary"
                                    :loading="scheduleSubmitting"
                                    @click="submitScheduleRule"
                                >
                                    Save & Apply Schedule
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- ========================================== -->
        <!-- DIALOG: DELETE / ARCHIVE TASK CONFIRMATION -->
        <!-- ========================================== -->
        <Dialog
            :open="deleteModalOpen"
            title="Delete or Archive Task"
            :description="
                taskToDelete?.has_history
                    ? 'This task has recorded activity history and cannot be deleted permanently. You can safely archive it instead.'
                    : 'Are you sure you want to delete this task? We recommend archiving tasks instead to maintain your schedule timeline.'
            "
            @close="deleteModalOpen = false"
        >
            <div class="p-3 rounded-xl bg-surface-subdued border border-border-subtle text-xs space-y-1">
                <p class="font-semibold text-content-primary">
                    {{ taskToDelete?.title }}
                </p>
                <p v-if="taskToDelete?.description" class="text-content-secondary line-clamp-1">
                    {{ taskToDelete.description }}
                </p>
            </div>

            <div v-if="deleteForm.errors.delete" class="p-3 rounded-lg bg-status-danger-subdued text-status-danger text-xs font-medium border border-status-danger/30">
                {{ deleteForm.errors.delete }}
            </div>

            <div class="pt-2 flex justify-end gap-2">
                <!-- Recommended Default Action: Archive -->
                <Button
                    v-if="!taskToDelete?.is_archived"
                    variant="primary"
                    icon="archive"
                    :disabled="deleteForm.processing"
                    @click="archiveInsteadOfDelete"
                >
                    Archive Instead (Recommended)
                </Button>

                <!-- Permanent Delete (only available when no history) -->
                <Button
                    v-if="!taskToDelete?.has_history"
                    variant="danger"
                    :loading="deleteForm.processing"
                    :disabled="deleteForm.processing"
                    @click="confirmDelete"
                >
                    Delete Permanently
                </Button>
            </div>
        </Dialog>
    </AppLayout>
</template>
