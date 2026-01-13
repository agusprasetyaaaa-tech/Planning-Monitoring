<script setup>
import { computed, ref } from 'vue';
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Disclosure, DisclosureButton, DisclosurePanel, Switch, SwitchGroup, SwitchLabel, TransitionRoot } from '@headlessui/vue';

const props = defineProps({
    settings: Object,
});

const form = useForm({
    time_offset_days: props.settings.time_offset_days ?? 0,
    planning_time_unit: props.settings.planning_time_unit || 'Days (Production)',
    planning_warning_threshold: props.settings.planning_warning_threshold ?? 14,
    plan_expiry_value: props.settings.plan_expiry_value ?? 7,
    plan_expiry_unit: props.settings.plan_expiry_unit || 'Days (Production)',
    allowed_plan_creation_days: props.settings.allowed_plan_creation_days || ['Friday'],
    testing_mode: props.settings.testing_mode || false,
});



const planningUnitLabel = computed(() => {
    switch (form.planning_time_unit) {
        case 'Hours': return 'Hours';
        case 'Minutes': return 'Minutes';
        default: return 'Days';
    }
});

const submit = () => {
    form.patch(route('time-settings.update'), {
        preserveScroll: true,
    });
};

const daysOfWeek = [
    'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'
];

defineOptions({ layout: NexusLayout });
</script>

<template>
    <Head title="Time Configuration" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Time Configuration</h1>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Planning Report Configuration -->
            <Disclosure as="div" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" v-slot="{ open }" defaultOpen>
                <DisclosureButton class="flex w-full justify-between items-center px-6 py-4 text-left bg-gray-50 hover:bg-gray-100 transition-colors focus:outline-none">
                    <div class="flex items-center gap-3">
                         <div class="h-8 w-8 rounded-lg bg-gray-200 flex items-center justify-center text-gray-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                         </div>
                         <div>
                            <span class="text-sm font-bold text-gray-900 block">Planning Report Configuration</span>
                            <span class="text-xs text-gray-500">Configure planning badge colors and expiry settings for testing.</span>
                         </div>
                    </div>
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        :class="open ? 'rotate-180 transform' : ''"
                        class="h-5 w-5 text-gray-400 transition-transform duration-200"
                    >
                        <path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 01-1.06-.02L10 8.832 6.29 12.77a.75.75 0 11-1.08-1.04l4.25-4.5a.75.75 0 011.08 0l4.25 4.5a.75.75 0 01-.02 1.06z" clip-rule="evenodd" />
                    </svg>
                </DisclosureButton>
                <DisclosurePanel class="bg-white px-6 py-6 border-t border-gray-100">
                    <div class="grid grid-cols-1 gap-6">
                        <!-- Time Offset for Testing -->
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <label class="block text-sm font-medium text-gray-900 mb-1">Testing Time Offset (Development Only)</label>
                            <div class="relative rounded-md shadow-sm">
                                <input type="number" v-model.number="form.time_offset_days" min="0" 
                                    class="block w-full rounded-lg border-gray-300 pr-12 focus:border-emerald-500 focus:ring-emerald-500 text-sm" 
                                    placeholder="0" />
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                    <span class="text-gray-500 sm:text-sm">Days</span>
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-gray-600">
                                Add days to current time for testing. Set to <strong>0</strong> to use real time. 
                                <br>
                                <span class="text-yellow-700">Example: Set to <strong>5</strong> to simulate 5 days ahead.</span>
                            </p>
                            <div v-if="form.time_offset_days > 0" class="mt-2 flex items-center gap-2 text-xs">
                                <svg class="h-4 w-4 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span class="text-yellow-700 font-medium">Time offset is active! Simulating +{{ form.time_offset_days }} day(s)</span>
                            </div>
                        </div>
                        
                         <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Time Unit for Planning Badge<span class="text-red-500">*</span></label>
                            <select v-model="form.planning_time_unit" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                <option>Days (Production)</option>
                                <option>Hours</option>
                                <option>Minutes</option>
                            </select>
                             <p class="mt-1 text-xs text-gray-500">Change to minutes/hours for faster testing of round color changes.</p>
                        </div>
                        
                         <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Warning Threshold (Blink Trigger)<span class="text-red-500">*</span></label>
                            <div class="relative rounded-md shadow-sm">
                                <input type="number" v-model="form.planning_warning_threshold" class="block w-full rounded-lg border-gray-300 pr-12 focus:border-emerald-500 focus:ring-emerald-500 text-sm" />
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                    <span class="text-gray-500 sm:text-sm">{{ planningUnitLabel }}</span>
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">When plan badge starts blinking red</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                             <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Plan Expiry Value<span class="text-red-500">*</span></label>
                                <input type="number" v-model="form.plan_expiry_value" class="block w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 text-sm" />
                                <p class="mt-1 text-xs text-gray-500">How long before reports cannot be updated</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Plan Expiry Time Unit<span class="text-red-500">*</span></label>
                                <select v-model="form.plan_expiry_unit" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                    <option>Days (Production)</option>
                                    <option>Hours</option>
                                    <option>Minutes</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Change to hours/minutes for faster testing</p>
                            </div>
                        </div>

                         <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Allowed Plan Creation Days</label>
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                                <div v-for="day in daysOfWeek" :key="day" class="flex items-center">
                                    <input type="checkbox" :id="day" :value="day" v-model="form.allowed_plan_creation_days" 
                                        class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                                    <label :for="day" class="ml-2 block text-sm text-gray-900">{{ day }}</label>
                                </div>
                            </div>
                             <p class="mt-2 text-xs text-gray-500">Select valid days for plan creation. Default is Friday only.</p>
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                             <SwitchGroup as="div" class="flex items-center justify-between">
                                <span class="flex flex-grow flex-col">
                                    <SwitchLabel as="span" class="text-sm font-medium text-gray-900" passive>Testing Mode: Allow Plan Creation Any Day</SwitchLabel>
                                    <span class="text-xs text-gray-500">Enable to bypass ALL day restrictions during development/testing</span>
                                </span>
                                <Switch v-model="form.testing_mode" 
                                    :class="[form.testing_mode ? 'bg-emerald-600' : 'bg-gray-200', 'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2']">
                                    <span aria-hidden="true" :class="[form.testing_mode ? 'translate-x-5' : 'translate-x-0', 'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out']" />
                                </Switch>
                            </SwitchGroup>
                        </div>
                    </div>
                </DisclosurePanel>
            </Disclosure>

             <div>
                <button type="submit" :disabled="form.processing" 
                    class="inline-flex justify-center rounded-lg border border-transparent bg-emerald-600 py-2 px-6 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-all disabled:opacity-50">
                    Save Changes
                </button>
            </div>
        </form>
    </div>


</template>
