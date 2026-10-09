<script setup lang="ts">
import { onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Button from '@/Components/ui/Button.vue';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';

const props = defineProps<{
    timezones: string[];
}>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    timezone: 'UTC',
});

onMounted(() => {
    try {
        const detected = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (detected && props.timezones.includes(detected)) {
            form.timezone = detected;
        }
    } catch {}
});

function submit() {
    form.post('/register', {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
}
</script>

<template>
    <Head title="Create Account - LifeLoop" />

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

        <!-- Register Card -->
        <main class="w-full max-w-md mx-auto my-auto py-8">
            <Card class="shadow-xl">
                <div class="text-center mb-6">
                    <h1 class="text-xl font-bold tracking-tight text-content-primary">
                        Create your account
                    </h1>
                    <p class="text-xs text-content-secondary mt-1">
                        Start tracking your schedule and actual time today
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <Input
                        v-model="form.name"
                        type="text"
                        label="Full Name"
                        placeholder="Alex Morgan"
                        required
                        :error="form.errors.name"
                        icon="user"
                    />

                    <Input
                        v-model="form.email"
                        type="email"
                        label="Email Address"
                        placeholder="you@example.com"
                        required
                        :error="form.errors.email"
                        icon="user"
                    />

                    <Select
                        v-model="form.timezone"
                        label="Your Timezone"
                        :options="timezones.map((tz) => ({ label: tz, value: tz }))"
                        :error="form.errors.timezone"
                    />

                    <Input
                        v-model="form.password"
                        type="password"
                        label="Password"
                        placeholder="At least 8 characters"
                        required
                        :error="form.errors.password"
                        icon="clock"
                    />

                    <Input
                        v-model="form.password_confirmation"
                        type="password"
                        label="Confirm Password"
                        placeholder="Re-enter your password"
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
                        Create Account
                    </Button>
                </form>

                <template #footer>
                    <div class="w-full text-center text-xs text-content-secondary">
                        Already have an account?
                        <Link
                            href="/login"
                            class="font-semibold text-primary hover:text-primary-hover ml-1"
                        >
                            Sign in
                        </Link>
                    </div>
                </template>
            </Card>
        </main>

        <footer class="max-w-md w-full mx-auto text-center text-xs text-content-muted">
            <p>LifeLoop &bull; Secure Email Verification Protected</p>
        </footer>
    </div>
</template>
