<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

defineOptions({ layout: NexusLayout });

const props = defineProps({
    users: Array,
});

const form = useForm({
    name: '',
    manager_id: '',
});

const submit = () => {
    form.post(route('teams.store'));
};
</script>

<template>
    <Head title="Create Team" />

    <div class="max-w-2xl mx-auto bg-white p-8 rounded-3xl shadow-sm border border-gray-100 font-sans">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-[18px] leading-[25px] font-semibold text-[rgb(15,23,42)]">Create New Team</h2>
            <Link :href="route('teams.index')" class="text-emerald-500 hover:text-emerald-600 font-medium text-sm">
                Back to Teams
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Team Name -->
            <div>
                <label for="name" class="block text-[14px] leading-[17px] font-medium text-[rgb(30,41,59)] mb-2">Team Name</label>
                <input id="name" type="text" v-model="form.name" required autofocus
                    class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm"
                    placeholder="Enter team name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <!-- Manager -->
            <div>
                <label for="manager_id" class="block text-[14px] leading-[17px] font-medium text-[rgb(30,41,59)] mb-2">Manager</label>
                <select id="manager_id" v-model="form.manager_id" required
                    class="w-full px-4 py-2 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm"
                >
                    <option value="">Select Manager</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                </select>
                <InputError class="mt-2" :message="form.errors.manager_id" />
            </div>

            <div class="flex items-end justify-end pt-4">
                <button type="submit" :disabled="form.processing"
                    class="px-6 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl shadow-md font-medium transition-all transform hover:scale-105"
                >
                    Create Team
                </button>
            </div>
        </form>
    </div>
</template>
