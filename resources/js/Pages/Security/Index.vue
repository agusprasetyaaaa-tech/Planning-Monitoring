<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: NexusLayout });

const props = defineProps({
    settings: Object,
    current_ip: String,
    countries: Array, // [{code, name}]
});

const form = useForm({
    rate_limit: props.settings?.rate_limit ?? 60,
    blocked_ips: props.settings?.blocked_ips || [],
    blocked_countries: props.settings?.blocked_countries || [],
    is_active: props.settings?.is_active ?? true,
});

const newIp = ref('');

const addIp = () => {
    if (newIp.value && !form.blocked_ips.includes(newIp.value)) {
        form.blocked_ips.push(newIp.value);
        newIp.value = '';
    }
};

const removeIp = (ip) => {
    form.blocked_ips = form.blocked_ips.filter(i => i !== ip);
};

const toggleCountry = (code) => {
    if (form.blocked_countries.includes(code)) {
        form.blocked_countries = form.blocked_countries.filter(c => c !== code);
    } else {
        form.blocked_countries.push(code);
    }
};

const submit = () => {
    form.patch(route('security.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Toast handled by layout/flash
        }
    });
};
</script>

<template>
    <Head title="Security Settings" />
    
    <div class="space-y-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
             <h2 class="text-2xl font-bold text-gray-800">Security Settings</h2>
             <div class="flex flex-col md:flex-row items-center gap-3 w-full md:w-auto">
                 <span class="text-sm text-gray-500 hidden md:inline-block whitespace-nowrap">Current IP: {{ current_ip }}</span>
                 <Link :href="route('security.online')" class="flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg font-bold shadow hover:bg-blue-700 transition-all w-full md:w-auto">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    Monitor Online Users
                </Link>
             </div>
        </div>

        <!-- Master Switch -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-gray-800">Enable Security Features</h3>
                <p class="text-xs text-gray-500">Toggle all security checks (Rate Limit, Blocks)</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.is_active" @change="submit" class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
            </label>
        </div>

        <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Rate Limiting -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <h3 class="font-bold text-emerald-600 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    Rate Limiting
                </h3>
                <div class="space-y-4">
                     <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Requests per Minute</label>
                        <input type="number" v-model="form.rate_limit" class="w-full rounded-lg border-gray-300 focus:ring-emerald-500 focus:border-emerald-500" min="1">
                        <p class="text-xs text-gray-500 mt-1">If an IP exceeds this limit, they will be blocked for 1 minute temporarily.</p>
                     </div>
                </div>
            </div>

            <!-- IP Blocking -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <h3 class="font-bold text-red-600 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                    IP Blocking
                </h3>
                <div class="space-y-4">
                     <div class="flex gap-2">
                        <input type="text" v-model="newIp" @keydown.enter.prevent="addIp" placeholder="e.g. 192.168.1.1" class="flex-1 rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500 placeholder:text-gray-400">
                        <button type="button" @click="addIp" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium">Add</button>
                     </div>
                     <div class="min-h-[100px] border border-dashed border-gray-200 rounded-lg p-3">
                        <div v-if="form.blocked_ips.length === 0" class="text-center text-sm text-gray-400 py-4">No IPs blocked</div>
                        <div v-else class="flex flex-wrap gap-2">
                            <span v-for="ip in form.blocked_ips" :key="ip" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                {{ ip }}
                                <button type="button" @click="removeIp(ip)" class="ml-1.5 text-red-600 hover:text-red-900 focus:outline-none">×</button>
                            </span>
                        </div>
                     </div>
                </div>
            </div>

             <!-- Country Blocking -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 lg:col-span-2">
                <h3 class="font-bold text-orange-600 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Country Blocking
                </h3>
                <p class="text-xs text-gray-500 mb-4">Click to toggle block status. Requires GeoIP or Cloudflare Headers.</p>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <button 
                        v-for="country in countries" 
                        :key="country.code"
                        type="button"
                        @click="toggleCountry(country.code)"
                        class="px-3 py-2 rounded-lg text-sm border font-medium transition-all flex justify-between items-center"
                        :class="form.blocked_countries.includes(country.code) 
                            ? 'bg-red-50 border-red-200 text-red-700 ring-1 ring-red-200' 
                            : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300'"
                    >
                        <span>{{ country.name }}</span>
                        <span v-if="form.blocked_countries.includes(country.code)" class="text-xs bg-red-200 text-red-800 px-1.5 rounded">BLOCKED</span>
                    </button>
                    <!-- Fallback for other countries input? Not for now. -->
                </div>
            </div>
            
            <div class="lg:col-span-2 flex justify-end">
                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-emerald-600 text-white rounded-xl font-bold shadow-lg shadow-emerald-200 hover:bg-emerald-700 transition-all flex items-center gap-2">
                    <svg v-if="form.processing" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</template>
