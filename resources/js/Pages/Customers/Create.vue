<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

defineOptions({ layout: NexusLayout });

const props = defineProps({
    products: Array,
    users: Array,
});

const form = useForm({
    company_name: '',
    product_id: '',
    marketing_sales_id: '',
    planning_start_date: '',
});

const submit = () => {
    form.post(route('customers.store'));
};
</script>

<template>
    <Head title="Create Customer" />

    <div class="max-w-2xl mx-auto bg-white p-8 rounded-3xl shadow-sm border border-gray-100 font-sans">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-[18px] leading-[25px] font-semibold text-[rgb(15,23,42)]">Create New Customer</h2>
            <Link :href="route('customers.index')" class="text-emerald-500 hover:text-emerald-600 font-medium text-sm">
                Back to Customers
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Company Name -->
            <div>
                <label for="company_name" class="block text-[14px] leading-[17px] font-medium text-[rgb(30,41,59)] mb-2">Company Name</label>
                <input id="company_name" type="text" v-model="form.company_name" required autofocus
                    class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm"
                    placeholder="Enter company name"
                />
                <InputError class="mt-2" :message="form.errors.company_name" />
            </div>

            <!-- Product -->
            <div>
                <label for="product_id" class="block text-[14px] leading-[17px] font-medium text-[rgb(30,41,59)] mb-2">Product</label>
                <select id="product_id" v-model="form.product_id" required
                    class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm"
                >
                    <option value="">Select Product</option>
                    <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}</option>
                </select>
                <InputError class="mt-2" :message="form.errors.product_id" />
            </div>

            <!-- Marketing Sales -->
            <div>
                <label for="marketing_sales_id" class="block text-[14px] leading-[17px] font-medium text-[rgb(30,41,59)] mb-2">Marketing Sales</label>
                <select id="marketing_sales_id" v-model="form.marketing_sales_id" required
                    class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm"
                >
                    <option value="">Select User</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                </select>
                <InputError class="mt-2" :message="form.errors.marketing_sales_id" />
            </div>

            <!-- Planning Start Date -->
            <div>
                <label for="planning_start_date" class="block text-[14px] leading-[17px] font-medium text-[rgb(30,41,59)] mb-2">Planning Start Date (Optional)</label>
                <input id="planning_start_date" type="date" v-model="form.planning_start_date"
                    class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm text-gray-700"
                />
                <p class="text-xs text-gray-500 mt-1">If set, the warning countdown will start from this date instead of the date this record was created.</p>
                <InputError class="mt-2" :message="form.errors.planning_start_date" />
            </div>

            <div class="flex items-end justify-end pt-4">
                <button type="submit" :disabled="form.processing"
                    class="px-6 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl shadow-md font-medium transition-all transform hover:scale-105"
                >
                    Create Customer
                </button>
            </div>
        </form>
    </div>
</template>
