<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

defineOptions({ layout: NexusLayout });

const props = defineProps({
    onlineUsers: Array,
    history: Array,
});

const clearHistory = () => {
    if (confirm('Are you sure you want to clear all login history? This cannot be undone.')) {
        router.delete(route('security.online.clear'), {
            preserveScroll: true,
            preserveState: true,
        });
    }
};

const activeTab = ref('online'); // 'online' or 'history'
let pollInterval = null;

const startPolling = () => {
    pollInterval = setInterval(() => {
        if (activeTab.value === 'online') {
            router.reload({
                only: ['onlineUsers'],
                preserveScroll: true,
                preserveState: true,
            });
        }
    }, 5000); // Poll every 5 seconds
};

onMounted(() => {
    startPolling();
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <Head title="Online Monitoring" />
    
    <div class="space-y-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
             <h2 class="text-2xl font-bold text-gray-800">Online Monitoring</h2>
             <div class="flex gap-2 w-full md:w-auto">
                 <button 
                    @click="activeTab = 'online'"
                    class="px-4 py-2 rounded-lg text-sm font-bold transition-all flex-1 md:flex-none justify-center"
                    :class="activeTab === 'online' ? 'bg-emerald-600 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-50'"
                 >
                    Active Users <span class="ml-1 bg-emerald-500 text-white px-1.5 py-0.5 rounded-full text-xs">{{ onlineUsers.length }}</span>
                 </button>
                 <button 
                    @click="activeTab = 'history'"
                    class="px-4 py-2 rounded-lg text-sm font-bold transition-all flex-1 md:flex-none justify-center"
                    :class="activeTab === 'history' ? 'bg-emerald-600 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-50'"
                 >
                    History
                 </button>
             </div>
        </div>

        <!-- Online Users Table -->
        <div v-if="activeTab === 'online'" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-xs uppercase font-semibold text-gray-500">
                        <tr>
                            <th class="px-6 py-4">User</th>
                            <th class="px-6 py-4">Team</th>
                            <th class="px-6 py-4">IP Address</th>
                            <th class="px-6 py-4">Device</th>
                            <th class="px-6 py-4">Login Time</th>
                            <th class="px-6 py-4">Last Activity</th>
                            <th class="px-6 py-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="onlineUsers.length === 0">
                            <td colspan="7" class="px-6 py-8 text-center text-gray-400">No active users found (besides you maybe?)</td>
                        </tr>
                        <tr v-for="user in onlineUsers" :key="user.id" class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 font-medium text-gray-900">
                                <div>{{ user.user_name }}</div>
                                <div class="text-xs text-gray-400 font-normal">{{ user.user_email }}</div>
                            </td>
                            <td class="px-6 py-4">{{ user.team }}</td>
                            <td class="px-6 py-4 font-mono text-xs">{{ user.ip_address }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-1 rounded bg-gray-100 text-xs font-medium text-gray-600">
                                    {{ user.platform }}
                                </span>
                            </td>
                            <td class="px-6 py-4">{{ user.login_at }}</td>
                            <td class="px-6 py-4">{{ user.last_activity }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Online
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- History Table -->
        <div v-if="activeTab === 'history'">
            <div class="flex justify-end mb-4">
                <button 
                    @click="clearHistory"
                    v-if="history.length > 0"
                    class="flex items-center gap-2 px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors font-semibold text-sm"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Clear History
                </button>
            </div>
            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
             <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-xs uppercase font-semibold text-gray-500">
                        <tr>
                            <th class="px-6 py-4">User</th>
                            <th class="px-6 py-4">IP Address</th>
                            <th class="px-6 py-4">Login Time</th>
                            <th class="px-6 py-4">Logout Time</th>
                            <th class="px-6 py-4">Duration</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="history.length === 0">
                            <td colspan="5" class="px-6 py-8 text-center text-gray-400">No login history available.</td>
                        </tr>
                         <tr v-for="log in history" :key="log.id" class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 font-medium text-gray-900">{{ log.user_name }}</td>
                            <td class="px-6 py-4 font-mono text-xs">{{ log.ip_address }}</td>
                            <td class="px-6 py-4">{{ log.login_at }}</td>
                            <td class="px-6 py-4">{{ log.logout_at }}</td>
                            <td class="px-6 py-4 text-gray-500">{{ log.duration }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</template>
