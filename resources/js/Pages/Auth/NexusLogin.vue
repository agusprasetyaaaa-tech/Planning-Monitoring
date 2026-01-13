<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="User Login" />

    <!-- Main Container: Full Screen Green Gradient -->
    <div class="min-h-screen relative flex items-center justify-center bg-gradient-to-br from-[#047857] via-[#065f46] to-[#064e3b] overflow-hidden font-sans selection:bg-emerald-500 selection:text-white">

        <!-- ============================================== -->
        <!-- BACKGROUND ANIMATED WIDGETS (Floating Layer) -->
        <!-- ============================================== -->
        
        <!-- ============================================== -->
        <!-- BACKGROUND ANIMATED WIDGETS (Floating Layer) -->
        <!-- ============================================== -->
        
        <!-- 1. Floating Animated Bar Chart (Top Left) -->
        <div class="absolute top-16 left-6 lg:left-16 bg-white/10 backdrop-blur-md border border-white/20 p-5 rounded-2xl shadow-2xl animate-float-slow hidden lg:block w-56 z-0">
             <div class="flex justify-between items-center mb-4">
                <div class="h-2.5 w-20 bg-white/40 rounded-full"></div>
                <div class="flex gap-1">
                    <div class="h-1.5 w-1.5 bg-emerald-300 rounded-full animate-pulse"></div>
                    <div class="h-1.5 w-1.5 bg-emerald-300/50 rounded-full"></div>
                </div>
             </div>
             <div class="flex items-end justify-between gap-2 h-28 px-1">
                <!-- Data Bars with Height Animation -->
                <div class="w-full bg-emerald-400/90 rounded-t-sm animate-bar-height-1"></div>
                <div class="w-full bg-emerald-300/90 rounded-t-sm animate-bar-height-2"></div>
                <div class="w-full bg-emerald-200/90 rounded-t-sm animate-bar-height-3"></div>
                <div class="w-full bg-white/90 rounded-t-sm shadow-[0_0_15px_rgba(255,255,255,0.4)] animate-bar-height-4"></div>
                <div class="w-full bg-emerald-500/80 rounded-t-sm animate-bar-height-5"></div>
             </div>
        </div>

        <!-- 2. Floating Animated Pie Chart (Below Bar Chart) -->
        <div class="absolute top-80 left-12 lg:left-24 bg-white/10 backdrop-blur-md border border-white/20 p-2 rounded-full shadow-2xl animate-float-delayed hidden lg:flex items-center justify-center w-40 h-40 z-0">
             <!-- Outer Ring Decoration -->
             <div class="absolute inset-0 border-[3px] border-white/5 rounded-full m-1"></div>
             
             <!-- Scalable SVG Container for Perfect Circle -->
             <div class="relative w-full h-full p-3">
                 <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                     <!-- Background Circle -->
                     <circle cx="50" cy="50" r="40" fill="transparent" stroke="rgba(255,255,255,0.1)" stroke-width="12" />
                     
                     <!-- Slice 1 (System) -->
                     <circle cx="50" cy="50" r="40" fill="transparent" stroke="#34d399" stroke-width="12" 
                             stroke-dasharray="180 251" stroke-linecap="round" class="animate-draw-circle origin-center" />
                             
                     <!-- Slice 2 (User) -->
                     <circle cx="50" cy="50" r="40" fill="transparent" stroke="#059669" stroke-width="12" 
                             stroke-dasharray="50 251" stroke-dashoffset="-190" stroke-linecap="round" class="animate-draw-circle-2 origin-center" />
                 </svg>
                 
                 <!-- Inner Content -->
                 <div class="absolute inset-0 flex flex-col items-center justify-center text-white">
                     <span class="text-xs font-medium text-emerald-100/80 tracking-widest uppercase text-[10px]">LOAD</span>
                     <span class="text-xl font-black tracking-tight drop-shadow-md">86%</span>
                 </div>
             </div>
        </div>

        <!-- 3. Floating User Avatars (Bottom Right - Adjusted Position) -->
        <div class="absolute bottom-16 right-8 lg:right-16 bg-white/10 backdrop-blur-md border border-white/20 px-6 py-4 rounded-2xl shadow-2xl animate-float-medium hidden lg:flex items-center gap-3 z-0">
             <div class="flex -space-x-4">
                 <div class="w-10 h-10 rounded-full bg-yellow-200 border-2 border-emerald-800 shadow-sm flex items-center justify-center text-xs font-bold text-yellow-700">JS</div>
                 <div class="w-10 h-10 rounded-full bg-blue-200 border-2 border-emerald-800 shadow-sm flex items-center justify-center text-xs font-bold text-blue-700">AD</div>
                 <div class="w-10 h-10 rounded-full bg-white border-2 border-emerald-800 shadow-sm flex items-center justify-center text-xs font-bold text-gray-700">+3</div>
             </div>
             <div class="text-white text-xs font-medium">
                 <div>Active Users</div>
                 <div class="text-emerald-300">Currently Online</div>
             </div>
        </div>

        <!-- 4. Floating Line Chart/Status Card (Top Right) -->
        <div class="absolute top-24 right-8 lg:right-32 bg-white/10 backdrop-blur-md border border-white/20 p-4 rounded-xl shadow-2xl animate-float-fast hidden lg:block w-40 transform rotate-6 z-0">
             <div class="flex items-center gap-2 mb-2">
                 <div class="w-3 h-3 rounded-full bg-green-400 animate-ping"></div>
                 <span class="text-[10px] text-white/80 font-mono tracking-wider">SYSTEM OK</span>
             </div>
             <svg class="w-full h-12 text-emerald-300" viewBox="0 0 100 40" fill="none" stroke="currentColor" stroke-width="3">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M0 35 Q 25 35 35 20 T 70 15 T 100 5" />
             </svg>
        </div>

         <!-- 4. Abstract Particles -->
         <div class="absolute top-1/2 left-10 w-4 h-4 bg-yellow-400 rounded-full blur-sm animate-float-delayed opacity-60"></div>
         <div class="absolute bottom-40 left-1/4 w-2 h-2 bg-white rounded-full blur-[1px] animate-ping opacity-40"></div>
         <div class="absolute top-20 right-1/3 w-20 h-20 bg-emerald-400 rounded-full blur-3xl opacity-20"></div>


        <!-- ============================================== -->
        <!-- LOGIN CARD (Centered) -->
        <!-- ============================================== -->
        <div class="relative z-20 w-full max-w-[420px] bg-white rounded-[2rem] shadow-[0_20px_70px_-10px_rgba(0,0,0,0.3)] p-6 sm:p-8 md:p-10 m-4">
            
            <!-- Logo Section -->
            <div class="flex flex-col items-center mb-8">
                <!-- Simple Elegant Logo Container -->
                <div class="w-16 h-16 bg-emerald-50 rounded-2xl flex items-center justify-center mb-4 shadow-sm border border-emerald-100 transform hover:scale-105 transition-transform duration-300">
                     <img src="/logo/logo.png" alt="Logo" class="w-10 h-10 object-contain" />
                </div>
                <h2 class="text-2xl font-bold text-gray-800 tracking-tight text-center">Welcome Back</h2>
                <p class="text-gray-500 text-xs sm:text-sm mt-2 text-center leading-relaxed px-2">Enter your credentials to access your workspace.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                
                <!-- Error Notification -->
                <!-- Error Notification with Smooth Transition -->
                <Transition
                    enter-active-class="transition-all duration-300 ease-out"
                    enter-from-class="opacity-0 -translate-y-2 scale-95"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                    leave-active-class="transition-all duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0 scale-100"
                    leave-to-class="opacity-0 -translate-y-2 scale-95"
                >
                    <div v-if="form.errors.email || form.errors.password" class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl shadow-sm">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700 font-medium">
                                    {{ form.errors.email || form.errors.password }}
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
                            class="peer w-full pl-11 pr-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-800 text-base placeholder-gray-400 focus:bg-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all outline-none"
                        />
                         <!-- Icon -->
                         <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 peer-focus:text-emerald-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Password Input -->
                <div class="group">
                    <div class="relative transition-all duration-300">
                        <input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            v-model="form.password"
                            @input="form.clearErrors()"
                            required
                            placeholder="Password"
                            class="peer w-full pl-11 pr-11 py-3.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-800 text-base placeholder-gray-400 focus:bg-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all outline-none"
                        />
                         <!-- Icon Lock -->
                         <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 peer-focus:text-emerald-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <!-- Toggle Show/Hide -->
                        <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center cursor-pointer text-gray-400 hover:text-gray-600 transition-colors" @click="showPassword = !showPassword">
                             <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                             <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Forgot Password Link -->
                <div class="text-right">
                    <Link :href="route('password.request')" class="text-sm font-medium text-emerald-600 hover:text-emerald-700 transition-colors">
                        Forgot Password?
                    </Link>
                </div>

                <!-- Main Button -->
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-3.5 px-4 bg-[#4f46e5] hover:bg-[#4338ca] text-white font-semibold rounded-xl shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.23)] transform active:scale-95 transition-all duration-200"
                >
                    <span v-if="!form.processing">Continue</span>
                    <span v-else class="flex items-center justify-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Processing...
                    </span>
                </button>

                <!-- Footer / Copyright -->
                <div class="text-center pt-4">
                    <p class="text-xs text-gray-400 font-light tracking-wide">
                        &copy; 2025 Created <span class="font-medium text-emerald-600">Agus Prasetya</span>
                    </p>
                </div>

            </form>
        </div>

        <!-- Bottom Copyright -->


    </div>
</template>

<style scoped>
/* Keyframes matching the previous lively feeling */
@keyframes float-slow {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}
@keyframes float-medium {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
@keyframes float-fast {
    0%, 100% { transform: translateY(0) rotate(6deg); }
    50% { transform: translateY(-8px) rotate(6deg); }
}
@keyframes float-delayed {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(20px); }
}

/* Dynamic Bar Height Animations */
@keyframes bar-height-1 { 0%, 100% { height: 40%; } 50% { height: 75%; } }
@keyframes bar-height-2 { 0%, 100% { height: 70%; } 50% { height: 35%; } }
@keyframes bar-height-3 { 0%, 100% { height: 50%; } 50% { height: 85%; } }
@keyframes bar-height-4 { 0%, 100% { height: 90%; } 50% { height: 45%; } }
@keyframes bar-height-5 { 0%, 100% { height: 60%; } 50% { height: 80%; } }

@keyframes draw-circle {
    0% { stroke-dasharray: 0 251; }
    100% { stroke-dasharray: 180 251; }
}
@keyframes draw-circle-2 {
    0% { stroke-dasharray: 0 251; }
    100% { stroke-dasharray: 50 251; }
}

.animate-float-slow { animation: float-slow 7s ease-in-out infinite; }
.animate-float-medium { animation: float-medium 5s ease-in-out infinite; animation-delay: 1s; }
.animate-float-fast { animation: float-fast 4s ease-in-out infinite; animation-delay: 0.5s; }
.animate-float-delayed { animation: float-delayed 10s ease-in-out infinite; }

.animate-bar-height-1 { animation: bar-height-1 3s ease-in-out infinite; }
.animate-bar-height-2 { animation: bar-height-2 4s ease-in-out infinite; animation-delay: 0.2s; }
.animate-bar-height-3 { animation: bar-height-3 3.5s ease-in-out infinite; animation-delay: 0.4s; }
.animate-bar-height-4 { animation: bar-height-4 4.5s ease-in-out infinite; animation-delay: 0.1s; }
.animate-bar-height-5 { animation: bar-height-5 3.2s ease-in-out infinite; animation-delay: 0.3s; }

.animate-draw-circle { animation: draw-circle 2s ease-out forwards; }
.animate-draw-circle-2 { animation: draw-circle-2 2.5s ease-out forwards; animation-delay: 0.5s; }
</style>

<style>
/* Custom Wave Path Correction if needed */
/* Ensure the svg path creates that nice bulge */
</style>
