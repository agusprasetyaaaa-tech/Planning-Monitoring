<script setup>
import { ref, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: String,
    label: String,
    placeholder: String,
    required: {
        type: Boolean,
        default: false
    },
    rows: {
        type: Number,
        default: 3
    },
    lang: {
        type: String,
        default: 'id-ID' // Default to Indonesian
    }
});

const emit = defineEmits(['update:modelValue']);

const isListening = ref(false);
const recognition = ref(null);
const errorMessage = ref('');

const showError = (msg) => {
    errorMessage.value = msg;
    // Auto hide after 5 seconds
    setTimeout(() => {
        errorMessage.value = '';
    }, 5000);
};

const startRecognition = () => {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    
    if (!SpeechRecognition) {
        showError('Browser Error: Your browser does not support Speech-to-Text. Please use Google Chrome or Microsoft Edge.');
        return;
    }

    // Double check for HTTPS or localhost
    if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        showError('Security Error: Speech-to-Text requires a secure connection (HTTPS) or localhost.');
        return;
    }

    try {
        const r = new SpeechRecognition();
        r.continuous = false;
        r.interimResults = false;
        r.lang = props.lang;

        r.onstart = () => {
            console.log('Voice recognition started...');
            isListening.value = true;
        };

        r.onresult = (event) => {
            const transcript = event.results[0][0].transcript;
            console.log('Result received:', transcript);
            
            const newValue = props.modelValue 
                ? props.modelValue + ' ' + transcript 
                : transcript;
                
            emit('update:modelValue', newValue);
            isListening.value = false;
        };

        r.onerror = (event) => {
            console.error('Speech recognition error:', event.error);
            isListening.value = false;
            
            if (event.error === 'not-allowed') {
                showError('Mic Access Denied: Please enable microphone access in browser settings (click lock icon next to URL).');
            } else if (event.error === 'network') {
                showError('Network Error: This feature requires an internet connection.');
            } else if (event.error !== 'no-speech' && event.error !== 'aborted') {
                showError('System Error: ' + event.error);
            }
        };

        r.onend = () => {
            console.log('Voice recognition ended.');
            isListening.value = false;
        };

        r.start();
        recognition.value = r;

    } catch (err) {
        console.error('Failed to initialize speech recognition:', err);
        showError('System Error: Could not initialize voice recognition.');
    }
};

const toggleListening = () => {
    if (isListening.value && recognition.value) {
        recognition.value.stop();
    } else {
        startRecognition();
    }
};

onUnmounted(() => {
    if (recognition.value) {
        recognition.value.stop();
    }
});
</script>

<template>
    <div class="relative group">
        <div class="flex flex-wrap items-center justify-between gap-y-2 mb-2">
            <label class="text-xs sm:text-sm font-bold text-gray-700 mr-2 flex-grow min-w-[120px]">
                {{ label }} <span v-if="required" class="text-rose-400">*</span>
            </label>
            
            <button 
                type="button"
                @click="toggleListening"
                :class="[
                    'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-bold transition-all duration-300 ring-1 outline-none shrink-0',
                    isListening 
                        ? 'bg-rose-50 text-rose-600 ring-rose-200 animate-pulse' 
                        : 'bg-emerald-50 text-emerald-600 ring-emerald-200 hover:bg-emerald-100 active:scale-95'
                ]"
            >
                <!-- Mic Icon -->
                <svg v-if="!isListening" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5 sm:w-4 sm:h-4">
                    <path d="M8.25 4.5a3.75 3.75 0 1 1 7.5 0v8.25a3.75 3.75 0 1 1-7.5 0V4.5Z" />
                    <path d="M6 10.5a.75.75 0 0 1 .75.75 5.25 5.25 0 1 0 10.5 0 .75.75 0 0 1 1.5 0 6.75 6.75 0 1 1-13.5 0 .75.75 0 0 1 .75-.75Z" />
                    <path d="M12 18.75a.75.75 0 0 1 .75.75v2.25a.75.75 0 0 1-1.5 0V19.5a.75.75 0 0 1 .75-.75Z" />
                </svg>
                <div v-else class="flex gap-0.5 items-center">
                    <span class="w-1 h-2 bg-rose-400 animate-bounce"></span>
                    <span class="w-1 h-3 bg-rose-500 animate-bounce [animation-delay:-0.2s]"></span>
                    <span class="w-1 h-2 bg-rose-400 animate-bounce [animation-delay:-0.4s]"></span>
                </div>
                
                <span class="whitespace-nowrap">{{ isListening ? 'Listening...' : 'Speech-to-Text' }}</span>
            </button>
        </div>

        <textarea
            :value="modelValue"
            @input="emit('update:modelValue', $event.target.value)"
            :rows="rows"
            :placeholder="placeholder"
            :required="required"
            class="w-full rounded-xl border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 placeholder:text-gray-300 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 resize-none transition-colors shadow-sm"
            :class="{ 'ring-2 ring-emerald-100 border-emerald-300': isListening, 'border-rose-300 ring-rose-50': errorMessage }"
        ></textarea>
        
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="transform -translate-y-1 opacity-0"
            enter-to-class="transform translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="errorMessage" class="mt-2">
                <div class="bg-rose-50 border border-rose-200 text-rose-700 text-[10px] sm:text-xs px-3 py-2 rounded-lg shadow-sm flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5 sm:w-4 sm:h-4 flex-shrink-0 text-rose-500">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                    </svg>
                    <span class="font-bold whitespace-normal">{{ errorMessage }}</span>
                </div>
            </div>
        </Transition>
        
        <!-- Tooltip for help -->
        <span v-if="isListening" class="absolute left-1/2 -top-10 -translate-x-1/2 bg-gray-800 text-white text-[10px] px-2 py-1 rounded shadow-lg whitespace-nowrap z-50">
            Please speak now...
        </span>
    </div>
</template>
