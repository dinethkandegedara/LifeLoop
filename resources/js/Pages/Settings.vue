<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Components/layout/AppLayout.vue';
import PageHeader from '@/Components/layout/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Icon from '@/Components/ui/Icon.vue';
import { useToast } from '@/composables/useToast';
import { useTheme, type ThemePreference } from '@/composables/useTheme';

interface UserData {
    id: number;
    name: string;
    email: string;
    timezone: string;
}

const props = defineProps<{
    user: UserData;
    timezones: string[];
    emailCooldown?: number;
    passwordCooldown?: number;
}>();

const toast = useToast();
const { themePreference, setTheme } = useTheme();

// 1. Profile Form
const formProfile = useForm({
    name: props.user.name,
    timezone: props.user.timezone || 'UTC',
});

function submitProfile() {
    formProfile.put('/settings', {
        onSuccess: () => {
            toast.success('Profile updated', 'Your timezone and name preferences have been saved.');
        },
    });
}

// 2. Email Change Flow
const isEmailSectionOpen = ref(false);
const emailCooldownRemaining = ref(props.emailCooldown || 0);
const emailOtpSending = ref(false);
const formEmail = useForm({
    new_email: '',
    email_otp: '',
});

let emailTimer: ReturnType<typeof setInterval> | null = null;

function startEmailCooldown(seconds: number) {
    emailCooldownRemaining.value = seconds;
    if (emailTimer) clearInterval(emailTimer);
    emailTimer = setInterval(() => {
        if (emailCooldownRemaining.value > 0) {
            emailCooldownRemaining.value--;
        } else {
            if (emailTimer) clearInterval(emailTimer);
        }
    }, 1000);
}

function requestEmailOtp() {
    emailOtpSending.value = true;
    router.post('/settings/email/send-otp', {}, {
        preserveScroll: true,
        onSuccess: () => {
            emailOtpSending.value = false;
            startEmailCooldown(60);
            toast.success('Code Sent', 'A 6-digit verification code was sent to ' + props.user.email);
        },
        onError: () => {
            emailOtpSending.value = false;
        },
    });
}

function submitEmailChange() {
    formEmail.put('/settings/email', {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Email updated', 'Your account email was successfully updated.');
            formEmail.reset();
            isEmailSectionOpen.value = false;
        },
    });
}

// 3. Password Change Flow
const isPasswordSectionOpen = ref(false);
const passwordCooldownRemaining = ref(props.passwordCooldown || 0);
const passwordOtpSending = ref(false);
const formPassword = useForm({
    password_otp: '',
    password: '',
    password_confirmation: '',
});

let passwordTimer: ReturnType<typeof setInterval> | null = null;

function startPasswordCooldown(seconds: number) {
    passwordCooldownRemaining.value = seconds;
    if (passwordTimer) clearInterval(passwordTimer);
    passwordTimer = setInterval(() => {
        if (passwordCooldownRemaining.value > 0) {
            passwordCooldownRemaining.value--;
        } else {
            if (passwordTimer) clearInterval(passwordTimer);
        }
    }, 1000);
}

function requestPasswordOtp() {
    passwordOtpSending.value = true;
    router.post('/settings/password/send-otp', {}, {
        preserveScroll: true,
        onSuccess: () => {
            passwordOtpSending.value = false;
            startPasswordCooldown(60);
            toast.success('Code Sent', 'A 6-digit security code was sent to ' + props.user.email);
        },
        onError: () => {
            passwordOtpSending.value = false;
        },
    });
}

function submitPasswordChange() {
    formPassword.put('/settings/password', {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Password updated', 'Your password has been securely updated.');
            formPassword.reset();
            isPasswordSectionOpen.value = false;
        },
    });
}

onMounted(() => {
    if (emailCooldownRemaining.value > 0) {
        startEmailCooldown(emailCooldownRemaining.value);
    }
    if (passwordCooldownRemaining.value > 0) {
        startPasswordCooldown(passwordCooldownRemaining.value);
    }
});

onUnmounted(() => {
    if (emailTimer) clearInterval(emailTimer);
    if (passwordTimer) clearInterval(passwordTimer);
});
</script>

<template>
    <Head title="Settings - LifeLoop" />

    <AppLayout current-tab="settings">
        <PageHeader
            title="Account & Preferences"
            subtitle="Manage your profile information, security, and appearance settings"
        />

        <div class="max-w-2xl space-y-6">
            <!-- Appearance & Theme Card -->
            <Card>
                <template #header>
                    <div class="flex items-center gap-2">
                        <Icon name="sparkles" :size="16" class="text-primary" />
                        <h2 class="text-base font-semibold text-content-primary">
                            Appearance & Theme
                        </h2>
                    </div>
                    <p class="text-xs text-content-secondary mt-0.5">
                        Customize how LifeLoop looks across all screens and workspaces.
                    </p>
                </template>

                <div class="grid grid-cols-3 gap-3 mt-2">
                    <button
                        type="button"
                        @click="setTheme('light')"
                        :class="[
                            'flex flex-col items-center justify-center p-3.5 rounded-xl border text-center transition-all cursor-pointer',
                            themePreference === 'light'
                                ? 'bg-primary/10 border-primary text-primary font-semibold ring-2 ring-primary/20 shadow-xs'
                                : 'bg-surface-subdued border-border-subtle text-content-secondary hover:border-border-strong hover:text-content-primary'
                        ]"
                    >
                        <Icon name="sun" :size="20" class="mb-2" />
                        <span class="text-xs">Light</span>
                        <span class="text-[10px] text-content-muted mt-0.5">Clean & bright</span>
                    </button>

                    <button
                        type="button"
                        @click="setTheme('dark')"
                        :class="[
                            'flex flex-col items-center justify-center p-3.5 rounded-xl border text-center transition-all cursor-pointer',
                            themePreference === 'dark'
                                ? 'bg-primary/10 border-primary text-primary font-semibold ring-2 ring-primary/20 shadow-xs'
                                : 'bg-surface-subdued border-border-subtle text-content-secondary hover:border-border-strong hover:text-content-primary'
                        ]"
                    >
                        <Icon name="moon" :size="20" class="mb-2" />
                        <span class="text-xs">Dark</span>
                        <span class="text-[10px] text-content-muted mt-0.5">Low-light focus</span>
                    </button>

                    <button
                        type="button"
                        @click="setTheme('system')"
                        :class="[
                            'flex flex-col items-center justify-center p-3.5 rounded-xl border text-center transition-all cursor-pointer',
                            themePreference === 'system'
                                ? 'bg-primary/10 border-primary text-primary font-semibold ring-2 ring-primary/20 shadow-xs'
                                : 'bg-surface-subdued border-border-subtle text-content-secondary hover:border-border-strong hover:text-content-primary'
                        ]"
                    >
                        <Icon name="monitor" :size="20" class="mb-2" />
                        <span class="text-xs">System</span>
                        <span class="text-[10px] text-content-muted mt-0.5">Match device OS</span>
                    </button>
                </div>
            </Card>

            <!-- Profile & Regional Settings Card -->
            <Card>
                <template #header>
                    <div class="flex items-center gap-2">
                        <Icon name="user" :size="16" class="text-primary" />
                        <h2 class="text-base font-semibold text-content-primary">
                            Profile & Regional Settings
                        </h2>
                    </div>
                    <p class="text-xs text-content-secondary mt-0.5">
                        Your timezone determines how scheduled blocks, daily reset times, and calendars calculate.
                    </p>
                </template>

                <form @submit.prevent="submitProfile" class="space-y-4 mt-2">
                    <Input
                        v-model="formProfile.name"
                        label="Full Name"
                        placeholder="Alex Morgan"
                        required
                        :error="formProfile.errors.name"
                        icon="user"
                    />

                    <Select
                        v-model="formProfile.timezone"
                        label="Timezone"
                        :options="timezones.map((tz) => ({ label: tz, value: tz }))"
                        :error="formProfile.errors.timezone"
                        required
                    />

                    <div class="pt-2 flex justify-end">
                        <Button
                            type="submit"
                            variant="primary"
                            :loading="formProfile.processing"
                            :disabled="formProfile.processing"
                        >
                            Save Preferences
                        </Button>
                    </div>
                </form>
            </Card>

            <!-- Account Email (With OTP Change Flow) -->
            <Card>
                <template #header>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Icon name="mail" :size="16" class="text-primary" />
                            <h2 class="text-base font-semibold text-content-primary">
                                Account Email Address
                            </h2>
                        </div>
                        <Badge variant="success" size="sm">Verified</Badge>
                    </div>
                    <p class="text-xs text-content-secondary mt-0.5">
                        Current login and notification address. Changing your email requires OTP verification to your existing address.
                    </p>
                </template>

                <div class="space-y-4 mt-2">
                    <!-- Current Email Display -->
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-subdued border border-border-subtle">
                        <div>
                            <span class="text-[11px] font-medium text-content-muted uppercase tracking-wider block">
                                Registered Email
                            </span>
                            <span class="text-sm font-semibold text-content-primary">
                                {{ user.email }}
                            </span>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            :variant="isEmailSectionOpen ? 'secondary' : 'outline'"
                            @click="isEmailSectionOpen = !isEmailSectionOpen"
                        >
                            {{ isEmailSectionOpen ? 'Cancel' : 'Change Email' }}
                        </Button>
                    </div>

                    <!-- Expandable Change Email Form -->
                    <div
                        v-if="isEmailSectionOpen"
                        class="p-4 rounded-xl bg-surface-subdued/50 border border-primary/20 space-y-4 animate-in fade-in duration-200"
                    >
                        <div class="flex items-center justify-between border-b border-border-subtle pb-3">
                            <div>
                                <h3 class="text-xs font-semibold text-content-primary">
                                    Change Email Address
                                </h3>
                                <p class="text-[11px] text-content-muted mt-0.5">
                                    A 6-digit code will be dispatched to <strong>{{ user.email }}</strong>.
                                </p>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                :loading="emailOtpSending"
                                :disabled="emailCooldownRemaining > 0 || emailOtpSending"
                                @click="requestEmailOtp"
                            >
                                <span v-if="emailCooldownRemaining > 0">
                                    Resend in {{ emailCooldownRemaining }}s
                                </span>
                                <span v-else>
                                    Send Verification OTP
                                </span>
                            </Button>
                        </div>

                        <form @submit.prevent="submitEmailChange" class="space-y-3.5">
                            <Input
                                v-model="formEmail.new_email"
                                type="email"
                                label="New Email Address"
                                placeholder="new.email@example.com"
                                required
                                :error="formEmail.errors.new_email"
                                icon="mail"
                            />

                            <div>
                                <label class="block text-xs font-medium text-content-secondary mb-1.5">
                                    6-Digit Verification Code (Sent to current email) <span class="text-status-danger">*</span>
                                </label>
                                <input
                                    v-model="formEmail.email_otp"
                                    type="text"
                                    maxlength="6"
                                    placeholder="• • • • • •"
                                    class="w-full h-10 rounded-lg text-sm bg-surface text-content-primary border border-border-subtle px-3 tracking-widest font-mono text-center focus:border-primary focus:ring-1 focus:ring-primary outline-none"
                                    required
                                />
                                <p v-if="formEmail.errors.email_otp" class="text-xs text-status-danger mt-1">
                                    {{ formEmail.errors.email_otp }}
                                </p>
                            </div>

                            <div class="pt-2 flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="isEmailSectionOpen = false"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="primary"
                                    size="sm"
                                    :loading="formEmail.processing"
                                    :disabled="formEmail.processing || !formEmail.new_email || formEmail.email_otp.length !== 6"
                                >
                                    Verify Code & Update Email
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </Card>

            <!-- Password & Security (With OTP Verification) -->
            <Card>
                <template #header>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Icon name="lock" :size="16" class="text-primary" />
                            <h2 class="text-base font-semibold text-content-primary">
                                Password & Security
                            </h2>
                        </div>
                    </div>
                    <p class="text-xs text-content-secondary mt-0.5">
                        Update your password with one-time verification sent to your registered email.
                    </p>
                </template>

                <div class="space-y-4 mt-2">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-subdued border border-border-subtle">
                        <div>
                            <span class="text-xs font-medium text-content-primary block">
                                Account Password
                            </span>
                            <span class="text-[11px] text-content-muted">
                                Protected with cryptographic Argon2/Bcrypt and email OTP verification
                            </span>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            :variant="isPasswordSectionOpen ? 'secondary' : 'outline'"
                            @click="isPasswordSectionOpen = !isPasswordSectionOpen"
                        >
                            {{ isPasswordSectionOpen ? 'Cancel' : 'Change Password' }}
                        </Button>
                    </div>

                    <!-- Expandable Change Password Form -->
                    <div
                        v-if="isPasswordSectionOpen"
                        class="p-4 rounded-xl bg-surface-subdued/50 border border-primary/20 space-y-4 animate-in fade-in duration-200"
                    >
                        <div class="flex items-center justify-between border-b border-border-subtle pb-3">
                            <div>
                                <h3 class="text-xs font-semibold text-content-primary">
                                    Update Password with Email OTP
                                </h3>
                                <p class="text-[11px] text-content-muted mt-0.5">
                                    Click send code to receive a 6-digit confirmation PIN on <strong>{{ user.email }}</strong>.
                                </p>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                :loading="passwordOtpSending"
                                :disabled="passwordCooldownRemaining > 0 || passwordOtpSending"
                                @click="requestPasswordOtp"
                            >
                                <span v-if="passwordCooldownRemaining > 0">
                                    Resend in {{ passwordCooldownRemaining }}s
                                </span>
                                <span v-else>
                                    Send Security Code
                                </span>
                            </Button>
                        </div>

                        <form @submit.prevent="submitPasswordChange" class="space-y-3.5">
                            <div>
                                <label class="block text-xs font-medium text-content-secondary mb-1.5">
                                    6-Digit Security Code (Sent to your email) <span class="text-status-danger">*</span>
                                </label>
                                <input
                                    v-model="formPassword.password_otp"
                                    type="text"
                                    maxlength="6"
                                    placeholder="• • • • • •"
                                    class="w-full h-10 rounded-lg text-sm bg-surface text-content-primary border border-border-subtle px-3 tracking-widest font-mono text-center focus:border-primary focus:ring-1 focus:ring-primary outline-none"
                                    required
                                />
                                <p v-if="formPassword.errors.password_otp" class="text-xs text-status-danger mt-1">
                                    {{ formPassword.errors.password_otp }}
                                </p>
                            </div>

                            <Input
                                v-model="formPassword.password"
                                type="password"
                                label="New Password (min. 8 characters)"
                                placeholder="••••••••••••"
                                required
                                :error="formPassword.errors.password"
                                icon="lock"
                            />

                            <Input
                                v-model="formPassword.password_confirmation"
                                type="password"
                                label="Confirm New Password"
                                placeholder="••••••••••••"
                                required
                                :error="formPassword.errors.password_confirmation"
                                icon="lock"
                            />

                            <div class="pt-2 flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="isPasswordSectionOpen = false"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="primary"
                                    size="sm"
                                    :loading="formPassword.processing"
                                    :disabled="formPassword.processing || !formPassword.password || formPassword.password_otp.length !== 6"
                                >
                                    Confirm & Update Password
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
