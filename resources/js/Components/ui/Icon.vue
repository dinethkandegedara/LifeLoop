<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        name: string;
        size?: number | string;
        class?: string;
    }>(),
    {
        size: 18,
        class: '',
    }
);

const iconSize = computed(() => {
    return typeof props.size === 'number' ? `${props.size}px` : props.size;
});
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        :width="iconSize"
        :height="iconSize"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        :class="['inline-block shrink-0 transition-colors', props.class]"
        aria-hidden="true"
    >
        <!-- Calendar -->
        <g v-if="name === 'calendar'">
            <path d="M8 2v4" />
            <path d="M16 2v4" />
            <rect width="18" height="18" x="3" y="4" rx="2" />
            <path d="M3 10h18" />
        </g>

        <!-- Clock -->
        <g v-else-if="name === 'clock'">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
        </g>

        <!-- Check Circle (Completed) -->
        <g v-else-if="name === 'check-circle'">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
        </g>

        <!-- Circle (Unchecked) -->
        <g v-else-if="name === 'circle'">
            <circle cx="12" cy="12" r="10" />
        </g>

        <!-- Check (Standalone) -->
        <g v-else-if="name === 'check'">
            <polyline points="20 6 9 17 4 12" />
        </g>

        <!-- Plus -->
        <g v-else-if="name === 'plus'">
            <path d="M5 12h14" />
            <path d="M12 5v14" />
        </g>

        <!-- Sun (Light) -->
        <g v-else-if="name === 'sun'">
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2" />
            <path d="M12 20v2" />
            <path d="m4.93 4.93 1.41 1.41" />
            <path d="m17.66 17.66 1.41 1.41" />
            <path d="M2 12h2" />
            <path d="M20 12h2" />
            <path d="m6.34 17.66-1.41 1.41" />
            <path d="m19.07 4.93-1.41 1.41" />
        </g>

        <!-- Moon (Dark) -->
        <g v-else-if="name === 'moon'">
            <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
        </g>

        <!-- Monitor (System) -->
        <g v-else-if="name === 'monitor'">
            <rect width="20" height="14" x="2" y="3" rx="2" />
            <line x1="8" x2="16" y1="21" y2="21" />
            <line x1="12" x2="12" y1="17" y2="21" />
        </g>

        <!-- Menu / Hamburger -->
        <g v-else-if="name === 'menu'">
            <line x1="4" x2="20" y1="12" y2="12" />
            <line x1="4" x2="20" y1="6" y2="6" />
            <line x1="4" x2="20" y1="18" y2="18" />
        </g>

        <!-- Close / X -->
        <g v-else-if="name === 'x'">
            <path d="M18 6 6 18" />
            <path d="m6 6 12 12" />
        </g>

        <!-- Chevron Down -->
        <g v-else-if="name === 'chevron-down'">
            <path d="m6 9 6 6 6-6" />
        </g>

        <!-- Chevron Left -->
        <g v-else-if="name === 'chevron-left'">
            <path d="m15 18-6-6 6-6" />
        </g>

        <!-- Chevron Right -->
        <g v-else-if="name === 'chevron-right'">
            <path d="m9 18 6-6-6-6" />
        </g>

        <!-- More Vertical -->
        <g v-else-if="name === 'more-vertical'">
            <circle cx="12" cy="12" r="1" />
            <circle cx="12" cy="5" r="1" />
            <circle cx="12" cy="19" r="1" />
        </g>

        <!-- Alert Circle -->
        <g v-else-if="name === 'alert-circle'">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" x2="12" y1="8" y2="12" />
            <line x1="12" x2="12.01" y1="16" y2="16" />
        </g>

        <!-- Check Square -->
        <g v-else-if="name === 'tasks'">
            <path d="m9 11 3 3L22 4" />
            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
        </g>

        <!-- Chart / Reports -->
        <g v-else-if="name === 'chart' || name === 'bar-chart'">
            <line x1="18" x2="18" y1="20" y2="10" />
            <line x1="12" x2="12" y1="20" y2="4" />
            <line x1="6" x2="6" y1="20" y2="14" />
        </g>

        <!-- Sparkles -->
        <g v-else-if="name === 'sparkles'">
            <path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z" />
        </g>

        <!-- Flame / Extra Work -->
        <g v-else-if="name === 'flame'">
            <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z" />
        </g>

        <!-- User -->
        <g v-else-if="name === 'user'">
            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
        </g>

        <!-- Search -->
        <g v-else-if="name === 'search'">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.3-4.3" />
        </g>

        <!-- Archive -->
        <g v-else-if="name === 'archive'">
            <rect width="20" height="5" x="2" y="3" rx="1" />
            <path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8" />
            <path d="M10 12h4" />
        </g>

        <!-- Edit -->
        <g v-else-if="name === 'edit'">
            <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
            <path d="m15 5 4 4" />
        </g>

        <!-- Trash / Delete -->
        <g v-else-if="name === 'trash'">
            <path d="M3 6h18" />
            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
        </g>

        <!-- Restore / Refresh -->
        <g v-else-if="name === 'restore' || name === 'refresh'">
            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
            <path d="M3 3v5h5" />
        </g>

        <!-- Spinner / Loader -->
        <g v-else-if="name === 'loader'">
            <path d="M21 12a9 9 0 1 1-6.219-8.56" />
        </g>

        <!-- Lock -->
        <g v-else-if="name === 'lock'">
            <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </g>

        <!-- Mail -->
        <g v-else-if="name === 'mail'">
            <rect width="20" height="16" x="2" y="4" rx="2" />
            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
        </g>

        <!-- Shield -->
        <g v-else-if="name === 'shield'">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
        </g>

        <!-- Copy -->
        <g v-else-if="name === 'copy'">
            <rect width="14" height="14" x="8" y="8" rx="2" ry="2" />
            <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" />
        </g>

        <!-- External Link -->
        <g v-else-if="name === 'external-link'">
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
            <polyline points="15 3 21 3 21 9" />
            <line x1="10" x2="21" y1="14" y2="3" />
        </g>

        <!-- Settings (Gear) -->
        <g v-else-if="name === 'settings'">
            <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" />
            <circle cx="12" cy="12" r="3" />
        </g>

        <!-- Fallback dot -->
        <circle v-else cx="12" cy="12" r="6" />
    </svg>
</template>
