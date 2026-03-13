<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { Combobox, ComboboxInput, ComboboxButton, ComboboxOptions, ComboboxOption } from '@headlessui/vue';
import Modal from '@/Components/Modal.vue';
import VoiceTextarea from '@/Components/VoiceTextarea.vue';

const props = defineProps({
    show: Boolean,
    customers: Array,
    products: Array,
    selectedCustomer: Object, // Pre-selected customer from table row button
    selectedDate: String,    // Pre-selected date from calendar
    currentSimulatedTime: String,
});

// Helper: Format date for input field (YYYY-MM-DD) strictly in Asia/Jakarta (WIB)
const formatDateForInput = (dateInput) => {
    if (!dateInput) return '';
    try {
        const date = new Date(dateInput);
        return new Intl.DateTimeFormat('en-CA', {
            timeZone: 'Asia/Jakarta',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        }).format(date);
    } catch (e) {
        return '';
    }
};

const getTodayForInput = () => {
    const now = props.currentSimulatedTime ? new Date(props.currentSimulatedTime) : new Date();
    return formatDateForInput(now);
};

const emit = defineEmits(['close']);

const form = useForm({
    planning_date: (props.selectedDate ? formatDateForInput(props.selectedDate) : getTodayForInput()),
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

// Update form when pre-selected props change
watch(() => props.selectedCustomer, (newCustomer) => {
    if (newCustomer) {
        form.customer_id = newCustomer.id;
        selectedCustomerRef.value = newCustomer;
        if (newCustomer.product_id) {
            form.product_id = newCustomer.product_id;
        }
    } else {
        form.customer_id = '';
        selectedCustomerRef.value = null;
    }
}, { immediate: true });

watch(() => props.selectedDate, (newDate) => {
    if (newDate) {
        form.planning_date = formatDateForInput(newDate);
    }
});

// Reset form when modal closes/opens for new plan
watch(() => props.show, (isShow) => {
    if (!isShow) {
        // Optional reset logic
    }
});

const minPlanningDate = computed(() => {
    return getTodayForInput();
});

const maxPlanningDate = computed(() => {
    const d = props.currentSimulatedTime ? new Date(props.currentSimulatedTime) : new Date();
    d.setDate(d.getDate() + 365); // Far future (1 year)
    return formatDateForInput(d);
});

const submit = () => {
    form.post(route('planning.store'), {
        onSuccess: () => {
            form.reset();
            emit('close');
        },
    });
};

const isPreSelected = computed(() => !!props.selectedCustomer);
</script>

<template>
    <Modal :show="show" @close="$emit('close')" maxWidth="2xl">
        <div class="font-sans">
            <!-- Header -->
            <div class="px-4 py-4 sm:px-5 sm:py-4 bg-emerald-600 flex items-center justify-between rounded-t-xl">
                <div class="pr-8 sm:pr-0">
                    <h2 class="text-base sm:text-lg font-bold text-white leading-tight">Add Activity</h2>
                    <p class="text-[9px] sm:text-xs text-emerald-100 flex items-center gap-1">
                        Plan your next business activity
                    </p>
                </div>
                <button @click="$emit('close')" class="p-2 -mr-2 text-emerald-100 hover:text-white transition-colors rounded-full hover:bg-emerald-500/50">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="submit" class="max-h-[85vh] overflow-y-auto overflow-x-hidden">
                <div class="p-4 sm:p-5 space-y-4 sm:space-y-5">
                    <!-- Planning Date -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Planning Date <span class="text-rose-400">*</span></label>
                        <input v-model="form.planning_date" type="date" required
                            :min="minPlanningDate"
                            :max="maxPlanningDate"
                            class="w-full rounded-xl border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm"
                        />
                        
                        <div v-if="form.planning_date && (form.planning_date < minPlanningDate || form.planning_date > maxPlanningDate)" class="text-red-600 text-[10px] mt-2 font-bold bg-red-50 p-2 rounded-lg border border-red-100 flex items-start gap-2">
                            <svg class="w-3.5 h-3.5 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Date must be between current and +2 weeks.</span>
                        </div>
                        <div v-if="form.errors.planning_date" class="text-rose-500 text-xs mt-1">{{ form.errors.planning_date }}</div>
                    </div>

                    <!-- Company Name -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Company Name <span class="text-rose-400">*</span></label>
                        
                        <div v-if="isPreSelected" class="w-full px-3 py-2.5 rounded-xl bg-gray-50 text-gray-900 border border-gray-100 font-semibold text-sm flex items-center justify-between group shadow-sm">
                            <span>{{ selectedCustomer?.company_name }}</span>
                            <span class="text-emerald-600 bg-emerald-100 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wide">Locked</span>
                        </div>

                        <Combobox v-else v-model="selectedCustomerRef" nullable>
                            <div class="relative">
                                <ComboboxInput
                                    class="w-full rounded-xl border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm"
                                    :displayValue="(customer) => customer?.company_name ?? ''"
                                    @change="customerQuery = $event.target.value"
                                    placeholder="Search company..."
                                    required
                                />
                                <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-3">
                                    <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </ComboboxButton>
                                <ComboboxOptions class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-sm shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none">
                                    <div v-if="filteredCustomers.length === 0 && customerQuery !== ''" class="relative cursor-default select-none px-4 py-2 text-gray-700">
                                        No company found.
                                    </div>
                                    <ComboboxOption
                                        v-for="customer in filteredCustomers"
                                        :key="customer.id"
                                        :value="customer"
                                        v-slot="{ selected, active }"
                                    >
                                        <li class="relative cursor-pointer select-none py-2 pl-10 pr-4" :class="{'bg-emerald-600 text-white': active, 'text-gray-900': !active}">
                                            <span class="block truncate font-medium" :class="{ 'font-bold': selected }">
                                                {{ customer.company_name }}
                                            </span>
                                            <span v-if="selected" class="absolute inset-y-0 left-0 flex items-center pl-3" :class="{ 'text-white': active, 'text-emerald-600': !active }">
                                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                        </li>
                                    </ComboboxOption>
                                </ComboboxOptions>
                            </div>
                        </Combobox>
                        <div v-if="form.errors.customer_id" class="text-rose-500 text-xs mt-1">{{ form.errors.customer_id }}</div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Product -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Product</label>
                            <select v-model="form.product_id"
                                class="w-full rounded-xl border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm cursor-pointer"
                            >
                                <option value="">No Product</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}</option>
                            </select>
                            <div v-if="form.errors.product_id" class="text-rose-500 text-xs mt-1">{{ form.errors.product_id }}</div>
                        </div>

                        <!-- Activity Type -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Activity Type <span class="text-rose-400">*</span></label>
                            <select v-model="form.activity_type" required
                                class="w-full rounded-xl border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm cursor-pointer"
                            >
                                <option value="" disabled>Select Activity</option>
                                <option v-for="type in activityTypes" :key="type" :value="type">{{ type }}</option>
                            </select>
                            <div v-if="form.errors.activity_type" class="text-rose-500 text-xs mt-1">{{ form.errors.activity_type }}</div>
                        </div>
                    </div>

                    <!-- Project Name -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Project Name</label>
                        <input v-model="form.project_name" type="text"
                            class="w-full rounded-xl border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 placeholder:text-gray-300 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm"
                            placeholder="Enter project (optional)"
                        />
                        <div v-if="form.errors.project_name" class="text-rose-500 text-xs mt-1">{{ form.errors.project_name }}</div>
                    </div>

                    <!-- Description -->
                    <VoiceTextarea 
                        label="Description" 
                        v-model="form.description" 
                        placeholder="Enter plan details..." 
                        :required="true"
                    />
                    <div v-if="form.errors.description" class="text-rose-500 text-xs mt-1">{{ form.errors.description }}</div>
                </div>

                <!-- Footer -->
                <div class="px-5 py-4 bg-gray-50/50 flex flex-col sm:flex-row items-center justify-end gap-3 rounded-b-xl border-t border-gray-100">
                    <button type="button" @click="$emit('close')"
                        class="w-full sm:w-auto px-6 py-2.5 text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors order-2 sm:order-1">
                        Cancel
                    </button>
                    <button type="submit" :disabled="form.processing"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm disabled:opacity-50 disabled:cursor-not-allowed order-1 sm:order-2 transition-all active:scale-95">
                        <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Add Activity</span>
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
