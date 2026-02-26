<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { Combobox, ComboboxInput, ComboboxButton, ComboboxOptions, ComboboxOption } from '@headlessui/vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    show: Boolean,
    report: Object,
    customers: Array,
    products: Array,
    users: Array,
    auth: Object,
});

const emit = defineEmits(['close']);

const isSuperAdmin = props.auth.user.roles.includes('Super Admin');

const form = useForm({
    user_id: props.report?.user_id || props.auth.user.id,
    customer_id: props.report?.customer_id || '',
    product_id: props.report?.product_id || '',
    report_date: props.report?.report_date || new Date().toISOString().substr(0, 10),
    activity_type: props.report?.activity_type || '',
    description: props.report?.description || '',
    location: props.report?.location || '',
    pic: props.report?.pic || '',
    position: props.report?.position || '',
    result_description: props.report?.result_description || '',
    progress: props.report?.progress || '',
    is_success: props.report ? !!props.report.is_success : true,
    next_plan: props.report?.next_plan || '',
});

// Update form when report prop changes
watch(() => props.report, (newReport) => {
    if (newReport) {
        form.user_id = newReport.user_id;
        form.customer_id = newReport.customer_id;
        form.product_id = newReport.product_id;
        form.report_date = newReport.report_date;
        form.activity_type = newReport.activity_type;
        form.description = newReport.description;
        form.location = newReport.location;
        form.pic = newReport.pic;
        form.position = newReport.position;
        form.result_description = newReport.result_description;
        form.progress = newReport.progress;
        form.is_success = !!newReport.is_success;
        form.next_plan = newReport.next_plan || '';
        
        selectedCustomer.value = props.customers.find(c => c.id === newReport.customer_id) || null;
        selectedProduct.value = props.products.find(p => p.id === newReport.product_id) || null;
    } else {
        form.reset();
        form.user_id = props.auth.user.id;
        form.report_date = new Date().toISOString().substr(0, 10);
        form.is_success = true;
        selectedCustomer.value = null;
        selectedProduct.value = null;
    }
}, { immediate: true });

const activityTypes = [
    'Call', 'Visit', 'Ent', 'Online Meeting', 'Email', 'Survey', 
    'Presentation', 'Proposal', 'Negotiation', 'Admin/Tender', 'Closing', 'Other'
];

const progressOptions = [
    '10%-Introduction', '20%-Visit', '30%-Presentation', '40%-Survey', 
    '50%-Proposal', '75%-Confirm Budget', '90%-Tender/Nego', '100%-Closing'
];

// Customer Search Logic
const customerQuery = ref('');
const selectedCustomer = ref(null);

const filteredCustomers = computed(() => {
    if (customerQuery.value === '') return props.customers;
    return props.customers.filter((customer) =>
        customer.company_name.toLowerCase().includes(customerQuery.value.toLowerCase())
    );
});

watch(selectedCustomer, (newCustomer) => {
    if (newCustomer) {
        form.customer_id = newCustomer.id;
        // Always set the product if the customer has one, to match Planning behavior
        if (newCustomer.product_id) {
            form.product_id = newCustomer.product_id;
            selectedProduct.value = props.products.find(p => p.id === newCustomer.product_id) || null;
        }
    } else {
        form.customer_id = '';
    }
});

// Product Search Logic
const productQuery = ref('');
const selectedProduct = ref(null);

const filteredProducts = computed(() => {
    const baseProducts = [
        { id: '', name: 'No Product' },
        ...props.products
    ];
    if (productQuery.value === '') return baseProducts;
    return baseProducts.filter((product) =>
        product.name.toLowerCase().includes(productQuery.value.toLowerCase())
    );
});

watch(selectedProduct, (newProduct) => {
    form.product_id = newProduct?.id || '';
});

const submit = () => {
    if (props.report) {
        form.put(route('daily-report.update', props.report.id), {
            onSuccess: () => emit('close'),
        });
    } else {
        form.post(route('daily-report.store'), {
            onSuccess: () => emit('close'),
        });
    }
};
</script>

<template>
    <Modal :show="show" @close="$emit('close')" maxWidth="2xl">
        <div class="font-sans">
            <!-- Header (Identical Icon & Text Style) -->
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                    <div>
                        <h1 class="text-sm sm:text-lg font-bold text-gray-900 leading-tight">{{ report ? 'Edit' : 'Create' }} Daily Activity</h1>
                        <p class="text-[10px] sm:text-xs text-gray-400">Record sales activity for today</p>
                    </div>
                </div>
                <button @click="$emit('close')" class="p-2 -mr-2 text-gray-400 hover:text-gray-600 transition-colors rounded-full hover:bg-gray-100">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <form @submit.prevent="submit" class="max-h-[75vh] overflow-y-auto">
                <!-- Section: Salesperson (Identical to Create.vue) -->
                <div class="px-4 sm:px-5 py-3 sm:py-4 border-b border-gray-100">
                    <label class="block text-[10px] sm:text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Report For <span class="text-rose-400">*</span></label>

                    <!-- Super Admin: Dropdown -->
                    <select v-if="isSuperAdmin" v-model="form.user_id" required
                        class="w-full rounded-lg border-gray-200 bg-gray-50/50 py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors">
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} ({{ user.email }})</option>
                    </select>

                    <!-- Regular User: Display -->
                    <div v-else class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-gray-50 border border-gray-100">
                        <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 text-xs font-semibold flex-shrink-0">
                            {{ auth.user.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs sm:text-sm font-bold text-gray-800 truncate">{{ auth.user.name }}</p>
                            <p class="text-[10px] sm:text-xs text-gray-400 truncate">{{ auth.user.email }}</p>
                        </div>
                    </div>
                    <p v-if="isSuperAdmin" class="text-[10px] text-gray-400 mt-1.5">Recording activity on behalf of salesperson.</p>
                </div>

                <!-- Section: Customer & Product (Identical to Create.vue) -->
                <div class="px-4 sm:px-5 py-3 sm:py-4 border-b border-gray-100 space-y-4">
                    <div class="text-[10px] sm:text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Customer & Product</div>

                    <!-- Customer -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Company Name <span class="text-rose-400">*</span></label>
                        <Combobox v-model="selectedCustomer" nullable>
                            <div class="relative">
                                <ComboboxInput
                                    class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors"
                                    :displayValue="(customer) => customer?.company_name ?? ''"
                                    @change="customerQuery = $event.target.value"
                                    placeholder=""
                                    required
                                />
                                <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-3">
                                    <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </ComboboxButton>
                                <ComboboxOptions class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg bg-white py-1 text-sm shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none">
                                    <div v-if="filteredCustomers.length === 0 && customerQuery !== ''" class="relative cursor-default select-none px-4 py-2 text-gray-700">
                                        No customer found.
                                    </div>
                                    <ComboboxOption
                                        v-for="customer in filteredCustomers"
                                        :key="customer.id"
                                        :value="customer"
                                        v-slot="{ selected, active }"
                                    >
                                        <li class="relative cursor-pointer select-none py-2 pl-10 pr-4" :class="{'bg-emerald-600 text-white': active, 'text-gray-900': !active}">
                                            <span class="block truncate" :class="{ 'font-semibold': selected, 'font-normal': !selected }">
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

                    <!-- Product & Date -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Product</label>
                            <Combobox v-model="selectedProduct" nullable>
                                <div class="relative">
                                    <ComboboxInput
                                        class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors"
                                        :displayValue="(product) => product?.name ?? ''"
                                        @change="productQuery = $event.target.value"
                                        placeholder=""
                                    />
                                    <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-3">
                                        <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" clip-rule="evenodd" />
                                        </svg>
                                    </ComboboxButton>
                                    <ComboboxOptions class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg bg-white py-1 text-sm shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none">
                                        <div v-if="filteredProducts.length === 0 && productQuery !== ''" class="relative cursor-default select-none px-4 py-2 text-gray-700">
                                            No product found.
                                        </div>
                                        <ComboboxOption
                                            v-for="product in filteredProducts"
                                            :key="product.id"
                                            :value="product"
                                            v-slot="{ selected, active }"
                                        >
                                            <li class="relative cursor-pointer select-none py-2 pl-10 pr-4" :class="{'bg-emerald-600 text-white': active, 'text-gray-900': !active}">
                                                <span class="block truncate" :class="{ 'font-semibold': selected, 'font-normal': !selected }">
                                                    {{ product.name }}
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
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Report Date <span class="text-rose-400">*</span></label>
                            <input v-model="form.report_date" type="date" required
                                class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors" />
                        </div>
                    </div>
                </div>

                <!-- Section: Activity Details (Identical to Create.vue) -->
                <div class="px-4 sm:px-5 py-3 sm:py-4 border-b border-gray-100 space-y-4">
                    <div class="text-[10px] sm:text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Activity Details</div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Activity Type <span class="text-rose-400">*</span></label>
                            <select v-model="form.activity_type" required
                                class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors">
                                <option value="" disabled>Select Activity</option>
                                <option v-for="type in activityTypes" :key="type" :value="type">{{ type }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Location <span class="text-rose-400">*</span></label>
                            <input v-model="form.location" type="text" required
                                class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 placeholder:text-gray-300 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Customer PIC <span class="text-rose-400">*</span></label>
                            <input v-model="form.pic" type="text" required
                                class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 placeholder:text-gray-300 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">PIC Position <span class="text-rose-400">*</span></label>
                            <input v-model="form.position" type="text" required
                                class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 placeholder:text-gray-300 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Activity Description <span class="text-rose-400">*</span></label>
                        <textarea v-model="form.description" required rows="3"
                            class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 placeholder:text-gray-300 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 resize-none transition-colors"
                        ></textarea>
                    </div>
                </div>

                <!-- Section: Results & Outcome (Identical to Create.vue) -->
                <div class="px-4 sm:px-5 py-3 sm:py-4 border-b border-gray-100 space-y-4">
                    <div class="text-[10px] sm:text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Results & Outcome</div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Result Description <span class="text-rose-400">*</span></label>
                        <textarea v-model="form.result_description" required rows="3"
                            class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 placeholder:text-gray-300 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 resize-none transition-colors"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Next Plan</label>
                        <textarea v-model="form.next_plan" rows="2"
                            class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 placeholder:text-gray-300 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 resize-none transition-colors"
                        ></textarea>
                    </div>

                    <!-- Progress -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Progress <span class="text-rose-400">*</span></label>
                        <select v-model="form.progress" required
                            class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors">
                            <option value="" disabled>Select Progress</option>
                            <option v-for="p in progressOptions" :key="p" :value="p">{{ p }}</option>
                        </select>
                    </div>

                    <!-- Goal Achievement Toggle -->
                    <div class="rounded-lg border p-3.5 transition-colors duration-200"
                         :class="form.is_success ? 'border-emerald-200 bg-emerald-50/50' : 'border-rose-200 bg-rose-50/50'">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-sm font-medium" :class="form.is_success ? 'text-emerald-800' : 'text-rose-800'">
                                    Goal Achievement
                                </span>
                                <p class="text-[10px] mt-0.5" :class="form.is_success ? 'text-emerald-500' : 'text-rose-500'">
                                    {{ form.is_success ? 'Activity goal was achieved' : 'Activity goal was not achieved' }}
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="form.is_success" class="sr-only peer">
                                <div class="w-11 h-6 rounded-full transition-colors duration-200 peer-focus:ring-2 peer-focus:ring-offset-1"
                                     :class="form.is_success ? 'bg-emerald-500 peer-focus:ring-emerald-300' : 'bg-rose-400 peer-focus:ring-rose-300'">
                                </div>
                                <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform duration-200"
                                     :class="form.is_success ? 'translate-x-5' : 'translate-x-0'">
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Submit Footer (Identical to Create.vue) -->
                <div class="px-4 sm:px-5 py-3 sm:py-4 bg-gray-50/50 flex flex-col sm:flex-row items-center justify-end gap-2 sm:gap-3 rounded-b-lg">
                    <button type="button" @click="$emit('close')"
                        class="w-full sm:w-auto px-4 py-2.5 text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors order-2 sm:order-1">
                        Cancel
                    </button>
                    <button type="submit" :disabled="form.processing"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-lg shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed order-1 sm:order-2">
                        <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ report ? 'Update' : 'Submit' }} Report
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
