<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Components/layout/AppLayout.vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import { useToast } from '@/composables/useToast';

interface UserData {
    id: number;
    name: string;
    email: string;
    timezone: string;
}

const props = defineProps<{
    user: UserData;
    timezones: string[];
}>();

const toast = useToast();

const form = useForm({
    name: props.user.name,
    timezone: props.user.timezone || 'UTC',
});

function submit() {
    form.put('/settings', {
        onSuccess: () => {
            toast.success('Settings updated', 'Your timezone and profile preferences have been saved.');
        },
    });
}
</script>

<template>
    <Head title="Settings - LifeLoop" />

    <AppLayout current-tab="settings">
        <PageHeader
            title="Account & Preferences"
            subtitle="Manage your profile information and local timezone settings"
        />

        <div class="max-w-2xl space-y-6">
            <!-- Profile Settings Card -->
            <Card>
                <template #header>
                    <h2 class="text-base font-semibold text-content-primary">
                        Profile & Regional Settings
                    </h2>
                    <p class="text-xs text-content-secondary mt-0.5">
                        Your timezone determines how scheduled blocks and daily boundaries are displayed.
                    </p>
                </template>

                <form @submit.prevent="submit" class="space-y-4 mt-2">
                    <Input
                        v-model="form.name"
                        label="Full Name"
                        placeholder="Alex Morgan"
                        required
                        :error="form.errors.name"
                        icon="user"
                    />

                    <div>
                        <label class="block text-xs font-medium text-content-secondary mb-1.5">
                            Account Email
                        </label>
                        <input
                            type="email"
                            :value="user.email"
                            disabled
                            class="w-full h-10 rounded-lg text-sm bg-surface-subdued text-content-muted border border-border-subtle px-3 cursor-not-allowed select-none opacity-80"
                        />
                        <p class="text-[11px] text-content-muted mt-1">
                            Email addresses are verified and locked to protect account integrity.
                        </p>
                    </div>

                    <Select
                        v-model="form.timezone"
                        label="Timezone"
                        :options="timezones.map((tz) => ({ label: tz, value: tz }))"
                        :error="form.errors.timezone"
                        required
                    />

                    <div class="pt-2 flex justify-end">
                        <Button
                            type="submit"
                            variant="primary"
                            :loading="form.processing"
                            :disabled="form.processing"
                        >
                            Save Preferences
                        </Button>
                    </div>
                </form>
            </Card>

            <!-- Security & Inactivity Session Lifetime Information Card -->
            <Card>
                <template #header>
                    <div class="flex items-center gap-2">
                        <Icon name="clock" :size="16" class="text-primary" />
                        <h2 class="text-base font-semibold text-content-primary">
                            Session Security & Data Isolation
                        </h2>
                    </div>
                </template>

                <div class="space-y-3 text-xs text-content-secondary leading-relaxed">
                    <div class="p-3 rounded-lg bg-surface-subdued border border-border-subtle flex items-start gap-3">
                        <span class="w-2 h-2 rounded-full bg-status-success mt-1.5 shrink-0" />
                        <div>
                            <p class="font-semibold text-content-primary">
                                24-Hour Inactivity Session Window
                            </p>
                            <p class="mt-0.5">
                                Your session stays active as long as you use the application. If you remain inactive for <strong>24 continuous hours</strong>, your session will safely expire. Closing and reopening your browser does not immediately log you out.
                            </p>
                        </div>
                    </div>

                    <div class="p-3 rounded-lg bg-surface-subdued border border-border-subtle flex items-start gap-3">
                        <span class="w-2 h-2 rounded-full bg-primary mt-1.5 shrink-0" />
                        <div>
                            <p class="font-semibold text-content-primary">
                                Strict Tenant Data Isolation
                            </p>
                            <p class="mt-0.5">
                                Every schedule item, task, and entry is bound directly to your user identifier. Other accounts cannot read, mutate, or access your data.
                            </p>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
