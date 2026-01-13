<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { debounce } from 'lodash';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';

defineOptions({ layout: NexusLayout });

const props = defineProps({
    products: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const sortField = ref(props.filters?.sort || 'created_at');
const sortDirection = ref(props.filters?.direction || 'desc');
const selectedIds = ref([]);
const selectAll = ref(false);

const updateParams = debounce(() => {
    router.get(route('products.index'), { 
        search: search.value, 
        sort: sortField.value,
        direction: sortDirection.value
    }, { 
        preserveState: true, 
        preserveScroll: true,
        replace: true,
        only: ['products']
    });
}, 100);

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
        selectedIds.value = props.products.data.map(p => p.id);
    } else {
        selectedIds.value = [];
    }
};

const confirmingDeletion = ref(false);
const itemToDelete = ref(null);
const isBulkDelete = ref(false);

const confirmDelete = (product) => {
    isBulkDelete.value = false;
    itemToDelete.value = product;
    confirmingDeletion.value = true;
};

const confirmBulkDelete = () => {
    isBulkDelete.value = true;
    confirmingDeletion.value = true;
};

const executeDelete = () => {
    if (isBulkDelete.value) {
        router.delete(route('products.bulk-destroy'), {
            data: { ids: selectedIds.value },
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                selectedIds.value = [];
                selectAll.value = false;
            }
        });
    } else {
        router.delete(route('products.destroy', itemToDelete.value.id), {
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

const pageNumbers = computed(() => {
    const links = props.products.links;
    return links.filter(link => !link.label.includes('&laquo;') && !link.label.includes('&raquo;'));
});
</script>

<template>
    <Head title="Product Management" />

    <div class="space-y-4 font-sans">
        <!-- Header & Search -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
             <h2 class="text-[24px] leading-[32px] font-bold text-gray-900">Product Management</h2>
        </div>

        <!-- Table Container -->
        <div class="flow-root rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
            <!-- Toolbar -->
            <!-- Toolbar -->
            <div class="border-b border-gray-200 px-4 py-3 bg-gray-50/50">
                <div class="flex flex-col sm:flex-row gap-4 justify-between items-center">
                    <div class="relative w-full sm:w-72">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input v-model="search" type="text" placeholder="Search products..." 
                            class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-emerald-600 sm:text-sm sm:leading-6 transition-shadow"
                        />
                    </div>

                    <div class="w-full sm:w-auto flex justify-end">
                        <Link :href="route('products.create')" class="inline-flex w-full sm:w-auto justify-center items-center gap-x-1.5 rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition-colors">
                            <svg class="-ml-0.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                            </svg>
                            Create
                        </Link>
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
                            <th scope="col" class="whitespace-nowrap px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Customers Used
                            </th>
                            <th scope="col" class="relative whitespace-nowrap px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="(product, index) in products.data" :key="product.id" class="hover:bg-gray-50/60 transition-colors" :class="{'bg-emerald-50/30': selectedIds.includes(product.id)}">
                            <td class="px-6 py-4">
                                <input type="checkbox" :value="product.id" v-model="selectedIds" class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs text-gray-500">{{ (products.from || 1) + index }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs font-medium">
                                <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                    {{ product.name }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs text-gray-500">{{ product.customers_count }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-xs font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="route('products.edit', product.id)" class="text-gray-400 hover:text-emerald-600 transition-colors" title="Edit">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                    </Link>
                                    <button @click="confirmDelete(product)" class="text-gray-400 hover:text-red-600 transition-colors" title="Delete">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="products.data.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">
                                No products found matching your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

             <div class="flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-gray-200 px-4 py-3">
                <div class="text-xs text-gray-500 text-center sm:text-left">
                    {{ selectedIds.length ? `${selectedIds.length} row(s) selected.` : `${products.from || 0}-${products.to || 0} of ${products.total || 0} products` }}
                </div>
                
                <div v-if="products.links.length > 3" class="w-full sm:w-auto flex justify-center sm:justify-end">
                    <div class="overflow-x-auto max-w-full pb-1 sm:pb-0 scrollbar-hide flex gap-1">
                        <Link v-if="products.first_page_url" :href="products.first_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors flex-shrink-0" :class="{'pointer-events-none opacity-50': products.current_page === 1}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m18.75 4.5-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5" />
                            </svg>
                        </Link>

                        <Link v-if="products.prev_page_url" :href="products.prev_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors flex-shrink-0">
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

                        <Link v-if="products.next_page_url" :href="products.next_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors flex-shrink-0">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </Link>

                        <Link v-if="products.last_page_url" :href="products.last_page_url" class="p-1 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-gray-50 transition-colors flex-shrink-0" :class="{'pointer-events-none opacity-50': products.current_page === products.last_page}">
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
        :title="isBulkDelete ? 'Delete Products' : 'Delete Product'"
        :content="isBulkDelete ? 'Are you sure you want to delete the selected products? This action cannot be undone.' : `Are you sure you want to delete the product '${itemToDelete?.name}'? This action cannot be undone.`"
        @close="closeModal"
        @confirm="executeDelete"
    />
</template>
