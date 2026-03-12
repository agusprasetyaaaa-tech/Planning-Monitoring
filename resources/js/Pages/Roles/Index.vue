<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineOptions({ layout: NexusLayout });

const props = defineProps({
    roles: Object,
    permissions: Array,
});

const selectedIds = ref([]);
const selectAll = ref(false);

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedIds.value = props.roles.data.map(role => role.id);
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

const confirmDelete = (role) => {
    isBulkDelete.value = false;
    itemToDelete.value = role;
    confirmingDeletion.value = true;
};

const confirmBulkDelete = () => {
    isBulkDelete.value = true;
    confirmingDeletion.value = true;
};

const executeDelete = () => {
    if (isBulkDelete.value) {
        router.delete(route('roles.bulk-destroy'), {
            data: { ids: selectedIds.value },
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                selectedIds.value = [];
                selectAll.value = false;
            }
        });
    } else {
        router.delete(route('roles.destroy', itemToDelete.value.id), {
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

// --- ROLE MODAL LOGIC ---
const showRoleModal = ref(false);
const editingRole = ref(null);
const roleForm = useForm({
    name: '',
    permissions: [],
});

const openCreateRoleModal = () => {
    editingRole.value = null;
    roleForm.reset();
    roleForm.clearErrors();
    showRoleModal.value = true;
};

const openEditRoleModal = (role) => {
    editingRole.value = role;
    roleForm.name = role.name;
    roleForm.permissions = role.permissions ? role.permissions.map(p => p.id) : [];
    roleForm.clearErrors();
    showRoleModal.value = true;
};

const closeRoleModal = () => {
    showRoleModal.value = false;
    setTimeout(() => {
        editingRole.value = null;
        roleForm.reset();
    }, 200);
};

const submitRole = () => {
    if (editingRole.value) {
        roleForm.put(route('roles.update', editingRole.value.id), {
            onSuccess: () => closeRoleModal(),
        });
    } else {
        roleForm.post(route('roles.store'), {
            onSuccess: () => closeRoleModal(),
        });
    }
};

const pageNumbers = computed(() => {
    const links = props.roles.links;
    return links.filter(link => !link.label.includes('&laquo;') && !link.label.includes('&raquo;'));
});
</script>

<template>
    <Head title="Role Management" />

    <div class="space-y-4 font-sans">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
             <h2 class="text-[24px] leading-[32px] font-bold text-gray-900">Role Management</h2>
        </div>

        <!-- Table Container -->
        <div class="flow-root rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
            <!-- Toolbar -->
            <div class="border-b border-gray-200 px-4 py-3 bg-gray-50/50">
                <div class="flex flex-col sm:flex-row gap-4 justify-between items-center">
                    <div class="relative w-full sm:w-72">
                         <!-- Placeholder to match other tables layout -->
                    </div>
                    
                    <!-- Actions -->
                    <div class="w-full sm:w-auto flex flex-col sm:flex-row justify-end gap-2">
                        <!-- Mobile Bulk Action Toggle -->
                        <button v-if="roles.data.length > 0" @click="toggleSelectAllMobile" class="sm:hidden inline-flex w-full justify-center items-center gap-x-1.5 rounded-md bg-white border border-emerald-600 px-3 py-2 text-sm font-bold text-emerald-700 shadow-sm hover:bg-emerald-50 transition-colors">
                            <svg class="-ml-0.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            {{ selectAll ? 'Deselect All' : 'Select All (Bulk)' }}
                        </button>

                        <button @click="openCreateRoleModal" class="inline-flex w-full sm:w-auto justify-center items-center gap-x-1.5 rounded-md bg-emerald-600 px-3 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-500 transition-colors">
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
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th scope="col" class="w-16 px-6 py-4">
                                <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                            </th>
                            <th scope="col" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                No
                            </th>
                            <th scope="col" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                            <th scope="col" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Permissions</th>
                            <th scope="col" class="relative whitespace-nowrap px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="(role, index) in roles.data" :key="role.id" class="hover:bg-gray-50/60 transition-colors" :class="{'bg-emerald-50/30': selectedIds.includes(role.id)}">
                            <td class="px-6 py-4">
                                <input type="checkbox" :value="role.id" v-model="selectedIds" class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs text-gray-500">{{ (roles.from || 1) + index }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs font-medium text-gray-900">{{ role.name }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs text-gray-500">
                                 <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">
                                    {{ role.permissions.length }} permissions
                                 </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-xs font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="openEditRoleModal(role)" class="text-gray-400 hover:text-emerald-600 transition-colors" title="Edit">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                    </button>
                                    <button @click="confirmDelete(role)" class="text-gray-400 hover:text-red-600 transition-colors" title="Delete">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="roles.data.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">
                                No roles found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

             <div class="flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-gray-200 px-4 py-3">
                <div class="text-xs text-gray-500 text-center sm:text-left">
                    {{ selectedIds.length ? `${selectedIds.length} row(s) selected.` : `${roles.from || 0}-${roles.to || 0} of ${roles.total || 0} roles` }}
                </div>
                
                <div v-if="roles.links.length > 3" class="w-full sm:w-auto flex justify-center sm:justify-end">
                    <div class="overflow-x-auto max-w-full pb-1 sm:pb-0 scrollbar-hide flex gap-1">
                        <Link v-if="roles.first_page_url" :href="roles.first_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors flex-shrink-0" :class="{'pointer-events-none opacity-50': roles.current_page === 1}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m18.75 4.5-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5" />
                        </svg>
                    </Link>

                    <Link v-if="roles.prev_page_url" :href="roles.prev_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors flex-shrink-0">
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

                    <Link v-if="roles.next_page_url" :href="roles.next_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors flex-shrink-0">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </Link>

                    <Link v-if="roles.last_page_url" :href="roles.last_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors flex-shrink-0" :class="{'pointer-events-none opacity-50': roles.current_page === roles.last_page}">
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
        :title="isBulkDelete ? 'Delete Roles' : 'Delete Role'"
        :content="isBulkDelete ? 'Are you sure you want to delete the selected roles? This action cannot be undone.' : `Are you sure you want to delete the role '${itemToDelete?.name}'? This action cannot be undone.`"
        @close="closeModal"
        @confirm="executeDelete"
    />

    <Modal :show="showRoleModal" @close="closeRoleModal" maxWidth="lg">
        <div class="font-sans">
            <!-- Header -->
            <div class="px-4 py-4 sm:px-5 sm:py-4 bg-emerald-600 flex items-center justify-between rounded-t-xl">
                <div class="pr-8 sm:pr-0">
                    <h2 class="text-base sm:text-lg font-bold text-white leading-tight">
                        {{ editingRole ? 'Edit Role' : 'Create New Role' }}
                    </h2>
                    <p class="text-[9px] sm:text-xs text-emerald-100 flex items-center gap-1">
                        {{ editingRole ? 'Update role details and permissions' : 'Add a new role to the system' }}
                    </p>
                </div>
                <button @click="closeRoleModal" type="button" class="p-2 -mr-2 text-emerald-100 hover:text-white transition-colors rounded-full hover:bg-emerald-500/50">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="submitRole" class="max-h-[85vh] overflow-y-auto overflow-x-hidden">
                <div class="p-4 sm:p-5 space-y-4 sm:space-y-5">
                    <div>
                        <label for="role_name" class="block text-sm font-bold text-gray-700 mb-2">Role Name <span class="text-rose-400">*</span></label>
                        <input id="role_name" type="text" v-model="roleForm.name" required autofocus
                            class="w-full rounded-xl border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm"
                            placeholder="Enter role name"
                        />
                        <InputError class="mt-2" :message="roleForm.errors.name" />
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Permissions</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 p-3 bg-gray-50 border border-gray-100 rounded-xl shadow-sm">
                            <label v-for="permission in permissions" :key="permission.id" class="inline-flex items-center cursor-pointer group">
                                <input type="checkbox" :value="permission.id" v-model="roleForm.permissions"
                                    class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500 w-4 h-4"
                                />
                                <span class="ml-2 text-sm text-gray-700 group-hover:text-emerald-600 transition-colors">{{ permission.name }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-5 py-4 bg-gray-50/50 flex flex-col sm:flex-row items-center justify-end gap-3 rounded-b-xl border-t border-gray-100">
                    <button type="button" @click="closeRoleModal"
                        class="w-full sm:w-auto px-6 py-2.5 text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors order-2 sm:order-1">
                        Cancel
                    </button>
                    <button type="submit" :disabled="roleForm.processing"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm disabled:opacity-50 disabled:cursor-not-allowed order-1 sm:order-2 transition-all active:scale-95">
                        <svg v-if="roleForm.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ editingRole ? 'Update Role' : 'Create Role' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
