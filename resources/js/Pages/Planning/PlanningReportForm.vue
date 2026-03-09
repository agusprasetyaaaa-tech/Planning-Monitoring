<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { Switch, SwitchGroup, SwitchLabel } from '@headlessui/vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    show: Boolean,
    plan: Object, // The plan we are reporting for
    companyName: String, // Company name for the header
    currentSimulatedTime: String,
});

const emit = defineEmits(['close']);

const form = useForm({
    execution_date: (props.currentSimulatedTime ? props.currentSimulatedTime.split('T')[0] : new Date().toISOString().split('T')[0]),
    location: '',
    pic: '',
    position: '',
    result_description: '',
    next_plan_description: '',
    next_activity_type: '', 
    next_planning_date: '', 
    progress: '',
    is_success: true, 
});

// Update form when plan changes
watch(() => props.plan, (newPlan) => {
    if (newPlan) {
        // Reset or init form
        form.execution_date = (props.currentSimulatedTime ? props.currentSimulatedTime.split('T')[0] : new Date().toISOString().split('T')[0]);
        form.location = '';
        form.pic = '';
        form.position = '';
        form.result_description = '';
        form.next_plan_description = '';
        form.next_activity_type = '';
        form.next_planning_date = '';
        form.progress = '';
        form.is_success = true;
    }
}, { immediate: true });

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
    date.setDate(date.getDate() + 365); // Far future (1 year)
    return date.toISOString().split('T')[0];
});

// Check if progress is Closing (100%)
const isClosing = computed(() => {
    return form.progress === '100%-Closing';
});

const submit = () => {
    if (!props.plan) return;
    form.post(route('planning.report.store', props.plan.id), {
        onSuccess: () => {
            form.reset();
            emit('close');
        },
    });
};
</script>

<template>
    <Modal :show="show" @close="$emit('close')" maxWidth="2xl">
        <div class="font-sans">
            <!-- Header -->
            <div class="px-4 py-4 sm:px-5 sm:py-4 bg-emerald-600 flex items-center justify-between rounded-t-lg">
                <div class="pr-8 sm:pr-0">
                    <h2 class="text-base sm:text-lg font-bold text-white leading-tight">Create Report</h2>
                    <p class="text-[9px] sm:text-xs text-emerald-100 italic" v-if="plan">
                        For: {{ companyName || plan?.customer?.company_name || 'N/A' }} | {{ plan?.project_name || 'No Project' }}
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
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Execution Date (Static Display) -->
                        <div>
                            <label class="block text-xs font-bold text-gray-900 mb-2">Execution Date</label>
                            <div class="w-full px-3 py-2.5 rounded-lg bg-gray-50 text-gray-500 border border-gray-100 font-semibold text-sm flex items-center gap-2 cursor-not-allowed">
                                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span>{{ form.execution_date }}</span>
                            </div>
                        </div>

                        <!-- Activity Type -->
                        <div>
                            <label class="block text-xs font-bold text-gray-900 mb-2">Activity Type <span class="text-rose-400">*</span></label>
                            <div class="w-full px-3 py-2.5 rounded-lg bg-gray-50 text-gray-900 border border-gray-100 font-semibold text-sm flex items-center justify-between group shadow-sm">
                                <span>{{ plan?.activity_type }}</span>
                                <span class="text-emerald-600 bg-emerald-100 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wide">Planned</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Location -->
                        <div>
                            <label class="block text-xs font-bold text-gray-900 mb-2">Location <span class="text-rose-400">*</span></label>
                            <input v-model="form.location" type="text" required
                                class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm"
                                placeholder="Enter location"
                            />
                            <div v-if="form.errors.location" class="text-rose-500 text-xs mt-1">{{ form.errors.location }}</div>
                        </div>

                        <!-- Progress -->
                        <div>
                            <label class="block text-xs font-bold text-gray-900 mb-2">Progress <span class="text-rose-400">*</span></label>
                            <select v-model="form.progress" required
                                class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm cursor-pointer"
                            >
                                <option value="" disabled>Select Progress</option>
                                <option v-for="opt in progressOptions" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                            <div v-if="form.errors.progress" class="text-rose-500 text-xs mt-1">{{ form.errors.progress }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- PIC -->
                        <div>
                            <label class="block text-xs font-bold text-gray-900 mb-2">Person In Charge <span class="text-rose-400">*</span></label>
                            <input v-model="form.pic" type="text" required
                                class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm"
                                placeholder="PIC Name"
                            />
                            <div v-if="form.errors.pic" class="text-rose-500 text-xs mt-1">{{ form.errors.pic }}</div>
                        </div>

                        <!-- Position -->
                        <div>
                            <label class="block text-xs font-bold text-gray-900 mb-2">PIC Position <span class="text-rose-400">*</span></label>
                            <input v-model="form.position" type="text" required
                                class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm"
                                placeholder="Division"
                            />
                            <div v-if="form.errors.position" class="text-rose-500 text-xs mt-1">{{ form.errors.position }}</div>
                        </div>
                    </div>

                    <!-- Result Description -->
                    <div>
                        <label class="block text-xs font-bold text-gray-900 mb-2">Result Description <span class="text-rose-400">*</span></label>
                        <textarea v-model="form.result_description" required rows="3"
                            class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 placeholder:text-gray-300 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 resize-none transition-colors shadow-sm"
                            placeholder="Briefly describe the outcome..."
                        ></textarea>
                        <div v-if="form.errors.result_description" class="text-rose-500 text-xs mt-1">{{ form.errors.result_description }}</div>
                    </div>

                    <!-- Goal Achievement -->
                    <div class="rounded-lg border p-3.5 transition-colors duration-200"
                         :class="form.is_success ? 'border-emerald-200 bg-emerald-50/50' : 'border-rose-200 bg-rose-50/50'">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-sm font-bold" :class="form.is_success ? 'text-emerald-800' : 'text-rose-800'">
                                    Goal Achievement
                                </span>
                                <p class="text-[10px] sm:text-xs mt-0.5" :class="form.is_success ? 'text-emerald-500' : 'text-rose-500'">
                                    {{ form.is_success ? 'Activity goal was achieved' : 'Activity goal was not achieved' }}
                                </p>
                            </div>
                            <SwitchGroup>
                                <Switch
                                    v-model="form.is_success"
                                    :class="form.is_success ? 'bg-emerald-500' : 'bg-red-500'"
                                    class="relative inline-flex h-7 w-14 items-center rounded-full transition-colors focus:outline-none"
                                >
                                    <span
                                        :class="form.is_success ? 'translate-x-8' : 'translate-x-1'"
                                        class="inline-block h-5 w-5 transform rounded-full bg-white transition-transform shadow-sm"
                                    />
                                </Switch>
                            </SwitchGroup>
                        </div>
                    </div>

                    <!-- Next Activity Plan Section (Hidden when Closing) -->
                    <div v-if="!isClosing" class="bg-gray-50 p-3 sm:p-4 rounded-xl border border-gray-100 space-y-3 sm:space-y-4">
                        <div class="flex items-center gap-2">
                             <h3 class="text-[10px] sm:text-xs font-bold text-emerald-700 uppercase tracking-wider">Next Plan Details (Auto-Create)</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Next Activity Type -->
                            <div>
                                <label class="block text-xs font-bold text-gray-900 mb-2">Next Activity Type <span class="text-rose-400">*</span></label>
                                <select v-model="form.next_activity_type" :required="!isClosing"
                                    class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm cursor-pointer"
                                >
                                    <option value="" disabled>Select Activity</option>
                                    <option v-for="type in activityTypes" :key="type" :value="type">{{ type }}</option>
                                </select>
                                <div v-if="form.errors.next_activity_type" class="text-rose-500 text-xs mt-1">{{ form.errors.next_activity_type }}</div>
                            </div>

                            <!-- Next Planning Date -->
                            <div>
                                <label class="block text-xs font-bold text-gray-900 mb-2">Next Planning Date <span class="text-rose-400">*</span></label>
                                <input v-model="form.next_planning_date" type="date" :required="!isClosing"
                                    :min="minNextPlanDate"
                                    :max="maxNextPlanDate"
                                    class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-colors shadow-sm"
                                />
                                
                                <div v-if="form.next_planning_date && (form.next_planning_date < minNextPlanDate || form.next_planning_date > maxNextPlanDate)" class="text-red-600 text-[10px] mt-2 font-bold bg-white p-2 rounded-lg border border-red-100 flex items-start gap-2 shadow-sm">
                                    <svg class="w-3.5 h-3.5 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Date must be between {{ minNextPlanDate }} and {{ maxNextPlanDate }} (Max 2 weeks).</span>
                                </div>

                                <div v-if="isSameWeek" class="text-amber-700 text-[10px] mt-2 font-semibold bg-amber-50 p-2 rounded-lg border border-amber-200 flex items-start gap-2 shadow-sm">
                                    <span>⚠️ Warning: Same week. Finished plan will be replaced in weekly view.</span>
                                </div>
                                <div v-if="form.errors.next_planning_date" class="text-rose-500 text-xs mt-1">{{ form.errors.next_planning_date }}</div>
                            </div>
                        </div>

                        <!-- Next Plan Description -->
                        <div>
                            <label class="block text-xs font-bold text-gray-900 mb-2">Next Plan Description (Optional)</label>
                            <textarea v-model="form.next_plan_description" rows="2"
                                class="w-full rounded-lg border-gray-200 bg-white py-2.5 px-3 text-sm text-gray-700 placeholder:text-gray-300 focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 resize-none transition-colors shadow-sm"
                                placeholder="Details for follow up..."
                            ></textarea>
                            <div v-if="form.errors.next_plan_description" class="text-rose-500 text-xs mt-1">{{ form.errors.next_plan_description }}</div>
                        </div>
                    </div>

                    <!-- Closing Confirmation Message -->
                    <div v-if="isClosing" class="bg-gray-900 p-4 rounded-xl border border-gray-800 shadow-lg">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-emerald-500/20 rounded-lg">
                                <svg class="w-6 h-6 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-white">🎉 Deal Closing</h3>
                                <p class="text-gray-400 text-[11px] mt-0.5 leading-tight">No follow-up required. Customer will be marked as <span class="text-emerald-400 font-semibold">CLOSED</span>.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-5 py-4 bg-gray-50/50 flex flex-col sm:flex-row items-center justify-end gap-3 rounded-b-lg border-t border-gray-100">
                    <button type="button" @click="$emit('close')"
                        class="w-full sm:w-auto px-6 py-2.5 text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors order-2 sm:order-1">
                        Cancel
                    </button>
                    <button type="submit" :disabled="form.processing"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-lg shadow-md disabled:opacity-50 disabled:cursor-not-allowed order-1 sm:order-2 transition-colors">
                        <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Submit Report</span>
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
