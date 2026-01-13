<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col justify-center items-center pt-6 sm:pt-0 bg-gray-50 relative overflow-hidden font-sans">
        
        <!-- Abstract Background -->
        <div class="absolute inset-0 z-0">
             <div class="absolute top-0 left-0 w-full h-[60vh] bg-gradient-to-br from-blue-600 to-emerald-600 rounded-b-[4rem] shadow-lg"></div>
             
             <!-- Decorative Circles -->
             <div class="absolute top-10 right-10 w-80 h-80 bg-white/10 rounded-full mix-blend-overlay filter blur-3xl"></div>
             <div class="absolute top-40 left-[-4rem] w-64 h-64 bg-white/10 rounded-full mix-blend-overlay filter blur-2xl"></div>
        </div>

        <div class="z-10 w-full flex flex-col items-center">
            <Link href="/" class="mb-8 transform hover:scale-105 transition-transform duration-300">
                <ApplicationLogo class="h-24 w-24 fill-current text-white drop-shadow-lg" />
            </Link>

            <div class="w-full sm:max-w-md px-8 py-10 bg-white/95 backdrop-blur-sm shadow-[0_8px_30px_rgb(0,0,0,0.04)] rounded-[2rem] border border-gray-100 relative mx-4">
                <slot />
            </div>
            
            <p class="mt-8 text-gray-400 text-sm font-medium">
                &copy; {{ new Date().getFullYear() }} Planly. All rights reserved.
            </p>
        </div>
    </div>
</template>
