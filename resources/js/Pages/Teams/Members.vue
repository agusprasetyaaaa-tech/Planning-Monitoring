<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineOptions({ layout: NexusLayout });

const props = defineProps({
    team: Object,
    availableUsers: Array,
});

const form = useForm({
    user_id: '',
});

const addMember = () => {
    form.post(route('teams.assign-member', props.team.id), {
        onSuccess: () => form.reset(),
    });
};

const removeMember = (userId) => {
    if (confirm('Are you sure you want to remove this member from the team?')) {
        form.delete(route('teams.remove-member', [props.team.id, userId]));
    }
};
</script>

<template>
    <Head :title="`Manage ${team.name} Members`" />

    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Team Info Card -->
        <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 font-sans">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-[18px] leading-[25px] font-semibold text-[rgb(15,23,42)]">{{ team.name }}</h2>
                    <p class="text-sm text-gray-500 mt-1">Manager: {{ team.manager?.name || 'No manager assigned' }}</p>
                </div>
                <Link :href="route('teams.index')" class="text-emerald-500 hover:text-emerald-600 font-medium text-sm">
                    Back to Teams
                </Link>
            </div>

            <!-- Add Member Form -->
            <div class="mb-6 p-4 bg-gray-50 rounded-xl">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Add New Member</h3>
                <form @submit.prevent="addMember" class="flex gap-3">
                    <select v-model="form.user_id" required
                        class="flex-1 px-4 py-2 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm"
                    >
                        <option value="">Select a user</option>
                        <option 
                            v-for="user in availableUsers.filter(u => !u.team_id || u.team_id === team.id)" 
                            :key="user.id" 
                            :value="user.id"
                            :disabled="user.team_id === team.id"
                        >
                            {{ user.name }} {{ user.team_id === team.id ? '(Already in team)' : '' }}
                        </option>
                    </select>
                    <button type="submit" :disabled="form.processing || !form.user_id"
                        class="px-6 py-2 bg-emerald-500 hover:bg-emerald-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-xl shadow-md font-medium transition-all transform hover:scale-105"
                    >
                        Add Member
                    </button>
                </form>
            </div>

            <!-- Current Members List -->
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Current Members ({{ team.members?.length || 0 }})</h3>
                <div v-if="team.members && team.members.length > 0" class="space-y-2">
                    <div 
                        v-for="member in team.members" 
                        :key="member.id"
                        class="flex items-center justify-between p-4 bg-white border border-gray-100 rounded-xl hover:shadow-sm transition-shadow"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center font-semibold text-sm">
                                {{ member.name.charAt(0).toUpperCase() }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">{{ member.name }}</p>
                                <p class="text-xs text-gray-500">{{ member.email }}</p>
                            </div>
                        </div>
                        <button 
                            @click="removeMember(member.id)"
                            class="px-4 py-2 text-sm text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                            :disabled="form.processing"
                        >
                            Remove
                        </button>
                    </div>
                </div>
                <div v-else class="text-center py-8 text-gray-400 text-sm">
                    No members in this team yet. Add members using the form above.
                </div>
            </div>
        </div>
    </div>
</template>
