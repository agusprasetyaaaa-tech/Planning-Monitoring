<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionRoot, TransitionChild } from '@headlessui/vue';
import Modal from '@/Components/Modal.vue';
import Toast from '@/Components/Toast.vue';
import DailyReportForm from './DailyReportForm.vue';
import debounce from 'lodash/debounce';

defineOptions({ layout: NexusLayout });

const props = defineProps({
    reports: Object,
    filters: Object,
    auth: Object,
    customers: Array,
    products: Array,
    users: Array,
});

const isSuperAdmin = computed(() => props.auth.user.roles.includes('Super Admin'));
const isManager = computed(() => props.auth.user.roles.includes('Manager'));
const isBOD = computed(() => props.auth.user.roles.includes('Board of Director') || props.auth.user.roles.includes('BOD'));

// Toast state
const toast = ref({
    show: false,
    message: '',
    type: 'success'
});

const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
};

// Listen for flash messages from Inertia
const page = computed(() => props);
watch(() => props.auth.flash, (flash) => {
    if (flash?.success) {
        showToast(flash.success, 'success');
    }
    if (flash?.error) {
        showToast(flash.error, 'error');
    }
}, { immediate: true, deep: true });

const canViewFilters = computed(() => isSuperAdmin.value || isManager.value || isBOD.value);

const search = ref(props.filters.search || '');
const startDate = ref(props.filters.start_date || '');
const endDate = ref(props.filters.end_date || '');
const customer_id = ref(props.filters.customer_id || '');
const user_id = ref(props.filters.user_id || '');
const perPage = ref(props.filters.perPage || '10');
const statusFilter = ref(props.filters.status || 'all');
const groupBy = ref(props.filters.group_by || 'customer');

const isGroupDropdownOpen = ref(false);
const isFilterDropdownOpen = ref(false);
const isColumnDropdownOpen = ref(false);
const isExportDropdownOpen = ref(false);

const selectedIds = ref([]);
const selectAll = ref(false);

// Column Visibility State
const columns = ref([
    { key: 'no', label: 'No', visible: true },
    { key: 'report_date', label: 'Date', visible: true },
    { key: 'sales', label: 'Sales', visible: isSuperAdmin.value },
    { key: 'customer', label: 'Customer', visible: true },
    { key: 'activity_code', label: 'Code', visible: true },
    { key: 'pic', label: 'PIC', visible: true },
    { key: 'description', label: 'Description', visible: true },
    { key: 'result_description', label: 'Result', visible: true },
    { key: 'next_plan', label: 'Next Plan', visible: true },
    { key: 'progress', label: 'Progress', visible: true },
    { key: 'status', label: 'Status', visible: true },
]);

// Detail Modal state
const isDetailModalOpen = ref(false);
// Form Modal state
const isFormModalOpen = ref(false);
const formReport = ref(null);

const openCreateModal = () => {
    formReport.value = null;
    isFormModalOpen.value = true;
};

const openEditModal = (report) => {
    formReport.value = report;
    isFormModalOpen.value = true;
};

const closeFormModal = () => {
    isFormModalOpen.value = false;
    formReport.value = null;
};

const selectedReport = ref(null);

const openDetail = (report) => {
    selectedReport.value = report;
    isDetailModalOpen.value = true;
};

const closeDetail = () => {
    isDetailModalOpen.value = false;
};

const statusTabs = [
    { id: 'all', name: 'All Report', icon: 'list' },
    { id: 'success', name: 'Success', icon: 'check' },
    { id: 'failed', name: 'Failed', icon: 'x' },
];

const activeFilterCount = computed(() => {
    let count = 0;
    if (customer_id.value) count++;
    if (user_id.value) count++;
    if (startDate.value) count++;
    if (endDate.value) count++;
    return count;
});

const updateFilters = debounce(() => {
    router.get(route('daily-report.index'), {
        search: search.value,
        start_date: startDate.value,
        end_date: endDate.value,
        customer_id: customer_id.value,
        user_id: user_id.value,
        perPage: perPage.value,
        group_by: groupBy.value,
        status: statusFilter.value === 'all' ? undefined : statusFilter.value,
    }, {
        preserveState: true,
        replace: true,
        only: ['reports', 'filters']
    });
}, 300);

const changeStatus = (tabId) => {
    if (statusFilter.value === tabId) return;
    statusFilter.value = tabId;
    updateFilters();
};

watch([search, startDate, endDate, perPage, groupBy, customer_id, user_id], () => {
    updateFilters();
});

const setGroupBy = (value) => {
    groupBy.value = value;
    isGroupDropdownOpen.value = false;
};

const toggleGroupDropdown = () => isGroupDropdownOpen.value = !isGroupDropdownOpen.value;
const toggleFilterDropdown = () => isFilterDropdownOpen.value = !isFilterDropdownOpen.value;
const toggleColumnDropdown = () => isColumnDropdownOpen.value = !isColumnDropdownOpen.value;
const toggleExportDropdown = () => isExportDropdownOpen.value = !isExportDropdownOpen.value;

const clearFilters = () => {
    search.value = '';
    startDate.value = '';
    endDate.value = '';
    customer_id.value = '';
    user_id.value = '';
    statusFilter.value = 'all';
    updateFilters();
    isFilterDropdownOpen.value = false;
};

const deleteReport = (id) => {
    if (confirm('Are you sure you want to delete this report?')) {
        router.delete(route('daily-report.destroy', id), {
            preserveScroll: true
        });
    }
};

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedIds.value = props.reports.data.map(r => r.id);
    } else {
        selectedIds.value = [];
    }
};

const deleteSelected = () => {
    if (confirm(`Are you sure you want to delete ${selectedIds.value.length} selected reports?`)) {
        router.delete(route('daily-report.bulk-destroy'), {
            data: { ids: selectedIds.value },
            onSuccess: () => {
                selectedIds.value = [];
                selectAll.value = false;
            }
        });
    }
};

const exportSelectedExcel = () => {
    window.location.href = route('daily-report.export-excel', { ids: selectedIds.value });
};

const exportSelectedPdf = () => {
    window.location.href = route('daily-report.export-pdf', { ids: selectedIds.value });
};

const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
};

const getProgressData = (progressValue) => {
    if (!progressValue) return null;
    const progressStr = String(progressValue);
    const isClosing = progressStr.includes('Closing');
    
    if (progressStr.includes('-')) {
        const parts = progressStr.split('-');
        const numericPart = parts[0].trim().replace('%', '');
        const percentage = parseInt(numericPart) || 0;
        const description = parts.slice(1).join('-').trim().replace('%', '');
        const formattedText = description ? `${percentage}% - ${description}` : `${percentage}%`;
        
        return {
            percentage: percentage,
            text: formattedText,
            isClosing: isClosing
        };
    }
    
    const numericValue = parseInt(String(progressValue).replace('%', '')) || 0;
    return {
        percentage: numericValue,
        text: `${numericValue}%`,
        isClosing: isClosing
    };
};

const getProgressColor = (data) => {
    if (!data) return 'bg-gray-300';
    if (data.isClosing) return 'bg-gray-800';
    if (data.percentage < 30) return 'bg-rose-500';
    if (data.percentage < 70) return 'bg-amber-500';
    return 'bg-emerald-500';
};

const closeDropdowns = (e) => {
    // Column Dropdown
    const colDropdown = document.getElementById('column-dropdown');
    const colButton = document.getElementById('column-dropdown-button');
    if (isColumnDropdownOpen.value && colDropdown && !colDropdown.contains(e.target) && !colButton.contains(e.target)) {
        isColumnDropdownOpen.value = false;
    }

    // Group Dropdown
    const groupDropdown = document.getElementById('group-dropdown-panel');
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

    // Export Dropdown
    const exportDropdown = document.getElementById('export-dropdown');
    const exportButton = document.getElementById('export-dropdown-button');
    if (isExportDropdownOpen.value && exportDropdown && !exportDropdown.contains(e.target) && !exportButton.contains(e.target)) {
        isExportDropdownOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeDropdowns);
});

onUnmounted(() => {
    document.removeEventListener('click', closeDropdowns);
});
</script>

<template>
    <Head title="Daily Activities" />

    <div class="font-sans p-4 sm:p-6 max-w-[1920px] mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <Link v-if="!isSuperAdmin" :href="route('dashboard')" class="hidden sm:inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                    <span>Back</span>
                </Link>
                <h1 class="text-xl sm:text-2xl font-semibold text-gray-900 tracking-tight">Daily Activities</h1>
            </div>
        </div>

        <!-- Main Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <!-- Toolbar & Tabs Container -->
            <div class="border-b border-gray-200">
                <!-- Status Filter Tabs -->
                <div class="border-b border-gray-200 px-4 sm:px-6">
                    <nav class="-mb-px flex space-x-6 sm:space-x-8 overflow-x-auto scrollbar-none" aria-label="Status Tabs">
                        <button v-for="tab in statusTabs" :key="tab.id"
                            @click="changeStatus(tab.id)"
                            :class="[
                                statusFilter === tab.id
                                    ? 'border-emerald-500 text-emerald-600 font-bold bg-emerald-50/30'
                                    : 'border-transparent text-gray-500 sm:hover:border-gray-300 sm:hover:text-gray-700 font-medium',
                                'whitespace-nowrap border-b-2 py-4 px-3 text-sm transition-all duration-200 flex items-center gap-2 rounded-t-lg'
                            ]"
                        >
                            <!-- All icon -->
                            <svg v-if="tab.icon === 'list'" class="h-4 w-4" :class="statusFilter === tab.id ? 'text-emerald-500' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 01-2-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <!-- Success icon -->
                            <svg v-else-if="tab.icon === 'check'" class="h-4 w-4" :class="statusFilter === tab.id ? 'text-emerald-500' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <!-- Failed icon -->
                            <svg v-else-if="tab.icon === 'x'" class="h-4 w-4" :class="statusFilter === tab.id ? 'text-emerald-500' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ tab.name }}
                        </button>
                    </nav>
                </div>
                <!-- Toolbar -->
                <div class="bg-gray-50/50 border-b border-gray-200 px-4 py-3">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <!-- Left: Group By -->
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
                                <div id="group-dropdown-panel" v-if="isGroupDropdownOpen" class="absolute left-0 z-20 mt-2 w-48 origin-top-left rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                    <div class="py-1">
                                        <button @click="setGroupBy('')" class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" :class="{'bg-gray-50 text-emerald-600 font-semibold': groupBy === ''}">
                                            None
                                        </button>
                                        <button @click="setGroupBy('customer')" class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" :class="{'bg-gray-50 text-emerald-600 font-semibold': groupBy === 'customer'}">
                                            Customer
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else></div>

                        <!-- Right: Search, Filter, Export, Columns, Create -->
                        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                            <!-- Search -->
                            <div class="relative w-full sm:w-auto flex-shrink-0">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 sm:pl-4">
                                    <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input v-model="search" type="text" placeholder="Search activities..."
                                    class="block w-full sm:w-64 rounded-xl border-0 py-2 pl-9 sm:pl-10 pr-3 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-emerald-600 transition-shadow"
                                />
                            </div>

                            <!-- Filter Button -->
                            <div v-if="canViewFilters" class="relative">
                                <button id="filter-dropdown-button" @click="toggleFilterDropdown" type="button" class="relative inline-flex items-center justify-center rounded-md bg-white p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-50 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-600 h-[38px]">
                                    <span class="sr-only">Filters</span>
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                                    </svg>
                                    <span v-if="activeFilterCount > 0" class="absolute -top-1 -right-1 h-4 w-4 rounded-full bg-emerald-600 ring-2 ring-white text-[10px] text-white font-bold flex items-center justify-center">{{ activeFilterCount }}</span>
                                </button>
                                <!-- Filter Dropdown -->
                                <div id="filter-dropdown" v-if="isFilterDropdownOpen" class="absolute right-0 z-50 mt-2 w-[calc(100vw-2rem)] sm:w-80 origin-top-right rounded-2xl bg-white shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none overflow-hidden">
                                    <div class="p-4 sm:p-5 space-y-5">
                                        <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-2">
                                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-tight">Advanced Filters</h3>
                                            <button @click="clearFilters" class="text-[10px] font-bold text-emerald-600 hover:text-emerald-700 uppercase tracking-widest">Reset</button>
                                        </div>
                                        <div class="space-y-4">
                                            <div>
                                                <label for="customer" class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Customer</label>
                                                <select v-model="customer_id" id="customer" class="block w-full rounded-xl border-gray-200 py-2.5 text-sm text-gray-700 bg-gray-50/50 focus:bg-white focus:border-emerald-500 focus:ring-emerald-500 transition-all">
                                                    <option value="">All Customers</option>
                                                    <option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.company_name }}</option>
                                                </select>
                                            </div>
                                            <div v-if="isSuperAdmin || isManager || isBOD">
                                                <label for="user" class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Sales / Marketing</label>
                                                <select v-model="user_id" id="user" class="block w-full rounded-xl border-gray-200 py-2.5 text-sm text-gray-700 bg-gray-50/50 focus:bg-white focus:border-emerald-500 focus:ring-emerald-500 transition-all">
                                                    <option value="">All Users</option>
                                                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                                </select>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label for="start_date" class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">From</label>
                                                    <input type="date" v-model="startDate" id="start_date" class="block w-full rounded-xl border-gray-200 py-2.5 text-xs text-gray-700 bg-gray-50/50 focus:bg-white focus:border-emerald-500 focus:ring-emerald-500 transition-all">
                                                </div>
                                                <div>
                                                    <label for="end_date" class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">To</label>
                                                    <input type="date" v-model="endDate" id="end_date" class="block w-full rounded-xl border-gray-200 py-2.5 text-xs text-gray-700 bg-gray-50/50 focus:bg-white focus:border-emerald-500 focus:ring-emerald-500 transition-all">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="pt-2 border-t border-gray-100 mt-2">
                                            <button @click="isFilterDropdownOpen = false" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm active:scale-95">
                                                Apply Filters
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Export Button -->
                            <div class="relative">
                                <button id="export-dropdown-button" @click="toggleExportDropdown" v-if="isSuperAdmin" type="button" class="relative inline-flex items-center justify-center rounded-md bg-white p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-50 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-600 h-[38px]">
                                    <span class="sr-only">Export</span>
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                </button>
                                <div id="export-dropdown" v-if="isExportDropdownOpen" class="absolute right-0 z-50 mt-2 w-48 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none py-1">
                                    <a :href="route('daily-report.export-excel', { search: search, start_date: startDate, end_date: endDate, customer_id: customer_id, user_id: user_id })"
                                        @click="isExportDropdownOpen = false"
                                        class="flex items-center gap-x-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                        <svg class="h-5 w-5 text-emerald-600" viewBox="0 0 24 24" fill="currentColor"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8" fill="none" stroke="white" stroke-width="1.5"/><path d="M8 13h2l1 3 1-3h2" fill="none" stroke="white" stroke-width="1.2"/></svg>
                                        Export Excel
                                    </a>
                                    <a :href="route('daily-report.export-pdf', { search: search, start_date: startDate, end_date: endDate, customer_id: customer_id, user_id: user_id })"
                                        @click="isExportDropdownOpen = false"
                                        class="flex items-center gap-x-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                        <svg class="h-5 w-5 text-rose-500" viewBox="0 0 24 24" fill="currentColor"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8" fill="none" stroke="white" stroke-width="1.5"/><text x="8" y="17" fill="white" font-size="6" font-weight="bold">PDF</text></svg>
                                        Export PDF
                                    </a>
                                </div>
                            </div>

                            <!-- Columns Button -->
                            <div v-if="canViewFilters" class="relative">
                                <button id="column-dropdown-button" @click="toggleColumnDropdown" type="button" class="relative inline-flex items-center justify-center rounded-md bg-white p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-50 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-600 h-[38px]">
                                    <span class="sr-only">Columns</span>
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v13.5c0 .621.504 1.125 1.125 1.125z" />
                                    </svg>
                                </button>
                                <div id="column-dropdown" v-if="isColumnDropdownOpen" class="absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none overflow-hidden">
                                    <div class="p-2 space-y-1 max-h-80 overflow-y-auto">
                                        <label v-for="col in columns" :key="col.key" class="flex items-center px-2 py-1.5 hover:bg-gray-100 rounded cursor-pointer transition-colors">
                                            <input type="checkbox" v-model="col.visible" class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                                            <span class="ml-2 text-xs font-medium text-gray-700 select-none">{{ col.label }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Create Daily Activity Button -->
                            <button @click="openCreateModal" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-lg transition-all shadow-sm active:scale-95 whitespace-nowrap h-[38px]">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                <span class="hidden sm:inline tracking-tight text-xs">Create Activity</span>
                                <span class="sm:hidden text-xs">Create Daily Activity</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions Bar -->
            <div v-if="selectedIds.length > 0 && isSuperAdmin" class="bg-emerald-50/80 border-b border-emerald-100 px-4 py-2.5">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-emerald-800">
                            <span class="font-semibold">{{ selectedIds.length }}</span> selected
                        </span>
                        <div class="h-4 w-px bg-emerald-200"></div>
                        <button @click="exportSelectedExcel" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100 rounded-md transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Excel
                        </button>
                        <button @click="exportSelectedPdf" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100/60 rounded-md transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            PDF
                        </button>
                    </div>
                    <button @click="deleteSelected" class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded-md transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        Delete
                    </button>
                </div>
            </div>

            <!-- Desktop Table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th v-if="isSuperAdmin" class="px-4 py-3 w-10">
                                <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" @click.stop
                                    class="h-3.5 w-3.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 focus:ring-offset-0" />
                            </th>
                            <th v-if="columns.find(c => c.key === 'no').visible" class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-10">No</th>
                            <th v-if="columns.find(c => c.key === 'report_date').visible" class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                            <th v-if="columns.find(c => c.key === 'sales').visible" class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Sales</th>
                            <th v-if="columns.find(c => c.key === 'customer').visible" class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Customer</th>
                            <th v-if="columns.find(c => c.key === 'activity_code').visible" class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-20">Code</th>
                            <th v-if="columns.find(c => c.key === 'pic').visible" class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">PIC</th>
                            <th v-if="columns.find(c => c.key === 'description').visible" class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider max-w-[180px]">Description</th>
                            <th v-if="columns.find(c => c.key === 'result_description').visible" class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider max-w-[160px]">Result</th>
                            <th v-if="columns.find(c => c.key === 'next_plan').visible" class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider max-w-[140px]">Next Plan</th>
                            <th v-if="columns.find(c => c.key === 'progress').visible" class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-32">Progress</th>
                            <th v-if="columns.find(c => c.key === 'status').visible" class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center w-20">Status</th>
                            <th class="px-4 py-3 w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(report, index) in reports.data" :key="report.id">
                            <!-- Group Header for Customer Grouping -->
                            <tr v-if="groupBy === 'customer' && (index === 0 || report.customer?.id !== reports.data[index-1]?.customer?.id)" class="bg-gray-50/40">
                                <td :colspan="columns.filter(c => c.visible).length + (isSuperAdmin ? 2 : 1)" class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="h-6 w-6 rounded-lg bg-white shadow-sm border border-gray-100 flex items-center justify-center text-emerald-600">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                        </div>
                                        <span class="font-bold text-xs text-gray-800 tracking-tight">{{ report.customer?.company_name || 'Individual / General' }}</span>
                                        <span class="ml-auto text-[10px] font-bold text-gray-400 bg-white px-2 py-0.5 rounded-full border border-gray-100 uppercase tracking-wider">
                                            {{ reports.data.filter(r => r.customer?.id === report.customer?.id).length }} Entries
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <tr @click="openDetail(report)"
                                class="border-b border-gray-50 last:border-0 hover:bg-emerald-50/30 transition-colors group cursor-pointer"
                                :class="{'bg-emerald-50/10': selectedIds.includes(report.id)}"
                            >
                            <!-- Checkbox -->
                            <td v-if="isSuperAdmin" class="px-4 py-3">
                                <input type="checkbox" v-model="selectedIds" :value="report.id" @click.stop
                                    class="h-3.5 w-3.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 focus:ring-offset-0" />
                            </td>
                            <!-- No -->
                            <td v-if="columns.find(c => c.key === 'no').visible" class="px-4 py-3 text-xs text-gray-400 tabular-nums">{{ (reports.from || 1) + index }}</td>
                            <!-- Date -->
                            <td v-if="columns.find(c => c.key === 'report_date').visible" class="px-4 py-3 whitespace-nowrap">
                                <span class="text-xs text-gray-800 font-medium">{{ formatDate(report.report_date) }}</span>
                            </td>
                            <!-- Sales -->
                            <td v-if="columns.find(c => c.key === 'sales').visible" class="px-4 py-3 whitespace-nowrap">
                                <span class="text-xs text-gray-600 font-medium">{{ report.user.name }}</span>
                            </td>
                            <!-- Customer -->
                            <td v-if="columns.find(c => c.key === 'customer').visible" class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-gray-800 truncate max-w-[150px] uppercase tracking-tight">{{ report.customer?.company_name || 'Individual' }}</span>
                                    <span v-if="report.product" class="text-[10px] text-gray-400 font-medium">{{ report.product.name }}</span>
                                </div>
                            </td>
                            <!-- Activity Code -->
                            <td v-if="columns.find(c => c.key === 'activity_code').visible" class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-gray-100 text-gray-600 uppercase tracking-wider border border-gray-200 shadow-sm">
                                    {{ report.activity_code || report.activity_type }}
                                </span>
                            </td>
                            <!-- PIC -->
                            <td v-if="columns.find(c => c.key === 'pic').visible" class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span class="text-xs font-semibold text-gray-700">{{ report.pic }}</span>
                                    <span class="text-[10px] text-gray-400 font-medium uppercase tracking-tight">{{ report.position }}</span>
                                </div>
                            </td>
                            <!-- Description -->
                            <td v-if="columns.find(c => c.key === 'description').visible" class="px-4 py-3 max-w-[180px]">
                                <p class="text-xs text-gray-600 line-clamp-2 leading-relaxed">{{ report.description }}</p>
                            </td>
                            <!-- Result Description -->
                            <td v-if="columns.find(c => c.key === 'result_description').visible" class="px-4 py-3 max-w-[160px]">
                                <p class="text-xs text-gray-600 line-clamp-2 leading-relaxed italic">{{ report.result_description }}</p>
                            </td>
                            <!-- Next Plan -->
                            <td v-if="columns.find(c => c.key === 'next_plan').visible" class="px-4 py-3 max-w-[140px]">
                                <p class="text-xs text-gray-500 line-clamp-2 leading-relaxed">{{ report.next_plan || '-' }}</p>
                            </td>
                            <!-- Progress -->
                            <td v-if="columns.find(c => c.key === 'progress').visible" class="px-4 py-3">
                                <div v-if="getProgressData(report.progress)" class="space-y-1.5 min-w-[100px]">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full transition-all duration-500"
                                                :class="getProgressColor(getProgressData(report.progress))"
                                                :style="{ width: getProgressData(report.progress).percentage + '%' }">
                                            </div>
                                        </div>
                                        <span class="text-[10px] font-bold tabular-nums" :class="getProgressData(report.progress).isClosing ? 'text-gray-900' : 'text-gray-500'">
                                            {{ getProgressData(report.progress).percentage }}%
                                        </span>
                                    </div>
                                    <span class="block text-[10px] text-gray-400 font-medium truncate italic leading-tight">{{ getProgressData(report.progress).text }}</span>
                                </div>
                                <span v-else class="text-[10px] text-gray-400 italic font-medium tracking-tight">No progress</span>
                            </td>
                             <!-- Status -->
                             <td v-if="columns.find(c => c.key === 'status').visible" class="px-4 py-3">
                                 <div class="flex justify-center">
                                     <span v-if="report.is_success"
                                         class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 shadow-sm text-[10px] font-bold tracking-wider"
                                     >
                                         <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4">
                                             <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                         </svg>
                                         Success
                                     </span>
                                     <span v-else
                                         class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-100 shadow-sm text-[10px] font-bold tracking-wider"
                                     >
                                         <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4">
                                             <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                         </svg>
                                         Failed
                                     </span>
                                 </div>
                             </td>
                            <!-- Actions -->
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button @click.stop="openEditModal(report)"
                                        v-if="isSuperAdmin"
                                        class="p-1.5 text-blue-500 hover:bg-blue-50 rounded-md transition-colors h-7 w-7 flex items-center justify-center border border-transparent hover:border-blue-100"
                                        title="Edit"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button @click.stop="deleteReport(report.id)"
                                        v-if="isSuperAdmin"
                                        class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-md transition-colors h-7 w-7 flex items-center justify-center border border-transparent hover:border-rose-100"
                                        title="Delete"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>

                        <!-- Empty State -->
                        <tr v-if="reports.data.length === 0">
                            <td :colspan="columns.filter(c => c.visible).length + (isSuperAdmin ? 2 : 1)" class="px-4 py-16 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-10 h-10 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                    </svg>
                                    <p class="text-sm font-medium text-gray-400">No reports found</p>
                                    <p class="text-xs text-gray-300">Adjust your filters or create a new report.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card Layout -->
            <div class="md:hidden space-y-4 p-4 bg-white border-t border-gray-100">
                <template v-for="(report, index) in reports.data" :key="report.id">
                    <!-- Group Header Mobile -->
                    <div v-if="groupBy === 'customer' && (index === 0 || report.customer?.id !== reports.data[index-1]?.customer?.id)" 
                        class="py-3 px-1 flex items-center gap-2 sticky top-0 bg-white/95 backdrop-blur-sm z-10 border-b border-gray-50 mb-1">
                        <div class="h-6 w-6 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <span class="font-bold text-[13px] text-gray-900 tracking-tight uppercase">{{ report.customer?.company_name || 'Individual / General' }}</span>
                    </div>

                    <div @click="openDetail(report)" 
                        class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex flex-col gap-4 cursor-pointer active:scale-[0.98] transition-all hover:border-emerald-200">
                        <!-- Header Row -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <input v-if="isSuperAdmin" type="checkbox" v-model="selectedIds" :value="report.id" @click.stop
                                    class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 flex-shrink-0" />
                                <div class="min-w-0">
                                    <div class="text-sm font-bold text-gray-900 truncate uppercase tracking-tight">{{ report.customer?.company_name || 'Individual' }}</div>
                                    <div class="text-[10px] text-gray-400 mt-0.5 flex items-center gap-1.5 font-bold uppercase tracking-wider">
                                        {{ formatDate(report.report_date) }}
                                        <span v-if="isSuperAdmin" class="flex items-center gap-1.5 text-emerald-600">
                                            <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                                            {{ report.user.name }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wider shadow-sm border"
                                    :class="report.is_success
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                        : 'bg-rose-50 text-rose-700 border-rose-200'"
                                >
                                    <svg v-if="report.is_success" class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <svg v-else class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    {{ report.is_success ? 'Success' : 'Failed' }}
                                </span>
                            </div>
                        </div>

                        <!-- Info Grid -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1">
                                <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Activity Code</span>
                                <div class="flex">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-gray-50 text-gray-600 border border-gray-200 shadow-sm tracking-widest">
                                        {{ report.activity_code || report.activity_type }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1">
                                <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">PIC / Role</span>
                                <div class="text-xs font-semibold text-gray-700 truncate flex items-center gap-1">
                                    {{ report.pic }}
                                    <span class="text-[10px] font-medium text-gray-400">({{ report.position }})</span>
                                </div>
                            </div>
                        </div>

                        <!-- Descriptions Snippet -->
                        <div v-if="report.description" class="pt-1">
                            <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Activity Summary</span>
                            <p class="text-xs text-gray-600 line-clamp-2 leading-relaxed mt-0.5">{{ report.description }}</p>
                        </div>

                        <!-- Progress Bar (Compact) -->
                        <div v-if="report.progress && getProgressData(report.progress)" class="pt-2 border-t border-gray-50">
                            <div class="flex justify-between items-center mb-1.5">
                                 <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Progress</span>
                                 <span class="text-[10px] font-bold text-gray-500">{{ getProgressData(report.progress).percentage }}%</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-700 ease-out"
                                    :class="getProgressColor(getProgressData(report.progress))"
                                    :style="{ width: getProgressData(report.progress).percentage + '%' }"
                                ></div>
                            </div>
                        </div>

                        <!-- Mobile Actions -->
                        <div class="flex items-center justify-end gap-2 pt-1">
                             <button @click.stop="openEditModal(report)"
                                v-if="isSuperAdmin"
                                class="flex-1 flex items-center justify-center py-2 px-3 text-[11px] font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors uppercase tracking-wider h-8"
                            >
                                Edit
                            </button>
                            <button v-if="isSuperAdmin" @click.stop="deleteReport(report.id)"
                                class="flex-1 flex items-center justify-center py-2 px-3 text-[11px] font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors uppercase tracking-wider h-8"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
            </template>

                <!-- Mobile Empty State -->
                <div v-if="reports.data.length === 0" class="bg-white rounded-2xl p-12 text-center border border-gray-100 shadow-sm">
                    <div class="flex flex-col items-center gap-3">
                        <div class="w-16 h-16 bg-gray-50 rounded-2xl flex items-center justify-center mb-2">
                            <svg class="w-8 h-8 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        </div>
                        <p class="text-sm font-bold text-gray-900 tracking-tight">No reports found</p>
                        <p class="text-xs text-gray-500 max-w-[200px] leading-relaxed">Adjust your filters or start by creating a new daily report.</p>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="border-t border-gray-200 px-4 py-3 sm:px-6 sm:py-4 flex flex-col sm:flex-row items-center justify-between gap-4" v-if="reports.links">
                <!-- Left Side: Showing Info & Per Page -->
                <div class="flex flex-col xs:flex-row items-center gap-3 w-full sm:w-auto justify-between sm:justify-start">
                    <div class="text-xs text-gray-500 whitespace-nowrap">
                         Showing <span class="font-medium">{{ reports.from || 1 }}</span> to <span class="font-medium">{{ reports.to || reports.total }}</span> of <span class="font-medium">{{ reports.total }}</span>
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
                <div class="flex flex-wrap justify-center gap-1 w-full sm:w-auto" v-if="reports.links && reports.links.length > 3 && perPage !== 'all'">
                     <Link v-for="(link, k) in reports.links" :key="k"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        class="px-2 py-1 sm:px-3 sm:py-1.5 rounded-md border text-xs font-medium transition-all active:scale-95"
                        :class="link.active 
                            ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' 
                            : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                     />
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal (Matched with Planning style) -->
    <TransitionRoot appear :show="isDetailModalOpen" as="template">
        <Dialog as="div" @close="closeDetail" class="relative z-50">
            <TransitionChild
                as="template"
                enter="duration-300 ease-out"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="duration-200 ease-in"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-black/25 backdrop-blur-sm" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4 text-center">
                    <TransitionChild
                        as="template"
                        enter="duration-300 ease-out"
                        enter-from="opacity-0 scale-95"
                        enter-to="opacity-100 scale-100"
                        leave="duration-200 ease-in"
                        leave-from="opacity-100 scale-100"
                        leave-to="opacity-0 scale-95"
                    >
                        <DialogPanel class="w-full max-w-4xl transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-xl transition-all sm:rounded-3xl">
                            <!-- Header -->
                            <div class="sticky top-0 z-10 bg-white/95 backdrop-blur-sm border-b border-gray-100 px-4 sm:px-6 py-4">
                                <div class="flex items-start sm:items-center justify-between gap-3">
                                    <DialogTitle as="h3" class="text-base sm:text-xl font-bold leading-tight text-gray-900">
                                        <span class="block sm:inline">Report Detail:</span>
                                        <span class="text-emerald-600 block sm:inline mt-1 sm:mt-0 sm:ml-1 text-sm sm:text-xl">{{ selectedReport?.customer?.company_name }}</span>
                                    </DialogTitle>
                                    <button @click="closeDetail" class="flex-shrink-0 p-2 -m-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-all">
                                        <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="max-h-[70vh] overflow-y-auto px-4 sm:px-6 py-6 space-y-6 scrollbar-thin scrollbar-thumb-gray-200 scrollbar-track-transparent">
                                <div v-if="selectedReport" class="border border-gray-200 rounded-xl sm:rounded-2xl overflow-hidden shadow-sm bg-white">
                                    <!-- Card Header -->
                                    <div class="bg-gradient-to-r from-gray-50 to-white px-3 sm:px-4 py-3 border-b border-gray-100">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                            <div class="flex items-center gap-2 sm:gap-3">
                                                <span class="text-base sm:text-lg font-bold text-emerald-600 bg-emerald-50 px-2.5 sm:px-3 py-1 rounded-lg border border-emerald-100 shadow-sm">
                                                    {{ selectedReport.activity_code }}
                                                </span>
                                                <div class="flex flex-col">
                                                     <span class="text-[10px] sm:text-xs text-gray-500 font-medium uppercase tracking-wider">{{ formatDate(selectedReport.report_date) }}</span>
                                                     <span class="text-[10px] sm:text-xs text-gray-600 font-semibold">{{ selectedReport.activity_type }}</span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <div class="flex items-center gap-1.5 bg-white/80 px-2 py-1 rounded-full border border-gray-100 shadow-sm transition-all hover:bg-white">
                                                    <img v-if="selectedReport.user?.avatar_url" :src="selectedReport.user.avatar_url" :alt="selectedReport.user?.name" class="h-5 w-5 sm:h-6 sm:w-6 rounded-full object-cover ring-1 ring-emerald-100">
                                                    <div v-else class="h-5 w-5 sm:h-6 sm:w-6 rounded-full bg-emerald-100 flex items-center justify-center text-[9px] sm:text-[10px] font-bold text-emerald-600 ring-1 ring-emerald-200">
                                                        {{ selectedReport.user?.name?.charAt(0) || 'U' }}
                                                    </div>
                                                    <span class="text-[10px] sm:text-xs text-gray-600 font-medium">{{ selectedReport.user?.name }}</span>
                                                </div>
                                                
                                                <span 
                                                    class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide border shadow-sm transition-all"
                                                    :class="selectedReport.is_success 
                                                        ? 'bg-emerald-100 text-emerald-700 border-emerald-200 ring-1 ring-emerald-50' 
                                                        : 'bg-rose-100 text-rose-700 border-rose-200 ring-1 ring-rose-50'"
                                                >
                                                    {{ selectedReport.is_success ? 'Success' : 'Failed' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Content Body -->
                                    <div class="grid grid-cols-1 md:grid-cols-2">
                                        <!-- Left Column: Activity Info -->
                                        <div class="p-4 sm:p-5 space-y-4 border-b md:border-b-0 md:border-r border-gray-100">
                                            <h4 class="text-[10px] sm:text-xs font-bold text-emerald-600 uppercase tracking-widest flex items-center gap-2">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                </svg>
                                                Report Details
                                            </h4>

                                            <div class="space-y-4">
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div class="bg-gray-50/80 p-3 rounded-xl border border-gray-100 transition-colors hover:bg-gray-50">
                                                        <label class="block text-[9px] text-gray-400 uppercase font-bold tracking-tight mb-0.5">Customer</label>
                                                        <div class="text-[12px] font-bold text-gray-900 leading-tight truncate" :title="selectedReport.customer.company_name">{{ selectedReport.customer.company_name }}</div>
                                                    </div>
                                                    <div class="bg-gray-50/80 p-3 rounded-xl border border-gray-100 transition-colors hover:bg-gray-50">
                                                        <label class="block text-[9px] text-gray-400 uppercase font-bold tracking-tight mb-0.5">Product</label>
                                                        <div class="text-[12px] font-bold text-emerald-600 leading-tight truncate" :title="selectedReport.product?.name">{{ selectedReport.product?.name || '—' }}</div>
                                                    </div>
                                                </div>

                                                <div class="bg-gray-50/30 p-3.5 rounded-xl border border-gray-100 group transition-all">
                                                    <label class="block text-[9px] text-gray-400 uppercase font-bold tracking-tight mb-1.5">Description</label>
                                                    <div class="text-xs sm:text-[13px] text-gray-700 leading-relaxed font-medium whitespace-pre-wrap">
                                                        {{ selectedReport.description }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Right Column: Results & Info -->
                                        <div class="p-4 sm:p-5 space-y-4 bg-gray-50/40">
                                            <h4 class="text-[10px] sm:text-xs font-bold text-blue-600 uppercase tracking-widest flex items-center gap-2">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                Achievement & Progress
                                            </h4>

                                            <div class="space-y-4">
                                                <!-- Meta Grid -->
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div class="bg-white p-3 rounded-xl border border-gray-100 shadow-sm transition-all hover:shadow-md">
                                                        <label class="block text-[9px] text-gray-400 uppercase font-bold tracking-tight mb-0.5">PIC / Position</label>
                                                        <div class="text-[12px] font-bold text-gray-900 truncate" :title="selectedReport.pic">{{ selectedReport.pic }}</div>
                                                        <div class="text-[10px] text-gray-500 font-medium truncate">{{ selectedReport.position }}</div>
                                                    </div>
                                                    <div class="bg-white p-3 rounded-xl border border-gray-100 shadow-sm transition-all hover:shadow-md">
                                                        <label class="block text-[9px] text-gray-400 uppercase font-bold tracking-tight mb-0.5">Location</label>
                                                        <div class="text-[12px] font-bold text-gray-900 truncate mt-0.5" :title="selectedReport.location">
                                                            {{ selectedReport.location || '—' }}
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Progress Bar -->
                                                <div v-if="selectedReport.progress" class="bg-white p-3.5 rounded-xl border border-gray-100 shadow-sm">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <label class="block text-[10px] text-gray-400 uppercase font-bold tracking-widest">Progress</label>
                                                        <span class="text-xs font-black px-1.5 py-0.5 bg-emerald-50 text-emerald-700 rounded-md">
                                                            {{ getProgressData(selectedReport.progress).percentage }}%
                                                        </span>
                                                    </div>
                                                    <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden shadow-inner">
                                                        <div
                                                            class="h-full rounded-full transition-all duration-700 ease-out shadow-sm"
                                                            :class="getProgressColor(getProgressData(selectedReport.progress))"
                                                            :style="{ width: getProgressData(selectedReport.progress).percentage + '%' }"
                                                        ></div>
                                                    </div>
                                                </div>

                                                <!-- Results -->
                                                <div class="bg-white p-3.5 rounded-xl border border-gray-100 shadow-sm group">
                                                    <label class="block text-[9px] text-blue-500 uppercase font-bold tracking-widest mb-1.5">Achievement Result</label>
                                                    <div class="text-xs sm:text-[13px] text-gray-800 leading-relaxed font-bold italic whitespace-pre-wrap">
                                                        "{{ selectedReport.result_description }}"
                                                    </div>
                                                </div>

                                                <!-- Next Plan -->
                                                <div v-if="selectedReport.next_plan" class="bg-emerald-50/30 p-3.5 rounded-xl border-2 border-emerald-100/50 border-dashed">
                                                    <label class="block text-[9px] text-emerald-600 uppercase font-bold tracking-widest mb-1">Next Plan</label>
                                                    <div class="text-xs sm:text-[13px] text-emerald-700 leading-relaxed font-medium italic">
                                                        {{ selectedReport.next_plan }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="sticky bottom-0 bg-white/95 backdrop-blur-sm border-t border-gray-100 px-4 sm:px-6 py-4 flex justify-end">
                                <button
                                    type="button"
                                    class="inline-flex justify-center items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm transition-all duration-200 active:scale-95"
                                    @click="closeDetail"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Close Detail
                                </button>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>

    <!-- Form Modal (Create/Edit) -->
    <DailyReportForm
        :show="isFormModalOpen"
        :report="formReport"
        :customers="customers"
        :products="products"
        :users="users"
        :auth="auth"
        @close="closeFormModal"
    />

    <!-- Toast Notifications -->
    <Toast
        :show="toast.show"
        :message="toast.message"
        :type="toast.type"
        @close="toast.show = false"
    />
</template>

<style scoped>
/* Hide default calendar icon but make it clickable across the entire input */
input[type="date"]::-webkit-calendar-picker-indicator {
    background: transparent;
    bottom: 0;
    color: transparent;
    cursor: pointer;
    height: auto;
    left: 0;
    position: absolute;
    right: 0;
    top: 0;
    width: auto;
}
</style>
