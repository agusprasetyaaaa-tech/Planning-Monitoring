<script setup>
import { watch, computed, onUnmounted } from 'vue';

const props = defineProps({
    message: String,
    type: {
        type: String,
        default: 'success'
    },
    show: Boolean
});

const emit = defineEmits(['close']);

let timer = null;

const startTimer = () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        emit('close');
    }, 3000);
};

// Watch for visibility changes
watch(() => props.show, (newValue) => {
    if (newValue) {
        startTimer();
    } else {
        if (timer) clearTimeout(timer);
    }
});

// Also watch for message changes to reset timer if already showing
watch(() => props.message, () => {
    if (props.show) {
        startTimer();
    }
});

onUnmounted(() => {
    if (timer) clearTimeout(timer);
});

const title = computed(() => {
    return props.type === 'success' ? 'Success' : 'Error';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-10"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="show" class="fixed top-4 left-4 right-4 sm:left-auto sm:top-5 sm:right-6 z-[9999] sm:max-w-sm flex justify-center sm:block">
                <div class="flex overflow-hidden rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.1)] ring-1 ring-black/5 backdrop-blur-md bg-white/95 border border-white">
                    <!-- Side Panel - Colored -->
                    <div class="flex-shrink-0 w-12 flex items-center justify-center relative overflow-hidden"
                         :class="{
                             'bg-emerald-500': type === 'success',
                             'bg-red-500': type === 'error'
                         }">
                        <!-- Decorative Glow -->
                        <div class="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent"></div>
                        
                        <div class="relative w-7 h-7 rounded-full bg-white/20 flex items-center justify-center">
                            <svg v-if="type === 'success'" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            <svg v-else class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </div>
                    </div>
                    
                    <!-- Content Panel - White -->
                    <div class="flex-1 p-4 flex items-start gap-4">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900">{{ title }}</p>
                            <p class="mt-0.5 text-sm text-gray-600 leading-snug">{{ message }}</p>
                        </div>
                        
                        <!-- Close Button -->
                        <button @click="$emit('close')" 
                                class="flex-shrink-0 p-1 -mr-1 -mt-1 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
