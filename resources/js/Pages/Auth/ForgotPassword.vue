<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';

const form = useForm({
    email: '',
});

function submit() {
    form.post('/forgot-password');
}
</script>

<template>
    <Head title="Forgot Password - LifeLoop" />

    <div class="min-h-screen bg-app flex flex-col justify-between p-6 sm:p-10 select-none antialiased">
        <!-- Top bar -->
        <header class="max-w-md w-full mx-auto flex items-center justify-between">
            <Link href="/" class="flex items-center gap-2.5 group">
                <div class="w-8 h-8 rounded-lg bg-primary-subdued text-primary border border-primary/25 flex items-center justify-center font-bold text-sm tracking-tight shadow-xs group-hover:scale-105 transition-transform">
                    LL
                </div>
                <span class="font-bold text-base tracking-tight text-content-primary">LifeLoop</span>
            </Link>

            <ThemeToggle variant="button" />
        </header>

        <!-- Forgot Password Card -->
        <main class="w-full max-w-md mx-auto my-auto py-8">
            <Card class="shadow-xl">
                <div class="text-center mb-6">
                    <h1 class="text-xl font-bold tracking-tight text-content-primary">
                        Reset your password
                    </h1>
                    <p class="text-xs text-content-secondary mt-1 leading-relaxed">
                        Enter your account email and we'll send a 6-digit security code to verify your identity.
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

                    <Button
                        type="submit"
                        variant="primary"
                        class="w-full mt-2"
                        :loading="form.processing"
                        :disabled="form.processing"
                    >
                        Send Reset Code
                    </Button>
                </form>

                <template #footer>
                    <div class="w-full text-center text-xs text-content-secondary">
                        Remembered your password?
                        <Link
                            href="/login"
                            class="font-semibold text-primary hover:text-primary-hover ml-1"
                        >
                            Return to sign in
                        </Link>
                    </div>
                </template>
            </Card>
        </main>

        <footer class="max-w-md w-full mx-auto text-center text-xs text-content-muted">
            <p>LifeLoop &bull; Cryptographic Email Protection</p>
        </footer>
    </div>
</template>
