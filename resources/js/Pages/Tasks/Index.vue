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

// Create Task Modal State & Form
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

// Edit Task Modal State & Form
const editModalOpen = ref(false);
const selectedTaskForEdit = ref<TaskItem | null>(null);
const editForm = useForm({
    title: '',
    description: '',
    status: 'active' as 'active' | 'archived',
});

function openEditModal(task: TaskItem) {
    selectedTaskForEdit.value = task;
    editForm.title = task.title;
    editForm.description = task.description || '';
    editForm.status = task.status;
    editForm.clearErrors();
    editModalOpen.value = true;
}

function submitEdit() {
    if (!selectedTaskForEdit.value) return;

    editForm.put(`/tasks/${selectedTaskForEdit.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editModalOpen.value = false;
            toast.success('Task updated successfully');
        },
        onError: () => {
            toast.danger('Please fix the errors before saving');
        },
    });
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
</script>

<template>
    <Head title="Tasks - LifeLoop" />

    <AppLayout current-tab="tasks">
        <!-- Page Header -->
        <PageHeader
            title="Task Management"
            subtitle="Organize, track, and archive your schedule tasks with full ownership"
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
            <!-- Empty state when no tasks exist at all -->
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

            <!-- Empty state when no archived tasks -->
            <EmptyState
                v-else-if="!searchInput && currentStatus === 'archived' && counts.archived === 0"
                title="No archived tasks"
                description="When you archive completed or inactive tasks, they will appear here safely preserved for your records."
                icon="archive"
            >
                <template #action>
                    <Button variant="outline" @click="currentStatus = 'active'">
                        View Active Tasks
                    </Button>
                </template>
            </EmptyState>

            <!-- Empty state for search/filter with no results -->
            <EmptyState
                v-else
                title="No matching tasks found"
                description="We couldn't find any tasks matching your current search query or filter."
                icon="search"
            >
                <template #action>
                    <Button variant="outline" icon="x" @click="resetAllFilters">
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
                    'group rounded-xl border p-4 sm:p-5 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4',
                    'bg-surface hover:border-border-strong hover:shadow-xs',
                    task.is_archived
                        ? 'border-border-subtle bg-surface-subdued/40 opacity-75'
                        : 'border-border-subtle',
                ]"
            >
                <!-- Task Details -->
                <div class="min-w-0 flex-1 space-y-1.5">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h3
                            :class="[
                                'text-base font-semibold tracking-tight transition-colors',
                                task.is_archived
                                    ? 'line-through text-content-muted'
                                    : 'text-content-primary',
                            ]"
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

                        <!-- History Protection Indicator -->
                        <Badge
                            v-if="task.has_history"
                            variant="extra"
                            size="sm"
                            title="Has historical records — protected from deletion"
                        >
                            Has History
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
                    <div class="flex items-center gap-3 text-xs text-content-muted pt-1">
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
                <div class="flex items-center gap-1.5 shrink-0 self-end sm:self-center">
                    <!-- Quick Archive / Unarchive Button -->
                    <Button
                        variant="ghost"
                        size="sm"
                        :icon="task.is_archived ? 'restore' : 'archive'"
                        @click="toggleArchive(task)"
                        :title="task.is_archived ? 'Restore to active' : 'Archive task'"
                    >
                        {{ task.is_archived ? 'Restore' : 'Archive' }}
                    </Button>

                    <!-- Edit Button -->
                    <IconButton
                        icon="edit"
                        label="Edit task"
                        size="sm"
                        @click="openEditModal(task)"
                    />

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

        <!-- CREATE TASK DIALOG -->
        <Dialog
            :open="createModalOpen"
            title="Create Task"
            description="Add a task to your schedule backlog. It will be available for tracking and scheduling."
            @close="createModalOpen = false"
        >
            <form @submit.prevent="submitCreate" class="space-y-4">
                <Input
                    v-model="createForm.title"
                    label="Task Title"
                    placeholder="e.g., Deep Focus & System Audit"
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
                    <div class="flex justify-between items-center text-[10px] text-content-muted">
                        <span v-if="createForm.errors.description" class="text-status-danger font-medium">
                            {{ createForm.errors.description }}
                        </span>
                        <span class="ml-auto">{{ createForm.description?.length || 0 }}/2000</span>
                    </div>
                </div>
            </form>

            <template #actions>
                <Button
                    variant="ghost"
                    @click="createModalOpen = false"
                    :disabled="createForm.processing"
                >
                    Cancel
                </Button>
                <Button
                    variant="primary"
                    :loading="createForm.processing"
                    :disabled="createForm.processing"
                    @click="submitCreate"
                >
                    Create Task
                </Button>
            </template>
        </Dialog>

        <!-- EDIT TASK DIALOG -->
        <Dialog
            :open="editModalOpen"
            title="Edit Task"
            description="Update the task title, description, or active status."
            @close="editModalOpen = false"
        >
            <form @submit.prevent="submitEdit" class="space-y-4">
                <Input
                    v-model="editForm.title"
                    label="Task Title"
                    required
                    :error="editForm.errors.title"
                />

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold tracking-tight text-content-primary">
                        Description (Optional)
                    </label>
                    <textarea
                        v-model="editForm.description"
                        rows="3"
                        placeholder="Add checklist, context, requirements, or links..."
                        class="w-full rounded-xl border border-border-subtle bg-surface px-3 py-2 text-sm text-content-primary placeholder:text-content-muted focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary transition-colors"
                        maxlength="2000"
                    />
                    <div class="flex justify-between items-center text-[10px] text-content-muted">
                        <span v-if="editForm.errors.description" class="text-status-danger font-medium">
                            {{ editForm.errors.description }}
                        </span>
                        <span class="ml-auto">{{ editForm.description?.length || 0 }}/2000</span>
                    </div>
                </div>

                <div class="pt-2">
                    <label class="block text-xs font-semibold tracking-tight text-content-primary mb-2">
                        Status
                    </label>
                    <SegmentedControl
                        v-model="editForm.status"
                        size="sm"
                        :options="[
                            { label: 'Active', value: 'active' },
                            { label: 'Archived', value: 'archived' },
                        ]"
                    />
                </div>
            </form>

            <template #actions>
                <Button
                    variant="ghost"
                    @click="editModalOpen = false"
                    :disabled="editForm.processing"
                >
                    Cancel
                </Button>
                <Button
                    variant="primary"
                    :loading="editForm.processing"
                    :disabled="editForm.processing"
                    @click="submitEdit"
                >
                    Save Changes
                </Button>
            </template>
        </Dialog>

        <!-- DELETE / ARCHIVE CONFIRMATION DIALOG -->
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

            <template #actions>
                <Button
                    variant="ghost"
                    @click="deleteModalOpen = false"
                    :disabled="deleteForm.processing"
                >
                    Cancel
                </Button>

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
            </template>
        </Dialog>
    </AppLayout>
</template>
