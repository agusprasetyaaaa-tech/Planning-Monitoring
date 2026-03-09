<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    customers: {
        type: Array,
        required: true
    },
    month: {
        type: Number,
        required: true
    },
    year: {
        type: Number,
        required: true
    },
    getStatus: {
        type: Function,
        required: true
    },
    getBlinkStyle: {
        type: Function,
        required: true
    }
});

const emit = defineEmits(['open-detail', 'create-plan', 'change-month']);

const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const daysOfWeekShort = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

// Responsive: detect mobile view
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);
const isMobileView = computed(() => windowWidth.value < 480);
const isTabletView = computed(() => windowWidth.value >= 480 && windowWidth.value < 768);

const handleResize = () => {
    windowWidth.value = window.innerWidth;
};

onMounted(() => {
    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
});

// Calendar Calculation
const calendarDays = computed(() => {
    const days = [];
    const firstDayOfMonth = new Date(props.year, props.month - 1, 1).getDay();
    const daysInMonth = new Date(props.year, props.month, 0).getDate();
    
    // Previous month padding
    const prevMonthDays = new Date(props.year, props.month - 1, 0).getDate();
    for (let i = firstDayOfMonth - 1; i >= 0; i--) {
        days.push({
            day: prevMonthDays - i,
            currentMonth: false,
            date: new Date(props.year, props.month - 2, prevMonthDays - i)
        });
    }
    
    // Current month
    for (let i = 1; i <= daysInMonth; i++) {
        days.push({
            day: i,
            currentMonth: true,
            date: new Date(props.year, props.month - 1, i)
        });
    }
    
    // Next month padding
    const remaining = 42 - days.length;
    for (let i = 1; i <= remaining; i++) {
        days.push({
            day: i,
            currentMonth: false,
            date: new Date(props.year, props.month, i)
        });
    }
    
    return days;
});

// Mobile: Only current month days with plans
const currentMonthDaysWithPlans = computed(() => {
    return calendarDays.value.filter(item => {
        if (!item.currentMonth) return false;
        const plans = getPlansForDate(item.date);
        return plans.length > 0 || isToday(item.date);
    });
});

const activeStatusFilter = ref('all');
const toggleStatusFilter = (status) => {
    activeStatusFilter.value = activeStatusFilter.value === status ? 'all' : status;
};

// Map plans to dates with filtering
const getPlansForDate = (date) => {
    const dateStr = date.toISOString().split('T')[0];
    const plans = [];
    
    props.customers.forEach(customer => {
        if (customer.plans) {
            customer.plans.forEach(plan => {
                if (plan.planning_date && plan.planning_date.startsWith(dateStr)) {
                    const status = props.getStatus({ latest_plan: plan, created_at: customer.created_at });
                    
                    // Filter match logic
                    let isMatch = activeStatusFilter.value === 'all';
                    if (!isMatch) {
                        if (activeStatusFilter.value === 'reported' && (status === 'reported' || status === 'approved')) isMatch = true;
                        if (activeStatusFilter.value === 'created' && status === 'created') isMatch = true;
                        if (activeStatusFilter.value === 'expired' && (status.includes('expired') || status.includes('rejected') || status.includes('no_plan_warning'))) isMatch = true;
                        if (activeStatusFilter.value === 'closed' && status === 'closed') isMatch = true;
                    }

                    if (isMatch) {
                        plans.push({
                            ...plan,
                            customer: customer,
                            computedStatus: status
                        });
                    }
                }
            });
        }
    });
    
    return plans.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
};

const isToday = (date) => {
    const today = new Date();
    return date.getDate() === today.getDate() &&
        date.getMonth() === today.getMonth() &&
        date.getFullYear() === today.getFullYear();
};

const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

const monthNamesShort = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
];

const currentFormattedDate = computed(() => {
    const today = new Date();
    if (isMobileView.value) {
        return today.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric',
            year: 'numeric'
        });
    }
    return today.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
});

const getStatusClasses = (status) => {
    const base = 'px-1.5 xs:px-2 py-0.5 xs:py-1 rounded-lg text-[8px] xs:text-[9px] md:text-[10px] font-bold transition-all duration-300 shadow-sm border ';
    
    switch (status) {
        case 'none': return base + 'bg-slate-50 text-slate-400 border-slate-100';
        case 'created': return base + 'bg-rose-600 text-white border-rose-700 shadow-rose-100'; // RED for pending report
        case 'reported': return base + 'bg-emerald-600 text-white border-emerald-700 shadow-emerald-100'; // GREEN for reported
        case 'expired': return base + 'bg-rose-600 text-white border-rose-700 animate-pulse'; // DISTINCT RED
        case 'warning': return base + 'bg-amber-500 text-white border-amber-600 animate-pulse';
        case 'no_plan_warning': return base + 'bg-rose-700 text-white border-rose-800 animate-pulse';
        case 'rejected_final': return base + 'bg-slate-800 text-white border-slate-900';
        case 'rejected_warning': return base + 'bg-slate-800 text-white border-slate-900 animate-pulse';
        case 'closed': return base + 'bg-black text-white border-black'; // PURE BLACK for closing
        case 'approved': return base + 'bg-emerald-600 text-white border-emerald-700 shadow-emerald-100'; // GREEN for approved/reported
        default: return base + 'bg-slate-50 text-slate-400 border-slate-100';
    }
};

const formatStatusText = (status) => {
    if (!status) return '-';
    let text = status.replace(/_/g, ' ');
    text = text.replace(/final/gi, '').trim();
    text = text.replace(/warning/gi, '').trim();
    return text;
};

const handleCreatePlan = (date) => {
    const dateStr = date.toISOString().split('T')[0];
    emit('create-plan', dateStr);
};

const navigateMonth = (direction) => {
    let newMonth = props.month;
    let newYear = props.year;
    
    if (direction === 'prev') {
        if (newMonth === 1) {
            newMonth = 12;
            newYear--;
        } else {
            newMonth--;
        }
    } else {
        if (newMonth === 12) {
            newMonth = 1;
            newYear++;
        } else {
            newMonth++;
        }
    }
    
    emit('change-month', { month: newMonth, year: newYear });
};

// Tooltip Logic
const hoveredPlanData = ref(null);
const tooltipPos = ref({ x: 0, y: 0 });

const handleMouseEnter = (plan, event) => {
    if (isMobileView.value) return; // Skip tooltip on mobile, use tap instead
    hoveredPlanData.value = plan;
    updateTooltipPos(event);
};

const handleMouseLeave = () => {
    hoveredPlanData.value = null;
};

const handlePlanClick = (customer) => {
    hoveredPlanData.value = null;
    emit('open-detail', customer);
};

const updateTooltipPos = (event) => {
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    let x = event.clientX + 15;
    let y = event.clientY + 15;
    
    // Prevent tooltip from going off-screen right
    if (x + 260 > viewportWidth) {
        x = event.clientX - 270;
    }
    // Prevent tooltip from going off-screen bottom
    if (y + 200 > viewportHeight) {
        y = event.clientY - 210;
    }
    
    tooltipPos.value = { x, y };
};

// Mobile: Format date for agenda view  
const formatMobileDate = (date) => {
    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    return {
        dayName: dayNames[date.getDay()],
        dayNum: date.getDate(),
        monthName: monthNamesShort[date.getMonth()]
    };
};
</script>

<template>
    <div class="bg-white rounded-2xl xs:rounded-3xl shadow-xl border border-gray-100 overflow-hidden w-full">
        <!-- Calendar Header Information -->
        <div class="p-3 xs:p-4 md:p-6 bg-gradient-to-br from-white to-gray-50/50 border-b border-gray-100">
            <!-- Top Row: Month/Year + Navigation -->
            <div class="flex items-center justify-between gap-2 xs:gap-4 mb-3 xs:mb-4">
                <h3 class="text-base xs:text-xl md:text-2xl font-black text-slate-800 tracking-tight flex items-center gap-1 xs:gap-2">
                    <span class="text-emerald-600">{{ isMobileView ? monthNamesShort[month - 1] : monthNames[month - 1] }}</span>
                    <span class="text-slate-400">{{ year }}</span>
                </h3>

                <div class="flex items-center gap-1 bg-white border border-slate-200 p-0.5 xs:p-1 rounded-xl xs:rounded-2xl shadow-sm">
                    <button @click="navigateMonth('prev')" 
                            class="p-1 xs:p-1.5 md:p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg xs:rounded-xl transition-all active:scale-90">
                        <svg class="w-3.5 h-3.5 xs:w-4 xs:h-4 md:w-5 md:h-5 focus:outline-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <div class="w-px h-3 xs:h-4 bg-slate-100 mx-0.5"></div>
                    <button @click="navigateMonth('next')" 
                            class="p-1 xs:p-1.5 md:p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg xs:rounded-xl transition-all active:scale-90">
                        <svg class="w-3.5 h-3.5 xs:w-4 xs:h-4 md:w-5 md:h-5 focus:outline-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Today info -->
            <p class="text-[9px] xs:text-[10px] md:text-xs text-slate-500 font-bold inline-flex items-center gap-1 xs:gap-1.5 bg-slate-100 px-2 xs:px-3 py-1 xs:py-1.5 rounded-full w-fit mb-3 xs:mb-4">
                <svg class="w-3 h-3 xs:w-3.5 xs:h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span class="whitespace-nowrap uppercase tracking-widest opacity-60">Today:</span>
                <span class="text-slate-700">{{ currentFormattedDate }}</span>
            </p>
            
            <!-- Filter Legends -->
            <div class="flex flex-wrap items-center gap-1 xs:gap-1.5 md:gap-2 text-[8px] xs:text-[9px] md:text-[10px] font-bold text-slate-400 bg-slate-50 border border-slate-200 p-1 xs:p-1.5 rounded-xl xs:rounded-2xl shadow-inner">
                <!-- Interactive Legends -->
                <button @click="toggleStatusFilter('all')" 
                     :class="activeStatusFilter === 'all' ? 'bg-white text-slate-900 shadow-sm border-slate-200' : 'text-slate-500 border-transparent hover:bg-white/50'"
                     class="flex items-center gap-1 xs:gap-1.5 px-2 xs:px-3 py-1 xs:py-1.5 rounded-lg xs:rounded-xl border transition-all duration-200">
                    <span class="w-1 h-1 xs:w-1.5 xs:h-1.5 rounded-full bg-slate-300"></span>
                    <span class="tracking-widest">ALL</span>
                </button>

                <button @click="toggleStatusFilter('created')" 
                     :class="activeStatusFilter === 'created' ? 'bg-rose-600 text-white shadow-md border-rose-700' : 'text-rose-600/70 border-transparent hover:bg-white/50'"
                     class="flex items-center gap-1 xs:gap-1.5 px-2 xs:px-3 py-1 xs:py-1.5 rounded-lg xs:rounded-xl border transition-all duration-200">
                    <span class="w-1 h-1 xs:w-1.5 xs:h-1.5 rounded-full bg-rose-400" :class="{'bg-white': activeStatusFilter === 'created'}"></span>
                    <span class="tracking-widest capitalize hidden xs:inline">PENDING</span>
                    <span class="tracking-widest capitalize xs:hidden">PNDG</span>
                </button>

                <button @click="toggleStatusFilter('reported')" 
                     :class="activeStatusFilter === 'reported' ? 'bg-emerald-600 text-white shadow-md border-emerald-700' : 'text-emerald-600/70 border-transparent hover:bg-white/50'"
                     class="flex items-center gap-1 xs:gap-1.5 px-2 xs:px-3 py-1 xs:py-1.5 rounded-lg xs:rounded-xl border transition-all duration-200">
                    <span class="w-1 h-1 xs:w-1.5 xs:h-1.5 rounded-full bg-emerald-400" :class="{'bg-white': activeStatusFilter === 'reported'}"></span>
                    <span class="tracking-widest hidden xs:inline">REPORTED</span>
                    <span class="tracking-widest xs:hidden">RPTD</span>
                </button>

                <button @click="toggleStatusFilter('expired')" 
                     :class="activeStatusFilter === 'expired' ? 'bg-rose-600 text-white shadow-md border-rose-700' : 'text-rose-600/70 border-transparent hover:bg-white/50'"
                     class="flex items-center gap-1 xs:gap-1.5 px-2 xs:px-3 py-1 xs:py-1.5 rounded-lg xs:rounded-xl border transition-all duration-200">
                    <span class="w-1 h-1 xs:w-1.5 xs:h-1.5 rounded-full bg-rose-400" :class="{'bg-white': activeStatusFilter === 'expired'}"></span>
                    <span class="tracking-widest capitalize hidden xs:inline">EXPIRED</span>
                    <span class="tracking-widest capitalize xs:hidden">EXPD</span>
                </button>

                <button @click="toggleStatusFilter('closed')" 
                     :class="activeStatusFilter === 'closed' ? 'bg-black text-white shadow-md border-black' : 'text-slate-900/70 border-transparent hover:bg-white/50'"
                     class="flex items-center gap-1 xs:gap-1.5 px-2 xs:px-3 py-1 xs:py-1.5 rounded-lg xs:rounded-xl border transition-all duration-200">
                    <span class="w-1 h-1 xs:w-1.5 xs:h-1.5 rounded-full bg-slate-700" :class="{'bg-white': activeStatusFilter === 'closed'}"></span>
                    <span class="tracking-widest uppercase hidden xs:inline">CLOSING</span>
                    <span class="tracking-widest uppercase xs:hidden">CLSD</span>
                </button>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- MOBILE AGENDA VIEW (< 480px) -->
        <!-- ============================================== -->
        <div v-if="isMobileView" class="divide-y divide-gray-50">
            <!-- Days of Week Mini Calendar (Compact) -->
            <div class="grid grid-cols-7 bg-gray-50/70 border-b border-gray-100">
                <div v-for="(item, index) in calendarDays" :key="'mini-'+index"
                     @click="item.currentMonth && getPlansForDate(item.date).length > 0 ? null : null"
                     class="py-1.5 flex flex-col items-center justify-center relative"
                     :class="{
                        'opacity-30': !item.currentMonth,
                        'bg-emerald-50': isToday(item.date),
                     }">
                    <!-- Day number -->
                    <span class="text-[9px] font-bold leading-none"
                          :class="isToday(item.date) ? 'text-emerald-600' : 'text-slate-500'">
                        {{ item.day }}
                    </span>
                    <!-- Dots for plans -->
                    <div v-if="item.currentMonth && getPlansForDate(item.date).length > 0" class="flex gap-0.5 mt-0.5">
                        <span v-for="n in Math.min(getPlansForDate(item.date).length, 3)" :key="n" 
                              class="w-1 h-1 rounded-full bg-emerald-500"></span>
                    </div>
                </div>
            </div>

            <!-- Agenda List -->
            <div class="max-h-[60vh] overflow-y-auto custom-scrollbar">
                <div v-if="currentMonthDaysWithPlans.length === 0" class="py-12 text-center text-gray-400">
                    <svg class="w-10 h-10 mx-auto mb-2 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-xs font-medium">No plans this month</p>
                </div>

                <div v-for="item in currentMonthDaysWithPlans" :key="'agenda-'+item.day" class="border-b border-gray-50 last:border-0">
                    <!-- Date Header -->
                    <div class="flex items-center gap-3 px-3 py-2 bg-gray-50/50 sticky top-0 z-10">
                        <div class="flex flex-col items-center justify-center w-10 h-10 rounded-xl shrink-0"
                             :class="isToday(item.date) ? 'bg-emerald-600 text-white shadow-md' : 'bg-white border border-gray-200 text-slate-700'">
                            <span class="text-[8px] font-bold uppercase leading-none">{{ formatMobileDate(item.date).dayName }}</span>
                            <span class="text-sm font-black leading-none">{{ item.day }}</span>
                        </div>
                        <div class="flex-1">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                                {{ formatMobileDate(item.date).monthName }} {{ item.day }}, {{ year }}
                            </span>
                            <span class="ml-2 text-[9px] text-slate-400 font-medium">
                                ({{ getPlansForDate(item.date).length }} {{ getPlansForDate(item.date).length === 1 ? 'plan' : 'plans' }})
                            </span>
                        </div>
                        <button @click="handleCreatePlan(item.date)" 
                                class="p-1.5 text-emerald-600 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-all">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Plans for this date -->
                    <div class="px-3 py-1.5 space-y-1.5">
                        <div v-for="plan in getPlansForDate(item.date)" :key="plan.id"
                             @click="handlePlanClick(plan.customer)"
                             class="cursor-pointer active:scale-[0.98] transition-transform">
                            <div :class="getStatusClasses(getStatus({ latest_plan: plan, created_at: plan.customer.created_at }))"
                                 :style="getBlinkStyle(plan)"
                                 class="!py-1.5 !px-3 rounded-xl border-b-[2px] border-black/10 shadow-sm">
                                <div class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 bg-white/40 rounded-full flex-shrink-0"></span>
                                    <span class="truncate leading-tight flex-1 text-left text-[10px] font-bold tracking-tight">{{ plan.customer.company_name }}</span>
                                    <span class="text-[8px] opacity-70 font-semibold uppercase shrink-0">{{ plan.activity_type }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- DESKTOP/TABLET GRID VIEW (>= 480px) -->
        <!-- ============================================== -->
        <template v-else>
            <!-- Days of Week Header -->
            <div class="grid grid-cols-7 border-b border-gray-100 bg-gray-50/50">
                <div v-for="(day, i) in daysOfWeek" :key="day" 
                     class="py-2 xs:py-3 text-center text-[9px] xs:text-[10px] md:text-xs font-bold text-gray-500 uppercase tracking-widest">
                    <span class="hidden xs:inline">{{ day }}</span>
                    <span class="xs:hidden">{{ daysOfWeekShort[i] }}</span>
                </div>
            </div>

            <!-- Calendar Grid -->
            <div class="grid grid-cols-7" :class="isTabletView ? 'auto-rows-[minmax(90px,auto)]' : 'auto-rows-[minmax(130px,auto)] md:auto-rows-[minmax(140px,auto)]'">
                <div v-for="(item, index) in calendarDays" :key="index"
                     class="p-1 xs:p-1.5 md:p-3 border-r border-b border-gray-50 group transition-colors hover:bg-emerald-50/10 relative"
                     :class="{ 
                        'bg-gray-50/30 text-gray-400': !item.currentMonth, 
                        'border-r-0': (index + 1) % 7 === 0,
                     }">
                    
                    <div class="flex justify-between items-start mb-0.5 xs:mb-1 md:mb-1.5">
                        <span class="text-[10px] xs:text-xs md:text-sm font-black w-5 h-5 xs:w-6 xs:h-6 md:w-8 md:h-8 flex items-center justify-center rounded-full transition-all shrink-0 aspect-square p-0 leading-none"
                              :class="isToday(item.date) ? 'bg-emerald-600 text-white shadow-md shadow-emerald-200/50 ring-2 ring-emerald-50' : 'text-slate-600 group-hover:text-emerald-700'">
                            {{ item.day }}
                        </span>
                        
                        <button @click="handleCreatePlan(item.date)" 
                                class="opacity-0 group-hover:opacity-100 p-0.5 xs:p-1 md:p-1.5 text-emerald-600 bg-emerald-50 md:bg-transparent hover:bg-emerald-100 rounded-lg xs:rounded-xl transition-all transform hover:scale-110 touch-action-manipulation"
                                :class="{'!opacity-100': isMobileView}">
                            <svg class="w-3 h-3 xs:w-3.5 xs:h-3.5 md:w-5 md:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                    </div>

                    <!-- Plans List -->
                    <div class="space-y-0.5 xs:space-y-1 md:space-y-2 overflow-y-auto custom-scrollbar pr-0.5"
                         :class="isTabletView ? 'max-h-[60px]' : 'max-h-[100px] md:max-h-[180px]'">
                        <div v-for="plan in getPlansForDate(item.date)" :key="plan.id"
                             @click="handlePlanClick(plan.customer)"
                             @mouseenter="handleMouseEnter(plan, $event)"
                             @mousemove="updateTooltipPos($event)"
                             @mouseleave="handleMouseLeave"
                             class="cursor-pointer group/item relative w-full"
                        >
                            <div :class="getStatusClasses(getStatus({ latest_plan: plan, created_at: plan.customer.created_at }))"
                                 :style="getBlinkStyle(plan)"
                                 class="!py-0.5 xs:!py-1 !px-1 xs:!px-2 rounded-lg xs:rounded-xl border-b-[2px] border-black/10 active:scale-95 transition-all shadow-sm hover:translate-x-0.5"
                            >
                                <div class="flex items-center gap-1 xs:gap-2">
                                    <span class="w-1 h-1 xs:w-1.5 xs:h-1.5 bg-white/40 rounded-full flex-shrink-0 hidden xs:block"></span>
                                    <span class="truncate leading-tight flex-1 text-left text-[7px] xs:text-[8px] md:text-[10px] font-bold tracking-tight">{{ plan.customer.company_name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Empty state hint -->
                    <div v-if="item.currentMonth && getPlansForDate(item.date).length === 0" 
                         class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-0 group-hover:opacity-10 transition-opacity">
                        <svg class="w-6 h-6 xs:w-8 xs:h-8 md:w-12 md:h-12 text-emerald-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 4v16m8-8H4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                </div>
            </div>
        </template>
    </div>

<!-- Global Floating Tooltip (Teleported to avoid clipping) - Desktop Only -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="hoveredPlanData && !isMobileView" 
                 :style="{ top: tooltipPos.y + 'px', left: tooltipPos.x + 'px' }"
                 class="fixed z-[9999] pointer-events-none"
            >
                <div class="bg-gray-100/95 backdrop-blur-xl text-gray-900 p-3 xs:p-4 rounded-[1.25rem] xs:rounded-[1.5rem] shadow-2xl border border-gray-200 w-52 xs:w-60 md:w-64 overflow-hidden relative group">
                    <!-- Subtle Glow Background -->
                    <div class="absolute -top-20 -right-20 w-40 h-40 bg-emerald-500/10 blur-[60px] rounded-full pointer-events-none"></div>
                    
                    <div class="flex flex-col gap-2 xs:gap-3 relative z-10">
                        <div class="flex justify-between items-center">
                            <span class="px-2 xs:px-2.5 py-0.5 bg-emerald-100 text-emerald-700 text-[7px] xs:text-[8px] font-black rounded-full uppercase tracking-[0.1em] border border-emerald-200/50">
                                {{ hoveredPlanData.activity_type }}
                            </span>
                            <div class="flex items-center gap-1 xs:gap-1.5 text-[8px] xs:text-[9px] text-gray-500 font-bold bg-gray-200/50 px-1.5 xs:px-2 py-0.5 rounded-full">
                                <svg class="w-2 h-2 xs:w-2.5 xs:h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ new Date(hoveredPlanData.planning_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                            </div>
                        </div>
                        
                        <div class="space-y-0.5">
                            <p class="text-[7px] xs:text-[8px] text-emerald-600 font-black uppercase tracking-widest opacity-80">Customer</p>
                            <h4 class="text-base xs:text-lg font-black text-gray-900 leading-[1.1] tracking-tight">
                                {{ hoveredPlanData.customer.company_name }}
                            </h4>
                            <div class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-emerald-600/10 text-emerald-700 text-[8px] xs:text-[9px] font-bold rounded-lg border border-emerald-600/20">
                                <svg class="w-2 h-2 xs:w-2.5 xs:h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                {{ hoveredPlanData.customer.product?.name || 'No Product' }}
                            </div>
                        </div>

                        <div v-if="hoveredPlanData.description" class="bg-white/60 p-2 xs:p-3 rounded-xl border border-gray-200 text-[10px] xs:text-[11px] text-gray-700 leading-relaxed font-medium">
                            <span class="text-emerald-600 font-black block text-[6px] xs:text-[7px] uppercase tracking-widest mb-1 opacity-50">Description</span>
                            "{{ hoveredPlanData.description }}"
                        </div>
                        
                        <div class="flex items-center justify-between pt-1 xs:pt-1.5 border-t border-gray-200">
                            <div class="flex items-center gap-1 xs:gap-1.5">
                                <div class="w-1.5 h-1.5 rounded-full relative">
                                    <div class="absolute inset-0 rounded-full animate-ping opacity-75" :class="getStatusClasses(getStatus({ latest_plan: hoveredPlanData, created_at: hoveredPlanData.customer.created_at })).includes('emerald-500') ? 'bg-emerald-500' : 'bg-red-500'"></div>
                                    <div class="relative w-full h-full rounded-full" :class="getStatusClasses(getStatus({ latest_plan: hoveredPlanData, created_at: hoveredPlanData.customer.created_at })).includes('emerald-500') ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]' : 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.5)]'"></div>
                                </div>
                                <span class="text-[8px] xs:text-[9px] font-black uppercase text-gray-500 tracking-widest">
                                    {{ formatStatusText(getStatus({ latest_plan: hoveredPlanData, created_at: hoveredPlanData.customer.created_at })) }}
                                </span>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 2px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.custom-scrollbar:hover::-webkit-scrollbar-thumb {
    background: #cbd5e1;
}

@keyframes blink-red {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; background-color: #ef4444; }
}

@keyframes blink-emerald {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; background-color: #10b981; }
}

@keyframes blink-amber {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; background-color: #f59e0b; }
}

.touch-action-manipulation {
    touch-action: manipulation;
}
</style>
