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

    <div class="h-[calc(100vh-4rem-3rem)] flex flex-col justify-center px-4 overflow-hidden">
        <div class="max-w-lg mx-auto w-full py-2">
            <div class="bg-white overflow-hidden shadow-2xl rounded-3xl sm:rounded-[2rem] border border-gray-100 p-5 sm:p-7 text-center relative">
                <div class="max-w-md mx-auto">
                    <!-- Header Section -->
                    <div class="flex flex-col items-center mb-4 sm:mb-6">
                        <div class="relative mb-3 sm:mb-4">
                            <div class="relative w-12 h-12 sm:w-16 sm:h-16 bg-emerald-50 rounded-xl sm:rounded-2xl flex items-center justify-center ring-4 ring-emerald-50/50">
                                <svg class="w-6 h-6 sm:w-8 sm:h-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-lg sm:text-xl font-extrabold text-gray-900 mb-1 tracking-tight">System Database Backup</h3>
                        <p class="text-xs sm:text-sm text-gray-500 font-medium">Create a secure snapshot of your application data.</p>
                    </div>

                    <!-- Info Section -->
                    <div class="bg-gray-50/80 rounded-2xl sm:rounded-2xl p-4 sm:p-5 border border-gray-100 mb-5 sm:mb-6 text-left">
                        <h4 class="font-bold text-gray-900 mb-2 sm:mb-3 flex items-center gap-2 text-xs sm:text-sm">
                            <span class="w-1.5 h-4 bg-emerald-500 rounded-full"></span>
                            Components Included
                        </h4>
                        <ul class="space-y-2 sm:space-y-3">
                            <li v-for="(item, index) in ['All products and master data', 'Planning and monitoring records', 'User accounts & security settings']" :key="index" 
                                class="flex items-center gap-2 text-gray-600">
                                <div class="flex-shrink-0 w-4 h-4 bg-emerald-100 rounded-full flex items-center justify-center">
                                    <svg class="w-2.5 h-2.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <span class="text-[10px] sm:text-xs font-semibold tracking-tight">{{ item }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="flex flex-col gap-3">
                        <button 
                            @click="startBackup"
                            :disabled="isBackingUp"
                            class="w-full flex items-center justify-center gap-2 px-5 py-3 sm:px-6 sm:py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm sm:text-base font-bold rounded-xl sm:rounded-xl transition-all duration-300 shadow-lg shadow-emerald-900/10 hover:shadow-emerald-900/20 hover:scale-[1.01] active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed group relative overflow-hidden"
                        >
                            <svg v-if="!isBackingUp" class="w-5 h-5 sm:w-6 h-6 transition-transform group-hover:-translate-y-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <svg v-else class="w-5 h-5 sm:w-6 h-6 animate-spin" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="relative z-10">{{ isBackingUp ? 'Preparing SQL...' : 'Generate Backup' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
