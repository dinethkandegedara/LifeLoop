<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    status?: string;
    laravelVersion: string;
    phpVersion: string;
    dbStatus: string;
}>();
</script>

<template>
    <Head title="LifeLoop - Foundations" />

    <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 text-slate-100 flex flex-col justify-between p-6 sm:p-12">
        <header class="max-w-6xl w-full mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/20 border border-indigo-500/40 flex items-center justify-center text-indigo-400 font-bold shadow-lg shadow-indigo-500/10">
                    LL
                </div>
                <div>
                    <h1 class="text-lg font-semibold tracking-tight text-white">LifeLoop</h1>
                    <p class="text-xs text-slate-400">Foundation Ready</p>
                </div>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>System Operational</span>
            </div>
        </header>

        <main class="max-w-4xl w-full mx-auto my-12">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 backdrop-blur-xl p-8 sm:p-10 shadow-2xl shadow-black/50">
                <div class="inline-block px-3 py-1 rounded-md text-xs font-medium bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 mb-4">
                    Milestone 1: Application Foundation
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-white mb-4">
                    LifeLoop Architecture Verified
                </h2>
                <p class="text-slate-300 leading-relaxed mb-8 max-w-2xl">
                    Lightweight, multi-user personal scheduling and actual-work tracking platform engineered for conventional PHP shared hosting and high-fidelity reactive UX.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-4 rounded-xl bg-slate-800/40 border border-slate-800">
                        <span class="text-xs text-slate-400 font-medium">Backend</span>
                        <p class="text-base font-semibold text-white mt-1">Laravel {{ laravelVersion }}</p>
                        <span class="text-xs text-indigo-400">PHP {{ phpVersion }}</span>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-800/40 border border-slate-800">
                        <span class="text-xs text-slate-400 font-medium">Frontend</span>
                        <p class="text-base font-semibold text-white mt-1">Vue 3 + TypeScript</p>
                        <span class="text-xs text-indigo-400">Inertia.js + Vite</span>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-800/40 border border-slate-800">
                        <span class="text-xs text-slate-400 font-medium">Database</span>
                        <p class="text-base font-semibold text-white mt-1">MariaDB 11</p>
                        <span class="text-xs text-emerald-400">{{ dbStatus }}</span>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-800/40 border border-slate-800">
                        <span class="text-xs text-slate-400 font-medium">Authentication</span>
                        <p class="text-base font-semibold text-white mt-1">Sanctum Session</p>
                        <span class="text-xs text-indigo-400">First-Party Cookies</span>
                    </div>
                </div>
            </div>
        </main>

        <footer class="max-w-6xl w-full mx-auto text-center sm:text-left text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2 border-t border-slate-800/60 pt-6">
            <p>LifeLoop &bull; Same-Origin Shared Hosting Architecture</p>
            <p>Ready for Authentication & Work Tracking Domains</p>
        </footer>
    </div>
</template>
