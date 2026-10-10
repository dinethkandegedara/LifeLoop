<script setup lang="ts">
interface Props {
    variant?: 'mark' | 'full' | 'icon-text';
    size?: 'sm' | 'md' | 'lg' | 'xl';
    showSubtitle?: boolean;
    subtitle?: string;
    class?: string;
}

withDefaults(defineProps<Props>(), {
    variant: 'icon-text',
    size: 'md',
    showSubtitle: false,
    subtitle: 'Productivity & Flow',
});
</script>

<template>
    <div :class="['inline-flex items-center gap-2.5 select-none', $props.class]">
        <!-- Full Brand Logo (Adaptive light / dark) -->
        <template v-if="variant === 'full'">
            <img
                src="/brand/logo-light.png"
                alt="LifeLoop"
                :class="[
                    'dark:hidden object-contain transition-transform group-hover:scale-[1.02]',
                    size === 'sm' && 'h-6',
                    size === 'md' && 'h-8',
                    size === 'lg' && 'h-10',
                    size === 'xl' && 'h-12',
                ]"
            />
            <img
                src="/brand/logo-dark.png"
                alt="LifeLoop"
                :class="[
                    'hidden dark:block object-contain transition-transform group-hover:scale-[1.02]',
                    size === 'sm' && 'h-6',
                    size === 'md' && 'h-8',
                    size === 'lg' && 'h-10',
                    size === 'xl' && 'h-12',
                ]"
            />
        </template>

        <!-- Mark Only -->
        <template v-else-if="variant === 'mark'">
            <img
                src="/brand/logo-mark.png"
                alt="LifeLoop"
                :class="[
                    'object-contain transition-transform group-hover:scale-105',
                    size === 'sm' && 'w-6 h-6',
                    size === 'md' && 'w-8 h-8',
                    size === 'lg' && 'w-10 h-10',
                    size === 'xl' && 'w-14 h-14',
                ]"
            />
        </template>

        <!-- Icon with Text Typography -->
        <template v-else>
            <div
                :class="[
                    'relative shrink-0 flex items-center justify-center group-hover:scale-105 transition-transform',
                    size === 'sm' && 'w-6 h-6',
                    size === 'md' && 'w-8 h-8',
                    size === 'lg' && 'w-10 h-10',
                    size === 'xl' && 'w-14 h-14',
                ]"
            >
                <img
                    src="/brand/logo-mark.png"
                    alt="LifeLoop Icon"
                    class="w-full h-full object-contain drop-shadow-xs"
                />
            </div>
            <div>
                <span
                    :class="[
                        'font-bold tracking-tight text-content-primary leading-none block',
                        size === 'sm' && 'text-sm',
                        size === 'md' && 'text-base',
                        size === 'lg' && 'text-lg',
                        size === 'xl' && 'text-2xl',
                    ]"
                >
                    LifeLoop
                </span>
                <span
                    v-if="showSubtitle"
                    :class="[
                        'text-content-muted leading-none font-medium block mt-0.5',
                        size === 'sm' && 'text-[9px]',
                        size === 'md' && 'text-[10px]',
                        size === 'lg' && 'text-xs',
                        size === 'xl' && 'text-sm',
                    ]"
                >
                    {{ subtitle }}
                </span>
            </div>
        </template>
    </div>
</template>
