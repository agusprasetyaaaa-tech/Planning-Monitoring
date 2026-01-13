<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { Switch, SwitchGroup, SwitchLabel } from '@headlessui/vue';
import { computed } from 'vue';

defineOptions({ layout: NexusLayout });

const props = defineProps({
    plan: Object,
    currentSimulatedTime: String,
});

const form = useForm({
    execution_date: props.currentSimulatedTime || new Date().toISOString().split('T')[0],
    location: '',
    pic: '',
    position: '',
    result_description: '',
    next_plan_description: '',
    next_activity_type: '', // NEW
    next_planning_date: '', // NEW - User must pick
    progress: '',
    is_success: true, 
});

const activityTypes = [
    'Call', 'Visit', 'Ent', 'Online Meeting', 'Survey', 
    'Presentation', 'Proposal', 'Negotiation', 'Admin/Tender', 'Other'
];

const progressOptions = [
    '10%-Introduction',
    '20%-Visit',
    '30%-Presentation',
    '40%-Survey',
    '50%-Proposal',
    '75%-Confirm Budget',
    '90%-Tender&Nego',
    '100%-Closing'
];

/* Helper to check week number */
const getWeekNumber = (d) => {
    d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay()||7));
    var yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
    var weekNo = Math.ceil(( ( (d - yearStart) / 86400000) + 1)/7);
    return weekNo;
}

const isSameWeek = computed(() => {
    if (!form.next_planning_date) return false;
    const current = new Date(form.execution_date);
    const next = new Date(form.next_planning_date);
    return getWeekNumber(current) === getWeekNumber(next) && current.getFullYear() === next.getFullYear();
});

const minNextPlanDate = computed(() => {
    return form.execution_date;
});

const maxNextPlanDate = computed(() => {
    if (!form.execution_date) return '';
    const date = new Date(form.execution_date);
    date.setDate(date.getDate() + 14);
    return date.toISOString().split('T')[0];
});

// Check if progress is Closing (100%)
const isClosing = computed(() => {
    return form.progress === '100%-Closing';
});

const submit = () => {
    form.post(route('planning.report.store', props.plan.id));
};
</script>

<template>
    <Head title="Create Report" />

    <div class="max-w-2xl mx-auto bg-white p-8 rounded-3xl shadow-sm border border-gray-100 font-sans">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-3">
                 <div class="bg-emerald-100 p-2 rounded-xl">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Create Report</h2>
                    <p class="text-xs text-gray-500 mt-0.5 flex items-center gap-1">
                        For: <span class="font-bold text-gray-700">{{ plan.customer.company_name }}</span>
                        <span class="text-gray-300">|</span>
                        <span>{{ plan.project_name || 'No Project' }}</span>
                    </p>
                </div>
            </div>
            <Link :href="route('planning.index')" class="group flex items-center gap-1.5 text-gray-500 hover:text-gray-900 transition-colors text-sm font-medium px-3 py-2 rounded-lg hover:bg-gray-50">
                <svg class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Execution Date -->
            <div>
                 <label class="block text-sm font-bold text-gray-700 mb-2">Execution Date</label>
                 <div class="w-full px-4 py-3 rounded-xl bg-gray-50 border-0 text-gray-500 font-medium text-sm flex items-center gap-2 cursor-not-allowed">
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>{{ form.execution_date }}</span>
                 </div>
                 <div v-if="form.errors.execution_date" class="text-red-500 text-xs mt-1 font-medium">{{ form.errors.execution_date }}</div>
            </div>

            <!-- Location -->
            <div>
                 <label class="block text-sm font-bold text-gray-700 mb-2">Location</label>
                 <input v-model="form.location" type="text" required
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm shadow-sm placeholder:text-gray-400"
                    placeholder="Enter location"
                 />
                 <div v-if="form.errors.location" class="text-red-500 text-xs mt-1 font-medium">{{ form.errors.location }}</div>
            </div>

             <!-- PIC -->
            <div>
                 <label class="block text-sm font-bold text-gray-700 mb-2">Person In Charge</label>
                 <input v-model="form.pic" type="text" required
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm shadow-sm placeholder:text-gray-400"
                    placeholder="Enter person in charge"
                 />
                 <div v-if="form.errors.pic" class="text-red-500 text-xs mt-1 font-medium">{{ form.errors.pic }}</div>
            </div>

             <!-- Division / Position -->
            <div>
                 <label class="block text-sm font-bold text-gray-700 mb-2">Division / Position</label>
                 <input v-model="form.position" type="text" required
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm shadow-sm placeholder:text-gray-400"
                    placeholder="Enter division or position"
                 />
                 <div v-if="form.errors.position" class="text-red-500 text-xs mt-1 font-medium">{{ form.errors.position }}</div>
            </div>

            <!-- Result Description -->
            <div>
                 <label class="block text-sm font-bold text-gray-700 mb-2">Result Description</label>
                 <textarea v-model="form.result_description" required rows="3"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm resize-none shadow-sm placeholder:text-gray-400"
                    placeholder="Describe the result"
                 ></textarea>
                 <div v-if="form.errors.result_description" class="text-red-500 text-xs mt-1 font-medium">{{ form.errors.result_description }}</div>
            </div>

            <!-- Progress -->
            <div>
                 <label class="block text-sm font-bold text-gray-700 mb-2">Progress</label>
                 <select v-model="form.progress" required
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm bg-white shadow-sm cursor-pointer"
                 >
                    <option value="" disabled>Select Progress</option>
                    <option v-for="opt in progressOptions" :key="opt" :value="opt">{{ opt }}</option>
                 </select>
                 <div v-if="form.errors.progress" class="text-red-500 text-xs mt-1 font-medium">{{ form.errors.progress }}</div>
            </div>

            <!-- Goal Achievement -->
            <div>
                <SwitchGroup>
                    <div class="flex items-center justify-between bg-gray-50 p-4 rounded-xl border border-gray-100">
                        <SwitchLabel class="mr-4 text-sm font-bold text-gray-700">Goal Achievement?</SwitchLabel>
                        <Switch
                            v-model="form.is_success"
                            :class="form.is_success ? 'bg-emerald-500' : 'bg-red-500'"
                            class="relative inline-flex h-8 w-16 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                        >
                            <span
                                :class="form.is_success ? 'translate-x-9' : 'translate-x-1'"
                                class="inline-block h-6 w-6 transform rounded-full bg-white transition-transform"
                            />
                             <span class="absolute text-[10px] font-bold text-white left-2" v-if="!form.is_success">FAIL</span>
                             <span class="absolute text-[10px] font-bold text-white right-2" v-if="form.is_success">SUCC</span>
                        </Switch>
                    </div>
                </SwitchGroup>
                <div v-if="form.errors.is_success" class="text-red-500 text-xs mt-1 font-medium">{{ form.errors.is_success }}</div>
            </div>

            <!-- Next Activity Plan Section (Hidden when Closing) -->
            <div v-if="!isClosing" class="bg-gradient-to-br from-emerald-50/80 to-teal-50/80 p-6 rounded-2xl border border-emerald-100 shadow-sm space-y-6">
                <div class="flex items-center gap-2 mb-2">
                    <div class="p-1.5 bg-emerald-100 rounded-lg">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-emerald-900 uppercase tracking-wider">Next Plan Details (Auto-Create)</h3>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                     <!-- Next Activity Type -->
                    <div>
                     <label class="block text-sm font-bold text-gray-700 mb-2">Activity Plan (Type)</label>
                         <select v-model="form.next_activity_type" :required="!isClosing"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm bg-white shadow-sm cursor-pointer"
                         >
                            <option value="" disabled>Select Activity</option>
                            <option v-for="type in activityTypes" :key="type" :value="type">{{ type }}</option>
                         </select>
                         <div v-if="form.errors.next_activity_type" class="text-red-500 text-xs mt-1 font-medium">{{ form.errors.next_activity_type }}</div>
                    </div>

                    <!-- Next Planning Date -->
                    <div>
                         <label class="block text-sm font-bold text-gray-700 mb-2">Next Planning Date</label>
                         <input v-model="form.next_planning_date" type="date" :required="!isClosing"
                            :min="minNextPlanDate"
                            :max="maxNextPlanDate"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm shadow-sm"
                         />
                         
                         <!-- Range Error Message -->
                         <div v-if="form.next_planning_date && (form.next_planning_date < minNextPlanDate || form.next_planning_date > maxNextPlanDate)" class="text-red-600 text-[11px] mt-2 font-bold bg-red-50 p-2.5 rounded-lg border border-red-200 flex items-start gap-2 shadow-sm">
                            <svg class="w-4 h-4 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="leading-tight">Date must be between {{ minNextPlanDate }} and {{ maxNextPlanDate }} (Max 2 weeks).</span>
                         </div>

                         <div v-if="isSameWeek" class="text-amber-700 text-[10px] mt-2 font-semibold bg-amber-50 p-2.5 rounded-lg border border-amber-200 flex items-start gap-2 shadow-sm">
                            <span class="text-base leading-none">⚠️</span>
                            <span class="leading-tight">Warning: This date is in the same week. Your completed plan will be overwritten by this new plan in the Weekly view.</span>
                         </div>
                         <div v-if="form.errors.next_planning_date" class="text-red-500 text-xs mt-1 font-medium">{{ form.errors.next_planning_date }}</div>
                    </div>
                </div>

                <!-- Next Plan Description -->
                <div>
                     <label class="block text-sm font-bold text-gray-700 mb-2">Plan Description (Optional)</label>
                     <textarea v-model="form.next_plan_description" rows="3"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm resize-none shadow-sm placeholder:text-gray-400"
                        placeholder="Describe next plan (optional)"
                     ></textarea>
                     <div v-if="form.errors.next_plan_description" class="text-red-500 text-xs mt-1 font-medium">{{ form.errors.next_plan_description }}</div>
                </div>
            </div>

            <!-- Closing Confirmation Message -->
            <div v-if="isClosing" class="bg-gradient-to-br from-gray-800 to-gray-900 p-4 sm:p-6 rounded-2xl border border-gray-700 shadow-lg">
                <div class="flex flex-col sm:flex-row items-center gap-3 sm:gap-4 text-center sm:text-left">
                    <div class="p-2.5 sm:p-3 bg-emerald-500/20 rounded-xl flex-shrink-0">
                        <svg class="w-6 h-6 sm:w-7 sm:h-7 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-white">🎉 Congratulations! Deal Closing</h3>
                        <p class="text-gray-400 text-xs sm:text-sm mt-1 leading-relaxed">No follow-up plan required. This customer will be marked as <span class="text-emerald-400 font-semibold">CLOSED</span> after approval.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end pt-4 border-t border-gray-50 mt-6">
                <button type="submit" :disabled="form.processing"
                    class="px-8 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white rounded-xl shadow-lg hover:shadow-xl font-bold transition-all transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none disabled:shadow-none flex items-center gap-2"
                >
                    <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Submit Report</span>
                    <svg v-if="!form.processing" class="w-4 h-4 text-white/90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                </button>
            </div>
        </form>
    </div>
</template>
