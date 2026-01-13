<script setup>
defineProps({
    visible: Boolean,
});
</script>

<template>
    <div
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-white transition-opacity duration-700 ease-out"
        :class="visible ? 'opacity-100' : 'opacity-0 pointer-events-none'"
    >
        <div class="relative flex flex-col items-center">
            <!-- Logo with breathing/hover effect (No Badge) -->
            <div class="w-24 h-24 flex items-center justify-center animate-breathing mb-5">
                <img src="/logo/logo.png" alt="Logo" class="w-24 h-24 object-contain" />
            </div>
            
            <!-- Text Content -->
            <div class="text-center animate-fade-up">
                <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Planly App</h1>
                <p class="text-sm text-gray-500 font-medium mt-1">Planning Monitoring System</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes breathing {
    0%, 100% { transform: scale(1); filter: drop-shadow(0 0 0 rgba(0,0,0,0)); }
    50% { transform: scale(1.15); filter: drop-shadow(0 10px 20px rgba(37, 99, 235, 0.2)); }
}
@keyframes fade-up {
    0% { opacity: 0; transform: translateY(10px); }
    100% { opacity: 1; transform: translateY(0); }
}
.animate-breathing {
    animation: breathing 2s ease-in-out infinite;
}
.animate-fade-up {
    animation: fade-up 0.8s ease-out forwards;
    animation-delay: 0.3s;
    opacity: 0;
}
</style>
