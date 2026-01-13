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
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-4"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="show" class="fixed top-4 right-4 left-4 sm:left-auto sm:top-5 sm:right-6 z-[9999] sm:max-w-sm">
                <div class="flex overflow-hidden rounded-xl shadow-2xl ring-1 ring-black/5">
                    <!-- Side Panel - Colored -->
                    <div class="flex-shrink-0 w-14 flex items-center justify-center"
                         :class="{
                             'bg-emerald-500': type === 'success',
                             'bg-red-500': type === 'error'
                         }">
                        <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
                            <svg v-if="type === 'success'" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            <svg v-else class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </div>
                    </div>
                    
                    <!-- Content Panel - White -->
                    <div class="flex-1 bg-white p-4 flex items-start gap-3">
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
