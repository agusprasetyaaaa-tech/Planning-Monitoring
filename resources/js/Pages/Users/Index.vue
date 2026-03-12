<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { debounce, throttle } from 'lodash';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineOptions({ layout: NexusLayout });

const props = defineProps({
    users: Object,
    filters: Object,
    roles: Array,
});

const search = ref(props.filters?.search || '');
const sortField = ref(props.filters?.sort || 'created_at');
const sortDirection = ref(props.filters?.direction || 'desc');
const isLoading = ref(false);
const selectedIds = ref([]);
const selectAll = ref(false);

const updateParams = throttle(() => {
    isLoading.value = true;
    router.get(route('users.index'), { 
        search: search.value, 
        sort: sortField.value,
        direction: sortDirection.value
    }, { 
        preserveState: true, 
        preserveScroll: true,
        replace: true,
        only: ['users'],
        onFinish: () => { isLoading.value = false; }
    });
}, 50);

watch(search, () => {
    updateParams();
});

const toggleSort = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'asc';
    }
    updateParams();
};

const getRoleBadgeClasses = (roleName) => {
    const lowerRole = roleName.toLowerCase();
    
    // "LOWSTOCK" style (Orange) for Admin/Special roles
    if (lowerRole.includes('admin') || lowerRole.includes('manager')) {
        return 'bg-[#FEEDD5] text-[#8D440B]'; // Light Orange BG, Dark Brown/Orange Text
    }
    
    // "INSTOCK" style (Green) for User/Standard roles
    return 'bg-[#DAF4E2] text-[#166034]'; // Light Mint Green BG, Dark Green Text
};

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedIds.value = props.users.data.map(user => user.id);
    } else {
        selectedIds.value = [];
    }
};

const toggleSelectAllMobile = () => {
    selectAll.value = !selectAll.value;
    toggleSelectAll();
};

const confirmingDeletion = ref(false);
const itemToDelete = ref(null);
const isBulkDelete = ref(false);

const confirmDelete = (user) => {
    isBulkDelete.value = false;
    itemToDelete.value = user;
    confirmingDeletion.value = true;
};

const confirmBulkDelete = () => {
    isBulkDelete.value = true;
    confirmingDeletion.value = true;
};

const executeDelete = () => {
    if (isBulkDelete.value) {
        router.delete(route('users.bulk-destroy'), {
            data: { ids: selectedIds.value },
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                selectedIds.value = [];
                selectAll.value = false;
            }
        });
    } else {
        router.delete(route('users.destroy', itemToDelete.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal()
        });
    }
};

const closeModal = () => {
    confirmingDeletion.value = false;
    setTimeout(() => {
        itemToDelete.value = null;
        isBulkDelete.value = false;
    }, 200);
};

// --- USER MODAL LOGIC ---
const showUserModal = ref(false);
const editingUser = ref(null);
const userForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [],
});

const openCreateUserModal = () => {
    editingUser.value = null;
    userForm.reset();
    userForm.clearErrors();
    showUserModal.value = true;
};

const openEditUserModal = (user) => {
    editingUser.value = user;
    userForm.name = user.name;
    userForm.email = user.email;
    userForm.password = '';
    userForm.password_confirmation = '';
    userForm.roles = user.roles ? user.roles.map(r => r.name) : [];
    userForm.clearErrors();
    showUserModal.value = true;
};

const closeUserModal = () => {
    showUserModal.value = false;
    setTimeout(() => {
        editingUser.value = null;
        userForm.reset();
        showPassword.value = false;
    }, 200);
};

const showPassword = ref(false);

const generatePassword = () => {
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
    let password = "";
    for (let i = 0, n = charset.length; i < 16; ++i) {
        password += charset.charAt(Math.floor(Math.random() * n));
    }
    userForm.password = password;
    userForm.password_confirmation = password;
    showPassword.value = true;
};

const submitUser = () => {
    if (editingUser.value) {
        userForm.put(route('users.update', editingUser.value.id), {
            onSuccess: () => closeUserModal(),
        });
    } else {
        userForm.post(route('users.store'), {
            onSuccess: () => closeUserModal(),
        });
    }
};

const pageNumbers = computed(() => {
    const links = props.users.links;
    // Filter out Prev/Next and keep only numbers
    return links.filter(link => !link.label.includes('&laquo;') && !link.label.includes('&raquo;'));
});
</script>

<template>
    <Head title="User Management" />

    <div class="space-y-4 font-sans">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
             <h2 class="text-[24px] leading-[32px] font-bold text-gray-900">User Management</h2>
        </div>

        <!-- Table Container -->
        <div class="flow-root rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
             <!-- Toolbar -->
            <div class="border-b border-gray-200 px-4 py-3 bg-gray-50/50">
                <div class="flex flex-col sm:flex-row gap-4 justify-between items-center">
                    <!-- Search -->
                    <div class="relative w-full sm:w-72">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input v-model="search" type="text" placeholder="Search users..." 
                            class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-emerald-600 sm:text-sm sm:leading-6"
                        />
                    </div>
                    
                    <!-- Actions -->
                    <div class="w-full sm:w-auto flex flex-col sm:flex-row justify-end gap-2">
                        <!-- Mobile Bulk Action Toggle -->
                        <button v-if="users.data.length > 0" @click="toggleSelectAllMobile" class="sm:hidden inline-flex w-full justify-center items-center gap-x-1.5 rounded-md bg-white border border-emerald-600 px-3 py-2 text-sm font-bold text-emerald-700 shadow-sm hover:bg-emerald-50 transition-colors">
                            <svg class="-ml-0.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            {{ selectAll ? 'Deselect All' : 'Select All (Bulk)' }}
                        </button>
                        
                        <button @click="openCreateUserModal" class="inline-flex w-full sm:w-auto justify-center items-center gap-x-1.5 rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 transition-colors">
                            <svg class="-ml-0.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                            </svg>
                            Create
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions Bar -->
            <div v-if="selectedIds.length > 0" class="bg-emerald-50 border-b border-emerald-200 px-4 py-3">
                 <div class="flex flex-col sm:flex-row items-center justify-between gap-2 sm:gap-4">
                    <div class="text-sm text-emerald-700 font-medium">
                        {{ selectedIds.length }} item(s) selected
                    </div>
                    <button @click="confirmBulkDelete" class="w-full sm:w-auto inline-flex justify-center items-center gap-x-1.5 rounded-md bg-red-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                        Delete Selected
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th scope="col" class="w-16 px-6 py-4">
                                <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                            </th>
                            <th scope="col" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                No
                            </th>
                            <th scope="col" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Avatar</th>
                            
                            <th scope="col" @click="toggleSort('name')" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer group select-none hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-1">
                                    Name
                                    <div class="p-1 rounded-md" :class="{'bg-gray-100': sortField === 'name'}">
                                        <svg v-if="sortField === 'name' && sortDirection === 'asc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" />
                                        </svg>
                                        <svg v-else-if="sortField === 'name' && sortDirection === 'desc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                           <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" style="transform: scaleY(-1); transform-origin: center;" /> 
                                        </svg>
                                        <svg v-else class="h-4 w-4 text-gray-400 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                                        </svg>
                                    </div>
                                </div>
                            </th>

                            <th scope="col" @click="toggleSort('email')" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer group select-none hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-1">
                                    Email
                                     <div class="p-1 rounded-md" :class="{'bg-gray-100': sortField === 'email'}">
                                        <svg v-if="sortField === 'email' && sortDirection === 'asc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" />
                                        </svg>
                                        <svg v-else-if="sortField === 'email' && sortDirection === 'desc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                             <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" style="transform: scaleY(-1); transform-origin: center;" />
                                        </svg>
                                        <svg v-else class="h-4 w-4 text-gray-400 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                                        </svg>
                                    </div>
                                </div>
                            </th>

                            <th scope="col" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                            <th scope="col" class="relative whitespace-nowrap px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="(user, index) in users.data" :key="user.id" class="hover:bg-gray-50/60 transition-colors" :class="{'bg-emerald-50/30': selectedIds.includes(user.id)}">
                            <td class="px-6 py-4">
                                <input type="checkbox" :value="user.id" v-model="selectedIds" class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs text-gray-500">{{ (users.from || 1) + index }}</td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <img :src="user.avatar_url" class="h-8 w-8 rounded-full ring-2 ring-white object-cover" :alt="user.name">
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs font-medium text-gray-900">{{ user.name }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs text-gray-500">{{ user.email }}</td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span v-for="role in user.roles" :key="role.id" 
                                    :class="['inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset', getRoleBadgeClasses(role.name).includes('Orange') ? 'bg-orange-50 text-orange-700 ring-orange-600/20' : 'bg-green-50 text-green-700 ring-green-600/20']">
                                    {{ role.name }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-xs font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="openEditUserModal(user)" class="text-gray-400 hover:text-emerald-600 transition-colors" title="Edit">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                    </button>
                                    <button @click="confirmDelete(user)" class="text-gray-400 hover:text-red-600 transition-colors" title="Delete">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">
                                No users found matching your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Enhanced Pagination -->
             <div class="flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-gray-200 px-4 py-3">
                <div class="text-xs text-gray-500 text-center sm:text-left">
                    {{ selectedIds.length ? `${selectedIds.length} row(s) selected.` : `${users.from || 0}-${users.to || 0} of ${users.total || 0} users` }}
                </div>
                
                <div v-if="users.links.length > 3" class="w-full sm:w-auto flex justify-center sm:justify-end">
                    <div class="overflow-x-auto max-w-full pb-1 sm:pb-0 scrollbar-hide flex gap-1">
                        <Link v-if="users.first_page_url" :href="users.first_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors flex-shrink-0" :class="{'pointer-events-none opacity-50': users.current_page === 1}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m18.75 4.5-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5" />
                            </svg>
                        </Link>

                        <Link v-if="users.prev_page_url" :href="users.prev_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors flex-shrink-0">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                            </svg>
                        </Link>

                        <template v-for="(link, key) in pageNumbers" :key="key">
                            <Link :href="link.url ?? '#'" 
                                class="px-3 py-1 rounded-md text-xs font-medium transition-colors border flex-shrink-0"
                                :class="link.active ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                                v-html="link.label" 
                            />
                        </template>

                        <Link v-if="users.next_page_url" :href="users.next_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors flex-shrink-0">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </Link>

                        <Link v-if="users.last_page_url" :href="users.last_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors flex-shrink-0" :class="{'pointer-events-none opacity-50': users.current_page === users.last_page}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m5.25 4.5 7.5 7.5-7.5 7.5m6-15 7.5 7.5-7.5 7.5" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <ConfirmDeleteModal
        :show="confirmingDeletion"
        :title="isBulkDelete ? 'Delete Users' : 'Delete User'"
        :content="isBulkDelete ? 'Are you sure you want to delete the selected users? This action cannot be undone.' : `Are you sure you want to delete the user '${itemToDelete?.name}'? This action cannot be undone.`"
        @close="closeModal"
        @confirm="executeDelete"
    />

    <Modal :show="showUserModal" @close="closeUserModal" maxWidth="lg">
        <div class="font-sans">
            <!-- Header -->
            <div class="px-4 py-4 sm:px-5 sm:py-4 bg-emerald-600 flex items-center justify-between rounded-t-xl">
                <div class="pr-8 sm:pr-0">
                    <h2 class="text-base sm:text-lg font-bold text-white leading-tight">
                        {{ editingUser ? 'Edit User' : 'Create New User' }}
                    </h2>
                    <p class="text-[9px] sm:text-xs text-emerald-100 flex items-center gap-1">
                        {{ editingUser ? 'Update user details and permissions' : 'Add a new user to the system' }}
                    </p>
                </div>
                <button @click="closeUserModal" type="button" class="p-2 -mr-2 text-emerald-100 hover:text-white transition-colors rounded-full hover:bg-emerald-500/50">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="submitUser" class="max-h-[85vh] overflow-y-auto overflow-x-hidden">
                <div class="p-4 sm:p-5 space-y-4 sm:space-y-5">
                    <div>
                        <label for="user_name" class="block text-sm font-bold text-gray-700 mb-2">Name <span class="text-rose-400">*</span></label>
                        <input id="user_name" type="text" v-model="userForm.name" required autofocus
                            class="w-full rounded-xl border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm"
                            placeholder="Enter user name"
                        />
                        <InputError class="mt-2" :message="userForm.errors.name" />
                    </div>

                    <div>
                        <label for="user_email" class="block text-sm font-bold text-gray-700 mb-2">Email <span class="text-rose-400">*</span></label>
                        <input id="user_email" type="email" v-model="userForm.email" required
                            class="w-full rounded-xl border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm"
                            placeholder="Enter email address"
                        />
                        <InputError class="mt-2" :message="userForm.errors.email" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div v-if="!editingUser" class="sm:col-span-2 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4 bg-emerald-50/50 p-4 rounded-xl border border-emerald-100">
                            <div class="pr-0 sm:pr-4">
                                <h4 class="text-sm font-bold text-emerald-800 flex items-center gap-2">
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    <span>Secure Password Generation</span>
                                </h4>
                                <p class="text-[11px] sm:text-xs text-emerald-600 mt-1.5 leading-relaxed">Generating a cryptographic, unpredictable password ensures optimal account security, effectively mitigating risks associated with easily guessable credentials and brute-force attacks.</p>
                            </div>
                            <button type="button" @click="generatePassword" class="w-full sm:w-auto shrink-0 px-4 py-2 bg-white border border-emerald-200 text-emerald-600 text-xs font-bold rounded-lg hover:bg-emerald-50 hover:border-emerald-300 transition-all shadow-sm active:scale-95 flex items-center justify-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                     <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Generate
                            </button>
                        </div>

                        <div>
                            <label for="user_password" class="block text-sm font-bold text-gray-700 mb-2">
                                Password <span v-if="!editingUser" class="text-rose-400">*</span>
                                <span v-else class="text-gray-400 font-normal text-xs">(Leave blank to keep current)</span>
                            </label>
                            <div class="relative">
                                <input id="user_password" :type="showPassword ? 'text' : 'password'" v-model="userForm.password" :required="!editingUser"
                                    class="w-full rounded-xl border-gray-200 bg-white py-2.5 pl-3 pr-10 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm"
                                    placeholder="Min 8 characters"
                                />
                                <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-emerald-600 transition-colors">
                                    <svg v-if="!showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                    </svg>
                                </button>
                            </div>
                            <InputError class="mt-2" :message="userForm.errors.password" />
                        </div>

                        <div>
                            <label for="user_password_confirmation" class="block text-sm font-bold text-gray-700 mb-2">Confirm Password</label>
                            <input id="user_password_confirmation" :type="showPassword ? 'text' : 'password'" v-model="userForm.password_confirmation" :required="!editingUser"
                                class="w-full rounded-xl border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Roles</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 p-3 bg-gray-50 border border-gray-100 rounded-xl shadow-sm">
                            <label v-for="role in roles" :key="role.id" class="inline-flex items-center cursor-pointer group">
                                <input type="checkbox" :value="role.name" v-model="userForm.roles"
                                    class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500 w-4 h-4"
                                />
                                <span class="ml-2 text-sm text-gray-700 group-hover:text-emerald-600 transition-colors">{{ role.name }}</span>
                            </label>
                        </div>
                        <InputError class="mt-2" :message="userForm.errors.roles" />
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-5 py-4 bg-gray-50/50 flex flex-col sm:flex-row items-center justify-end gap-3 rounded-b-xl border-t border-gray-100">
                    <button type="button" @click="closeUserModal"
                        class="w-full sm:w-auto px-6 py-2.5 text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors order-2 sm:order-1">
                        Cancel
                    </button>
                    <button type="submit" :disabled="userForm.processing"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm disabled:opacity-50 disabled:cursor-not-allowed order-1 sm:order-2 transition-all active:scale-95">
                        <svg v-if="userForm.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ editingUser ? 'Update User' : 'Create User' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
