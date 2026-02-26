<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { debounce } from 'lodash';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';


defineOptions({ layout: NexusLayout });

const props = defineProps({
    customers: Object,
    filters: Object,
    teams: Array,
    users: Array,
});

const search = ref(props.filters?.search || '');
const sortField = ref(props.filters?.sort || 'created_at');
const sortDirection = ref(props.filters?.direction || 'desc');
const filterTeam = ref(props.filters?.team || '');
const filterUser = ref(props.filters?.user || '');
const perPage = ref(props.filters?.per_page || 10);
const selectedIds = ref([]);
const selectAll = ref(false);

// Computed: Filter users based on selected team
const filteredUsers = computed(() => {
    if (!filterTeam.value) {
        return props.users;
    }
    return props.users.filter(user => user.team_id == filterTeam.value);
});

const updateParams = debounce(() => {
    router.get(route('customers.index'), { 
        search: search.value, 
        sort: sortField.value,
        direction: sortDirection.value,
        team: filterTeam.value,
        sort: sortField.value,
        direction: sortDirection.value,
        team: filterTeam.value,
        user: filterUser.value,
        per_page: perPage.value,
    }, { 
        preserveState: true, 
        preserveScroll: true,
        replace: true,
        only: ['customers', 'flash']
    });
}, 100);

watch(search, () => {
    updateParams();
});

const page = usePage();

// Watch filterTeam to reset filterUser if it's not in the filtered list
watch(filterTeam, (newTeam) => {
    if (newTeam && filterUser.value) {
        const userExists = filteredUsers.value.some(u => u.id == filterUser.value);
        if (!userExists) {
            filterUser.value = '';
        }
    }
});

watch([filterTeam, filterUser, perPage], () => {
    updateParams();
});

const importFileInput = ref(null);
const selectedFile = ref(null);
const importing = ref(false);
const showImportModal = ref(false);

const closeImportModal = () => {
    showImportModal.value = false;
    selectedFile.value = null; 
    if (importFileInput.value) {
        importFileInput.value.value = '';
    }
};

const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        selectedFile.value = file;
    }
};

const downloadTemplate = () => {
    window.location.href = route('customers.template');
};

const handleImport = () => {
    if (!selectedFile.value) {
        alert('Please select an Excel file first');
        return;
    }

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    
    importing.value = true;
    
    router.post(route('customers.import'), formData, {
        onSuccess: () => {
            closeImportModal();
            // Success handled by global flash watcher
        },
        onError: (errors) => {
            closeImportModal();
            // Handle error globally or via form errors if possible
        },
        onFinish: () => {
            importing.value = false;
        }
    });
};

const toggleSort = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'asc';
    }
    updateParams();
};

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedIds.value = props.customers.data.map(c => c.id);
    } else {
        selectedIds.value = [];
    }
};

// Custom Delete Modal State
const showDeleteConfirm = ref(false);
const deleteType = ref('single'); // 'single' or 'bulk'
const deleteId = ref(null);

const deleteTargetName = ref('');

const confirmDelete = (id) => {
    deleteType.value = 'single';
    deleteId.value = id;
    // Find customer name
    const customer = props.customers.data.find(c => c.id === id);
    deleteTargetName.value = customer ? customer.company_name : 'this item';
    showDeleteConfirm.value = true;
};

const handleDeleteSelectedClick = () => {
    deleteType.value = 'bulk';
    showDeleteConfirm.value = true;
};

const executeDelete = () => {
    if (deleteType.value === 'single') {
        router.delete(route('customers.destroy', deleteId.value), {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteConfirm.value = false;
                // Success handled by global flash watcher
            },
            onFinish: () => {
                 showDeleteConfirm.value = false;
            }
        });
    } else {
        router.delete(route('customers.bulk-destroy'), {
            data: { ids: selectedIds.value },
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
                selectAll.value = false;
                showDeleteConfirm.value = false;
                // Success handled by global flash watcher
            },
            onFinish: () => {
                 showDeleteConfirm.value = false;
            }
        });
    }
};

const closeDeleteModal = () => {
    showDeleteConfirm.value = false;
    deleteId.value = null;
};

const pageNumbers = computed(() => {
    const links = props.customers.links;
    return links.filter(link => !link.label.includes('&laquo;') && !link.label.includes('&raquo;'));
});
</script>

<template>
    <Head title="Customer Management" />

    <div class="space-y-4 font-sans">
        <!-- Header & Search -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
             <h2 class="text-[24px] leading-[32px] font-bold text-gray-900">Customer Management</h2>
        </div>

        <!-- Table Container -->
        <div class="flow-root rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
            <!-- Toolbar -->
            <!-- Toolbar -->
            <div class="border-b border-gray-200 px-4 py-3 bg-gray-50/50">
                <div class="flex flex-col gap-4">
                    <!-- Search -->
                    <div class="relative w-full">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input v-model="search" type="text" placeholder="Search customers..." 
                            class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-emerald-600 sm:text-sm sm:leading-6 transition-shadow"
                        />
                    </div>

                    <div class="flex flex-col md:flex-row gap-3 justify-between">
                        <!-- Filters -->
                        <div class="grid grid-cols-2 gap-2 w-full md:w-auto">
                            <!-- Team Filter -->
                            <select v-model="filterTeam" class="block w-full md:w-40 rounded-md border-0 py-1.5 pl-3 pr-8 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-emerald-600 sm:text-sm sm:leading-6">
                                <option value="">All Teams</option>
                                <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                            </select>

                            <!-- User Filter -->
                            <select v-model="filterUser" class="block w-full md:w-40 rounded-md border-0 py-1.5 pl-3 pr-8 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-emerald-600 sm:text-sm sm:leading-6">
                                <option value="">All Users</option>
                                <option v-for="user in filteredUsers" :key="user.id" :value="user.id">
                                    {{ user.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Actions -->
                        <div class="grid grid-cols-2 gap-2 w-full md:w-auto">
                            <!-- Import Button -->
                            <button @click="showImportModal = true" class="flex items-center justify-center gap-x-1.5 rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition-colors w-full">
                                <svg class="-ml-0.5 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                </svg>
                                <span class="truncate">Import</span>
                            </button>
                            
                            <!-- Create Button -->
                            <Link :href="route('customers.create')" class="flex items-center justify-center gap-x-1.5 rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition-colors w-full">
                                <svg class="-ml-0.5 h-5 w-5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                                </svg>
                                <span class="truncate">Create</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions Bar -->
            <div v-if="selectedIds.length > 0" class="bg-emerald-50 border-b border-emerald-200 px-4 py-3">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-2 sm:gap-4">
                    <div class="text-sm text-emerald-700 font-medium">
                        {{ selectedIds.length }} item(s) selected
                    </div>
                    <button @click="handleDeleteSelectedClick" class="w-full sm:w-auto justify-center inline-flex items-center gap-x-1.5 rounded-md bg-red-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                        Delete Selected
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" style="table-layout: fixed;">
                    <thead class="bg-gray-50/50">
                        <tr>
                             <th scope="col" class="w-16 px-6 py-4">
                                <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                            </th>
                            <th scope="col" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                No
                            </th>
                            <th scope="col" @click="toggleSort('company_name')" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer group select-none hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-1">
                                    Company Name
                                    <div class="p-1 rounded-md" :class="{'bg-gray-100': sortField === 'company_name'}">
                                        <svg v-if="sortField === 'company_name' && sortDirection === 'asc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" />
                                        </svg>
                                        <svg v-else-if="sortField === 'company_name' && sortDirection === 'desc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                           <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" style="transform: scaleY(-1); transform-origin: center;" /> 
                                        </svg>
                                        <svg v-else class="h-4 w-4 text-gray-400 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                                        </svg>
                                    </div>
                                </div>
                            </th>

                            <th scope="col" @click="toggleSort('product.name')" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer group select-none hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-1">
                                    Product
                                    <div class="p-1 rounded-md" :class="{'bg-gray-100': sortField === 'product.name'}">
                                        <svg v-if="sortField === 'product.name' && sortDirection === 'asc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" />
                                        </svg>
                                        <svg v-else-if="sortField === 'product.name' && sortDirection === 'desc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                           <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" style="transform: scaleY(-1); transform-origin: center;" /> 
                                        </svg>
                                        <svg v-else class="h-4 w-4 text-gray-400 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                                        </svg>
                                    </div>
                                </div>
                            </th>
                            
                            <th scope="col" @click="toggleSort('marketing.name')" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer group select-none hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-1">
                                    Marketing Sales
                                    <div class="p-1 rounded-md" :class="{'bg-gray-100': sortField === 'marketing.name'}">
                                        <svg v-if="sortField === 'marketing.name' && sortDirection === 'asc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" />
                                        </svg>
                                        <svg v-else-if="sortField === 'marketing.name' && sortDirection === 'desc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                           <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" style="transform: scaleY(-1); transform-origin: center;" /> 
                                        </svg>
                                        <svg v-else class="h-4 w-4 text-gray-400 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                                        </svg>
                                    </div>
                                </div>
                            </th>

                            <th scope="col" @click="toggleSort('planning_start_date')" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer group select-none hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-1">
                                    Planning Start Date
                                    <div class="p-1 rounded-md" :class="{'bg-gray-100': sortField === 'planning_start_date'}">
                                        <svg v-if="sortField === 'planning_start_date' && sortDirection === 'asc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" />
                                        </svg>
                                        <svg v-else-if="sortField === 'planning_start_date' && sortDirection === 'desc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                           <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" style="transform: scaleY(-1); transform-origin: center;" /> 
                                        </svg>
                                        <svg v-else class="h-4 w-4 text-gray-400 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                                        </svg>
                                    </div>
                                </div>
                            </th>

                            <th scope="col" class="relative whitespace-nowrap px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="(customer, index) in customers.data" :key="customer.id" class="hover:bg-gray-50/60 transition-colors" :class="{'bg-emerald-50/30': selectedIds.includes(customer.id)}">
                            <td class="px-6 py-4">
                                <input type="checkbox" :value="customer.id" v-model="selectedIds" class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs text-gray-500">{{ (customers.from || 1) + index }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs font-medium text-gray-900">{{ customer.company_name }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs font-medium">
                                <span v-if="customer.product?.name" class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                    {{ customer.product.name }}
                                </span>
                                <span v-else class="text-gray-500">-</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs text-gray-500">{{ customer.marketing?.name || '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs text-gray-500">
                                <span v-if="customer.planning_start_date" class="px-2 py-1 bg-yellow-50 text-yellow-700 rounded-md border border-yellow-200">
                                    {{ customer.planning_start_date }}
                                </span>
                                <span v-else class="text-gray-400 italic">Default (Created At)</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-xs font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="route('customers.edit', customer.id)" class="text-gray-400 hover:text-emerald-600 transition-colors" title="Edit">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                    </Link>
                                    <button @click="confirmDelete(customer.id)" class="text-gray-400 hover:text-red-600 transition-colors" title="Delete">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="customers.data.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">
                                No customers found matching your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="border-t border-gray-200 px-4 py-3 sm:px-6 sm:py-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                <!-- Left Side: Selection & Per Page -->
                <div class="flex flex-col xs:flex-row items-center gap-3 w-full sm:w-auto justify-between sm:justify-start">
                    <div class="text-xs text-gray-500 whitespace-nowrap">
                        {{ selectedIds.length }} of {{ customers.data.length }} row(s) selected
                    </div>
                    
                    <div class="flex items-center border border-gray-300 rounded-md overflow-hidden shadow-sm">
                        <div class="px-3 py-1.5 text-xs font-medium text-slate-500 bg-slate-50 border-r border-gray-300 whitespace-nowrap">
                            Per page
                        </div>
                        <select v-model="perPage" class="border-none py-1.5 pl-2 pr-8 text-xs font-medium text-gray-700 bg-white focus:ring-0 cursor-pointer hover:bg-gray-50 transition-colors">
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                </div>
                
                <!-- Right Side: Page Links -->
                <div v-if="customers.links.length > 3" class="flex items-center gap-1 overflow-x-auto max-w-full pb-1 sm:pb-0 scrollbar-hide">
                     <Link v-if="customers.first_page_url" :href="customers.first_page_url" class="flex-shrink-0 p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors" :class="{'pointer-events-none opacity-50': customers.current_page === 1}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m18.75 4.5-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5" />
                        </svg>
                    </Link>

                    <Link v-if="customers.prev_page_url" :href="customers.prev_page_url" class="flex-shrink-0 p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </Link>

                    <template v-for="(link, key) in pageNumbers" :key="key">
                        <Link :href="link.url ?? '#'" 
                            class="flex-shrink-0 px-3 py-1 rounded-md text-xs font-medium transition-colors border"
                            :class="link.active ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                            v-html="link.label" 
                        />
                    </template>

                    <Link v-if="customers.next_page_url" :href="customers.next_page_url" class="flex-shrink-0 p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </Link>

                     <Link v-if="customers.last_page_url" :href="customers.last_page_url" class="flex-shrink-0 p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors" :class="{'pointer-events-none opacity-50': customers.current_page === customers.last_page}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m5.25 4.5 7.5 7.5-7.5 7.5m6-15 7.5 7.5-7.5 7.5" />
                        </svg>
                    </Link>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <Modal :show="showImportModal" @close="closeImportModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                Import Customers
            </h2>

            <!-- Template Download Section -->
            <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-100">
                <h3 class="text-sm font-medium text-blue-800 mb-2">1. Download Template (Optional)</h3>
                <p class="text-sm text-blue-600 mb-3">
                    Download the Excel template to ensure your data is formatted correctly before importing.
                </p>
                <SecondaryButton @click="downloadTemplate" class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    </svg>
                    Download Template
                </SecondaryButton>
            </div>

            <!-- File Upload Section -->
            <div class="mt-6">
                <h3 class="text-sm font-medium text-gray-700 mb-2">2. Upload Excel File</h3>
                <div class="mt-2 flex justify-center rounded-lg border border-dashed border-gray-900/25 px-6 py-10"
                    :class="{'bg-gray-50': !selectedFile, 'bg-emerald-50 border-emerald-300': selectedFile}">
                    <div class="text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        <div class="mt-4 flex text-sm leading-6 text-gray-600 justify-center">
                            <label for="file-upload" class="relative cursor-pointer rounded-md bg-white font-semibold text-emerald-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-emerald-600 focus-within:ring-offset-2 hover:text-emerald-500">
                                <span>{{ selectedFile ? selectedFile.name : 'Upload a file' }}</span>
                                <input id="file-upload" ref="importFileInput" type="file" accept=".xlsx,.xls" class="sr-only" @change="handleFileChange">
                            </label>
                        </div>
                        <p class="text-xs leading-5 text-gray-600">XLSX or XLS up to 2MB</p>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="closeImportModal">
                    Cancel
                </SecondaryButton>
                <!-- Using a standard button to ensure Blue color override -->
                <button @click="handleImport" :disabled="importing || !selectedFile"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:bg-blue-500 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ importing ? 'Importing...' : 'Import Customers' }}
                </button>
            </div>
        </div>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal :show="showDeleteConfirm" @close="closeDeleteModal">
        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
                <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
                <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                    <h3 class="text-base font-semibold leading-6 text-gray-900">
                        {{ deleteType === 'single' ? 'Delete Customer' : 'Delete Customers' }}
                    </h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500">
                             {{ deleteType === 'single' 
                                ? `Are you sure you want to delete the customer "${deleteTargetName}"? This action cannot be undone.` 
                                : `Are you sure you want to delete ${selectedIds.length} selected customers? This action cannot be undone.` 
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
            <DangerButton @click="executeDelete" class="w-full sm:w-auto sm:ml-3">
                Yes, Delete
            </DangerButton>
            <SecondaryButton @click="closeDeleteModal" class="mt-3 w-full sm:mt-0 sm:w-auto">
                Cancel
            </SecondaryButton>
        </div>
    </Modal>
</template>
