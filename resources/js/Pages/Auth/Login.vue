<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

function submit() {
    form.post('/login', {
        onFinish: () => {
            form.reset('password');
        },
    });
}
</script>

<template>
    <Head title="Sign In - LifeLoop" />

    <div class="min-h-screen bg-app flex flex-col justify-between p-6 sm:p-10 select-none antialiased">
        <!-- Top bar with brand & theme switch -->
        <header class="max-w-md w-full mx-auto flex items-center justify-between">
            <Link href="/" class="flex items-center gap-2.5 group">
                <div class="w-8 h-8 rounded-lg bg-primary-subdued text-primary border border-primary/25 flex items-center justify-center font-bold text-sm tracking-tight shadow-xs group-hover:scale-105 transition-transform">
                    LL
                </div>
                <span class="font-bold text-base tracking-tight text-content-primary">LifeLoop</span>
            </Link>

            <ThemeToggle variant="button" />
        </header>

        <!-- Main Auth Card -->
        <main class="w-full max-w-md mx-auto my-auto py-8">
            <Card class="shadow-xl">
                <div class="text-center mb-6">
                    <h1 class="text-xl font-bold tracking-tight text-content-primary">
                        Welcome back
                    </h1>
                    <p class="text-xs text-content-secondary mt-1">
                        Sign in to track your schedule and deep-work flow
                    </p>
                </div>

                <!-- Session Flash Message -->
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

                    <div class="space-y-1">
                        <Input
                            v-model="form.password"
                            type="password"
                            label="Password"
                            placeholder="••••••••"
                            required
                            :error="form.errors.password"
                            icon="clock"
                        />
                        <div class="flex justify-end pt-0.5">
                            <Link
                                href="/forgot-password"
                                class="text-xs font-medium text-primary hover:text-primary-hover transition-colors"
                            >
                                Forgot password?
                            </Link>
                        </div>
                    </div>

                    <div class="pt-1">
                        <Checkbox
                            v-model="form.remember"
                            label="Remember me"
                        />
                    </div>

                    <Button
                        type="submit"
                        variant="primary"
                        class="w-full mt-2"
                        :loading="form.processing"
                        :disabled="form.processing"
                    >
                        Sign In
                    </Button>
                </form>

                <template #footer>
                    <div class="w-full text-center text-xs text-content-secondary">
                        Don't have an account?
                        <Link
                            href="/register"
                            class="font-semibold text-primary hover:text-primary-hover ml-1"
                        >
                            Create an account
                        </Link>
                    </div>
                </template>
            </Card>
        </main>

        <!-- Footer -->
        <footer class="max-w-md w-full mx-auto text-center text-xs text-content-muted">
            <p>LifeLoop &bull; Secure Session Authentication</p>
        </footer>
    </div>
</template>
