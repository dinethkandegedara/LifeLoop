<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';
import AppLogo from '@/Components/ui/AppLogo.vue';

const props = defineProps<{
    email?: string;
}>();

const form = useForm({
    email: props.email || '',
    code: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/reset-password', {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
}
</script>

<template>
    <Head title="Reset Password - LifeLoop" />

    <div class="min-h-screen bg-app flex flex-col justify-between p-6 sm:p-10 select-none antialiased">
        <!-- Top bar -->
        <header class="max-w-md w-full mx-auto flex items-center justify-between">
            <Link href="/" class="group">
                <AppLogo variant="icon-text" size="md" />
            </Link>

            <ThemeToggle variant="button" />
        </header>

        <!-- Reset Password Card -->
        <main class="w-full max-w-md mx-auto my-auto py-8">
            <Card class="shadow-xl">
                <div class="text-center mb-6">
                    <div class="flex justify-center mb-3">
                        <AppLogo variant="mark" size="lg" />
                    </div>
                    <h1 class="text-xl font-bold tracking-tight text-content-primary">
                        Set new password
                    </h1>
                    <p class="text-xs text-content-secondary mt-1 leading-relaxed">
                        Enter the 6-digit code sent to your email and your new password.
                    </p>
                </div>

                <!-- Flash Message -->
                <div
                    v-if="$page.props.flash?.success"
                    class="mb-4 p-3 rounded-lg bg-status-success-subdued border border-status-success/30 text-status-success text-xs font-medium"
                >
                    {{ $page.props.flash.success }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <Input
                        v-model="form.email"
                        type="email"
                        label="Email Address"
                        placeholder="you@example.com"
                        required
                        :error="form.errors.email"
                        icon="user"
                    />

                    <div>
                        <Input
                            v-model="form.code"
                            type="text"
                            label="6-Digit Verification Code"
                            placeholder="000000"
                            required
                            :error="form.errors.code"
                            icon="clock"
                        />
                        <p class="text-[11px] text-content-muted mt-1">
                            Codes expire in 5 minutes and allow up to 5 attempts.
                        </p>
                    </div>

                    <Input
                        v-model="form.password"
                        type="password"
                        label="New Password"
                        placeholder="At least 8 characters"
                        required
                        :error="form.errors.password"
                        icon="clock"
                    />

                    <Input
                        v-model="form.password_confirmation"
                        type="password"
                        label="Confirm New Password"
                        placeholder="Re-enter your new password"
                        required
                        :error="form.errors.password_confirmation"
                        icon="clock"
                    />

                    <Button
                        type="submit"
                        variant="primary"
                        class="w-full mt-2"
                        :loading="form.processing"
                        :disabled="form.processing"
                    >
                        Update Password
                    </Button>
                </form>

                <template #footer>
                    <div class="w-full text-center text-xs text-content-secondary">
                        <Link
                            href="/login"
                            class="font-semibold text-primary hover:text-primary-hover"
                        >
                            Return to sign in
                        </Link>
                    </div>
                </template>
            </Card>
        </main>

        <footer class="max-w-md w-full mx-auto text-center text-xs text-content-muted">
            <p>LifeLoop &bull; Cryptographic Password Reset</p>
        </footer>
    </div>
</template>
