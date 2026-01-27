<script setup>
import { computed, ref } from 'vue';

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

// Map plans to dates
const getPlansForDate = (date) => {
    const dateStr = date.toISOString().split('T')[0];
    const plans = [];
    
    props.customers.forEach(customer => {
        if (customer.plans) {
            customer.plans.forEach(plan => {
                if (plan.planning_date && plan.planning_date.startsWith(dateStr)) {
                    plans.push({
                        ...plan,
                        customer: customer // Pass the full customer object including 'plans' history
                    });
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

const currentFormattedDate = computed(() => {
    const today = new Date();
    return today.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
});

const getStatusClasses = (status) => {
    const base = 'px-2 py-1 rounded-lg text-[10px] mobile:text-[9px] font-bold transition-all duration-300 shadow-sm border border-black/5 ';
    
    switch (status) {
        case 'none': return base + 'bg-gray-100 text-slate-500';
        case 'created': return base + 'bg-red-500 text-white';
        case 'reported': return base + 'bg-emerald-500 text-white';
        case 'expired': return base + 'bg-red-600 text-white animate-pulse';
        case 'warning': return base + 'bg-amber-500 text-white animate-pulse';
        case 'no_plan_warning': return base + 'bg-red-700 text-white animate-pulse';
        case 'rejected_final': return base + 'bg-rose-600 text-white';
        case 'rejected_warning': return base + 'bg-rose-600 text-white animate-pulse';
        case 'closed': return base + 'bg-slate-900 text-white';
        case 'approved': return base + 'bg-emerald-500 text-white shadow-emerald-200/50';
        default: return base + 'bg-gray-100 text-slate-500';
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
    // Offset to keep tooltip away from cursor
    tooltipPos.value = { 
        x: event.clientX + 15, 
        y: event.clientY + 15 
    };
};
</script>

<template>
    <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">
        <!-- Calendar Header Information -->
        <div class="p-6 bg-gradient-to-br from-white to-gray-50/50 border-b border-gray-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
            <div class="flex flex-col gap-4 w-full md:w-auto">
                <div class="flex items-center justify-between gap-4">
                    <h3 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2">
                        <span class="text-emerald-600">{{ monthNames[month - 1] }}</span>
                        <span class="text-slate-400">{{ year }}</span>
                    </h3>

                    <div class="flex items-center gap-1 bg-white border border-slate-200 p-1 rounded-2xl shadow-sm">
                        <button @click="navigateMonth('prev')" 
                                class="p-1.5 sm:p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-xl transition-all active:scale-90">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 focus:outline-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <div class="w-px h-4 bg-slate-100 mx-0.5 sm:mx-1"></div>
                        <button @click="navigateMonth('next')" 
                                class="p-1.5 sm:p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-xl transition-all active:scale-90">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 focus:outline-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>

                <p class="text-[10px] sm:text-xs text-slate-500 font-bold inline-flex items-center gap-1.5 bg-slate-100 px-3 py-1.5 rounded-full w-fit">
                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="whitespace-nowrap uppercase tracking-widest opacity-60">Today:</span>
                    <span class="text-slate-700">{{ currentFormattedDate }}</span>
                </p>
            </div>
            
            <div class="flex flex-col items-start md:items-end gap-3 self-stretch md:self-auto">
                <div class="grid grid-cols-2 sm:flex sm:flex-wrap items-center gap-x-4 gap-y-2 text-[9px] md:text-[10px] font-black text-slate-400 bg-white/50 backdrop-blur-sm border border-slate-100 p-3 rounded-2xl shadow-sm w-full md:w-auto">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-red-500 shadow-sm shadow-red-200"></span>
                        <span>Created</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 shadow-sm shadow-emerald-200"></span>
                        <span>Reported</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-rose-600 shadow-sm shadow-rose-200"></span>
                        <span>Expired</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-slate-900 shadow-sm shadow-slate-200"></span>
                        <span>Closed</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Days of Week Header -->
        <div class="grid grid-cols-7 border-b border-gray-100 bg-gray-50/50">
            <div v-for="day in daysOfWeek" :key="day" 
                 class="py-3 text-center text-[10px] md:text-xs font-bold text-gray-500 uppercase tracking-widest">
                {{ day }}
            </div>
        </div>

        <!-- Calendar Grid -->
        <div class="grid grid-cols-7 auto-rows-[minmax(160px,auto)] md:auto-rows-[minmax(140px,auto)] mobile:auto-rows-[minmax(110px,auto)]">
            <div v-for="(item, index) in calendarDays" :key="index"
                 class="p-1.5 md:p-3 border-r border-b border-gray-50 group transition-colors hover:bg-emerald-50/10 relative"
                 :class="{ 
                    'bg-gray-50/30 text-gray-400': !item.currentMonth, 
                    'border-r-0': (index + 1) % 7 === 0,
                    'tablet:p-2 mobile:p-1': true
                 }">
                
                <div class="flex justify-between items-start mb-1.5 mobile:mb-1">
                    <span class="text-xs md:text-sm font-black w-7 h-7 md:w-8 md:h-8 flex items-center justify-center rounded-full transition-all shrink-0 aspect-square p-0 leading-none"
                          :class="isToday(item.date) ? 'bg-emerald-600 text-white shadow-md shadow-emerald-200/50 ring-2 ring-emerald-50' : 'text-slate-600 group-hover:text-emerald-700'">
                        {{ item.day }}
                    </span>
                    
                    <button @click="handleCreatePlan(item.date)" 
                            class="opacity-0 group-hover:opacity-100 p-1.5 text-emerald-600 bg-emerald-50 md:bg-transparent hover:bg-emerald-100 rounded-xl transition-all transform hover:scale-110 mobile:opacity-100">
                        <svg class="w-4 h-4 md:w-5 md:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>
                </div>

                <!-- Plans List -->
                <div class="space-y-1.5 md:space-y-2 max-h-[160px] md:max-h-[180px] mobile:max-h-[120px] overflow-y-auto custom-scrollbar pr-0.5">
                    <div v-for="plan in getPlansForDate(item.date)" :key="plan.id"
                         @click="handlePlanClick(plan.customer)"
                         @mouseenter="handleMouseEnter(plan, $event)"
                         @mousemove="updateTooltipPos($event)"
                         @mouseleave="handleMouseLeave"
                         class="cursor-pointer group/item relative w-full"
                    >
                        <div :class="getStatusClasses(getStatus({ latest_plan: plan, created_at: plan.customer.created_at }))"
                             :style="getBlinkStyle(plan)"
                             class="!py-1 !px-2 rounded-xl border-b-[2px] border-black/10 active:scale-95 transition-all shadow-sm hover:translate-x-0.5"
                        >
                            <div class="flex items-center gap-2">
                                <span class="w-1 h-1 md:w-1.5 md:h-1.5 bg-white/40 rounded-full flex-shrink-0"></span>
                                <span class="truncate leading-tight flex-1 text-left text-[9px] md:text-[10px] font-bold tracking-tight">{{ plan.customer.company_name }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Empty state hint -->
                <div v-if="item.currentMonth && getPlansForDate(item.date).length === 0" 
                     class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-0 group-hover:opacity-10 transition-opacity">
                    <svg class="w-10 h-10 md:w-12 md:h-12 text-emerald-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 4v16m8-8H4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Global Floating Tooltip (Teleported to avoid clipping) -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="hoveredPlanData" 
                 :style="{ top: tooltipPos.y + 'px', left: tooltipPos.x + 'px' }"
                 class="fixed z-[9999] pointer-events-none"
            >
                <div class="bg-slate-900/90 backdrop-blur-xl text-white p-4 rounded-[1.5rem] shadow-2xl border border-white/10 w-60 md:w-64 overflow-hidden relative group">
                    <!-- Subtle Glow Background -->
                    <div class="absolute -top-20 -right-20 w-40 h-40 bg-emerald-500/20 blur-[60px] rounded-full pointer-events-none"></div>
                    
                    <div class="flex flex-col gap-3 relative z-10">
                        <div class="flex justify-between items-center">
                            <span class="px-2.5 py-0.5 bg-white/10 backdrop-blur-md text-emerald-400 text-[8px] font-black rounded-full uppercase tracking-[0.1em] border border-white/5">
                                {{ hoveredPlanData.activity_type }}
                            </span>
                            <div class="flex items-center gap-1.5 text-[9px] text-slate-400 font-bold bg-white/5 px-2 py-0.5 rounded-full">
                                <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ new Date(hoveredPlanData.planning_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                            </div>
                        </div>
                        
                        <div class="space-y-0.5">
                            <p class="text-[8px] text-emerald-500 font-black uppercase tracking-widest opacity-80">Customer</p>
                            <h4 class="text-lg font-black text-white leading-[1.1] tracking-tight">
                                {{ hoveredPlanData.customer.company_name }}
                            </h4>
                            <div class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-emerald-500/10 text-emerald-400 text-[9px] font-bold rounded-lg border border-emerald-500/20">
                                <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                {{ hoveredPlanData.customer.product?.name || 'No Product' }}
                            </div>
                        </div>

                        <div v-if="hoveredPlanData.description" class="bg-white/5 p-3 rounded-xl border border-white/5 text-[11px] text-slate-300 leading-relaxed font-medium">
                            <span class="text-emerald-500 font-black block text-[7px] uppercase tracking-widest mb-1 opacity-50">Description</span>
                            "{{ hoveredPlanData.description }}"
                        </div>
                        
                        <div class="flex items-center justify-between pt-1.5 border-t border-white/5">
                            <div class="flex items-center gap-1.5">
                                <div class="w-1.5 h-1.5 rounded-full relative">
                                    <div class="absolute inset-0 rounded-full animate-ping opacity-75" :class="getStatusClasses(getStatus({ latest_plan: hoveredPlanData, created_at: hoveredPlanData.customer.created_at })).includes('emerald-500') ? 'bg-emerald-500' : 'bg-red-500'"></div>
                                    <div class="relative w-full h-full rounded-full" :class="getStatusClasses(getStatus({ latest_plan: hoveredPlanData, created_at: hoveredPlanData.customer.created_at })).includes('emerald-500') ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]' : 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.5)]'"></div>
                                </div>
                                <span class="text-[9px] font-black uppercase text-slate-400 tracking-widest">
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
</style>
