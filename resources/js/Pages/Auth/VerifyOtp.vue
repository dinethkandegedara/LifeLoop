<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import ThemeToggle from '@/Components/ui/ThemeToggle.vue';
import Icon from '@/Components/ui/Icon.vue';

const props = defineProps<{
    email: string;
    cooldownSeconds: number;
}>();

const form = useForm({
    code: '',
});

const resendForm = useForm({});
const logoutForm = useForm({});

const countdown = ref(props.cooldownSeconds || 0);
let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    if (countdown.value > 0) {
        startTimer();
    }
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

function startTimer() {
    if (timer) clearInterval(timer);
    timer = setInterval(() => {
        if (countdown.value > 0) {
            countdown.value--;
        } else {
            if (timer) clearInterval(timer);
        }
    }, 1000);
}

function submit() {
    form.post('/verify-otp');
}

function resend() {
    if (countdown.value > 0 || resendForm.processing) return;

    resendForm.post('/verify-otp/resend', {
        onSuccess: () => {
            countdown.value = 60;
            startTimer();
        },
    });
}

function handleInput(event: Event) {
    const target = event.target as HTMLInputElement;
    // Keep only numbers and max 6 chars
    const cleaned = target.value.replace(/\D/g, '').slice(0, 6);
    form.code = cleaned;
    if (cleaned.length === 6) {
        submit();
    }
}

function logout() {
    logoutForm.post('/logout');
}
</script>

<template>
    <Head title="Verify Email - LifeLoop" />

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

        <!-- Verification Card -->
        <main class="w-full max-w-md mx-auto my-auto py-8">
            <Card class="shadow-xl text-center">
                <div class="w-12 h-12 rounded-2xl bg-primary-subdued text-primary border border-primary/25 flex items-center justify-center mx-auto mb-4">
                    <Icon name="clock" :size="24" />
                </div>

                <h1 class="text-xl font-bold tracking-tight text-content-primary">
                    Verify your email address
                </h1>
                <p class="text-xs text-content-secondary mt-1.5 leading-relaxed">
                    We sent a 6-digit verification code to<br />
                    <strong class="font-semibold text-content-primary">{{ email }}</strong>
                </p>

                <!-- Flash Message -->
                <div
                    v-if="$page.props.flash?.success"
                    class="mt-4 p-3 rounded-lg bg-status-success-subdued border border-status-success/30 text-status-success text-xs font-medium text-left"
                >
                    {{ $page.props.flash.success }}
                </div>

                <form @submit.prevent="submit" class="mt-6 space-y-5 text-left">
                    <div>
                        <label for="otp-code" class="block text-xs font-medium text-content-secondary mb-2 text-center">
                            Enter 6-digit OTP code
                        </label>

                        <!-- Large Focused Code Input -->
                        <div class="relative max-w-xs mx-auto">
                            <input
                                id="otp-code"
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="6"
                                :value="form.code"
                                @input="handleInput"
                                placeholder="000000"
                                autofocus
                                class="w-full h-14 rounded-xl text-center font-mono text-2xl tracking-[0.4em] font-bold bg-surface text-content-primary border border-border-strong focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/20 transition-all shadow-inner"
                            />
                        </div>

                        <p v-if="form.errors.code" class="text-xs text-status-danger text-center mt-2 font-medium">
                            {{ form.errors.code }}
                        </p>
                        <p v-else class="text-[11px] text-content-muted text-center mt-2">
                            Valid for 5 minutes. Supersedes any previous codes.
                        </p>
                    </div>

                    <Button
                        type="submit"
                        variant="primary"
                        class="w-full"
                        :loading="form.processing"
                        :disabled="form.code.length !== 6 || form.processing"
                    >
                        Verify & Continue
                    </Button>
                </form>

                <div class="mt-6 pt-5 border-t border-border-subtle flex items-center justify-between text-xs">
                    <button
                        type="button"
                        @click="resend"
                        :disabled="countdown > 0 || resendForm.processing"
                        :class="[
                            'font-semibold transition-colors cursor-pointer',
                            countdown > 0
                                ? 'text-content-muted cursor-not-allowed'
                                : 'text-primary hover:text-primary-hover',
                        ]"
                    >
                        <span v-if="countdown > 0">Resend code in {{ countdown }}s</span>
                        <span v-else>Resend Code</span>
                    </button>

                    <button
                        type="button"
                        @click="logout"
                        class="text-content-muted hover:text-content-primary transition-colors cursor-pointer"
                    >
                        Sign out
                    </button>
                </div>
            </Card>
        </main>

        <footer class="max-w-md w-full mx-auto text-center text-xs text-content-muted">
            <p>LifeLoop &bull; Cryptographic Hash Protected</p>
        </footer>
    </div>
</template>
