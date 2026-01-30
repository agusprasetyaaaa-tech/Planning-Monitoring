<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    isCollapsed: {
        type: Boolean,
        default: false
    },
    mobileOpen: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['toggle', 'close-mobile']);

// Get reactive page data
const page = usePage();

// Computed properties for active states
const currentUrl = computed(() => page.url || '');

const isActive = computed(() => ({
    dashboard: currentUrl.value === '/dashboard',
    products: currentUrl.value.startsWith('/products'),
    customers: currentUrl.value.startsWith('/customers'),
    teams: currentUrl.value.startsWith('/teams'),
    users: currentUrl.value.startsWith('/users'),
    roles: currentUrl.value.startsWith('/roles'),
    planning: currentUrl.value.startsWith('/planning') && !currentUrl.value.startsWith('/planning-report'),
    planningReport: currentUrl.value.startsWith('/planning-report'),
    timeSettings: currentUrl.value.startsWith('/time-settings'),
    security: currentUrl.value.startsWith('/security'),
    databaseBackup: currentUrl.value.startsWith('/settings/backup'),
}));

// State Persistence for Groups
const savedGroups = localStorage.getItem('nexus_sidebar_groups');
const openGroups = ref(savedGroups ? JSON.parse(savedGroups) : {
    dashboard: true,
    database: true,
    settings: true,
    workspace: true
});

const toggleGroup = (group) => {
    openGroups.value[group] = !openGroups.value[group];
    localStorage.setItem('nexus_sidebar_groups', JSON.stringify(openGroups.value));
};

// Transition Control
const isMounted = ref(false);
import { onMounted } from 'vue';
onMounted(() => {
    // Ensure group is open for active item if not already
    if (isActive.value.products || isActive.value.customers || isActive.value.teams || isActive.value.users || isActive.value.roles) openGroups.value.database = true;
    if (isActive.value.security || isActive.value.databaseBackup) openGroups.value.settings = true;
    if (isActive.value.planning || isActive.value.planningReport || isActive.value.timeSettings) openGroups.value.workspace = true;
    
    setTimeout(() => { isMounted.value = true; }, 50);
});
</script>

<template>
    <aside class="bg-white border-r border-gray-100 flex flex-col fixed inset-y-0 left-0 z-50 font-sans shadow-xl lg:shadow-none"
        :class="[
            isCollapsed ? 'lg:w-20' : 'lg:w-72',
            mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            'w-72',
            isMounted ? 'transition-all duration-300' : 'transition-none'
        ]">
        <!-- Brand -->
        <div class="h-16 flex items-center gap-3 bg-white" :class="isCollapsed ? 'justify-center px-4' : 'pl-7 pr-4'">
            <img src="/logo/logo.png" alt="Planning Monitoring System" class="w-12 h-12 object-contain" />
            
            <div v-show="!isCollapsed" class="flex flex-col justify-center">
                <span class="text-xl font-bold text-slate-900 leading-tight whitespace-nowrap">Planning Monitoring System</span>
                <span class="text-[10px] text-gray-500 font-normal whitespace-nowrap">Planning Monitoring System</span>
            </div>
            
            <!-- Desktop Toggle -->
            <button v-show="!isCollapsed" @click="$emit('toggle')" class="hidden lg:block ml-auto text-gray-400 hover:text-gray-600 focus:outline-none">
                <svg class="w-5 h-5 transition-transform duration-300" :class="isCollapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                 </svg>
            </button>
            
            <!-- Mobile Close -->
            <button @click="$emit('close-mobile')" class="lg:hidden ml-auto text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

             <button v-show="isCollapsed" @click="$emit('toggle')" class="hidden lg:block absolute -right-3 top-6 bg-white border border-gray-100 rounded-full p-1 shadow-sm text-gray-500 hover:text-emerald-600 transform transition-transform hover:scale-110 focus:outline-none z-50">
                <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                </svg>
            </button>
        </div>

        <!-- Scrollable Navigation -->
        <div class="flex-1 overflow-y-auto pr-4 py-4 space-y-6 scrollbar-hide">
            
            <!-- Dashboard -->
            <div class="relative">
                <!-- Left Indicator Bar -->
                <div v-if="isActive.dashboard" class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-10 bg-emerald-500 rounded-r-full z-10"></div>
                 <Link :href="route('dashboard')" class="relative flex items-center gap-3 px-3.5 py-3 rounded-xl transition-all duration-200 group ml-5"
                    :class="[isActive.dashboard ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-200/50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900', isCollapsed ? 'justify-center ml-0' : '']"
                    title="Dashboard">
                    <svg class="w-6 h-6 shrink-0 transition-colors" :class="isActive.dashboard ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span v-show="!isCollapsed" class="font-medium whitespace-nowrap text-[15px] leading-[22px]">Dashboard</span>
                </Link>
            </div>

            <!-- Database -->
            <div>
                <div @click="toggleGroup('database')" v-show="!isCollapsed" class="flex items-center justify-between px-3 ml-4 mb-2 cursor-pointer group select-none">
                     <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider group-hover:text-gray-600 transition-colors">Database</div>
                     <svg class="w-3 h-3 text-gray-400 transition-transform duration-200" :class="openGroups.database ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                     </svg>
                </div>
                <div v-show="isCollapsed" class="mb-2 h-px bg-gray-100 mx-2"></div>
                
                <div v-show="openGroups.database || isCollapsed" class="space-y-1">
                     <!-- Products -->
                     <div class="relative">
                        <div v-if="isActive.products" class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-10 bg-emerald-500 rounded-r-full z-10"></div>
                        <Link :href="route('products.index')" class="relative flex items-center gap-3 px-3.5 py-3 rounded-xl transition-all duration-200 group ml-5"
                            :class="[isActive.products ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-200/50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900', isCollapsed ? 'justify-center ml-0' : '']"
                            title="Products">
                            <svg class="w-6 h-6 shrink-0 transition-colors" :class="isActive.products ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <span v-show="!isCollapsed" class="font-medium whitespace-nowrap text-[15px] leading-[22px]">Products</span>
                        </Link>
                     </div>

                     <!-- Customers -->
                     <div class="relative">
                        <div v-if="isActive.customers" class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-10 bg-emerald-500 rounded-r-full z-10"></div>
                        <Link :href="route('customers.index')" class="relative flex items-center gap-3 px-3.5 py-3 rounded-xl transition-all duration-200 group ml-5"
                            :class="[isActive.customers ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-200/50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900', isCollapsed ? 'justify-center ml-0' : '']"
                            title="Customers">
                            <svg class="w-6 h-6 shrink-0 transition-colors" :class="isActive.customers ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <span v-show="!isCollapsed" class="font-medium whitespace-nowrap text-[15px] leading-[22px]">Customers</span>
                        </Link>
                     </div>

                     <!-- Teams -->
                     <div class="relative">
                        <div v-if="isActive.teams" class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-10 bg-emerald-500 rounded-r-full z-10"></div>
                        <Link :href="route('teams.index')" class="relative flex items-center gap-3 px-3.5 py-3 rounded-xl transition-all duration-200 group ml-5"
                            :class="[isActive.teams ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-200/50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900', isCollapsed ? 'justify-center ml-0' : '']"
                            title="Teams">
                            <svg class="w-6 h-6 shrink-0 transition-colors" :class="isActive.teams ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <span v-show="!isCollapsed" class="font-medium whitespace-nowrap text-[15px] leading-[22px]">Teams</span>
                        </Link>
                     </div>

                     <!-- Users -->
                     <div class="relative">
                        <div v-if="isActive.users" class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-10 bg-emerald-500 rounded-r-full z-10"></div>
                        <Link :href="route('users.index')" class="relative flex items-center gap-3 px-3.5 py-3 rounded-xl transition-all duration-200 group ml-5"
                            :class="[isActive.users ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-200/50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900', isCollapsed ? 'justify-center ml-0' : '']"
                            title="Users">
                            <svg class="w-6 h-6 shrink-0 transition-colors" :class="isActive.users ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span v-show="!isCollapsed" class="font-medium whitespace-nowrap text-[15px] leading-[22px]">Users</span>
                        </Link>
                     </div>

                     <!-- Roles -->
                     <div class="relative">
                        <div v-if="isActive.roles" class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-10 bg-emerald-500 rounded-r-full z-10"></div>
                        <Link :href="route('roles.index')" class="relative flex items-center gap-3 px-3.5 py-3 rounded-xl transition-all duration-200 group ml-5"
                            :class="[isActive.roles ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-200/50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900', isCollapsed ? 'justify-center ml-0' : '']"
                            title="Roles">
                            <svg class="w-6 h-6 shrink-0 transition-colors" :class="isActive.roles ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span v-show="!isCollapsed" class="font-medium whitespace-nowrap text-[15px] leading-[22px]">Roles</span>
                        </Link>
                     </div>


                </div>
            </div>

            <!-- Settings -->
            <div>
                <div @click="toggleGroup('settings')" v-show="!isCollapsed" class="flex items-center justify-between px-3 ml-4 mb-2 cursor-pointer group select-none">
                     <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider group-hover:text-gray-600 transition-colors">Settings</div>
                     <svg class="w-3 h-3 text-gray-400 transition-transform duration-200" :class="openGroups.settings ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                     </svg>
                </div>
                <!-- Divider only visible if collapsed -->
                <div v-show="isCollapsed" class="mb-2 h-px bg-gray-100 mx-2"></div>
                
                <div v-show="openGroups.settings || isCollapsed" class="space-y-1">
                     <!-- Security -->
                     <div class="relative">
                        <div v-if="isActive.security" class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-10 bg-emerald-500 rounded-r-full z-10"></div>
                        <Link :href="route('security.index')" class="relative flex items-center gap-3 px-3.5 py-3 rounded-xl transition-all duration-200 group ml-5"
                            :class="[isActive.security ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-200/50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900', isCollapsed ? 'justify-center ml-0' : '']"
                            title="Security">
                            <svg class="w-6 h-6 shrink-0 transition-colors" :class="isActive.security ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span v-show="!isCollapsed" class="font-medium whitespace-nowrap text-[15px] leading-[22px]">Security</span>
                        </Link>
                     </div>

                     <!-- Database Backup -->
                     <div class="relative">
                        <div v-if="isActive.databaseBackup" class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-10 bg-emerald-500 rounded-r-full z-10"></div>
                        <Link :href="route('settings.backup.index')" class="relative flex items-center gap-3 px-3.5 py-3 rounded-xl transition-all duration-200 group ml-5"
                            :class="[isActive.databaseBackup ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-200/50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900', isCollapsed ? 'justify-center ml-0' : '']"
                            title="Database Backup">
                            <svg class="w-6 h-6 shrink-0 transition-colors" :class="isActive.databaseBackup ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                            </svg>
                            <span v-show="!isCollapsed" class="font-medium whitespace-nowrap text-[15px] leading-[22px]">Database Backup</span>
                        </Link>
                     </div>
                </div>
            </div>

            <!-- My Workspace -->
             <div>
                <div @click="toggleGroup('workspace')" v-show="!isCollapsed" class="flex items-center justify-between px-3 ml-4 mb-2 cursor-pointer group select-none">
                     <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider group-hover:text-gray-600 transition-colors">My Workspace</div>
                     <svg class="w-3 h-3 text-gray-400 transition-transform duration-200" :class="openGroups.workspace ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                     </svg>
                </div>
                <div v-show="isCollapsed" class="mb-2 h-px bg-gray-100 mx-2"></div>
                
                <div v-show="openGroups.workspace || isCollapsed" class="space-y-1">
                     <!-- Planning -->
                     <div class="relative">
                        <div v-if="isActive.planning" class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-10 bg-emerald-500 rounded-r-full z-10"></div>
                        <Link :href="route('planning.index')" class="relative flex items-center gap-3 px-3.5 py-3 rounded-xl transition-all duration-200 group ml-5"
                            :class="[isActive.planning ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-200/50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900', isCollapsed ? 'justify-center ml-0' : '']"
                            title="Planning">
                            <svg class="w-6 h-6 shrink-0 transition-colors" :class="isActive.planning ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span v-show="!isCollapsed" class="font-medium whitespace-nowrap text-[15px] leading-[22px]">Planning</span>
                        </Link>
                     </div>

                     <!-- Planning Report -->
                     <div class="relative">
                        <div v-if="isActive.planningReport" class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-10 bg-emerald-500 rounded-r-full z-10"></div>
                        <Link :href="route('planning-report.index')" class="relative flex items-center gap-3 px-3.5 py-3 rounded-xl transition-all duration-200 group ml-5"
                            :class="[isActive.planningReport ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-200/50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900', isCollapsed ? 'justify-center ml-0' : '']"
                            title="Planning Report">
                            <svg class="w-6 h-6 shrink-0 transition-colors" :class="isActive.planningReport ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span v-show="!isCollapsed" class="font-medium whitespace-nowrap text-[15px] leading-[22px]">Planning Report</span>
                        </Link>
                     </div>

                     <!-- Time Management -->
                     <div class="relative">
                        <div v-if="isActive.timeSettings" class="absolute left-0 top-1/2 -translate-y-1/2 w-2 h-10 bg-emerald-500 rounded-r-full z-10"></div>
                        <Link :href="route('time-settings.index')" class="relative flex items-center gap-3 px-3.5 py-3 rounded-xl transition-all duration-200 group ml-5"
                            :class="[isActive.timeSettings ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-200/50' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900', isCollapsed ? 'justify-center ml-0' : '']"
                            title="Time Management">
                            <svg class="w-6 h-6 shrink-0 transition-colors" :class="isActive.timeSettings ? 'text-white' : 'text-gray-400 group-hover:text-gray-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span v-show="!isCollapsed" class="font-medium whitespace-nowrap text-[15px] leading-[22px]">Time Management</span>
                        </Link>
                     </div>
                </div>
            </div>
        </div>

        <!-- User Profile Footer -->

    </aside>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
