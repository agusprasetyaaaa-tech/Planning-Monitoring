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
    teams: Object,
    filters: Object,
    users: Array,
});

const search = ref(props.filters?.search || '');
const sortField = ref(props.filters?.sort || 'created_at');
const sortDirection = ref(props.filters?.direction || 'desc');
const isLoading = ref(false);
const selectedIds = ref([]);
const selectAll = ref(false);

const updateParams = throttle(() => {
    isLoading.value = true;
    router.get(route('teams.index'), { 
        search: search.value, 
        sort: sortField.value,
        direction: sortDirection.value
    }, { 
        preserveState: true, 
        preserveScroll: true,
        replace: true,
        only: ['teams'],
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

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedIds.value = props.teams.data.map(team => team.id);
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

const confirmDelete = (team) => {
    isBulkDelete.value = false;
    itemToDelete.value = team;
    confirmingDeletion.value = true;
};

const confirmBulkDelete = () => {
    isBulkDelete.value = true;
    confirmingDeletion.value = true;
};

const executeDelete = () => {
    if (isBulkDelete.value) {
        router.delete(route('teams.bulk-destroy'), {
            data: { ids: selectedIds.value },
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                selectedIds.value = [];
                selectAll.value = false;
            }
        });
    } else {
        router.delete(route('teams.destroy', itemToDelete.value.id), {
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

// --- TEAM MODAL LOGIC ---
const showTeamModal = ref(false);
const editingTeam = ref(null);
const teamForm = useForm({
    name: '',
    manager_id: '',
});

const openCreateTeamModal = () => {
    editingTeam.value = null;
    teamForm.reset();
    teamForm.clearErrors();
    showTeamModal.value = true;
};

const openEditTeamModal = (team) => {
    editingTeam.value = team;
    teamForm.name = team.name;
    teamForm.manager_id = team.manager_id;
    teamForm.clearErrors();
    showTeamModal.value = true;
};

const closeTeamModal = () => {
    showTeamModal.value = false;
    setTimeout(() => {
        editingTeam.value = null;
        teamForm.reset();
    }, 200);
};

const submitTeam = () => {
    if (editingTeam.value) {
        teamForm.put(route('teams.update', editingTeam.value.id), {
            onSuccess: () => closeTeamModal(),
        });
    } else {
        teamForm.post(route('teams.store'), {
            onSuccess: () => closeTeamModal(),
        });
    }
};

// --- MEMBERS MODAL LOGIC ---
const showMembersModal = ref(false);
const activeTeamForMembers = ref(null);
const memberForm = useForm({
    user_id: '',
});

const openMembersModal = (team) => {
    activeTeamForMembers.value = team;
    memberForm.reset();
    memberForm.clearErrors();
    showMembersModal.value = true;
};

const closeMembersModal = () => {
    showMembersModal.value = false;
    setTimeout(() => {
        activeTeamForMembers.value = null;
        memberForm.reset();
    }, 200);
};

const addMember = () => {
    memberForm.post(route('teams.assign-member', activeTeamForMembers.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            memberForm.reset();
        },
    });
};

const removeMember = (userId) => {
    if (confirm('Are you sure you want to remove this member from the team?')) {
        router.delete(route('teams.remove-member', [activeTeamForMembers.value.id, userId]), {
            preserveScroll: true,
        });
    }
};

const pageNumbers = computed(() => {
    const links = props.teams.links;
    return links.filter(link => !link.label.includes('&laquo;') && !link.label.includes('&raquo;'));
});
</script>

<template>
    <Head title="Team Management" />

    <div class="space-y-4 font-sans">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
             <h2 class="text-[24px] leading-[32px] font-bold text-gray-900">Team Management</h2>
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
                        <input v-model="search" type="text" placeholder="Search teams..." 
                            class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-emerald-600 sm:text-sm sm:leading-6"
                        />
                    </div>
                    
                    <!-- Actions -->
                    <div class="w-full sm:w-auto flex flex-col sm:flex-row justify-end gap-2">
                        <!-- Mobile Bulk Action Toggle -->
                        <button v-if="teams.data.length > 0" @click="toggleSelectAllMobile" class="sm:hidden inline-flex w-full justify-center items-center gap-x-1.5 rounded-md bg-white border border-emerald-600 px-3 py-2 text-sm font-bold text-emerald-700 shadow-sm hover:bg-emerald-50 transition-colors">
                            <svg class="-ml-0.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            {{ selectAll ? 'Deselect All' : 'Select All (Bulk)' }}
                        </button>

                        <button @click="openCreateTeamModal" class="inline-flex w-full sm:w-auto justify-center items-center gap-x-1.5 rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition-colors">
                            <svg class="-ml-0.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
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
                <table class="min-w-full divide-y divide-gray-200" style="table-layout: fixed;">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th scope="col" class="w-16 px-6 py-4">
                                <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                            </th>
                            <th scope="col" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                No
                            </th>
                            <th scope="col" @click="toggleSort('name')" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer group select-none hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-1">
                                    Team
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

                            <th scope="col" @click="toggleSort('manager.name')" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer group select-none hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-1">
                                    Manager
                                    <div class="p-1 rounded-md" :class="{'bg-gray-100': sortField === 'manager.name'}">
                                        <svg v-if="sortField === 'manager.name' && sortDirection === 'asc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" />
                                        </svg>
                                        <svg v-else-if="sortField === 'manager.name' && sortDirection === 'desc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                           <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" style="transform: scaleY(-1); transform-origin: center;" /> 
                                        </svg>
                                        <svg v-else class="h-4 w-4 text-gray-400 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                                        </svg>
                                    </div>
                                </div>
                            </th>
                            
                            <th scope="col" @click="toggleSort('members_count')" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer group select-none hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-1">
                                    Total Members
                                    <div class="p-1 rounded-md" :class="{'bg-gray-100': sortField === 'members_count'}">
                                        <svg v-if="sortField === 'members_count' && sortDirection === 'asc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" />
                                        </svg>
                                        <svg v-else-if="sortField === 'members_count' && sortDirection === 'desc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
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
                        <tr v-for="(team, index) in teams.data" :key="team.id" class="hover:bg-gray-50/60 transition-colors" :class="{'bg-emerald-50/30': selectedIds.includes(team.id)}">
                            <td class="px-6 py-4">
                                <input type="checkbox" :value="team.id" v-model="selectedIds" class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs text-gray-500">{{ (teams.from || 1) + index }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs font-medium text-gray-900">{{ team.name }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs text-gray-500">{{ team.manager?.name || '-' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs text-gray-500">{{ team.members_count }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-xs font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="openMembersModal(team)" class="text-gray-400 hover:text-blue-600 transition-colors" title="Manage Members">
                                       <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                                       </svg>
                                    </button>
                                    <button @click="openEditTeamModal(team)" class="text-gray-400 hover:text-emerald-600 transition-colors" title="Edit">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                    </button>
                                    <button @click="confirmDelete(team)" class="text-gray-400 hover:text-red-600 transition-colors" title="Delete">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="teams.data.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">
                                No teams found matching your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

             <div class="flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-gray-200 px-4 py-3">
                <div class="text-xs text-gray-500 text-center sm:text-left">
                    {{ selectedIds.length ? `${selectedIds.length} row(s) selected.` : `${teams.from || 0}-${teams.to || 0} of ${teams.total || 0} teams` }}
                </div>
                
                <div v-if="teams.links.length > 3" class="w-full sm:w-auto flex justify-center sm:justify-end">
                     <div class="overflow-x-auto max-w-full pb-1 sm:pb-0 scrollbar-hide flex gap-1">
                        <Link v-if="teams.first_page_url" :href="teams.first_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors flex-shrink-0" :class="{'pointer-events-none opacity-50': teams.current_page === 1}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" d="m18.75 4.5-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5" />
                            </svg>
                        </Link>

                        <Link v-if="teams.prev_page_url" :href="teams.prev_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors flex-shrink-0">
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

                        <Link v-if="teams.next_page_url" :href="teams.next_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors flex-shrink-0">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </Link>

                         <Link v-if="teams.last_page_url" :href="teams.last_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors flex-shrink-0" :class="{'pointer-events-none opacity-50': teams.current_page === teams.last_page}">
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
        :title="isBulkDelete ? 'Delete Teams' : 'Delete Team'"
        :content="isBulkDelete ? 'Are you sure you want to delete the selected teams? This action cannot be undone.' : `Are you sure you want to delete the team '${itemToDelete?.name}'? This action cannot be undone.`"
        @close="closeModal"
        @confirm="executeDelete"
    />

    <Modal :show="showTeamModal" @close="closeTeamModal" maxWidth="md">
        <div class="font-sans">
            <!-- Header -->
            <div class="px-4 py-4 sm:px-5 sm:py-4 bg-emerald-600 flex items-center justify-between rounded-t-xl">
                <div class="pr-8 sm:pr-0">
                    <h2 class="text-base sm:text-lg font-bold text-white leading-tight">
                        {{ editingTeam ? 'Edit Team' : 'Create New Team' }}
                    </h2>
                    <p class="text-[9px] sm:text-xs text-emerald-100 flex items-center gap-1">
                        {{ editingTeam ? 'Update team details' : 'Add a new team to the system' }}
                    </p>
                </div>
                <button @click="closeTeamModal" type="button" class="p-2 -mr-2 text-emerald-100 hover:text-white transition-colors rounded-full hover:bg-emerald-500/50">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="submitTeam" class="max-h-[85vh] overflow-y-auto overflow-x-hidden">
                <div class="p-4 sm:p-5 space-y-4 sm:space-y-5">
                    <div>
                        <label for="team_name" class="block text-sm font-bold text-gray-700 mb-2">Team Name <span class="text-rose-400">*</span></label>
                        <input id="team_name" type="text" v-model="teamForm.name" required autofocus
                            class="w-full rounded-xl border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm"
                            placeholder="Enter team name"
                        />
                        <InputError class="mt-2" :message="teamForm.errors.name" />
                    </div>

                    <div>
                        <label for="manager_id" class="block text-sm font-bold text-gray-700 mb-2">Manager <span class="text-rose-400">*</span></label>
                        <select id="manager_id" v-model="teamForm.manager_id" required
                            class="w-full rounded-xl border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm"
                        >
                            <option value="">Select Manager</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                        <InputError class="mt-2" :message="teamForm.errors.manager_id" />
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-5 py-4 bg-gray-50/50 flex flex-col sm:flex-row items-center justify-end gap-3 rounded-b-xl border-t border-gray-100">
                    <button type="button" @click="closeTeamModal"
                        class="w-full sm:w-auto px-6 py-2.5 text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors order-2 sm:order-1">
                        Cancel
                    </button>
                    <button type="submit" :disabled="teamForm.processing"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm disabled:opacity-50 disabled:cursor-not-allowed order-1 sm:order-2 transition-all active:scale-95">
                        <svg v-if="teamForm.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ editingTeam ? 'Update Team' : 'Create Team' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </Modal>

    <!-- Members Form Modal -->
    <Modal :show="showMembersModal" @close="closeMembersModal" maxWidth="md">
        <div class="font-sans">
            <!-- Header -->
            <div class="px-4 py-4 sm:px-5 sm:py-4 bg-emerald-600 flex items-center justify-between rounded-t-xl">
                <div class="pr-8 sm:pr-0">
                    <h2 class="text-base sm:text-lg font-bold text-white leading-tight">
                        Manage Members
                    </h2>
                    <p class="text-[9px] sm:text-xs text-emerald-100 flex items-center gap-1">
                         {{ activeTeamForMembers?.name || 'Team' }}
                    </p>
                </div>
                <button @click="closeMembersModal" type="button" class="p-2 -mr-2 text-emerald-100 hover:text-white transition-colors rounded-full hover:bg-emerald-500/50">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-4 sm:p-5 max-h-[70vh] overflow-y-auto">
                <!-- Add Member Form -->
                <div class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                    <h3 class="text-sm font-bold text-gray-700 mb-3">Add New Member</h3>
                    <form @submit.prevent="addMember" class="flex flex-col sm:flex-row gap-3">
                        <select v-model="memberForm.user_id" required
                            class="flex-1 w-full rounded-xl border-gray-200 bg-white py-2 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm"
                        >
                            <option value="">Select a user</option>
                            <option 
                                v-for="user in users" 
                                :key="user.id" 
                                :value="user.id"
                                :disabled="user.team_id === activeTeamForMembers?.id"
                            >
                                {{ user.name }} {{ user.team_id === activeTeamForMembers?.id ? '(Already in team)' : (user.team_id ? '(In another team)' : '') }}
                            </option>
                        </select>
                        <button type="submit" :disabled="memberForm.processing || !memberForm.user_id"
                            class="w-full sm:w-auto px-4 py-2 bg-emerald-500 hover:bg-emerald-600 disabled:bg-opacity-50 disabled:cursor-not-allowed text-white text-sm font-bold rounded-xl shadow-sm transition-all"
                        >
                            Add
                        </button>
                    </form>
                </div>

                <!-- Current Members List -->
                <div>
                    <h3 class="text-sm font-bold text-gray-700 mb-3">Current Members ({{ activeTeamForMembers?.members?.length || 0 }})</h3>
                    <div v-if="activeTeamForMembers?.members && activeTeamForMembers.members.length > 0" class="space-y-2">
                        <div 
                            v-for="member in activeTeamForMembers.members" 
                            :key="member.id"
                            class="flex items-center justify-between p-3 bg-white border border-gray-100 rounded-xl hover:shadow-sm transition-shadow"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold text-xs">
                                    {{ member.name.charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <p class="font-bold text-sm text-gray-900 leading-tight">{{ member.name }}</p>
                                    <p class="text-[10px] sm:text-xs text-gray-500">{{ member.email }}</p>
                                </div>
                            </div>
                            <button 
                                @click="removeMember(member.id)"
                                class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                :disabled="memberForm.processing"
                                title="Remove Member"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div v-else class="text-center py-6 bg-gray-50 rounded-xl border border-gray-100 text-gray-400 text-sm">
                        No members in this team yet.
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="px-5 py-4 bg-gray-50/50 flex justify-end rounded-b-xl border-t border-gray-100">
                <button type="button" @click="closeMembersModal"
                    class="w-full sm:w-auto px-6 py-2.5 text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </Modal>
</template>
