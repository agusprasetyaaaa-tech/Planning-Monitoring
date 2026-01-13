<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    mustVerifyEmail: Boolean,
    status: String,
});

const page = usePage();
const isSuperAdmin = computed(() => {
    return page.props.auth?.user?.roles?.includes('Super Admin');
});
</script>

<template>
    <Head title="Profile" />

    <NexusLayout>
        <!-- MODERN THEME FOR NON-ADMIN (Gradient Header) -->
        <template v-if="!isSuperAdmin">
            <!-- Background Backdrop -->
            <div class="fixed inset-0 pointer-events-none overflow-hidden -z-10 bg-gray-50"></div>

            <!-- Stylish Header Section -->
            <div class="relative mb-8 text-white z-50">
                 <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-emerald-600 rounded-b-[2rem] md:rounded-b-[3rem] shadow-lg overflow-hidden -z-10">
                     <!-- Abstract Shapes -->
                     <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full mix-blend-overlay filter blur-3xl translate-x-1/3 -translate-y-1/2"></div>
                     <div class="absolute bottom-0 left-0 w-64 h-64 bg-white/10 rounded-full mix-blend-overlay filter blur-2xl -translate-x-1/3 translate-y-1/3"></div>
                 </div>

                 <div class="px-6 md:px-8 pt-8 pb-6 mx-auto max-w-6xl">
                     <!-- Back Button & Title -->
                     <div class="flex items-center gap-4">
                          <Link :href="route('dashboard')" class="p-2 bg-white/20 backdrop-blur-sm rounded-xl hover:bg-white/30 transition-colors">
                               <svg class="w-5 h-5 md:w-6 md:h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                          </Link>
                          <div>
                              <h1 class="text-2xl md:text-3xl font-bold tracking-tight">Account Settings</h1>
                              <p class="text-blue-100 text-sm md:text-base">Manage your profile and security preferences</p>
                          </div>
                     </div>
                 </div>
            </div>
        </template>

        <!-- TITLE FOR ADMIN (Standard Layout) -->
        <template v-else>
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Profile Settings</h2>
            </div>
        </template>

        <!-- MAIN CONTENT GRID -->
        <div 
            class="mx-auto max-w-6xl pb-20 relative z-10"
            :class="{ 'px-6 md:px-8 -mt-4': !isSuperAdmin }"
        >
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                 <!-- Profile Info -->
                 <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
                      <UpdateProfileInformationForm
                          :must-verify-email="mustVerifyEmail"
                          :status="status"
                          class="max-w-xl"
                      />
                 </div>

                 <!-- Update Password -->
                 <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-gray-100">
                      <UpdatePasswordForm class="max-w-xl" />
                 </div>

                 <!-- Delete Account (Full Width) -->
                 <div v-if="isSuperAdmin" class="lg:col-span-2 bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-rose-100 bg-rose-50/10">
                      <DeleteUserForm class="max-w-xl" />
                 </div>
            </div>
        </div>

    </NexusLayout>
</template>
