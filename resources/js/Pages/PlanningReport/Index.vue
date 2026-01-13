<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { debounce, throttle } from 'lodash';

defineOptions({ layout: NexusLayout });

const props = defineProps({
    plans: Object,
    filters: Object,
    timeSettings: Object,
    customers: Array,
    users: Array,
    user_roles: Array,
});

const search = ref(props.filters?.search || '');
const customer_id = ref(props.filters?.customer_id || '');
const user_id = ref(props.filters?.user_id || '');
const group_by = ref(props.filters?.group_by || 'customer');
const perPage = ref(props.filters?.perPage || '10');
const currentTab = ref(props.filters?.tab || 'all');
const start_date = ref(props.filters?.start_date || '');
const end_date = ref(props.filters?.end_date || '');
const isLoading = ref(false);

const selectedIds = ref([]);
const selectAll = ref(false);

// Tab definitions
const tabs = [
    { id: 'all', name: 'All Reports', icon: 'M4 6h16M4 12h16M4 18h16' },
    { id: 'on_track', name: 'On Track', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { id: 'warning', name: 'Warning', icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' },
    { id: 'failed', name: 'Failed', icon: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { id: 'completed', name: 'Completed', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { id: 'history', name: 'History', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
];

const updateParams = throttle(() => {
    isLoading.value = true;
    router.get(route('planning-report.index'), {
        search: search.value,
        customer_id: customer_id.value,
        user_id: user_id.value,
        group_by: group_by.value,
        tab: currentTab.value,
        perPage: perPage.value,
        start_date: start_date.value,
        end_date: end_date.value,
        page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['plans', 'filters'],
        onFinish: () => { isLoading.value = false; }
    });
}, 300);

const changeTab = (tabId) => {
    currentTab.value = tabId;
    updateParams();
};

watch([search, customer_id, user_id, group_by, perPage, start_date, end_date], () => {
    updateParams();
});

// Column Visibility State
const isSuperAdmin = computed(() => props.user_roles && props.user_roles.includes('Super Admin'));
const canViewFilters = computed(() => {
    if (isSuperAdmin.value) return true;
    return props.user_roles && (props.user_roles.includes('Manager') || props.user_roles.includes('Board of Director'));
});

// Column Visibility State
const columns = ref([
    { key: 'no', label: 'No', visible: true },
    { key: 'activity_code', label: 'Code', visible: true },
    { key: 'created_at', label: 'Input Time', visible: true },
    { key: 'updated_at', label: 'Last Update', visible: true },
    { key: 'planning_date', label: 'Activity Date', visible: true },
    { key: 'status', label: 'Lifecycle Status', visible: true },
    { key: 'user', label: 'Sales / Marketing', visible: true },
    { key: 'company', label: 'Company', visible: true },
    { key: 'project', label: 'Project', visible: true },
    { key: 'pic', label: 'Customer PIC', visible: true },
    { key: 'position', label: 'Position', visible: true },
    { key: 'location', label: 'Location', visible: true },
    { key: 'product', label: 'Product', visible: true },
    { key: 'activity', label: 'Activity', visible: true },
    { key: 'progress', label: 'Progress', visible: true },
    { key: 'update_status', label: 'Update Status', visible: true },
]);

const isGroupDropdownOpen = ref(false);
const isFilterDropdownOpen = ref(false);
const isColumnDropdownOpen = ref(false);
const isExportDropdownOpen = ref(false);

const toggleGroupDropdown = () => isGroupDropdownOpen.value = !isGroupDropdownOpen.value;
const toggleFilterDropdown = () => isFilterDropdownOpen.value = !isFilterDropdownOpen.value;
const toggleColumnDropdown = () => isColumnDropdownOpen.value = !isColumnDropdownOpen.value;

const setGroupBy = (value) => {
    group_by.value = value;
    isGroupDropdownOpen.value = false;
};

// Close dropdowns when clicking outside
const closeDropdowns = (e) => {
    // Column Dropdown
    const colDropdown = document.getElementById('column-dropdown');
    const colButton = document.getElementById('column-dropdown-button');
    if (isColumnDropdownOpen.value && colDropdown && !colDropdown.contains(e.target) && !colButton.contains(e.target)) {
        isColumnDropdownOpen.value = false;
    }

    // Group Dropdown
    const groupDropdown = document.getElementById('group-dropdown');
    const groupButton = document.getElementById('group-dropdown-button');
    if (isGroupDropdownOpen.value && groupDropdown && !groupDropdown.contains(e.target) && !groupButton.contains(e.target)) {
        isGroupDropdownOpen.value = false;
    }

    // Filter Dropdown
    const filterDropdown = document.getElementById('filter-dropdown');
    const filterButton = document.getElementById('filter-dropdown-button');
    if (isFilterDropdownOpen.value && filterDropdown && !filterDropdown.contains(e.target) && !filterButton.contains(e.target)) {
        isFilterDropdownOpen.value = false;
    }
};

window.addEventListener('click', closeDropdowns);

const activeFilterCount = computed(() => {
    let count = 0;
    if (customer_id.value) count++;
    if (user_id.value) count++;
    return count;
});

const columnVisibility = computed(() => {
    return columns.value.reduce((acc, col) => {
        acc[col.key] = col.visible;
        return acc;
    }, {});
});

const formatDate = (dateString, includeTime = false) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    if (includeTime) {
        return date.toLocaleString('en-GB', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
    }
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: '2-digit' });
};

const getBadgeColor = (plan) => {
    if (plan.status === 'reported' || plan.status === 'success' || plan.status === 'failed') {
        return 'bg-green-100 text-green-800'; // Done/Reported
    }

    if (!props.timeSettings) return 'bg-gray-100 text-gray-800';

    const unit = props.timeSettings.planning_time_unit || 'Days (Production)';
    const threshold = props.timeSettings.planning_warning_threshold || 14;
    const offsetDays = props.timeSettings.testing_time_offset || 0;

    const now = new Date().getTime() + (offsetDays * 24 * 60 * 60 * 1000);
    
    // FIX: Check if planning_date is in the FUTURE
    const planningDateStr = plan.planning_date ? plan.planning_date.split('T')[0] : null;
    const todayStr = new Date(now).toISOString().split('T')[0];
    
    if (planningDateStr && planningDateStr > todayStr) {
        // Plan is for a FUTURE date - show as normal (scheduled)
        return 'bg-blue-100 text-blue-800'; // Normal/Scheduled
    }
    
    // Calculate diff from planning_date end of day
    const planningDateMs = planningDateStr 
        ? new Date(planningDateStr + 'T23:59:59').getTime()
        : new Date(plan.created_at).getTime();
    
    let diff = now - planningDateMs;
    
    // If diff is negative, still on the planning day
    if (diff < 0) {
        return 'bg-blue-100 text-blue-800'; // Normal/On track
    }

    // Convert diff to the unit
    let diffValue = 0;
    if (unit === 'Hours') {
        diffValue = diff / (1000 * 60 * 60);
    } else if (unit === 'Minutes') {
        diffValue = diff / (1000 * 60);
    } else {
        // Days
        diffValue = diff / (1000 * 60 * 60 * 24);
    }

    if (diffValue >= threshold) {
        return 'bg-red-100 text-red-800 animate-pulse'; // Blink/Warning
    }

    return 'bg-blue-100 text-blue-800'; // Normal/New
};

const getUpdateStatus = (plan) => {
    // 1. History: Hanya jika ada plan versi lebih baru
    if (plan.is_history) {
        return { 
            label: 'History', 
            class: 'bg-gray-100 text-gray-800',
            icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' // Clock icon
        };
    }

    // 2. Completed: Jika sudah update (Reported/Success/Failed) tapi statusnya masih update terbaru (bukan history)
    if (plan.status === 'reported' || plan.status === 'success' || plan.status === 'failed') {
         return { 
            label: 'Completed', 
            class: 'bg-emerald-100 text-emerald-800 font-medium',
            icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' // Check circle icon
        };
    }

    // 2. Jika TimeSettings tidak ada, default On Track
    if (!props.timeSettings) return { 
        label: 'On Track', 
        class: 'bg-green-100 text-green-800',
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
    };

    // 3. Logika Strict Expiry
    const unit = props.timeSettings.planning_time_unit || 'Days (Production)';
    const threshold = props.timeSettings.planning_warning_threshold || 14; 
    
    const expiryValue = props.timeSettings.plan_expiry_value || 7;
    const expiryUnit = props.timeSettings.plan_expiry_unit || 'Days (Production)';
    const offsetDays = props.timeSettings.testing_time_offset || 0;

    const now = new Date().getTime() + (offsetDays * 24 * 60 * 60 * 1000);
    
    // =========================================================================
    // FIX: Check if planning_date is in the FUTURE
    // Plans scheduled for future dates should NOT show as expired
    // =========================================================================
    const planningDateStr = plan.planning_date ? plan.planning_date.split('T')[0] : null;
    const todayStr = new Date(now).toISOString().split('T')[0];
    
    if (planningDateStr && planningDateStr > todayStr) {
        // Plan is for a FUTURE date - show as Scheduled, not expired
        return { 
            label: 'Scheduled', 
            class: 'bg-blue-100 text-blue-800',
            icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' // Calendar icon
        };
    }
    
    // =========================================================================
    // planning_date has passed - calculate expiry from planning_date end of day
    // =========================================================================
    const planningDateMs = planningDateStr 
        ? new Date(planningDateStr + 'T23:59:59').getTime()
        : new Date(plan.created_at).getTime();
    
    const diff = now - planningDateMs;
    
    // If diff is negative, still on the planning day
    if (diff < 0) {
        return { 
            label: 'On Track', 
            class: 'bg-green-100 text-green-800',
            icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
        };
    }
    
    let diffValue = 0;
    // Konversi selisih ke unit Expiry
    if (expiryUnit === 'Hours') {
        diffValue = diff / (1000 * 60 * 60);
    } else if (expiryUnit === 'Minutes') {
        diffValue = diff / (1000 * 60);
    } else {
        // Days
        diffValue = diff / (1000 * 60 * 60 * 24);
    }

    // 4. Jika MELEWATI Expiry Value -> EXPIRED / FAILED
    // Only apply expiry check for 'created' plans (not yet reported)
    if (plan.status === 'created' && diffValue >= expiryValue && diffValue > 0) {
        return { 
            label: 'Expired', 
            class: 'bg-red-100 text-red-800 font-bold',
            icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' 
        };
    }

    return { 
        label: 'On Track', 
        class: 'bg-green-100 text-green-800',
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
    };
};

const getPlanLifecycleStatus = (plan) => {
    // 0. Late Report Check (Higest Priority for "Done" items)
    // If report exists and is late, show Late Report
    if (plan.report && plan.report.execution_date) {
        const exec = new Date(plan.report.execution_date).setHours(0,0,0,0);
        // Use created_at or planning_date? Planning Date is the target.
        const planD = new Date(plan.planning_date).setHours(0,0,0,0);
        
        if (exec > planD) {
             return { label: 'Late Report', class: 'bg-yellow-100 text-yellow-800 border-yellow-200' };
        }
    }

    // 1. BOD Final Status
    if (plan.bod_status === 'success') {
        return { label: 'Success', class: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
    }
    if (plan.bod_status === 'failed') {
        return { label: 'Failed', class: 'bg-red-100 text-red-800 border-red-200' };
    }

    // 2. Lifecycle Expired
    // Cek lifecycle_status database atau hitung manual jika perlu
    if (plan.lifecycle_status === 'expired' || (plan.status === 'created' && getBadgeColor(plan).includes('animate-pulse'))) { 
        // Note: getBadgeColor handles warning blinking, but strict expired logic is needed.
        // Let's rely on 'lifecycle_status' from DB or strict calculation if DB status not updated
        return { label: 'Expired', class: 'bg-gray-100 text-gray-800 border-gray-200' };
    }
    
    // Manual Check for Expired if DB status not updated
    const expiryValue = props.timeSettings?.plan_expiry_value || 7;
    const expiryUnit = props.timeSettings?.plan_expiry_unit || 'Days';
    const offsetDays = props.timeSettings?.testing_time_offset || 0;

    const now = new Date().getTime() + (offsetDays * 24 * 60 * 60 * 1000);
    
    // FIX: Check if planning_date is in the FUTURE first
    const planningDateStr = plan.planning_date ? plan.planning_date.split('T')[0] : null;
    const todayStr = new Date(now).toISOString().split('T')[0];
    
    if (planningDateStr && planningDateStr > todayStr) {
        // Plan is for a FUTURE date - show as Scheduled
        return { label: 'Scheduled', class: 'bg-blue-100 text-blue-800 border-blue-200' };
    }
    
    // Calculate expiry from planning_date (not created_at)
    const planningDateMs = planningDateStr 
        ? new Date(planningDateStr + 'T23:59:59').getTime()
        : new Date(plan.created_at).getTime();
    
    let diff = now - planningDateMs;
    
    // If diff is negative, still on the planning day
    if (diff < 0) {
        // Will be caught by Created status below
    } else {
        let diffVal = 0;
        if (expiryUnit === 'Hours') diffVal = diff / (1000*3600);
        else if (expiryUnit === 'Minutes') diffVal = diff / (1000*60);
        else diffVal = diff / (1000*3600*24);

        // Only Expired if diffVal is positive and exceeds limit
        if (plan.status === 'created' && diffVal >= expiryValue && diffVal > 0) {
            return { label: 'Expired', class: 'bg-gray-800 text-white border-gray-600' };
        }
    }

    // 3. Manager Status
    if (plan.manager_status === 'rejected') {
        return { label: 'Rejected', class: 'bg-rose-100 text-rose-800 border-rose-200' };
    }
    
    // FIX: Add specific states for plans waiting approval (for consistency with Planning/Index.vue)
    // This makes it clear that the plan is NOT expired/warning - it's waiting for approval
    if (plan.manager_status === 'escalated') {
        return { label: 'Escalated', class: 'bg-amber-100 text-amber-800 border-amber-200' };
    }
    
    if (plan.manager_status === 'approved') {
        // Check if waiting for BOD
        if (plan.bod_status === 'pending' || !plan.bod_status) {
            return { label: 'Awaiting BOD', class: 'bg-blue-100 text-blue-800 border-blue-200' };
        }
        return { label: 'Approved', class: 'bg-blue-100 text-blue-800 border-blue-200' };
    }

    // 4. Basic Status
    if (plan.status === 'reported') {
        // Check if waiting for Manager review
        if (plan.manager_status === 'pending' || !plan.manager_status) {
            return { label: 'Pending Review', class: 'bg-cyan-100 text-cyan-800 border-cyan-200' };
        }
        return { label: 'Reported', class: 'bg-cyan-100 text-cyan-800 border-cyan-200' };
    }
    
    // Default Created
    return { label: 'Created', class: 'bg-slate-100 text-slate-800 border-slate-200' };
};

const pageNumbers = computed(() => {
    const links = props.plans.links;
    return links.filter(link => !link.label.includes('&laquo;') && !link.label.includes('&raquo;'));
});

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedIds.value = props.plans.data.map(p => p.id);
    } else {
        selectedIds.value = [];
    }
};

const deleteSelected = () => {
    if (confirm('Are you sure you want to delete the selected plans?')) {
        router.delete(route('planning-report.bulk-destroy'), {
            data: { ids: selectedIds.value },
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
                selectAll.value = false;
            }
        });
    }
};

const getProgressData = (progressValue) => {
    if (!progressValue) return null;
    
    // Convert to string in case it's a number
    const progressStr = String(progressValue);
    
    // Check if it's a closing deal
    const isClosing = progressStr.includes('Closing');
    
    // Check if it contains a dash or hyphen (like "10-Introduction" or "10%-Introduction")
    if (progressStr.includes('-')) {
        // Split by dash
        const parts = progressStr.split('-');
        
        // Extract numeric value from first part (remove % if present)
        const numericPart = parts[0].trim().replace('%', '');
        const percentage = parseInt(numericPart) || 0;
        
        // Get the description part
        const description = parts.slice(1).join('-').trim().replace('%', '');
        
        // Format as "X% - Description"
        const formattedText = description ? `${percentage}% - ${description}` : `${percentage}%`;
        
        return {
            percentage: percentage,
            text: formattedText,
            isClosing: isClosing
        };
    }
    
    // If it's just a number or "number%"
    const numericValue = parseInt(String(progressValue).replace('%', '')) || 0;
    return {
        percentage: numericValue,
        text: `${numericValue}%`,
        isClosing: isClosing
    };
};
</script>

<template>
    <Head title="Planning Report" />

    <div class="space-y-4 font-sans p-4 sm:p-6 max-w-[1920px] mx-auto">
        <!-- Page Title & Back Button -->
        <div class="flex items-center gap-2 sm:gap-4 mb-2">
            <!-- Back Button hidden on mobile -->
            <Link v-if="!isSuperAdmin" :href="route('dashboard')" class="hidden lg:inline-flex group items-center gap-2 px-4 py-2 rounded-xl bg-white border border-gray-200 shadow-sm hover:shadow hover:border-gray-300 text-gray-600 hover:text-gray-900 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 active:scale-[0.98]">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform duration-200">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                <span class="text-xs sm:text-sm font-bold">Back</span>
            </Link>
            <h2 class="text-[24px] leading-[32px] font-bold text-gray-900">Planning Report</h2>
        </div>

        <!-- Table Container with Integrated Tabs and Toolbar -->
        <div class="flow-root rounded-xl sm:rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <!-- Tabs (same style as Planning page) -->
            <div class="border-b border-gray-200 bg-gray-50/80 px-4 sm:px-6">
                <nav class="-mb-px flex space-x-6 sm:space-x-8 overflow-x-auto scrollbar-none" aria-label="Tabs">
                    <button v-for="tab in tabs" :key="tab.id"
                        @click="changeTab(tab.id)"
                        :class="[
                            currentTab === tab.id
                                ? 'border-emerald-500 text-emerald-600 font-bold bg-emerald-50/50'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 font-medium',
                            'whitespace-nowrap border-b-2 py-4 px-2 text-sm transition-all duration-200 flex items-center gap-2'
                        ]"
                    >
                        <svg class="h-4 w-4" :class="currentTab === tab.id ? 'text-emerald-500' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon" />
                        </svg>
                        {{ tab.name }}
                    </button>
                </nav>
            </div>
            
            <!-- Toolbar inside table -->
            <div class="bg-gray-50/50 border-b border-gray-200 px-4 py-3">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <!-- Left: Group By (Visible only to authorized roles) -->
                    <div v-if="canViewFilters" class="flex items-center gap-2">
                        <div class="relative">
                            <button id="group-dropdown-button" @click="toggleGroupDropdown" type="button" class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-colors">
                                <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                                </svg>
                                Group by
                                <svg class="-mr-0.5 h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div id="group-dropdown" v-if="isGroupDropdownOpen" class="absolute left-0 z-20 mt-2 w-48 origin-top-left rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                <div class="py-1">
                                    <button @click="setGroupBy('')" class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" :class="{'bg-gray-50 text-emerald-600 font-semibold': group_by === ''}">
                                        None
                                    </button>
                                    <button @click="setGroupBy('customer')" class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" :class="{'bg-gray-50 text-emerald-600 font-semibold': group_by === 'customer'}">
                                        Customer
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else></div> <!-- Spacer for flex alignment if needed, or just let it collapse -->

                    <!-- Right: Search, Filter, Columns -->
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <!-- Search -->
                        <div class="relative flex-1 sm:flex-none">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                                <svg class="h-4 w-4 sm:h-5 sm:w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input v-model="search" type="text" placeholder="Search"
                                class="block w-full sm:w-64 rounded-xl border-0 py-3.5 sm:py-2.5 pl-11 sm:pl-10 pr-3 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-emerald-600 transition-shadow"
                            />
                        </div>

                        <!-- Filter Button (Authorized only) -->
                        <div v-if="canViewFilters" class="relative">
                            <button id="filter-dropdown-button" @click="toggleFilterDropdown" type="button" class="relative inline-flex items-center justify-center rounded-md bg-white p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-50 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                                <span class="sr-only">Filters</span>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                                </svg>
                                <span v-if="activeFilterCount > 0" class="absolute -top-1 -right-1 h-4 w-4 rounded-full bg-emerald-600 ring-2 ring-white text-[10px] text-white font-bold flex items-center justify-center">{{ activeFilterCount }}</span>
                            </button>
                            <!-- Filter Dropdown -->
                            <div id="filter-dropdown" v-if="isFilterDropdownOpen" class="absolute right-0 z-20 mt-2 w-72 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none p-4">
                                <div class="space-y-4">
                                    <div>
                                        <label for="customer" class="block text-sm font-medium leading-6 text-gray-900">Customer</label>
                                        <select v-model="customer_id" id="customer" class="mt-1 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-emerald-600 sm:text-sm sm:leading-6">
                                            <option value="">All Customers</option>
                                            <option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.company_name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="user" class="block text-sm font-medium leading-6 text-gray-900">User</label>
                                        <select v-model="user_id" id="user" class="mt-1 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-emerald-600 sm:text-sm sm:leading-6">
                                            <option value="">All Users</option>
                                            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="start_date" class="block text-sm font-medium leading-6 text-gray-900">Start Date</label>
                                        <input type="date" v-model="start_date" id="start_date" class="mt-1 block w-full rounded-md border-0 py-1.5 pl-3 pr-3 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-emerald-600 sm:text-sm sm:leading-6">
                                    </div>
                                    <div>
                                        <label for="end_date" class="block text-sm font-medium leading-6 text-gray-900">End Date</label>
                                        <input type="date" v-model="end_date" id="end_date" class="mt-1 block w-full rounded-md border-0 py-1.5 pl-3 pr-3 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-emerald-600 sm:text-sm sm:leading-6">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Export Button -->
                        <div class="relative">
                            <button @click="() => { isExportDropdownOpen = !isExportDropdownOpen }" type="button" class="relative inline-flex items-center justify-center rounded-md bg-white p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-50 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                                <span class="sr-only">Export</span>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                            </button>
                            <div v-if="isExportDropdownOpen" class="absolute right-0 z-20 mt-2 w-48 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                <div class="py-1">
                                    <a :href="`/planning-report/export-excel?tab=${currentTab}&search=${search}&start_date=${start_date}&end_date=${end_date}`" class="flex items-center gap-x-3 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                        Export to Excel
                                    </a>
                                    <a :href="`/planning-report/export-pdf?tab=${currentTab}&search=${search}&start_date=${start_date}&end_date=${end_date}`" class="flex items-center gap-x-3 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                        Export to PDF
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Columns Button (Authorized only) -->
                        <div v-if="canViewFilters" class="relative">
                            <button id="column-dropdown-button" @click="toggleColumnDropdown" type="button" class="relative inline-flex items-center justify-center rounded-md bg-white p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-50 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                                <span class="sr-only">Columns</span>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v13.5c0 .621.504 1.125 1.125 1.125z" />
                                </svg>
                            </button>
                            <div id="column-dropdown" v-if="isColumnDropdownOpen" class="absolute right-0 z-20 mt-2 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                <div class="p-2 space-y-1 max-h-80 overflow-y-auto">
                                    <label v-for="col in columns" :key="col.key" class="flex items-center px-2 py-1.5 hover:bg-gray-100 rounded cursor-pointer">
                                        <input type="checkbox" v-model="col.visible" class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                                        <span class="ml-2 text-sm text-gray-700 select-none">{{ col.label }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions Bar -->
            <div v-if="selectedIds.length > 0 && isSuperAdmin" class="bg-emerald-50 border-b border-emerald-200 px-4 py-3">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-emerald-700 font-medium">
                        {{ selectedIds.length }} item(s) selected
                    </div>
                    <button @click="deleteSelected" class="inline-flex items-center gap-x-1.5 rounded-md bg-red-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                        Delete Selected
                    </button>
                </div>
            </div>

            <!-- Mobile Card View -->
            <div class="block sm:hidden space-y-4 p-4 bg-gray-50/50">
                <template v-for="(plan, index) in plans.data" :key="plan.id">
                    <!-- Group Header -->
                    <div v-if="group_by === 'customer' && (index === 0 || plan.customer?.id !== plans.data[index-1]?.customer?.id)" class="py-2 flex items-center gap-2">
                        <span class="inline-flex items-center justify-center p-1 rounded bg-white shadow-sm ring-1 ring-gray-200">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </span>
                        <span class="font-bold text-sm text-gray-800 tracking-tight">{{ plan.customer?.company_name || 'Unknown Company' }}</span>
                    </div>

                    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col gap-4">
                        <!-- Header: Company & Product -->
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-bold text-gray-900">{{ plan.customer?.company_name || 'Unknown Company' }}</h3>
                                <p class="text-xs text-gray-500 mt-1">{{ plan.project_name || '-' }}</p>
                                <p class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <span class="font-medium text-emerald-600">{{ plan.user?.name || '-' }}</span>
                                </p>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-600 text-[10px] font-bold uppercase tracking-wide ring-1 ring-blue-100">
                                {{ plan.product?.name || '-' }}
                            </span>
                        </div>

                    <!-- Main Info Grid -->
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Activity Code Badge -->
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] uppercase font-bold text-gray-400">Activity</span>
                            <div class="flex items-center gap-2">
                                <span :class="['px-2.5 py-1.5 rounded-lg text-xs font-bold shadow-sm', getBadgeColor(plan)]">
                                    {{ plan.activity_code }}
                                </span>
                            </div>
                        </div>
                        
                        <!-- Date -->
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] uppercase font-bold text-gray-400">Date</span>
                            <span class="text-xs font-semibold text-gray-700">{{ formatDate(plan.planning_date) }}</span>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div v-if="plan.report?.progress" class="flex flex-col gap-1.5">
                        <div class="flex justify-between items-end">
                            <span class="text-[10px] uppercase font-bold text-gray-400">Progress</span>
                            <span class="text-xs font-bold text-gray-700 bg-gray-100 px-2 py-0.5 rounded-md">
                                {{ getProgressData(plan.report.progress).text }}
                            </span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                            <div 
                                class="h-full rounded-full transition-all duration-500"
                                :class="{
                                    'bg-gray-800': getProgressData(plan.report.progress).isClosing,
                                    'bg-red-500': !getProgressData(plan.report.progress).isClosing && getProgressData(plan.report.progress).percentage < 30,
                                    'bg-yellow-500': !getProgressData(plan.report.progress).isClosing && getProgressData(plan.report.progress).percentage >= 30 && getProgressData(plan.report.progress).percentage < 70,
                                    'bg-green-500': !getProgressData(plan.report.progress).isClosing && getProgressData(plan.report.progress).percentage >= 70
                                }"
                                :style="{ width: getProgressData(plan.report.progress).percentage + '%' }"
                            ></div>
                        </div>
                    </div>

                    <!-- Footer Badges -->
                    <div class="flex items-center gap-2 pt-3 border-t border-gray-50">
                        <span :class="['px-2 py-1 rounded text-[10px] font-bold border uppercase', getPlanLifecycleStatus(plan).class]">
                           {{ getPlanLifecycleStatus(plan).label }}
                        </span>
                        
                         <span :class="['inline-flex items-center gap-1 px-2 py-1 rounded text-[10px] font-medium', getUpdateStatus(plan).class]">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getUpdateStatus(plan).icon" />
                            </svg>
                            {{ getUpdateStatus(plan).label }}
                        </span>
                    </div>
                </div>
            </template>
                 <!-- Empty State Mobile -->
                 <div v-if="plans.data.length === 0" class="flex flex-col items-center justify-center p-8 text-center">
                    <div class="h-16 w-16 bg-gray-100 rounded-2xl flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <p class="text-gray-500 text-sm font-medium">No reports found</p>
                </div>
            </div>

            <!-- Table (Desktop Only) -->
            <div class="hidden sm:block overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th v-if="isSuperAdmin" scope="col" class="px-4 py-3">
                                <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                            </th>
                            <template v-for="col in columns" :key="col.key">
                                <th v-if="col.visible" scope="col" class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">{{ col.label }}</th>
                            </template>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <template v-for="(plan, index) in plans.data" :key="plan.id">
                            <!-- Group Header for Customer Grouping -->
                            <tr v-if="group_by === 'customer' && (index === 0 || plan.customer?.id !== plans.data[index-1]?.customer?.id)" class="bg-gray-100">
                                <td :colspan="columns.filter(c => c.visible).length + (isSuperAdmin ? 1 : 0)" class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center justify-center p-1 rounded bg-white shadow-sm ring-1 ring-gray-200">
                                             <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                        </span>
                                        <span class="font-bold text-sm text-gray-800 tracking-tight">{{ plan.customer?.company_name || 'Unknown Company' }}</span>
                                        <span class="text-xs text-gray-500 font-medium bg-white px-1.5 py-0.5 rounded border border-gray-200 shadow-sm ml-auto">
                                            {{ plans.data.filter(p => p.customer?.id === plan.customer?.id).length }} Entries (Page)
                                        </span>
                                    </div>
                                </td>
                            </tr>

                            <tr class="hover:bg-gray-50/60 transition-colors" :class="{'bg-emerald-50/30': selectedIds.includes(plan.id)}">
                                <td v-if="isSuperAdmin" class="px-4 py-4">
                                    <input type="checkbox" :value="plan.id" v-model="selectedIds" class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                                </td>
                                <td v-if="columns.find(c => c.key === 'no').visible" class="px-3 py-4 whitespace-nowrap text-xs text-gray-500">{{ (plans.from || 1) + index }}</td>
                                <td v-if="columns.find(c => c.key === 'activity_code').visible" class="px-3 py-4 whitespace-nowrap text-xs text-gray-900 font-medium">
                                    <span :class="['px-2 py-1 rounded-md text-xs font-semibold', getBadgeColor(plan)]">
                                        {{ plan.activity_code }}
                                    </span>
                                </td>
                                <td v-if="columns.find(c => c.key === 'created_at').visible" class="px-3 py-4 whitespace-nowrap text-xs text-gray-500">{{ formatDate(plan.created_at, true) }}</td>
                                <td v-if="columns.find(c => c.key === 'updated_at').visible" class="px-3 py-4 whitespace-nowrap text-xs text-gray-500">{{ formatDate(plan.updated_at, true) }}</td>
                                <td v-if="columns.find(c => c.key === 'planning_date').visible" class="px-3 py-4 whitespace-nowrap text-xs text-gray-500">{{ formatDate(plan.planning_date) }}</td>
                                <td v-if="columns.find(c => c.key === 'status').visible" class="px-3 py-4 whitespace-nowrap text-xs">
                                    <span :class="['px-2.5 py-1 rounded-md text-xs font-bold border shadow-sm uppercase tracking-wide', getPlanLifecycleStatus(plan).class]">
                                        {{ getPlanLifecycleStatus(plan).label }}
                                    </span>
                                </td>
                                <td v-if="columns.find(c => c.key === 'user').visible" class="px-3 py-4 whitespace-nowrap text-xs text-gray-900">{{ plan.user?.name || '-' }}</td>
                                <td v-if="columns.find(c => c.key === 'company').visible" class="px-3 py-4 whitespace-nowrap text-xs text-gray-900 font-medium">{{ plan.customer?.company_name || '-' }}</td>
                                <td v-if="columns.find(c => c.key === 'project').visible" class="px-3 py-4 whitespace-nowrap text-xs text-gray-500">{{ plan.project_name || '-' }}</td>
                                <td v-if="columns.find(c => c.key === 'pic').visible" class="px-3 py-4 whitespace-nowrap text-xs text-gray-500">{{ plan.report?.pic || '-' }}</td>
                                <td v-if="columns.find(c => c.key === 'position').visible" class="px-3 py-4 whitespace-nowrap text-xs text-gray-500">{{ plan.report?.position || '-' }}</td>
                                <td v-if="columns.find(c => c.key === 'location').visible" class="px-3 py-4 whitespace-nowrap text-xs text-gray-500">{{ plan.report?.location || '-' }}</td>
                                <td v-if="columns.find(c => c.key === 'product').visible" class="px-3 py-4 whitespace-nowrap text-xs text-gray-500">{{ plan.product?.name || '-' }}</td>
                                <td v-if="columns.find(c => c.key === 'activity').visible" class="px-3 py-4 whitespace-nowrap text-xs text-gray-500">{{ plan.activity_type || '-' }}</td>
                                <td v-if="columns.find(c => c.key === 'progress').visible" class="px-3 py-4 whitespace-nowrap text-xs">
                                    <div v-if="plan.report?.progress && getProgressData(plan.report.progress)" class="flex flex-col items-center gap-1" style="min-width: 120px;">
                                        <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                                            <div 
                                                class="h-full rounded-full transition-all duration-300"
                                                :class="{
                                                    'bg-gray-800': getProgressData(plan.report.progress).isClosing,
                                                    'bg-red-500': !getProgressData(plan.report.progress).isClosing && getProgressData(plan.report.progress).percentage < 30,
                                                    'bg-yellow-500': !getProgressData(plan.report.progress).isClosing && getProgressData(plan.report.progress).percentage >= 30 && getProgressData(plan.report.progress).percentage < 70,
                                                    'bg-green-500': !getProgressData(plan.report.progress).isClosing && getProgressData(plan.report.progress).percentage >= 70
                                                }"
                                                :style="{ width: getProgressData(plan.report.progress).percentage + '%' }"
                                            ></div>
                                        </div>
                                        <span class="text-xs text-gray-600 font-medium">{{ getProgressData(plan.report.progress).text }}</span>
                                    </div>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                                <td v-if="columns.find(c => c.key === 'update_status').visible" class="px-3 py-4 whitespace-nowrap text-xs">
                                    <span :class="['inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-xs font-semibold', getUpdateStatus(plan).class]">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getUpdateStatus(plan).icon" />
                                        </svg>
                                        {{ getUpdateStatus(plan).label }}
                                    </span>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="plans.data.length === 0">
                            <td :colspan="columns.filter(c => c.visible).length" class="px-6 py-12 text-center text-sm text-gray-500">
                                No planning reports found
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="border-t border-gray-200 px-4 py-3 sm:px-6 sm:py-4 flex flex-col sm:flex-row items-center justify-between gap-4" v-if="plans.links">
                <!-- Left Side: Showing Info & Per Page -->
                <div class="flex flex-col xs:flex-row items-center gap-3 w-full sm:w-auto justify-between sm:justify-start">
                    <div class="text-xs text-gray-500 whitespace-nowrap">
                         Showing <span class="font-medium">{{ plans.from || 1 }}</span> to <span class="font-medium">{{ plans.to || plans.total }}</span> of <span class="font-medium">{{ plans.total }}</span>
                    </div>
                    
                    <div class="flex items-center border border-gray-300 rounded-md overflow-hidden shadow-sm">
                        <div class="px-3 py-1.5 text-xs font-medium text-slate-500 bg-slate-50 border-r border-gray-300 whitespace-nowrap">
                            Per page
                        </div>
                        <select v-model="perPage" class="border-none py-1.5 pl-2 pr-8 text-xs font-medium text-gray-700 bg-white focus:ring-0 cursor-pointer hover:bg-gray-50 transition-colors">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                </div>

                <!-- Right Side: Pagination Links -->
                <div class="flex flex-wrap justify-center gap-1 w-full sm:w-auto" v-if="plans.links && plans.links.length > 3 && perPage !== 'all'">
                    <Link v-if="plans.first_page_url" :href="plans.first_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors" :class="{'pointer-events-none opacity-50': plans.current_page === 1}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m18.75 4.5-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5" />
                        </svg>
                    </Link>

                    <Link v-if="plans.prev_page_url" :href="plans.prev_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </Link>

                    <template v-for="(link, key) in pageNumbers" :key="key">
                        <Link :href="link.url ?? '#'" 
                            class="px-3 py-1 rounded-md text-xs font-medium transition-colors border"
                            :class="link.active ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                            v-html="link.label" 
                        />
                    </template>

                    <Link v-if="plans.next_page_url" :href="plans.next_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </Link>

                    <Link v-if="plans.last_page_url" :href="plans.last_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors" :class="{'pointer-events-none opacity-50': plans.current_page === plans.last_page}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m5.25 4.5 7.5 7.5-7.5 7.5m6-15 7.5 7.5-7.5 7.5" />
                        </svg>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
