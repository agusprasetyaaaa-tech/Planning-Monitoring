<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

defineOptions({ layout: NexusLayout });

defineProps({
    permissions: Array,
});

const form = useForm({
    name: '',
    permissions: [],
});

const submit = () => {
    form.post(route('roles.store'));
};
</script>

<template>
    <Head title="Create Role" />

    <div class="max-w-2xl mx-auto bg-white p-8 rounded-3xl shadow-sm border border-gray-100 font-sans">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-[18px] leading-[25px] font-semibold text-[rgb(15,23,42)]">Create New Role</h2>
            <Link :href="route('roles.index')" class="text-emerald-500 hover:text-emerald-600 font-medium text-sm">
                Back to Roles
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Name -->
            <div>
                 <label class="block text-[14px] leading-[17px] font-medium text-[rgb(30,41,59)] mb-2">Role Name</label>
                 <input v-model="form.name" type="text" required autofocus
                    class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm"
                    placeholder="Enter role name"
                 />
                 <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
            </div>

            <!-- Permissions -->
            <div>
                <label class="block text-[14px] leading-[17px] font-medium text-[rgb(30,41,59)] mb-2">Permissions</label>
                <div v-if="permissions.length > 0" class="space-y-2 bg-gray-50 p-4 rounded-xl border border-gray-100 max-h-60 overflow-y-auto scrollbar-hide">
                    <div v-for="permission in permissions" :key="permission.id" class="flex items-center">
                        <input :value="permission.name" v-model="form.permissions" type="checkbox"
                            class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 rounded" />
                        <label class="ml-2 text-sm text-gray-700">{{ permission.name }}</label>
                    </div>
                </div>
                <div v-else class="text-sm text-gray-500 italic">No permissions available.</div>
            </div>

            <div class="flex items-end justify-end pt-4">
                <button type="submit" :disabled="form.processing"
                    class="px-6 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl shadow-md font-medium transition-all transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Create Role
                </button>
            </div>
        </form>
    </div>
</template>
