<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const isBackingUp = ref(false);

defineOptions({ layout: NexusLayout });

const startBackup = () => {
    isBackingUp.value = true;
    window.location.href = route('settings.backup.download');
    setTimeout(() => {
        isBackingUp.value = false;
    }, 2000);
};
</script>

<template>
    <Head title="Database Backup" />

    <div class="space-y-6">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 tracking-tight">System Database Backup</h2>
                <p class="text-sm text-gray-500 font-medium">Create and download a secure snapshot of your application data.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Info Section -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 lg:col-span-2">
                <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Backup Overview
                </h3>
                
                <div class="bg-gray-50/80 rounded-2xl p-5 border border-gray-100 mb-6">
                    <h4 class="font-bold text-gray-900 mb-4 flex items-center gap-2 text-sm uppercase tracking-wider text-emerald-600">
                        Components Included
                    </h4>
                    <ul class="space-y-4">
                        <li v-for="(item, index) in [
                            { title: 'Master Data', desc: 'All products, customers, and category definitions.' },
                            { title: 'Operational Records', desc: 'Planning activities, monitoring reports, and history data.' },
                            { title: 'System Security', desc: 'User accounts, role permissions, and security configurations.' }
                        ]" :key="index" 
                            class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-5 h-5 bg-emerald-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-3 h-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div>
                                <span class="block text-sm font-bold text-gray-800">{{ item.title }}</span>
                                <span class="text-xs text-gray-500 font-medium leading-relaxed">{{ item.desc }}</span>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="flex items-center gap-2 p-4 bg-amber-50 rounded-xl border border-amber-100 text-amber-700">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <p class="text-xs font-semibold uppercase tracking-tight">Security Note: Keep your backup files in a safe and private location.</p>
                </div>
            </div>

            <!-- Action Section -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between">
                <div>
                    <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                        </svg>
                        Manual Snapshot
                    </h3>
                    <p class="text-xs text-gray-500 font-medium leading-relaxed mb-6 italic">
                        Click the button below to generate a fresh SQL dump of the entire database. The process will start immediately and download the file to your device.
                    </p>
                </div>

                <button 
                    @click="startBackup"
                    :disabled="isBackingUp"
                    class="w-full flex items-center justify-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition-all shadow-sm active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed group"
                >
                    <svg v-if="!isBackingUp" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <svg v-else class="w-6 h-6 animate-spin" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ isBackingUp ? 'Preparing SQL...' : 'Generate Backup' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
