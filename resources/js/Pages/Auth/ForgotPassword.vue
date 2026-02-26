<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Forgot Password" />

    <!-- Main Container: Full Screen Background Image -->
    <div class="min-h-screen relative flex items-center justify-center overflow-hidden font-sans selection:bg-emerald-500 selection:text-white bg-[#558678]">
        
        <!-- Background Image -->
        <div class="absolute inset-0 w-full h-full z-0 bg-cover bg-center bg-no-repeat" style="background-image: url('/portal.png');">
            <!-- Dim Overlay -->
            <div class="absolute inset-0 bg-emerald-900/40 mix-blend-multiply pointer-events-none"></div>
        </div>

        <!-- ============================================== -->
        <!-- FORGOT PASSWORD CARD (Centered) -->
        <!-- ============================================== -->
        <div class="relative z-20 w-full max-w-[420px] bg-white/95 backdrop-blur-xl border border-white/50 rounded-[2rem] shadow-[0_20px_70px_-10px_rgba(0,0,0,0.4)] p-6 sm:p-8 m-4 max-h-[calc(100vh-2rem)] overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
            
            <!-- Logo Section -->
            <div class="flex flex-col items-center mb-6">
                <!-- Simple Elegant Logo Container -->
                <div class="mb-3 transform hover:scale-105 transition-transform duration-300">
                     <img src="/logo/logo.png" alt="Logo" class="w-14 h-14 object-contain drop-shadow-sm" />
                </div>
                <h2 class="text-2xl font-bold text-gray-800 tracking-tight text-center">Forgot Password?</h2>
                <p class="text-gray-500 text-xs sm:text-sm mt-1 text-center px-2">No worries! Enter your email and we will send you reset instructions.</p>
            </div>

            <div v-if="status" class="mb-6 font-medium text-sm text-emerald-700 bg-emerald-50 p-4 rounded-xl border border-emerald-100 text-center shadow-sm">
                {{ status }}
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                
                <!-- Error Notification -->
                <Transition
                    enter-active-class="transition-all duration-300 ease-out"
                    enter-from-class="opacity-0 -translate-y-2 scale-95"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                    leave-active-class="transition-all duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0 scale-100"
                    leave-to-class="opacity-0 -translate-y-2 scale-95"
                >
                    <div v-if="form.errors.email" class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl shadow-sm">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700 font-medium">
                                    {{ form.errors.email }}
                                </p>
                            </div>
                        </div>
                    </div>
                </Transition>
                
                <!-- Email Input -->
                <div class="group">
                    <div class="relative transition-all duration-300">
                        <input
                            id="email"
                            type="email"
                            v-model="form.email"
                            @input="form.clearErrors()"
                            required
                            autofocus
                            placeholder="Email Address"
                            class="peer w-full pl-11 pr-4 py-3 bg-white/60 border border-gray-200 rounded-xl text-gray-800 text-base placeholder-gray-400 focus:bg-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all outline-none"
                        />
                         <!-- Icon -->
                         <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 peer-focus:text-emerald-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Main Button -->
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl shadow-[0_4px_14px_0_rgba(5,150,105,0.39)] hover:shadow-[0_6px_20px_rgba(5,150,105,0.23)] transform active:scale-95 transition-all duration-200"
                >
                    <span v-if="!form.processing">Send Reset Link</span>
                    <span v-else class="flex items-center justify-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Sending...
                    </span>
                </button>

                <!-- Back to Login -->
                <div class="text-center mt-2">
                     <Link :href="route('login')" class="inline-flex items-center justify-center gap-2 text-sm font-medium text-gray-500 hover:text-emerald-600 transition-colors group">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transform group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Login
                    </Link>
                </div>

                <!-- Footer / Copyright -->
                <div class="text-center pt-4">
                    <p class="text-xs text-gray-400 font-light tracking-wide">
                        &copy; 2025 Created <span class="font-medium text-emerald-600">Agus Prasetya</span>
                    </p>
                </div>

            </form>
        </div>
    </div>
</template>

<style scoped>
</style>
