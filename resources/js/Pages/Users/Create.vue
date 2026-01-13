<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

defineOptions({ layout: NexusLayout });

defineProps({
    roles: Array,
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [],
});

const submit = () => {
    form.post(route('users.store'));
};
</script>

<template>
    <Head title="Create User" />

    <div class="max-w-2xl mx-auto bg-white p-8 rounded-3xl shadow-sm border border-gray-100 font-sans">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-[18px] leading-[25px] font-semibold text-[rgb(15,23,42)]">Create New User</h2>
            <Link :href="route('users.index')" class="text-emerald-500 hover:text-emerald-600 font-medium text-sm">
                Back to Users
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Name -->
            <div>
                <label class="block text-[14px] leading-[17px] font-medium text-[rgb(30,41,59)] mb-2">Name</label>
                <input v-model="form.name" type="text" required autofocus
                    class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm"
                    placeholder="Enter user name"
                />
                <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
            </div>

            <!-- Email -->
            <div>
                <label class="block text-[14px] leading-[17px] font-medium text-[rgb(30,41,59)] mb-2">Email</label>
                <input v-model="form.email" type="email" required
                    class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm"
                    placeholder="Enter email address"
                />
                <div v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</div>
            </div>

            <div class="grid grid-cols-2 gap-6">
                <!-- Password -->
                <div>
                     <label class="block text-[14px] leading-[17px] font-medium text-[rgb(30,41,59)] mb-2">Password</label>
                     <input v-model="form.password" type="password" required
                        class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm"
                        placeholder="Enter password"
                     />
                     <div v-if="form.errors.password" class="text-red-500 text-xs mt-1">{{ form.errors.password }}</div>
                </div>
                <!-- Confirm Password -->
                <div>
                     <label class="block text-[14px] leading-[17px] font-medium text-[rgb(30,41,59)] mb-2">Confirm Password</label>
                     <input v-model="form.password_confirmation" type="password" required
                        class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm"
                        placeholder="Confirm password"
                     />
                </div>
            </div>

            <!-- Roles -->
            <div>
                <label class="block text-[14px] leading-[17px] font-medium text-[rgb(30,41,59)] mb-2">Roles</label>
                <div class="space-y-2 bg-gray-50 p-4 rounded-xl border border-gray-100">
                    <div v-for="role in roles" :key="role.id" class="flex items-center">
                        <input :value="role.name" v-model="form.roles" type="checkbox"
                            class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 rounded" />
                        <label class="ml-2 text-sm text-gray-700">{{ role.name }}</label>
                    </div>
                </div>
            </div>

            <div class="flex items-end justify-end pt-4">
                <button type="submit" :disabled="form.processing"
                    class="px-6 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl shadow-md font-medium transition-all transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Create User
                </button>
            </div>
        </form>
    </div>
</template>
