<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Toast from '@/Components/Toast.vue';

const page = usePage();

// Toast Logic
const showToast = ref(false);
const toastMessage = ref('');
const toastType = ref('success');
const lastShownMessage = ref(''); // Track last shown message to prevent duplicates

watch(() => page.props.flash, (flash) => {
    // Prevent showing the same message multiple times (e.g., from auto-refresh)
    const currentMessage = flash?.success || flash?.error || '';
    
    if (currentMessage && currentMessage !== lastShownMessage.value) {
        if (flash?.success) {
            toastMessage.value = flash.success;
            toastType.value = 'success';
            showToast.value = true;
            lastShownMessage.value = flash.success;
        } else if (flash?.error) {
            toastMessage.value = flash.error;
            toastType.value = 'error';
            showToast.value = true;
            lastShownMessage.value = flash.error;
        }
    }
    
    // Clear lastShownMessage when flash is empty (for next action)
    if (!currentMessage) {
        lastShownMessage.value = '';
    }
}, { deep: true, immediate: true });
</script>

<template>
    <div class="bg-gray-50 min-h-screen font-sans">
        
        <!-- Portal Login Consistent Header -->
        <div class="relative mb-8 text-white z-50">
             <!-- Background with overflow hidden -->
             <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-emerald-600 rounded-b-[2rem] md:rounded-b-[3rem] shadow-lg overflow-hidden -z-10">
                 <!-- Abstract Shapes -->
                 <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full mix-blend-overlay filter blur-3xl translate-x-1/3 -translate-y-1/2"></div>
                 <div class="absolute bottom-0 left-0 w-64 h-64 bg-white/10 rounded-full mix-blend-overlay filter blur-2xl -translate-x-1/3 translate-y-1/3"></div>
             </div>

             <div class="px-6 md:px-8 pt-8 pb-6 mx-auto max-w-7xl">
                 <div class="flex items-center justify-between">
                     <div class="flex flex-col">
                         <span class="text-blue-100 font-medium text-sm md:text-base mb-1">Welcome back,</span>
                         <h1 class="text-2xl md:text-4xl font-bold tracking-tight uppercase">{{ $page.props.auth.user.name }}</h1>
                         <div class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/20 backdrop-blur-sm border border-white/20 self-start shadow-sm">
                             <div class="w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]"></div>
                             <span class="text-xs md:text-sm font-semibold text-white tracking-wide">
                                 {{ $page.props.auth.user.roles && $page.props.auth.user.roles.length > 0 ? $page.props.auth.user.roles[0] : 'Team Member' }}
                             </span>
                         </div>
                     </div>
                     
                     <!-- Avatar Dropdown -->
                     <div class="relative">
                         <Dropdown align="right" width="48">
                             <template #trigger>
                                 <button class="relative group transition-transform active:scale-95 focus:outline-none">
                                     <div class="p-1 rounded-full border-2 border-white/30 hover:border-white transition-colors">
                                        <img :src="$page.props.auth.user.avatar_url" 
                                             class="w-14 h-14 md:w-16 md:h-16 rounded-full object-cover shadow-sm bg-white" />
                                     </div>
                                     <div class="absolute bottom-1 right-1 bg-emerald-400 w-3.5 h-3.5 md:w-4 md:h-4 rounded-full border-2 border-slate-800"></div>
                                 </button>
                             </template>

                             <template #content>
                                 <!-- User Info Header -->
                                 <div class="px-4 py-3 border-b border-gray-100">
                                     <p class="text-sm font-semibold text-gray-900">{{ $page.props.auth.user.name }}</p>
                                     <p class="text-xs text-gray-500">{{ $page.props.auth.user.email }}</p>
                                 </div>
                                 
                                 <!-- Menu Items with Icons -->
                                 <DropdownLink :href="route('profile.edit')" class="flex items-center gap-2">
                                     <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                     </svg>
                                     Profile
                                 </DropdownLink>

                                 <DropdownLink :href="route('logout')" method="post" as="button" class="flex items-center gap-2">
                                     <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                     </svg>
                                     Log Out
                                 </DropdownLink>
                             </template>
                         </Dropdown>
                     </div>
                 </div>
             </div>
        </div>

        <!-- Custom Animations -->
        <style scoped>
        @keyframes blob {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(20px, -20px) scale(1.1); }
            50% { transform: translate(-20px, 20px) scale(0.9); }
            75% { transform: translate(10px, 10px) scale(1.05); }
        }
        .animate-blob {
            animation: blob 7s infinite;
        }
        .animation-delay-2000 {
            animation-delay: 2s;
        }
        .animation-delay-4000 {
            animation-delay: 4s;
        }
        .animation-delay-1000 {
            animation-delay: 1s;
        }
        .animation-delay-3000 {
            animation-delay: 3s;
        }
        </style>
  
        <!-- Main Content Area -->
        <main class="max-w-7xl mx-auto px-6 py-8">
            <!-- Optional Page Header Slot -->
            <div v-if="$slots.header" class="mb-6">
                <slot name="header" />
            </div>
            
            <!-- Main Content Slot -->
            <slot />
        </main>

        <!-- Footer (Optional) -->
        <footer class="max-w-7xl mx-auto px-6 py-6 mt-12">
            <p class="text-center text-xs text-gray-400 font-medium">
                &copy; 2025 Planly App • Created by Agus Prasetya
            </p>
        </footer>
        
        <!-- Global Toast Notification -->
        <Toast 
          :show="showToast" 
          :message="toastMessage" 
          :type="toastType" 
          @close="showToast = false" 
        />
    </div>
</template>
