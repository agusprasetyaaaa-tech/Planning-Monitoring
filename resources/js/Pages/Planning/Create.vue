<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { watch, ref, computed, onMounted } from 'vue';
import { Combobox, ComboboxInput, ComboboxButton, ComboboxOptions, ComboboxOption } from '@headlessui/vue';

defineOptions({ layout: NexusLayout });

const props = defineProps({
    customers: Array,
    products: Array,
    selectedCustomer: Object, // Pre-selected customer from table row button
});

// Check if customer is pre-selected (from table row Create Plan button)
const isPreSelected = computed(() => !!props.selectedCustomer);

const form = useForm({
    planning_date: new Date().toISOString().split('T')[0],
    activity_type: '',
    customer_id: props.selectedCustomer?.id || '',
    project_name: '',
    product_id: props.selectedCustomer?.product_id || '',
    description: '',
});

const activityTypes = [
    'Call', 'Visit', 'Ent', 'Online Meeting', 'Survey', 
    'Presentation', 'Proposal', 'Negotiation', 'Admin/Tender', 'Other'
];

// Customer search functionality
const customerQuery = ref('');
const selectedCustomerRef = ref(props.selectedCustomer || null);

const filteredCustomers = computed(() => {
    if (customerQuery.value === '') {
        return props.customers;
    }
    return props.customers.filter((customer) =>
        customer.company_name.toLowerCase().includes(customerQuery.value.toLowerCase())
    );
});

// Watch for customer selection changes (only if not pre-selected)
watch(selectedCustomerRef, (newCustomer) => {
    if (newCustomer) {
        form.customer_id = newCustomer.id;
        if (newCustomer.product_id) {
            form.product_id = newCustomer.product_id;
        } else {
            form.product_id = '';
        }
    } else {
        form.customer_id = '';
        form.product_id = '';
    }
});

// Get selected product name
const selectedProductName = computed(() => {
    // If pre-selected, use props.selectedCustomer
    if (isPreSelected.value && props.selectedCustomer?.product) {
        return props.selectedCustomer.product.name;
    }
    if (selectedCustomerRef.value && selectedCustomerRef.value.product) {
        return selectedCustomerRef.value.product.name;
    }
    if (form.product_id) {
        const product = props.products.find(p => p.id === form.product_id);
        return product ? product.name : '';
    }
    return '';
});



const minPlanningDate = computed(() => {
    return new Date().toISOString().split('T')[0];
});

const maxPlanningDate = computed(() => {
    const d = new Date();
    d.setDate(d.getDate() + 14);
    return d.toISOString().split('T')[0];
});

const submit = () => {
    form.post(route('planning.store'));
};
</script>

<template>
    <Head title="Create Plan" />

    <div class="max-w-2xl mx-auto bg-white p-8 rounded-3xl shadow-sm border border-gray-100 font-sans">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-3">
                <div class="bg-emerald-100 p-2 rounded-xl">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900">Create New Plan</h2>
            </div>
            <Link :href="route('planning.index')" class="group flex items-center gap-1.5 text-gray-500 hover:text-gray-900 transition-colors text-sm font-medium px-3 py-2 rounded-lg hover:bg-gray-50">
                <svg class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Planning Date -->
            <div>
                 <label class="block text-sm font-bold text-gray-700 mb-2">Planning Date</label>
                 <input v-model="form.planning_date" type="date" required
                    :min="minPlanningDate"
                    :max="maxPlanningDate"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm shadow-sm"
                 />
                 
                 <div v-if="form.planning_date && (form.planning_date < minPlanningDate || form.planning_date > maxPlanningDate)" class="text-red-600 text-[11px] mt-2 font-bold bg-red-50 p-2.5 rounded-lg border border-red-200 flex items-start gap-2 shadow-sm">
                    <svg class="w-4 h-4 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="leading-tight">Date must be between {{ minPlanningDate }} and {{ maxPlanningDate }} (Max 2 weeks).</span>
                 </div>

                 <div v-if="form.errors.planning_date" class="text-red-500 text-xs mt-1 font-medium">{{ form.errors.planning_date }}</div>
            </div>

            <!-- Activity Type -->
            <div>
                 <label class="block text-sm font-bold text-gray-700 mb-2">Activity Type</label>
                 <select v-model="form.activity_type" required
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm bg-white shadow-sm cursor-pointer"
                 >
                    <option value="" disabled>Select Activity</option>
                    <option v-for="type in activityTypes" :key="type" :value="type">{{ type }}</option>
                 </select>
                 <div v-if="form.errors.activity_type" class="text-red-500 text-xs mt-1 font-medium">{{ form.errors.activity_type }}</div>
            </div>

            <!-- Company Name (Customer) -->
            <div>
                 <label class="block text-sm font-bold text-gray-700 mb-2">Company Name</label>
                 
                 <!-- Pre-selected: Show read-only static card -->
                 <div v-if="isPreSelected">
                    <div class="w-full px-4 py-3 rounded-xl bg-gray-50 text-gray-900 border-0 font-semibold text-sm flex items-center justify-between group">
                        <span>{{ selectedCustomer?.company_name }}</span>
                        <span class="text-emerald-600 bg-emerald-100 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide group-hover:bg-emerald-200 transition-colors">Locked</span>
                    </div>
                 </div>
                 
                 <!-- Not pre-selected: Show searchable combobox -->
                 <Combobox v-else v-model="selectedCustomerRef" nullable>
                    <div class="relative">
                        <ComboboxInput
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm bg-white shadow-sm"
                            :displayValue="(customer) => customer?.company_name ?? ''"
                            @change="customerQuery = $event.target.value"
                            placeholder="Search company name..."
                        />
                        <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-4">
                            <svg class="h-5 w-5 text-gray-400 group-hover:text-emerald-500 transition-colors" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" clip-rule="evenodd" />
                            </svg>
                        </ComboboxButton>
                        <ComboboxOptions class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-sm shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                            <div v-if="filteredCustomers.length === 0 && customerQuery !== ''" class="relative cursor-default select-none px-4 py-2 text-gray-700">
                                No company found.
                            </div>
                            <ComboboxOption
                                v-for="customer in filteredCustomers"
                                :key="customer.id"
                                :value="customer"
                                v-slot="{ selected, active }"
                            >
                                <li
                                    class="relative cursor-pointer select-none py-2 pl-10 pr-4"
                                    :class="{
                                        'bg-emerald-500 text-white': active,
                                        'text-gray-900': !active,
                                    }"
                                >
                                    <span class="block truncate" :class="{ 'font-medium': selected, 'font-normal': !selected }">
                                        {{ customer.company_name }}
                                    </span>
                                    <span
                                        v-if="selected"
                                        class="absolute inset-y-0 left-0 flex items-center pl-3"
                                        :class="{ 'text-white': active, 'text-emerald-600': !active }"
                                    >
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </li>
                            </ComboboxOption>
                        </ComboboxOptions>
                    </div>
                 </Combobox>
                 <div v-if="form.errors.customer_id" class="text-red-500 text-xs mt-1">{{ form.errors.customer_id }}</div>
            </div>

            <!-- Project -->
            <div>
                 <label class="block text-sm font-bold text-gray-700 mb-2">Project</label>
                 <input v-model="form.project_name" type="text"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm shadow-sm placeholder:text-gray-400"
                    placeholder="Enter project name (optional)"
                 />
                 <div v-if="form.errors.project_name" class="text-red-500 text-xs mt-1 font-medium">{{ form.errors.project_name }}</div>
            </div>

            <!-- Product (Editable - syncs with Customer) -->
            <div>
                 <label class="block text-sm font-bold text-gray-700 mb-2">Product</label>
                 <select v-model="form.product_id"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm bg-white shadow-sm cursor-pointer"
                 >
                    <option value="">No Product</option>
                    <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}</option>
                 </select>
                 <p v-if="form.product_id" class="text-[10px] text-emerald-500 mt-1 italic">* Product change here will also update the customer profile.</p>
                 <div v-if="form.errors.product_id" class="text-red-500 text-xs mt-1 font-medium">{{ form.errors.product_id }}</div>
            </div>

            <!-- Description -->
            <div>
                 <label class="block text-sm font-bold text-gray-700 mb-2">Description Planning</label>
                 <textarea v-model="form.description" required rows="4"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm resize-none shadow-sm placeholder:text-gray-400"
                    placeholder="Enter planning description"
                 ></textarea>
                 <div v-if="form.errors.description" class="text-red-500 text-xs mt-1 font-medium">{{ form.errors.description }}</div>
            </div>

            <div class="flex items-center justify-end pt-4 border-t border-gray-50 mt-6">
                <button type="submit" :disabled="form.processing"
                    class="px-8 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white rounded-xl shadow-lg hover:shadow-xl font-bold transition-all transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none disabled:shadow-none flex items-center gap-2"
                >
                    <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Create Plan</span>
                    <svg v-if="!form.processing" class="w-4 h-4 text-white/90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </button>
            </div>
        </form>
    </div>
</template>
