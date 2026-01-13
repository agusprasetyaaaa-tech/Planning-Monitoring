<script setup>
import { ref, computed, onMounted } from 'vue';
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';

const props = defineProps({
    data: Object,
    timeSettings: Object,
    userRoles: Array, // Added userRoles prop
});

const emit = defineEmits(['close']);

const isOpen = ref(false);

// Session storage keys
const DONT_REMIND_KEY = 'planning_reminder_hidden_day'; // Renamed to be explicit about day scope
const SESSION_SHOWN_KEY = 'planning_reminder_shown_session'; // New key for session scope

// Check if reminder should be shown
const shouldShowReminder = () => {
    console.log('=== shouldShowReminder CHECK ===');
    
    // For Managers with team data, always show (bypass session storage)
    // This is critical team management information
    const isManager = props.userRoles && props.userRoles.includes('Manager');
    const hasTeamData = props.data && (props.data.customersWithoutPlanning > 0 || props.data.plansNeedingReport > 0);
    
    console.log('Is Manager:', isManager);
    console.log('Has Team Data:', hasTeamData);
    console.log('customersWithoutPlanning:', props.data?.customersWithoutPlanning);
    console.log('plansNeedingReport:', props.data?.plansNeedingReport);
    
    if (isManager && hasTeamData) {
        // Still respect "Don't remind today" but ignore session storage
        const hiddenUntil = localStorage.getItem(DONT_REMIND_KEY);
        console.log('Manager path - hiddenUntil:', hiddenUntil);
        
        if (hiddenUntil) {
            const today = new Date().toDateString();
            console.log('Today:', today);
            console.log('Hidden until matches today?', hiddenUntil === today);
            
            if (hiddenUntil === today) {
                console.log('BLOCKED: Hidden until today');
                return false;
            }
            localStorage.removeItem(DONT_REMIND_KEY);
        }
        console.log('ALLOWED: Manager with team data');
        return true; // Show for managers with team data
    }
    
    console.log('Non-manager path');
    
    // For non-managers, use normal logic
    // 1. Check if user explicitly said "Don't remind today"
    const hiddenUntil = localStorage.getItem(DONT_REMIND_KEY);
    if (hiddenUntil) {
        const today = new Date().toDateString();
        if (hiddenUntil === today) {
            console.log('BLOCKED: Non-manager hidden until today');
            return false;
        }
        localStorage.removeItem(DONT_REMIND_KEY);
    }

    // 2. Check if already shown in this session (e.g. navigated back)
    const shownInSession = sessionStorage.getItem(SESSION_SHOWN_KEY);
    console.log('Shown in session:', shownInSession);
    if (shownInSession) {
        console.log('BLOCKED: Already shown in session');
        return false;
    }

    console.log('ALLOWED: All checks passed');
    return true;
};

// Check if today is Friday
const isFriday = computed(() => {
    return new Date().getDay() === 5;
});

// Determine which reminder type to show (priority: report > no-planning > friday)
const reminderType = computed(() => {
    if (!props.data) return null;
    
    if (props.data.plansNeedingReport > 0) {
        return 'report';
    }
    if (props.data.customersWithoutPlanning > 0) {
        return 'no-planning';
    }
    if (isFriday.value) {
        return 'friday';
    }
    return null;
});

// Get reminder content (all in English)
const reminderContent = computed(() => {
    if (!props.data) return null;
    
    switch (reminderType.value) {
        case 'report':
            return {
                icon: 'warning',
                iconBg: 'bg-gradient-to-br from-red-100 to-red-200',
                iconColor: 'text-red-600',
                titleColor: 'text-red-700',
                borderColor: 'border-red-200',
                title: 'Reminder: Update Report',
                message: `There are ${props.data.plansNeedingReport} plans that have passed execution date and need a report:`,
                items: (props.data.plansNeedingReportList || []).map(c => ({
                    name: c.company_name,
                    detail: c.latest_plan?.activity_code || '-',
                    date: formatDate(c.latest_plan?.planning_date),
                    urgent: true
                })),
                showMore: props.data.plansNeedingReport > 5 ? props.data.plansNeedingReport - 5 : 0
            };
        case 'no-planning':
            return {
                icon: 'alert',
                iconBg: 'bg-gradient-to-br from-amber-100 to-amber-200',
                iconColor: 'text-amber-600',
                titleColor: 'text-amber-700',
                borderColor: 'border-amber-200',
                title: 'Warning: Customers Without Planning',
                message: `There are ${props.data.customersWithoutPlanning} customers that don't have any planning yet:`,
                items: (props.data.customersWithoutPlanningList || []).map(c => ({
                    name: c.company_name,
                    detail: c.product?.name || '-',
                    date: null,
                    urgent: false
                })),
                showMore: props.data.customersWithoutPlanning > 5 ? props.data.customersWithoutPlanning - 5 : 0
            };
        case 'friday':
            return {
                icon: 'info',
                iconBg: 'bg-gradient-to-br from-blue-100 to-blue-200',
                iconColor: 'text-blue-600',
                titleColor: 'text-blue-700',
                borderColor: 'border-blue-200',
                title: 'Reminder: Friday Planning',
                message: 'Today is Friday. Make sure to create your planning for next week as planning can only be created on Fridays.',
                items: [],
                showMore: 0
            };
        default:
            return null;
    }
});

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' });
};

const closePopup = () => {
    isOpen.value = false;
    // Clear session storage so popup can show again next time user visits Planning page
    sessionStorage.removeItem(SESSION_SHOWN_KEY);
    emit('close');
};

const dontRemindToday = () => {
    const today = new Date().toDateString();
    localStorage.setItem(DONT_REMIND_KEY, today);
    closePopup();
};

onMounted(() => {
    console.log('PlanningReminderPopup mounted');
    console.log('Reminder data:', props.data);
    console.log('User roles:', props.userRoles);
    console.log('Reminder type:', reminderType.value);
    console.log('Should show reminder:', shouldShowReminder());
    
    // Only show if there's something to remind and not hidden for today
    // Session storage check is removed so popup shows every time user visits Planning page
    if (reminderType.value && shouldShowReminder()) {
        console.log('Showing reminder popup');
        setTimeout(() => {
            isOpen.value = true;
            // Mark as shown for this page load only (prevents multiple popups from reactive updates)
            sessionStorage.setItem(SESSION_SHOWN_KEY, 'true');
        }, 500); // Small delay for better UX
    }
});
</script>

<template>
    <TransitionRoot appear :show="isOpen" as="template">
        <Dialog as="div" @close="closePopup" class="relative z-50">
            <!-- Backdrop -->
            <TransitionChild
                as="template"
                enter="duration-300 ease-out"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="duration-200 ease-in"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" />
            </TransitionChild>

            <!-- Dialog Container -->
            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-3 sm:p-4 md:p-6">
                    <TransitionChild
                        as="template"
                        enter="duration-300 ease-out"
                        enter-from="opacity-0 scale-95 translate-y-4"
                        enter-to="opacity-100 scale-100 translate-y-0"
                        leave="duration-200 ease-in"
                        leave-from="opacity-100 scale-100 translate-y-0"
                        leave-to="opacity-0 scale-95 translate-y-4"
                    >
                        <DialogPanel 
                            class="w-full max-w-[calc(100vw-24px)] sm:max-w-md md:max-w-lg transform overflow-hidden rounded-2xl sm:rounded-3xl bg-white shadow-2xl transition-all"
                            :class="reminderContent?.borderColor"
                        >
                            <!-- Header with gradient background -->
                            <div class="relative px-4 sm:px-6 pt-5 sm:pt-6 pb-3 sm:pb-4">
                                <!-- Close Button -->
                                <button 
                                    @click="closePopup"
                                    class="absolute top-3 right-3 sm:top-4 sm:right-4 p-1.5 sm:p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-all duration-200 active:scale-95"
                                >
                                    <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>

                                <!-- Icon & Title -->
                                <div class="flex items-start gap-3 sm:gap-4 pr-8">
                                    <!-- Icon Circle -->
                                    <div :class="[reminderContent?.iconBg, 'flex-shrink-0 h-10 w-10 sm:h-12 sm:w-12 md:h-14 md:w-14 rounded-full flex items-center justify-center shadow-sm']">
                                        <!-- Warning Icon -->
                                        <svg v-if="reminderContent?.icon === 'warning'" :class="[reminderContent?.iconColor, 'h-5 w-5 sm:h-6 sm:w-6 md:h-7 md:w-7']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <!-- Alert Icon -->
                                        <svg v-else-if="reminderContent?.icon === 'alert'" :class="[reminderContent?.iconColor, 'h-5 w-5 sm:h-6 sm:w-6 md:h-7 md:w-7']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <!-- Info Icon -->
                                        <svg v-else :class="[reminderContent?.iconColor, 'h-5 w-5 sm:h-6 sm:w-6 md:h-7 md:w-7']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    
                                    <!-- Title & Message -->
                                    <div class="flex-1 min-w-0">
                                        <DialogTitle as="h3" :class="[reminderContent?.titleColor, 'text-base sm:text-lg md:text-xl font-bold leading-tight']">
                                            {{ reminderContent?.title }}
                                        </DialogTitle>
                                        <p class="mt-1 sm:mt-1.5 text-xs sm:text-sm text-gray-500 leading-relaxed">
                                            {{ reminderContent?.message }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Items List -->
                            <div v-if="reminderContent?.items?.length" class="px-4 sm:px-6 pb-2">
                                <div class="max-h-40 sm:max-h-48 md:max-h-56 overflow-y-auto rounded-xl bg-gray-50/80 p-2 sm:p-3 space-y-2">
                                    <div 
                                        v-for="(item, index) in reminderContent.items" 
                                        :key="index"
                                        class="flex items-center justify-between p-2.5 sm:p-3 rounded-lg bg-white border border-gray-100 shadow-sm hover:shadow-md transition-shadow duration-200"
                                    >
                                        <div class="flex-1 min-w-0 pr-2">
                                            <p class="text-xs sm:text-sm font-semibold text-gray-800 truncate">{{ item.name }}</p>
                                            <p class="text-[10px] sm:text-xs text-gray-500 mt-0.5">{{ item.detail }}</p>
                                        </div>
                                        <div v-if="item.date" class="flex-shrink-0">
                                            <span 
                                                :class="[
                                                    item.urgent ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600', 
                                                    'text-[10px] sm:text-xs font-medium px-2 py-1 rounded-full whitespace-nowrap'
                                                ]"
                                            >
                                                {{ item.date }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <p v-if="reminderContent.showMore > 0" class="text-[10px] sm:text-xs text-gray-400 text-center py-2 font-medium">
                                    + {{ reminderContent.showMore }} more customers...
                                </p>
                            </div>

                            <!-- Footer Actions -->
                            <div class="px-4 sm:px-6 py-4 sm:py-5 bg-gray-50/50 border-t border-gray-100">
                                <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-2 sm:gap-3">
                                    <button
                                        type="button"
                                        @click="dontRemindToday"
                                        class="w-full sm:w-auto text-xs sm:text-sm font-medium text-gray-500 hover:text-gray-700 py-2.5 px-4 rounded-xl hover:bg-gray-100 transition-all duration-200 active:scale-95"
                                    >
                                        Don't Remind Today
                                    </button>
                                    <button
                                        type="button"
                                        @click="closePopup"
                                        class="w-full sm:w-auto inline-flex justify-center items-center rounded-xl bg-gray-900 px-5 sm:px-6 py-2.5 sm:py-3 text-xs sm:text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200 shadow-lg hover:shadow-xl active:scale-95"
                                    >
                                        <span>Close</span>
                                        <svg class="ml-1.5 h-3.5 w-3.5 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
