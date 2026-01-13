<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';

import { computed, ref, onMounted, watch } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

defineOptions({ layout: NexusLayout });

const props = defineProps({
    totalActivitiesCount: { type: Number, default: 0 },
    activeCustomerCount: { type: Number, default: 0 },
    totalCustomerCount: { type: Number, default: 0 },
    activitiesTrend: { type: Array, default: () => [] },
    activeCustomersTrend: { type: Array, default: () => [] },
    totalCustomersTrend: { type: Array, default: () => [] },
    noPlanningCount: Number,
    warningPlanningCount: Number,
    lateRejectedCount: Number,
    completedCount: Number,
    closingCount: Number,
    onProgressCount: Number,
    activityDistribution: { type: Object, default: () => ({}) },
    customerHealthStats: { type: Object, default: () => ({}) },
    inactiveCustomers: { type: Array, default: () => [] }, // New Prop
    customerActivityBreakdown: { type: Array, default: () => [] }, // Activity Breakdown
    dailyActivityTrend: { type: Array, default: () => [] }, // Daily Activity Trend
    teams: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    timeUnit: { type: String, default: 'days' },
});

const filterForm = ref({
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
    team_id: props.filters.team_id || '',
    user_id: props.filters.user_id || '',
});

// Notification Logic
const markRead = (notification) => {
    const visit = () => {
        if (notification.data && notification.data.link) {
            router.visit(notification.data.link);
        }
    };

    if (!notification.read_at) {
        router.post(route('notifications.read', notification.id), {}, { 
            preserveScroll: true,
            onSuccess: () => visit()
        });
    } else {
        visit();
    }
};

const markAllRead = () => {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
};

// Watch for filter changes
watch(filterForm.value, (val) => {
    // Basic debounce could be added here if needed, but for now instant update on change (or blur for dates)
    // Actually for date inputs, waiting for valid input is better. 
    // And for selects, instant is fine.
    // Using deep: true for object watch if reactive, but ref.value watch works for property mutation? 
    // Wait, watching a ref object needs deep: true or watching the ref itself.
    router.get(route('dashboard'), val, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, { deep: true });

// Max Scale for Inactive Chart
const maxInactiveDays = computed(() => {
    if (!props.inactiveCustomers || props.inactiveCustomers.length === 0) return 0;
    const values = props.inactiveCustomers.map(c => Number(c.days_inactive) || 0);
    return Math.max(...values, 0);
});

// Y-Axis Ticks Calculation
const yAxisTicks = computed(() => {
    let rawMax = maxInactiveDays.value;
    
    // Add breathing room (buffer) so bars don't hit the top
    // If rawMax is small, ensure at least 5.
    // If rawMax is larger, add ~20% padding.
    let max = rawMax < 5 ? 5 : Math.ceil(rawMax * 1.2);

    // Ensure strictly integer ticks and adaptive scaling
    if (max <= 10) {
        // For small ranges (e.g. 0-10), show every integer or every other integer
        // If max is 5: 0, 1, 2, 3, 4, 5
        const ticks = [];
        for (let i = max; i >= 0; i--) ticks.push(i);
        return ticks;
    }

    // For larger ranges, use 5 fixed steps
    const steps = 5;
    const stepSize = Math.ceil(max / steps);
    const ticks = [];
    for (let i = steps; i >= 0; i--) {
        ticks.push(i * stepSize);
    }
    return ticks;
});

const yAxisMax = computed(() => yAxisTicks.value[0]);


// Pagination for Inactive Chart
const currentPage = ref(1);
const itemsPerPage = 15;

const paginatedCustomers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return props.inactiveCustomers.slice(start, start + itemsPerPage);
});

const totalPages = computed(() => Math.ceil((props.inactiveCustomers.length || 0) / itemsPerPage));

const nextPage = () => {
    if (currentPage.value < totalPages.value) currentPage.value++;
};

const prevPage = () => {
    if (currentPage.value > 1) currentPage.value--;
};

// Pagination for Activity Breakdown Chart
const currentActivityPage = ref(1);
const activityItemsPerPage = 12;

const paginatedActivityCustomers = computed(() => {
    const start = (currentActivityPage.value - 1) * activityItemsPerPage;
    return props.customerActivityBreakdown.slice(start, start + activityItemsPerPage);
});

const activityTotalPages = computed(() => Math.ceil((props.customerActivityBreakdown.length || 0) / activityItemsPerPage));

const nextActivityPage = () => {
    if (currentActivityPage.value < activityTotalPages.value) currentActivityPage.value++;
};

const prevActivityPage = () => {
    if (currentActivityPage.value > 1) currentActivityPage.value--;
};

// Helper: Get all unique activity types
const activityTypes = computed(() => {
    const types = new Set();
    props.customerActivityBreakdown.forEach(customer => {
        Object.keys(customer.activities).forEach(type => types.add(type));
    });
    return Array.from(types);
});

// Activity Colors (matching reference image)
const activityColors = computed(() => ({
    'Visit': '#d946ef',
    'Ent': '#f59e0b', // Amber (Swapped with Call)
    'Entertainment': '#f59e0b',
    'Online meeting': '#ef4444',
    'Online Meeting': '#ef4444',
    'Call': '#22c55e', // Green (Requested)
    'Survey': '#8b5cf6',
    'Admin/Tender': '#06b6d4', // Cyan
    'Tender': '#06b6d4', // Cyan
    'Administration tender': '#06b6d4', // Cyan
    'Proposal': '#f97316',
    'Presentation': '#0ea5e9',
    'Closing': '#000000',
    'Negotiation': '#ec4899',
    'Other': '#6366f1' // Indigo (Distinct from Green/Cyan)
}));

// Max total activity for Y-axis
const maxTotalActivity = computed(() => {
    if (!props.customerActivityBreakdown || props.customerActivityBreakdown.length === 0) return 10;
    return Math.max(...props.customerActivityBreakdown.map(c => c.total), 10);
});

const page = usePage();
const isSuperAdmin = computed(() => {
    return page.props.auth?.user?.roles?.includes('Super Admin');
});

// Helper to generate smooth SVG path for sparkline (Cubic Bezier)
const getSparklinePath = (data) => {
    if (!data || data.length < 2) return '';
    const width = 200; // Higher resolution for smoother curve
    const height = 60;
    const max = Math.max(...data, 1);
    const min = Math.min(...data, 0);
    const range = max - min || 1;
    
    // Calculate points
    const stepX = width / (data.length - 1);
    const points = data.map((val, i) => {
        const x = i * stepX;
        const normalizedY = (val - min) / range;
        const effectiveY = range === 0 ? 0.5 : normalizedY; 
        const y = height - (effectiveY * height);
        return { x, y };
    });

    // Generate Cubic Bezier Path
    let d = `M ${points[0].x} ${points[0].y}`;
    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i];
        const p1 = points[i + 1];
        
        // Control points for smooth curve
        const cp1x = p0.x + (p1.x - p0.x) / 2;
        const cp1y = p0.y;
        const cp2x = p1.x - (p1.x - p0.x) / 2;
        const cp2y = p1.y;

        d += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${p1.x} ${p1.y}`;
    }
    
    return d;
};

// Helper for fill gradient path (closes the loop at the bottom)
const getSparklineFillPath = (data) => {
    const path = getSparklinePath(data);
    return `${path} V 60 H 0 Z`;
};

// --- CHART LOGIC (SVG) ---
const calculateChartSegments = (data, radius = 40, innerRadius = 0) => {
    let total = Object.values(data).reduce((sum, val) => sum + val, 0);
    if (total === 0) return [];
    
    let startAngle = 0;
    const segments = [];
    const centerX = 50;
    const centerY = 50;
    
    // Default Palette (Backup)
    const defaultColors = ['#0ea5e9', '#8b5cf6', '#f59e0b', '#ec4899', '#10b981', '#6366f1'];
    let colorIndex = 0;

    // Specific Color Map based on User Request
    const colorMap = {
        'Online Meeting': '#ef4444', // Red
        'Visit': '#d946ef',          // Purple/Magenta
        'Survey': '#8b5cf6',         // Violet
        'Call': '#eab308',           // Yellow/Gold
        'Other': '#9ca3af',          // Gray
        'Negotiation': '#ec4899',    // Pink
        'Ent': '#22c55e',            // Green
        'Presentation': '#0ea5e9',   // Sky Blue
        'Administration tender': '#4b5563', // Dark Gray
        'Proposal': '#f97316',       // Orange
        'On Track': '#10b981',       // Emerald (Health)
        'Warning': '#ef4444'         // Red (Health)
    };

    // Filter out zero values and sort by value descending for better visualization
    const entries = Object.entries(data)
        .filter(([_, val]) => val > 0)
        .sort((a, b) => b[1] - a[1]);

    for (const [label, value] of entries) {
        let angle = (value / total) * 360;
        
        // Fix to ensure 100% segments render (SVG arc fails if start == end)
        const isFullCircle = angle >= 360;
        if (isFullCircle) angle = 359.99;

        const largeArcFlag = angle > 180 ? 1 : 0;
        
        // Polar to Cartesian (Chart Path)
        const startRad = (startAngle - 90) * Math.PI / 180;
        const endRad = (startAngle + angle - 90) * Math.PI / 180;

        const startX = centerX + radius * Math.cos(startRad);
        const startY = centerY + radius * Math.sin(startRad);
        const endX = centerX + radius * Math.cos(endRad);
        const endY = centerY + radius * Math.sin(endRad);

        // Calculate Text Position (Middle of segment)
        const midAngle = startAngle + angle / 2;
        const midRad = (midAngle - 90) * Math.PI / 180;
        // Position text in the middle of the ring
        const labelRadius = innerRadius + (radius - innerRadius) / 2;
        const labelX = centerX + labelRadius * Math.cos(midRad);
        const labelY = centerY + labelRadius * Math.sin(midRad);
        
        // Build Path
        let pathStr = "";
        
        if (innerRadius > 0) {
            // Doughnut
            const startInnerX = centerX + innerRadius * Math.cos(startRad);
            const startInnerY = centerY + innerRadius * Math.sin(startRad);
            const endInnerX = centerX + innerRadius * Math.cos(endRad);
            const endInnerY = centerY + innerRadius * Math.sin(endRad);

            pathStr = [
                `M ${startX} ${startY}`,
                `A ${radius} ${radius} 0 ${largeArcFlag} 1 ${endX} ${endY}`,
                `L ${endInnerX} ${endInnerY}`,
                `A ${innerRadius} ${innerRadius} 0 ${largeArcFlag} 0 ${startInnerX} ${startInnerY}`,
                `Z`
            ].join(' ');
        } else {
            // Pie
            pathStr = [
                `M ${centerX} ${centerY}`,
                `L ${startX} ${startY}`,
                `A ${radius} ${radius} 0 ${largeArcFlag} 1 ${endX} ${endY}`,
                `Z`
            ].join(' ');
        }
        
        // Resolve Color
        let color = colorMap[Object.keys(colorMap).find(k => k.toLowerCase() === label.toLowerCase())] 
                   || colorMap[label] 
                   || defaultColors[colorIndex % defaultColors.length];

        const percentage = Math.round((value / total) * 100);

        segments.push({
            d: pathStr,
            color: color,
            label: label,
            value: value,
            percentage: percentage,
            formattedPercentage: (value / total * 100).toFixed(1) + '%',
            labelX: labelX,
            labelY: labelY,
            // Only show percentage label if arc is big enough (e.g. > 5%)
            showLabel: percentage > 4 
        });
        
        startAngle += angle;
        colorIndex++;
    }
    return segments;
};

// Computed Segments
// Both are now doughnuts with the same thickness (Radius 40, Inner 25)
const activitySegments = computed(() => calculateChartSegments(props.activityDistribution, 40, 25)); 
const healthSegments = computed(() => calculateChartSegments(props.customerHealthStats, 40, 25));

// Computed Legend for Customer Health (Always show both keys)
const healthLegendItems = computed(() => {
    const total = props.totalCustomerCount || 1;
    const stats = props.customerHealthStats || {};
    
    return [
        { 
            label: 'On Track', 
            value: stats['On Track'] || 0, 
            color: '#10b981',
            percentage: ((stats['On Track'] || 0) / total * 100).toFixed(1) 
        },
        { 
            label: 'Warning', 
            value: stats['Warning'] || 0, 
            color: '#ef4444',
            percentage: ((stats['Warning'] || 0) / total * 100).toFixed(1) 
        }
    ];
});

// Chart.js Instances
const activityTrendChart = ref(null);
const activityMarketingChart = ref(null);
const customerHealthChart = ref(null);
let activityTrendChartInstance = null;
let activityMarketingChartInstance = null;
let customerHealthChartInstance = null;

// Activity Marketing Chart Data
const activityMarketingData = computed(() => {
    const distribution = props.activityDistribution || {};
    const rawLabels = Object.keys(distribution);
    const data = Object.values(distribution);
    const total = data.reduce((a, b) => a + b, 0);

    const labels = rawLabels; // percentages removed as requested

    const colors = rawLabels.map(label => {
        const colorMap = {
            'Online Meeting': '#ef4444',
            'Visit': '#d946ef',
            'Survey': '#8b5cf6',
            'Call': '#22c55e', // Green
            'Other': '#6366f1', // Indigo
            'Negotiation': '#ec4899',
            'Ent': '#f59e0b', // Amber
            'Presentation': '#0ea5e9',
            'Administration tender': '#06b6d4',
            'Proposal': '#f97316',
        };
        return colorMap[label] || '#6366f1';
    });
    
    return { labels, data, colors };
});

// Customer Health Chart Data (Now Planning Health)
const customerHealthData = computed(() => {
    const stats = props.customerHealthStats || {};
    const data = [
        stats['No Planning'] || 0, 
        stats['Planning Approved'] || 0, 
        stats['Planning Rejected'] || 0,
        stats['Planning Closing'] || 0,
        stats['Planning Expired'] || 0,
        stats['Warning Planning'] || 0
    ];
    const total = data.reduce((a, b) => a + b, 0);
    const rawLabels = ['No Planning', 'Approved', 'Rejected', 'Closing', 'Expired', 'Warning'];
    
    const labels = rawLabels; // percentages removed as requested

    return {
        labels: labels,
        data: data,
        colors: [
            '#d1d5db', // No Planning (Light Gray)
            '#22c55e', // Approved (Green)
            '#ef4444', // Rejected (Red)
            '#000000', // Closing (Black)
            '#6b7280', // Expired (Gray)
            '#f59e0b'  // Warning (Amber)
        ]
    };
});

onMounted(() => {
    // Dynamically load Chart.js and chartjs-plugin-datalabels
    if (typeof Chart === 'undefined') {
        const chartScript = document.createElement('script');
        chartScript.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
        
        const datalabelsScript = document.createElement('script');
        datalabelsScript.src = 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js';
        
        chartScript.onload = () => {
            datalabelsScript.onload = () => {
                if (typeof ChartDataLabels !== 'undefined') {
                    Chart.register(ChartDataLabels);
                }
                initActivityMarketingChart();
                initCustomerHealthChart();
                initActivityTrendChart();
            };
            document.head.appendChild(datalabelsScript);
        };
        document.head.appendChild(chartScript);
    } else {
        // Chart.js already loaded, load datalabels plugin
        if (typeof ChartDataLabels === 'undefined') {
            const datalabelsScript = document.createElement('script');
            datalabelsScript.src = 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js';
            datalabelsScript.onload = () => {
                if (typeof ChartDataLabels !== 'undefined') {
                    Chart.register(ChartDataLabels);
                }
                initActivityMarketingChart();
                initCustomerHealthChart();
                initActivityTrendChart();
            };
            document.head.appendChild(datalabelsScript);
        } else {
            Chart.register(ChartDataLabels);
            initActivityMarketingChart();
            initCustomerHealthChart();
            initActivityTrendChart();
        }
    }
});

function initActivityMarketingChart() {
    if (!activityMarketingChart.value || !activityMarketingData.value) return;
    
    const ctx = activityMarketingChart.value.getContext('2d');
    const chartData = activityMarketingData.value;
    
    if (activityMarketingChartInstance) {
        activityMarketingChartInstance.destroy();
    }
    
    activityMarketingChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: chartData.labels,
            datasets: [{
                data: chartData.data,
                backgroundColor: chartData.colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            animation: false,
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                },
                datalabels: {
                    display: false // Hide text on chart slices
                }
            }
        }
    });
}

function initCustomerHealthChart() {
    if (!customerHealthChart.value || !customerHealthData.value) return;
    
    const ctx = customerHealthChart.value.getContext('2d');
    const chartData = customerHealthData.value;
    
    if (customerHealthChartInstance) {
        customerHealthChartInstance.destroy();
    }
    
    const totalHealth = chartData.data.reduce((acc, val) => acc + val, 0);
    
    customerHealthChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: chartData.labels,
            datasets: [{
                data: chartData.data,
                backgroundColor: chartData.colors,
                borderWidth: 3,
                borderColor: '#fff'
            }]
        },
        options: {
            animation: false,
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                },
                datalabels: {
                    display: false // Hide text on chart slices
                }
            }
        }
    });
}

function initActivityTrendChart() {
    if (!activityTrendChart.value || !props.dailyActivityTrend) return;
    
    const ctx = activityTrendChart.value.getContext('2d');
    
    // Process data - last 30 days
    const labels = props.dailyActivityTrend.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });
    
    const data = props.dailyActivityTrend.map(item => item.count || 0);
    
    if (activityTrendChartInstance) {
        activityTrendChartInstance.destroy();
    }
    
    activityTrendChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Activities',
                data: data,
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#f59e0b',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    callbacks: {
                        label: function(context) {
                            return `Activities: ${context.parsed.y}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45,
                        font: {
                            size: 11
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        stepSize: 5,
                        font: {
                            size: 11
                        }
                    }
                }
            },
            interaction: {
                mode: 'index',
                intersect: false,
            }
        }
    });
}

// Watch for data changes and update charts
watch(() => props.activityDistribution, () => {
    if (typeof Chart !== 'undefined' && activityMarketingChart.value) {
        initActivityMarketingChart();
    }
}, { deep: true });

watch(() => props.customerHealthStats, () => {
    if (typeof Chart !== 'undefined' && customerHealthChart.value) {
        initCustomerHealthChart();
    }
}, { deep: true });
</script>

<template>
    <Head title="Dashboard" />
    
    <!-- DASHBOARD FOR SUPER ADMIN -->
    <template v-if="isSuperAdmin">
        <!-- Dashboard Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Analytics Dashboard</h1>
            <p class="text-gray-500 text-sm mt-1">Comprehensive data insights and performance metrics</p>
        </div>

        <!-- Date Range & Filters -->
        <div class="bg-gray-100 rounded-2xl pt-5 px-3 pb-3 mb-8">
            <h2 class="text-gray-900 text-lg font-bold ml-3 mb-4">Date Range & Filters</h2>
            <div class="bg-white rounded-2xl p-5">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Start Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" v-model="filterForm.start_date" class="w-full rounded-lg border-gray-300 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    </div>
                    <!-- End Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" v-model="filterForm.end_date" class="w-full rounded-lg border-gray-300 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    </div>
                    <!-- Filter Teams -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Filter Teams</label>
                        <select v-model="filterForm.team_id" class="w-full rounded-lg border-gray-300 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                            <option value="">All Teams</option>
                            <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                        </select>
                    </div>
                    <!-- Filter User -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Filter User</label>
                        <select v-model="filterForm.user_id" class="w-full rounded-lg border-gray-300 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                            <option value="">All Users</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 mb-6 md:mb-8">
            
            <!-- Stat Card 1: Total Activities -->
            <div class="bg-gray-100 rounded-2xl pt-5 px-3 pb-3">
                <h3 class="text-gray-900 text-lg font-bold ml-3 mb-4">Total Activities</h3>
                <div class="bg-white rounded-2xl p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-900 text-4xl font-extrabold mb-1">{{ totalActivitiesCount }}</p>
                            <p class="text-gray-500 text-sm font-medium">All reports</p>
                        </div>
                        <div class="w-16 h-16 bg-[#4169E1] rounded-full flex items-center justify-center">
                            <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stat Card 2: Active Customers -->
            <div class="bg-gray-100 rounded-2xl pt-5 px-3 pb-3">
                <h3 class="text-gray-900 text-lg font-bold ml-3 mb-4">Active Customers</h3>
                <div class="bg-white rounded-2xl p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-900 text-4xl font-extrabold mb-1">{{ activeCustomerCount }}</p>
                            <p class="text-green-600 text-sm font-medium">On progress</p>
                        </div>
                        <div class="w-16 h-16 bg-[#059669] rounded-full flex items-center justify-center">
                            <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
    
            <!-- Stat Card 3: Total Customers -->
            <div class="bg-gray-100 rounded-2xl pt-5 px-3 pb-3">
                <h3 class="text-gray-900 text-lg font-bold ml-3 mb-4">Total Customers</h3>
                <div class="bg-white rounded-2xl p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-900 text-4xl font-extrabold mb-1">{{ totalCustomerCount }}</p>
                            <p class="text-gray-500 text-sm font-medium">In database</p>
                        </div>
                        <div class="w-16 h-16 bg-[#FFD700] rounded-full flex items-center justify-center">
                            <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
        <!-- Chart Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-6 md:mb-8">
            
            <!-- Chart 1: Activity Marketing (Doughnut) -->
            <div class="bg-gray-100 rounded-2xl pt-5 px-3 pb-3">
                <h3 class="text-gray-900 text-lg font-bold ml-3 mb-4">Activity Marketing</h3>
                <div class="bg-white rounded-2xl p-4 md:p-5">
                    <div class="w-full">
                        <div class="h-64">
                            <canvas ref="activityMarketingChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart 2: Customer Health Status (Doughnut) -->
            <div class="bg-gray-100 rounded-2xl pt-5 px-3 pb-3">
                <h3 class="text-gray-900 text-lg font-bold ml-3 mb-4">Planning Health</h3>
                <div class="bg-white rounded-2xl p-4 md:p-5">
                    <div class="w-full">
                        <div class="h-64">
                            <canvas ref="customerHealthChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 3: Customer Health Status (Inactive Duration) -->
        <div class="bg-gray-100 rounded-2xl pt-5 px-3 pb-3 mb-6 md:mb-8">
            <h3 class="text-gray-900 text-lg font-bold ml-3 mb-4">Customer Health Status (Inactive Duration)</h3>
            <div class="bg-white rounded-2xl p-4 md:p-5">
                <div class="flex gap-4 pl-4 pr-8 relative mb-4">
                <!-- Y-Axis -->
                <div class="flex flex-col justify-between items-end h-80 relative shrink-0 w-8 select-none">
                    <div class="absolute -left-10 top-1/2 -translate-y-1/2 -rotate-90 text-xs font-bold text-gray-500 whitespace-nowrap tracking-wide">
                        Day Inactive
                    </div>
                    <!-- Safe Loop with Index -->
                    <div v-for="(tick, i) in yAxisTicks" :key="'tick-'+i" class="text-xs text-gray-400 font-medium h-4 flex items-center transform translate-y-2">
                        {{ tick }}
                    </div>
                </div>

                <!-- Chart Area -->
                <div class="flex-1 relative h-80 border-l border-b border-gray-100">
                    <!-- Grid Lines -->
                    <div class="absolute inset-0 w-full h-full flex flex-col justify-between pointer-events-none">
                        <div v-for="(tick, i) in yAxisTicks" :key="'grid-'+i" class="w-full border-t border-gray-100/60 h-0 transform translate-y-2"></div>
                    </div>

                    <!-- Bars -->
                    <div class="relative w-full h-full flex items-end justify-between px-2 gap-2 z-10">
                         <div v-if="paginatedCustomers.length === 0" class="absolute inset-0 flex items-center justify-center text-gray-400">No Data Available</div>

                         <div v-for="customer in paginatedCustomers" :key="customer.id" class="group flex flex-col justify-end items-center flex-1 h-full relative">
                              
                              <!-- Bar (Pill Shape) -->
                              <div class="w-full max-w-[40px] rounded-lg transition-all duration-500 hover:opacity-90 relative"
                                   :class="customer.is_warning ? 'bg-red-500' : 'bg-emerald-400'" 
                                   :style="{ 
                                        height: Math.max((customer.days_inactive / (yAxisMax || 1) * 100), 1) + '%',
                                        backgroundColor: customer.is_warning ? '#ef4444' : '#34d399'
                                   }">
                                   
                                  <!-- Tooltip (Restored) -->
                                  <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:flex flex-col items-center z-50 pointer-events-none transition-opacity duration-200 min-w-max">
                                      <div class="bg-gray-900 text-white text-xs rounded-md py-2 px-3 shadow-xl mb-1 flex flex-col items-center gap-1">
                                          <span class="font-bold uppercase tracking-wide border-b border-gray-700 pb-1 mb-1 whitespace-nowrap">
                                              {{ customer.name }}{{ customer.product ? ' (' + customer.product + ')' : '' }}
                                          </span>
                                          <div class="flex items-center gap-2">
                                              <span class="w-2 h-2 rounded-full" :class="customer.is_warning ? 'bg-red-400' : 'bg-emerald-400'"></span>
                                              <span>Inactive: {{ customer.days_inactive }} days</span>
                                          </div>
                                      </div>
                                      <div class="w-2 h-2 bg-gray-900 rotate-45 -mt-1.5"></div>
                                  </div>
                              </div>
                              
                              <!-- Label -->
                              <div class="absolute top-full left-1/2 mt-3 transform rotate-45 origin-top-left text-[10px] text-gray-500 font-medium whitespace-nowrap w-32 truncate group-hover:text-gray-800 transition-colors z-0">
                                  {{ customer.name }}
                              </div>
                         </div>
                    </div>
                </div>
            </div>

                    <div class="h-32"></div>

                    <div class="text-center text-sm font-bold text-gray-700 mb-4 select-none">
                        Customer Name (Product)
                    </div>

                    <!-- Pagination -->
                    <div class="flex items-center justify-end gap-4 mt-2 px-4 border-t border-gray-100 pt-4 select-none">
                        <span class="text-sm text-gray-500">Page {{ currentPage }} of {{ totalPages }}</span>
                        <div class="flex gap-2">
                            <button @click="prevPage" :disabled="currentPage === 1" 
                                    class="px-3 py-1 text-sm bg-gray-100 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium text-gray-700">
                                « Prev
                            </button>
                            <button @click="nextPage" :disabled="currentPage === totalPages"
                                    class="px-3 py-1 text-sm bg-blue-50 text-blue-600 rounded hover:bg-blue-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium">
                                Next »
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Chart 4: Companies Activity Breakdown (Stacked Bar) -->
        <div class="bg-gray-100 rounded-2xl pt-5 px-3 pb-3 mb-6 md:mb-8">
            <h3 class="text-gray-900 text-lg font-bold ml-3 mb-4">Companies Activity Breakdown</h3>
            <div class="bg-white rounded-2xl p-4 md:p-5">
                <div class="flex gap-4 pl-4 pr-8 relative mb-4">
                    <!-- Y-Axis (All Activity) -->
                <div class="flex flex-col justify-between items-end h-80 relative shrink-0 w-10 select-none">
                    <div class="absolute -left-12 top-1/2 -translate-y-1/2 -rotate-90 text-xs font-bold text-gray-500 whitespace-nowrap tracking-wide">
                        ALL ACTIVITY
                    </div>
                    <!-- Dynamic Y-Axis Ticks -->
                    <div v-for="i in 6" :key="'ytick-'+i" class="text-xs text-gray-400 font-medium h-4 flex items-center transform translate-y-2">
                        {{ Math.round(maxTotalActivity * (6-i) / 5) }}
                    </div>
                </div>

                <!-- Chart Area -->
                <div class="flex-1 relative h-80 border-l border-b border-gray-100">
                    <!-- Grid Lines -->
                    <div class="absolute inset-0 w-full h-full flex flex-col justify-between pointer-events-none">
                        <div v-for="i in 6" :key="'ygrid-'+i" class="w-full border-t border-gray-100/60 h-0 transform translate-y-2"></div>
                    </div>

                    <!-- Stacked Bars Container -->
                    <div class="relative w-full h-full flex items-end justify-between px-2 gap-1 z-10">
                         <div v-if="paginatedActivityCustomers.length === 0" class="absolute inset-0 flex items-center justify-center text-gray-400">No Activity Data</div>

                         <div v-for="(customer, cidx) in paginatedActivityCustomers" :key="'act-'+customer.id" class="group flex flex-col justify-end items-center flex-1 h-full relative">
                              
                              <!-- Stacked Bar (Pill Shape) -->
                              <div class="w-full max-w-[50px] rounded-lg overflow-hidden relative flex flex-col-reverse items-stretch"
                                   :style="{ height: Math.max((customer.total / maxTotalActivity * 100), 1) + '%' }">
                                   
                                  <!-- Activity Segments (Bottom to Top) -->
                                  <div v-for="(count, activityType, idx) in customer.activities" :key="activityType"
                                       class="transition-opacity duration-300 relative"
                                       :style="{ 
                                           height: (count / customer.total * 100) + '%',
                                           backgroundColor: activityColors[activityType] || '#9ca3af'
                                       }">
                                  </div>
                              </div>
                              
                              <!-- Tooltip on Hover (Positioned closer to bar) -->
                              <div class="absolute -bottom-6 left-1/2 -translate-x-1/2 -translate-y-full mb-3 hidden group-hover:flex flex-col items-start z-[100] pointer-events-none transition-opacity duration-200 min-w-max">
                                  <div class="bg-gray-900 text-white text-xs rounded-md py-2 px-3 shadow-xl mb-1">
                                      <div class="font-bold uppercase tracking-wide border-b border-gray-700 pb-1 mb-2 whitespace-nowrap text-center">
                                          {{ customer.name }} {{ Math.round((customer.total / (paginatedActivityCustomers.reduce((sum, c) => sum + c.total, 0) || 1)) * 100) }}%
                                      </div>
                                      <div v-for="(count, activityType) in customer.activities" :key="'tt-'+activityType" class="flex items-center gap-2 mb-1">
                                          <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: activityColors[activityType] }"></span>
                                          <span class="whitespace-nowrap">{{ activityType }}: {{ count }}</span>
                                      </div>
                                  </div>
                                  <div class="w-2 h-2 bg-gray-900 rotate-45 -mt-1.5 self-center"></div>
                              </div>
                              
                              <!-- Label (Customer Name - Rotated) -->
                              <div class="absolute top-full left-1/2 mt-3 transform rotate-45 origin-top-left text-[10px] text-gray-500 font-medium whitespace-nowrap w-32 truncate group-hover:text-gray-800 transition-colors z-0">
                                  {{ customer.name }}
                              </div>
                         </div>
                    </div>
                </div>
                </div>

                <div class="h-32"></div>

                    <!-- X-Axis Title -->
                    <div class="text-center text-sm font-bold text-gray-700 mb-6 select-none">
                        Customer Name
                    </div>

                    <!-- Legend (Activity Types) -->
                    <div class="flex flex-wrap justify-center gap-x-4 gap-y-2 mb-6 pb-6 border-b border-gray-100">
                        <div v-for="type in activityTypes" :key="type" class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: activityColors[type] || '#9ca3af' }"></span>
                            <span class="text-xs text-gray-600 font-medium">{{ type }}</span>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="flex items-center justify-end gap-4 px-4 select-none">
                        <span class="text-sm text-gray-500">Page {{ currentActivityPage }} of {{ activityTotalPages }}</span>
                        <div class="flex gap-2">
                            <button @click="prevActivityPage" :disabled="currentActivityPage === 1" 
                                    class="px-3 py-1 text-sm bg-gray-100 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium text-gray-700">
                                « Prev
                            </button>
                            <button @click="nextActivityPage" :disabled="currentActivityPage === activityTotalPages"
                                    class="px-3 py-1 text-sm bg-blue-50 text-blue-600 rounded hover:bg-blue-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium">
                                Next »
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Chart 5: Activity Trend (Line Chart with Chart.js) -->
        <div class="bg-gray-100 rounded-2xl pt-5 px-3 pb-3 mb-6 md:mb-8">
            <h3 class="text-gray-900 text-lg font-bold ml-3 mb-4">Activity Trend</h3>
            <div class="bg-white rounded-2xl p-4 md:p-5">
                <div class="relative" style="height: 400px;">
                    <canvas ref="activityTrendChart"></canvas>
                </div>
            </div>
        </div>
    </template>

    <!-- ULTRA MODERN DASHBOARD FOR NON-SUPER ADMIN ROLES -->
    <template v-else>
        <!-- Portal Login Consistent Header -->
        <div class="relative mb-8 text-white z-50">
             <!-- Background with overflow hidden -->
             <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-emerald-600 rounded-b-[2rem] md:rounded-b-[3rem] shadow-lg overflow-hidden -z-10">
                 <!-- Abstract Shapes -->
                 <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full mix-blend-overlay filter blur-3xl translate-x-1/3 -translate-y-1/2"></div>
                 <div class="absolute bottom-0 left-0 w-64 h-64 bg-white/10 rounded-full mix-blend-overlay filter blur-2xl -translate-x-1/3 translate-y-1/3"></div>
                 
                 <!-- Animated Line Chart Widget (Kiri Atas) -->
                 <div class="absolute left-8 md:left-16 top-8 opacity-20 hidden lg:block">
                     <svg width="100" height="60" viewBox="0 0 100 60" class="overflow-visible">
                         <!-- Grid lines -->
                         <line x1="0" y1="15" x2="100" y2="15" stroke="white" stroke-width="0.5" opacity="0.3"/>
                         <line x1="0" y1="30" x2="100" y2="30" stroke="white" stroke-width="0.5" opacity="0.3"/>
                         <line x1="0" y1="45" x2="100" y2="45" stroke="white" stroke-width="0.5" opacity="0.3"/>
                         
                         <!-- Animated Line -->
                         <path 
                             d="M 0 40 Q 25 20, 50 35 T 100 25" 
                             fill="none" 
                             stroke="white" 
                             stroke-width="2" 
                             opacity="0.7"
                             class="animate-line-draw"
                         />
                         
                         <!-- Dots on line -->
                         <circle cx="0" cy="40" r="2" fill="cyan" opacity="0.8" class="animate-pulse"/>
                         <circle cx="50" cy="35" r="2" fill="cyan" opacity="0.8" class="animate-pulse" style="animation-delay: 0.5s"/>
                         <circle cx="100" cy="25" r="2" fill="cyan" opacity="0.8" class="animate-pulse" style="animation-delay: 1s"/>
                     </svg>
                 </div>
                 
                 <!-- Animated Pie Chart Widget (Kiri Bawah) -->
                 <div class="absolute left-8 md:left-16 bottom-6 opacity-20 hidden lg:block">
                     <svg width="70" height="70" viewBox="0 0 100 100" class="animate-spin-slow">
                         <!-- Background circle -->
                         <circle cx="50" cy="50" r="45" fill="none" stroke="white" stroke-width="8" opacity="0.2"/>
                         
                         <!-- Pie segments -->
                         <circle 
                             cx="50" 
                             cy="50" 
                             r="45" 
                             fill="none" 
                             stroke="white" 
                             stroke-width="8" 
                             opacity="0.6"
                             stroke-dasharray="90 282"
                             stroke-dashoffset="0"
                             transform="rotate(-90 50 50)"
                             class="animate-pie-1"
                         />
                         <circle 
                             cx="50" 
                             cy="50" 
                             r="45" 
                             fill="none" 
                             stroke="cyan" 
                             stroke-width="8" 
                             opacity="0.7"
                             stroke-dasharray="70 282"
                             stroke-dashoffset="-90"
                             transform="rotate(-90 50 50)"
                             class="animate-pie-2"
                         />
                         <circle 
                             cx="50" 
                             cy="50" 
                             r="45" 
                             fill="none" 
                             stroke="emerald" 
                             stroke-width="8" 
                             opacity="0.6"
                             stroke-dasharray="50 282"
                             stroke-dashoffset="-160"
                             transform="rotate(-90 50 50)"
                             class="animate-pie-3"
                         />
                         
                         <!-- Center dot -->
                         <circle cx="50" cy="50" r="8" fill="white" opacity="0.4"/>
                     </svg>
                 </div>
                 
                 <!-- Floating Particles -->
                 <div class="absolute inset-0 overflow-hidden pointer-events-none">
                     <!-- Particle 1 -->
                     <div class="absolute w-2 h-2 bg-white/30 rounded-full animate-float-1" style="left: 15%; top: 20%;"></div>
                     <!-- Particle 2 -->
                     <div class="absolute w-1.5 h-1.5 bg-cyan-200/40 rounded-full animate-float-2" style="left: 25%; top: 60%;"></div>
                     <!-- Particle 3 -->
                     <div class="absolute w-2.5 h-2.5 bg-white/25 rounded-full animate-float-3" style="left: 45%; top: 35%;"></div>
                     <!-- Particle 4 -->
                     <div class="absolute w-1 h-1 bg-emerald-200/50 rounded-full animate-float-4" style="left: 55%; top: 70%;"></div>
                     <!-- Particle 5 -->
                     <div class="absolute w-2 h-2 bg-white/35 rounded-full animate-float-5" style="left: 70%; top: 25%;"></div>
                     <!-- Particle 6 -->
                     <div class="absolute w-1.5 h-1.5 bg-cyan-200/30 rounded-full animate-float-6" style="left: 80%; top: 55%;"></div>
                     <!-- Particle 7 -->
                     <div class="absolute w-2 h-2 bg-white/30 rounded-full animate-float-7" style="left: 35%; top: 45%;"></div>
                     <!-- Particle 8 -->
                     <div class="absolute w-1 h-1 bg-emerald-200/40 rounded-full animate-float-8" style="left: 65%; top: 80%;"></div>
                 </div>
             </div>

             <div class="px-6 md:px-8 pt-8 pb-6 mx-auto max-w-7xl">
                 <div class="flex items-center justify-between">
                     <div class="flex flex-col">
                         <span class="text-blue-100 font-medium text-sm md:text-base mb-1">Welcome back,</span>
                         <h1 class="text-2xl md:text-4xl font-bold tracking-tight uppercase">{{ $page.props.auth.user.name }}</h1>
                         <div class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/20 backdrop-blur-sm border border-white/20 self-start shadow-sm">
                             <div class="w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]"></div>
                             <span class="text-xs md:text-sm font-semibold text-white tracking-wide">
                                 {{ $page.props.auth.user.roles && $page.props.auth.user.roles.length > 0 ? $page.props.auth.user.roles[0] : 'Team Member' }}
                             </span>
                         </div>
                     </div>
                     
                     <!-- Right Actions: Notification & Avatar -->
                     <div class="flex items-center gap-3 md:gap-5">
                         <!-- Notification Bell (Inside Banner) -->
                         <div class="relative">
                             <Dropdown align="right" width="responsive" contentClasses="py-1 bg-white ring-1 ring-black ring-opacity-5">
                                 <template #trigger>
                                     <button class="p-2.5 text-white/90 hover:bg-white/20 hover:text-white rounded-full relative transition-all duration-200 group active:scale-95 shadow-sm border border-white/10 backdrop-blur-sm">
                                          <svg class="w-6 h-6 group-hover:animate-swing drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                          </svg>
                                          <!-- Red Dot Indicator -->
                                          <span v-if="$page.props.unreadCount > 0" class="absolute top-2.5 right-3 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white animate-pulse shadow-sm"></span>
                                     </button>
                                 </template>

                                 <template #content>
                                     <div class="px-4 py-3 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                                         <h3 class="font-bold text-gray-700 text-sm">Notifications</h3>
                                         <button v-if="$page.props.unreadCount > 0" @click="markAllRead" class="text-xs text-blue-600 hover:text-blue-800 font-medium hover:underline">Mark all read</button>
                                     </div>

                                     <div v-if="!$page.props.notifications || $page.props.notifications.length === 0" class="px-4 py-8 text-center text-gray-400 text-sm">
                                         <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                         </svg>
                                         No new notifications
                                     </div>

                                     <div v-else class="max-h-80 overflow-y-auto custom-scrollbar">
                                         <div v-for="notification in $page.props.notifications" :key="notification.id" 
                                              class="px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition-colors flex gap-3 cursor-pointer relative group"
                                              :class="{'bg-blue-50/40': !notification.read_at}"
                                              @click="markRead(notification)"
                                         >
                                             <!-- Icon Section -->
                                             <div class="flex-shrink-0 mt-1">
                                                 <div class="w-8 h-8 rounded-full flex items-center justify-center shadow-sm"
                                                      :class="{
                                                          'bg-emerald-100 text-emerald-600': notification.data.color === 'emerald',
                                                          'bg-red-100 text-red-600': notification.data.color === 'red',
                                                          'bg-amber-100 text-amber-600': notification.data.color === 'amber',
                                                          'bg-blue-100 text-blue-600': !notification.data.color
                                                      }"
                                                 >
                                                      <svg v-if="notification.data.icon === 'check'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                      <svg v-else-if="notification.data.icon === 'x'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                      <svg v-else-if="notification.data.icon === 'clock'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                      <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                 </div>
                                             </div>
                                             
                                             <!-- Content Section -->
                                             <div class="ml-3 flex-1 text-left">
                                                 <p class="text-sm font-bold text-gray-900">{{ notification.data.title }}</p>
                                                 <p class="text-xs text-gray-700 mt-0.5 line-clamp-2 leading-relaxed">{{ notification.data.message }}</p>
                                                 <p class="text-xs text-gray-500 mt-1.5 flex items-center gap-1">
                                                      <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                      {{ notification.created_at }} ago
                                                 </p>
                                             </div>
                                             
                                             <!-- Unread Indicator -->
                                             <div v-if="!notification.read_at" class="absolute top-4 right-4 w-2 h-2 rounded-full bg-blue-500 ring-2 ring-white"></div>
                                         </div>
                                     </div>
                                 </template>
                             </Dropdown>
                         </div>

                         <!-- Avatar Dropdown -->
                         <div class="relative">
                             <Dropdown align="right" width="48">
                                 <template #trigger>
                                     <button class="relative group transition-transform active:scale-95 focus:outline-none">
                                         <div class="p-1 rounded-full border-2 border-white/30 hover:border-white transition-colors">
                                            <img :src="$page.props.auth.user.avatar_url" 
                                                 class="w-14 h-14 md:w-16 md:h-16 rounded-full object-cover shadow-sm bg-white" />
                                         </div>
                                         <div class="absolute bottom-1 right-1 bg-emerald-400 w-3.5 h-3.5 md:w-4 md:h-4 rounded-full border-2 border-slate-800"></div>
                                     </button>
                                 </template>
    
                                 <template #content>
                                     <!-- User Info Header -->
                                     <div class="px-4 py-3 border-b border-gray-100">
                                         <p class="text-sm font-semibold text-gray-900">{{ $page.props.auth.user.name }}</p>
                                         <p class="text-xs text-gray-500">{{ $page.props.auth.user.email }}</p>
                                     </div>
                                     
                                     <!-- Menu Items with Icons -->
                                     <DropdownLink :href="route('profile.edit')" class="flex items-center gap-2">
                                         <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                         </svg>
                                         Profile
                                     </DropdownLink>
    
                                     <DropdownLink :href="route('logout')" method="post" as="button" class="flex items-center gap-2">
                                         <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                         </svg>
                                         Log Out
                                     </DropdownLink>
                                 </template>
                             </Dropdown>
                         </div>
                     </div>
                 </div>
             </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-6 md:px-8 pb-12">
            <!-- Action & Status Group Box -->
            <div class="hidden lg:block bg-white rounded-[2.5rem] p-6 md:p-10 shadow-lg border border-gray-100 relative overflow-hidden mb-12">
                 <!-- Decorative Top Object -->
                <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-purple-50 rounded-full blur-3xl opacity-50 pointer-events-none"></div>

                <!-- Header -->
                <div class="mb-8 relative z-10">
                    <h1 class="text-3xl font-black text-gray-900 tracking-tight">My Workspace</h1>
                    <p class="text-gray-500 font-medium mt-2">Manage your plans, reports and monitoring efficiently</p>
                </div>

                <!-- Quick Actions Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12 relative z-10">

                    <!-- Planning Card -->
                    <Link :href="route('planning.index')" class="group relative overflow-hidden bg-gray-50 rounded-3xl p-8 border border-gray-100 hover:bg-white hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-100/50 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-500"></div>
                        <div class="relative z-10">
                            <div class="w-14 h-14 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-white mb-6 group-hover:scale-110 group-hover:rotate-3 transition-all duration-300 shadow-lg">
                                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-emerald-600 transition-colors">Planning</h3>
                            <p class="text-sm text-gray-500">Create & Monitoring Plans</p>
                        </div>
                    </Link>

                    <!-- Reports Card -->
                    <Link :href="route('planning-report.index')" class="group relative overflow-hidden bg-gray-50 rounded-3xl p-8 border border-gray-100 hover:bg-white hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-[#226ed9]/10 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-500"></div>
                        <div class="relative z-10">
                            <div class="w-14 h-14 bg-gradient-to-br from-[#226ed9] to-[#1b5bb5] rounded-2xl flex items-center justify-center text-white mb-6 group-hover:scale-110 group-hover:rotate-3 transition-all duration-300 shadow-lg">
                                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-[#226ed9] transition-colors">Reports</h3>
                            <p class="text-sm text-gray-500">Performance Insights</p>
                        </div>
                    </Link>
                </div>

                <!-- Overview Status Section -->
                <div>
                    <div class="flex items-center gap-3 mb-8 relative z-10">
                        <h2 class="text-xl font-bold text-gray-900">Overview Status</h2>
                    </div>

                    <!-- Stats Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 relative z-10">
                        <!-- No Planning -->
                        <div class="group relative overflow-hidden bg-gradient-to-br from-gray-50 to-gray-100/50 rounded-2xl p-6 border border-gray-200 hover:border-gray-300 hover:shadow-lg transition-all duration-300">
                            <div class="absolute top-0 right-0 w-20 h-20 bg-gray-200/30 rounded-full -mr-10 -mt-10 group-hover:scale-150 transition-transform duration-500"></div>
                            <div class="relative z-10">
                                <p class="text-sm font-semibold text-gray-600 mb-2">No Planning</p>
                                <div class="flex items-baseline gap-2">
                                    <p class="text-4xl font-black text-gray-800">{{ noPlanningCount }}</p>
                                    <span class="text-xs text-gray-400 font-medium">items</span>
                                </div>
                            </div>
                        </div>

                        <!-- On Progress -->
                        <div class="group relative overflow-hidden bg-gradient-to-br from-blue-50 to-blue-100/50 rounded-2xl p-6 border border-blue-200 hover:border-blue-300 hover:shadow-lg hover:shadow-blue-100 transition-all duration-300">
                            <div class="absolute top-0 right-0 w-20 h-20 bg-blue-300/30 rounded-full -mr-10 -mt-10 group-hover:scale-150 transition-transform duration-500"></div>
                            <div class="relative z-10">
                                <p class="text-sm font-semibold text-blue-700 mb-2">On Progress</p>
                                <div class="flex items-baseline gap-2">
                                    <p class="text-4xl font-black text-blue-700">{{ onProgressCount }}</p>
                                    <span class="text-xs text-blue-500 font-medium">items</span>
                                </div>
                            </div>
                        </div>

                        <!-- Late / Rejected -->
                        <div class="group relative overflow-hidden bg-gradient-to-br from-amber-50 to-amber-100/50 rounded-2xl p-6 border border-amber-200 hover:border-amber-300 hover:shadow-lg hover:shadow-amber-100 transition-all duration-300">
                            <div class="absolute top-0 right-0 w-20 h-20 bg-amber-300/30 rounded-full -mr-10 -mt-10 group-hover:scale-150 transition-transform duration-500"></div>
                            <div class="relative z-10">
                                <div class="flex items-center gap-2 mb-2">
                                    <p class="text-sm font-semibold text-amber-700">Late / Rejected</p>
                                    <span v-if="lateRejectedCount > 0" class="flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-2 w-2 rounded-full bg-amber-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                                    </span>
                                </div>
                                <div class="flex items-baseline gap-2">
                                    <p class="text-4xl font-black text-amber-700">{{ lateRejectedCount }}</p>
                                    <span class="text-xs text-amber-500 font-medium">items</span>
                                </div>
                            </div>
                        </div>

                        <!-- Completed -->
                        <!-- Closing -->
                        <div class="group relative overflow-hidden bg-gradient-to-br from-gray-100 to-gray-200/50 rounded-2xl p-6 border border-gray-300 hover:border-gray-400 hover:shadow-lg hover:shadow-gray-200 transition-all duration-300">
                            <div class="absolute top-0 right-0 w-20 h-20 bg-black/10 rounded-full -mr-10 -mt-10 group-hover:scale-150 transition-transform duration-500"></div>
                            <div class="relative z-10">
                                <p class="text-sm font-semibold text-gray-900 mb-2">Closing</p>
                                <div class="flex items-baseline gap-2">
                                    <p class="text-4xl font-black text-gray-900">{{ closingCount }}</p>
                                    <span class="text-xs text-gray-600 font-medium">items</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Dashboard Group Box -->
            <div class="bg-white rounded-[2.5rem] p-6 md:p-10 shadow-lg border border-gray-100 relative overflow-hidden mb-12">
                <!-- Decorative Top Object -->
                <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-gray-50 rounded-full blur-3xl opacity-50 pointer-events-none"></div>

                <!-- Dashboard Header -->
                <div class="mb-8 relative z-10">
                    <h1 class="text-3xl font-black text-gray-900 tracking-tight">Analytics Dashboard</h1>
                    <p class="text-gray-500 font-medium mt-2">Comprehensive data insights and performance metrics</p>
                </div>

                <!-- Date Filter Section -->
                <!-- Date & User Filter Section -->
                <div class="mb-10 p-6 bg-gray-50 rounded-3xl border border-gray-100">
                     <div class="flex items-center gap-3 mb-6">
                        <div class="w-1.5 h-6 bg-emerald-500 rounded-full"></div>
                        <h3 class="text-lg font-bold text-gray-900">Filters</h3>
                     </div>
                     <div class="grid grid-cols-1 gap-6" :class="teams.length > 0 ? 'md:grid-cols-4' : (users.length > 0 ? 'md:grid-cols-3' : 'md:grid-cols-2')">
                         <div>
                             <label class="block text-sm font-bold text-gray-700 mb-2">Start Date</label>
                             <input type="date" v-model="filterForm.start_date" class="w-full rounded-2xl border-gray-200 bg-white focus:ring-emerald-500 focus:border-emerald-500 text-sm py-3 px-4 transition-all outline-none">
                         </div>
                         <div>
                             <label class="block text-sm font-bold text-gray-700 mb-2">End Date</label>
                             <input type="date" v-model="filterForm.end_date" class="w-full rounded-2xl border-gray-200 bg-white focus:ring-emerald-500 focus:border-emerald-500 text-sm py-3 px-4 transition-all outline-none">
                         </div>
                         <div v-if="teams.length > 0">
                             <label class="block text-sm font-bold text-gray-700 mb-2">Filter Team</label>
                             <select v-model="filterForm.team_id" class="w-full rounded-2xl border-gray-200 bg-white focus:ring-emerald-500 focus:border-emerald-500 text-sm py-3 px-4 transition-all outline-none">
                                 <option :value="null">All Teams</option>
                                 <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                             </select>
                         </div>
                         <div v-if="users.length > 0">
                             <label class="block text-sm font-bold text-gray-700 mb-2">Filter User</label>
                             <select v-model="filterForm.user_id" class="w-full rounded-2xl border-gray-200 bg-white focus:ring-emerald-500 focus:border-emerald-500 text-sm py-3 px-4 transition-all outline-none">
                                 <option :value="null">All Team Members</option>
                                 <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                             </select>
                         </div>
                     </div>
                </div>

                <!-- Key Metrics Section -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                    <!-- Stat Card 1 -->
                    <div class="bg-gray-50 rounded-3xl p-6 border border-gray-100 flex items-center justify-between hover:bg-gray-100 transition-colors">
                        <div>
                            <p class="text-gray-500 text-sm font-bold mb-1">Total Activities</p>
                            <p class="text-gray-900 text-3xl font-black">{{ totalActivitiesCount }}</p>
                        </div>
                        <div class="w-14 h-14 bg-white text-blue-600 rounded-2xl flex items-center justify-center shadow-sm">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z" /></svg>
                        </div>
                    </div>
                     <!-- Stat Card 2 -->
                    <div class="bg-gray-50 rounded-3xl p-6 border border-gray-100 flex items-center justify-between hover:bg-gray-100 transition-colors">
                        <div>
                            <p class="text-gray-500 text-sm font-bold mb-1">Active Customers</p>
                            <p class="text-gray-900 text-3xl font-black">{{ activeCustomerCount }}</p>
                        </div>
                        <div class="w-14 h-14 bg-white text-emerald-600 rounded-2xl flex items-center justify-center shadow-sm">
                             <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        </div>
                    </div>
                     <!-- Stat Card 3 -->
                    <div class="bg-gray-50 rounded-3xl p-6 border border-gray-100 flex items-center justify-between hover:bg-gray-100 transition-colors">
                        <div>
                             <p class="text-gray-500 text-sm font-bold mb-1">Total Customers</p>
                             <p class="text-gray-900 text-3xl font-black">{{ totalCustomerCount }}</p>
                        </div>
                        <div class="w-14 h-14 bg-white text-yellow-600 rounded-2xl flex items-center justify-center shadow-sm">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-10">
                    <div class="bg-gray-50 rounded-3xl p-8 border border-gray-100">
                         <h3 class="text-xl font-bold text-gray-900 mb-6">Activity Marketing</h3>
                         <div class="h-64"><canvas ref="activityMarketingChart"></canvas></div>
                    </div>
                    <div class="bg-gray-50 rounded-3xl p-8 border border-gray-100">
                         <h3 class="text-xl font-bold text-gray-900 mb-6">Planning Health</h3>
                         <div class="h-64"><canvas ref="customerHealthChart"></canvas></div>
                    </div>
                </div>

                <!-- Inactive Duration -->
                <div class="bg-gray-50 rounded-3xl p-4 md:p-8 border border-gray-100 mb-10">
                    <h3 class="text-xl font-bold text-gray-900 mb-6">Customer Health (Inactive Duration)</h3>
                    <div class="relative bg-white rounded-2xl p-2 md:p-5 overflow-x-auto">
                    <div class="flex gap-4 pl-8 pr-4 relative mb-4 min-w-[750px] md:min-w-0">
                        <!-- Y-Axis -->
                        <div class="flex flex-col justify-between items-end h-80 relative shrink-0 w-8 select-none">
                            <div class="absolute -left-10 top-1/2 -translate-y-1/2 -rotate-90 text-xs font-bold text-gray-500 whitespace-nowrap tracking-wide capitalize">{{ timeUnit }} Inactive</div>
                            <div v-for="(tick, i) in yAxisTicks" :key="'tick-'+i" class="text-xs text-gray-400 font-medium h-4 flex items-center transform translate-y-2">{{ tick }}</div>
                        </div>
                        <!-- Chart Area -->
                        <div class="flex-1 relative h-80 border-l border-b border-gray-100">
                             <div class="absolute inset-0 w-full h-full flex flex-col justify-between pointer-events-none">
                                  <div v-for="(tick, i) in yAxisTicks" :key="'grid-'+i" class="w-full border-t border-gray-100/60 h-0 transform translate-y-2"></div>
                             </div>
                             <div class="relative w-full h-full flex items-end justify-between px-2 gap-2 z-10">
                                  <div v-if="paginatedCustomers.length === 0" class="absolute inset-0 flex items-center justify-center text-gray-400">No Data Available</div>
                                  <div v-for="customer in paginatedCustomers" :key="customer.id" class="group flex flex-col justify-end items-center flex-1 h-full relative">
                                       <div class="w-full max-w-[40px] rounded-lg transition-all duration-500 hover:opacity-90 relative" :class="customer.is_warning ? 'bg-red-500' : 'bg-emerald-400'" :style="{ height: Math.max((customer.days_inactive / (yAxisMax || 1) * 100), 1) + '%', backgroundColor: customer.is_warning ? '#ef4444' : '#34d399' }">
                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:flex flex-col items-center z-50 pointer-events-none transition-opacity duration-200 min-w-max">
                                                 <div class="bg-gray-900 text-white text-xs rounded-md py-2 px-3 shadow-xl mb-1 flex flex-col items-center gap-1">
                                                      <span class="font-bold uppercase tracking-wide border-b border-gray-700 pb-1 mb-1 whitespace-nowrap">{{ customer.name }}{{ customer.product ? ' (' + customer.product + ')' : '' }}</span>
                                                      <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full" :class="customer.is_warning ? 'bg-red-400' : 'bg-emerald-400'"></span><span>Inactive: {{ customer.days_inactive }} {{ timeUnit }}</span></div>
                                                 </div>
                                                 <div class="w-2 h-2 bg-gray-900 rotate-45 -mt-1.5"></div>
                                            </div>
                                       </div>
                                       <div class="absolute top-full left-1/2 mt-3 transform rotate-45 origin-top-left text-[10px] text-gray-500 font-medium whitespace-nowrap w-32 truncate group-hover:text-gray-800 transition-colors z-0">{{ customer.name }}</div>
                                  </div>
                             </div>
                        </div>
                    </div>
                    <div class="h-32"></div>
                    <div class="text-center text-sm font-bold text-gray-700 mb-4 select-none">Customer Name (Product)</div>
                    <!-- Pagination -->
                    <div class="flex items-center justify-end gap-4 mt-2 px-4 border-t border-gray-100 pt-4 select-none">
                        <span class="text-sm text-gray-500">Page {{ currentPage }} of {{ totalPages }}</span>
                        <div class="flex gap-2">
                            <button @click="prevPage" :disabled="currentPage === 1" class="px-3 py-1 text-sm bg-gray-100 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium text-gray-700">« Prev</button>
                            <button @click="nextPage" :disabled="currentPage === totalPages" class="px-3 py-1 text-sm bg-blue-50 text-blue-600 rounded hover:bg-blue-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium">Next »</button>
                        </div>
                    </div>
                </div>
            </div>

                <!-- Companies Activity Breakdown -->
                <div class="bg-gray-50 rounded-3xl p-4 md:p-8 border border-gray-100 mb-10">
                    <h3 class="text-xl font-bold text-gray-900 mb-6">Companies Activity Breakdown</h3>
                    <div class="bg-white rounded-2xl p-2 md:p-5 overflow-x-auto">
                    <div class="flex gap-4 pl-8 pr-4 relative mb-4 min-w-[750px] md:min-w-0">
                        <div class="flex flex-col justify-between items-end h-80 relative shrink-0 w-10 select-none">
                            <div class="absolute -left-12 top-1/2 -translate-y-1/2 -rotate-90 text-xs font-bold text-gray-500 whitespace-nowrap tracking-wide">ALL ACTIVITY</div>
                            <div v-for="i in 6" :key="'ytick-'+i" class="text-xs text-gray-400 font-medium h-4 flex items-center transform translate-y-2">{{ Math.round(maxTotalActivity * (6-i) / 5) }}</div>
                        </div>
                        <div class="flex-1 relative h-80 border-l border-b border-gray-100">
                             <div class="absolute inset-0 w-full h-full flex flex-col justify-between pointer-events-none">
                                  <div v-for="i in 6" :key="'ygrid-'+i" class="w-full border-t border-gray-100/60 h-0 transform translate-y-2"></div>
                             </div>
                             <div class="relative w-full h-full flex items-end justify-between px-2 gap-1 z-10">
                                  <div v-if="paginatedActivityCustomers.length === 0" class="absolute inset-0 flex items-center justify-center text-gray-400">No Activity Data</div>
                                  <div v-for="(customer, cidx) in paginatedActivityCustomers" :key="'act-'+customer.id" class="group flex flex-col justify-end items-center flex-1 h-full relative">
                                       <div class="w-full max-w-[50px] rounded-lg overflow-hidden relative flex flex-col-reverse items-stretch" :style="{ height: Math.max((customer.total / maxTotalActivity * 100), 1) + '%' }">
                                            <div v-for="(count, activityType, idx) in customer.activities" :key="activityType" class="transition-opacity duration-300 relative" :style="{ height: (count / customer.total * 100) + '%', backgroundColor: activityColors[activityType] || '#9ca3af' }"></div>
                                       </div>
                                       <div class="absolute -bottom-6 left-1/2 -translate-x-1/2 -translate-y-full mb-3 hidden group-hover:flex flex-col items-start z-[100] pointer-events-none transition-opacity duration-200 min-w-max">
                                            <div class="bg-gray-900 text-white text-xs rounded-md py-2 px-3 shadow-xl mb-1">
                                                 <div class="font-bold uppercase tracking-wide border-b border-gray-700 pb-1 mb-2 whitespace-nowrap text-center">{{ customer.name }} {{ Math.round((customer.total / (paginatedActivityCustomers.reduce((sum, c) => sum + c.total, 0) || 1)) * 100) }}%</div>
                                                 <div v-for="(count, activityType) in customer.activities" :key="'tt-'+activityType" class="flex items-center gap-2 mb-1"><span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: activityColors[activityType] }"></span><span class="whitespace-nowrap">{{ activityType }}: {{ count }}</span></div>
                                            </div>
                                            <div class="w-2 h-2 bg-gray-900 rotate-45 -mt-1.5 self-center"></div>
                                       </div>
                                       <div class="absolute top-full left-1/2 mt-3 transform rotate-45 origin-top-left text-[10px] text-gray-500 font-medium whitespace-nowrap w-32 truncate group-hover:text-gray-800 transition-colors z-0">{{ customer.name }}</div>
                                  </div>
                             </div>
                        </div>
                    </div>
                    <div class="h-32"></div>
                    <div class="text-center text-sm font-bold text-gray-700 mb-6 select-none">Customer Name</div>
                    <div class="flex flex-wrap justify-center gap-x-4 gap-y-2 mb-6 pb-6 border-b border-gray-100">
                        <div v-for="type in activityTypes" :key="type" class="flex items-center gap-2"><span class="w-3 h-3 rounded-full" :style="{ backgroundColor: activityColors[type] || '#9ca3af' }"></span><span class="text-xs text-gray-600 font-medium">{{ type }}</span></div>
                    </div>
                    <div class="flex items-center justify-end gap-4 px-4 select-none">
                        <span class="text-sm text-gray-500">Page {{ currentActivityPage }} of {{ activityTotalPages }}</span>
                        <div class="flex gap-2">
                            <button @click="prevActivityPage" :disabled="currentActivityPage === 1" class="px-3 py-1 text-sm bg-gray-100 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium text-gray-700">« Prev</button>
                            <button @click="nextActivityPage" :disabled="currentActivityPage === activityTotalPages" class="px-3 py-1 text-sm bg-blue-50 text-blue-600 rounded hover:bg-blue-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium">Next »</button>
                        </div>
                    </div>
                </div>
            </div>

                <!-- Activity Trend -->
                <div class="bg-gray-50 rounded-3xl p-4 md:p-8 border border-gray-100">
                    <h3 class="text-xl font-bold text-gray-900 mb-6">Activity Trend</h3>
                    <div class="relative" style="height: 400px;"><canvas ref="activityTrendChart"></canvas></div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center">
                <p class="text-sm text-gray-400 font-medium">&copy; 2025 Planly App • Created by Agus Prasetya</p>
            </div>
        </div>

        <!-- Custom Animations -->
        <style scoped>
        @keyframes blob {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(20px, -20px) scale(1.1); }
            50% { transform: translate(-20px, 20px) scale(0.9); }
            75% { transform: translate(10px, 10px) scale(1.05); }
        }
        .animate-blob {
            animation: blob 7s infinite;
        }
        .animation-delay-2000 {
            animation-delay: 2s;
        }
        .animation-delay-4000 {
            animation-delay: 4s;
        }
        
        /* Bar Chart Animations */
        @keyframes barHeight {
            0%, 100% { 
                transform: scaleY(1);
            }
            50% { 
                transform: scaleY(0.5);
            }
        }
        
        .animate-bar-1 {
            animation: barHeight 2s ease-in-out infinite;
            transform-origin: bottom;
        }
        .animate-bar-2 {
            animation: barHeight 2.3s ease-in-out infinite;
            animation-delay: 0.2s;
            transform-origin: bottom;
        }
        .animate-bar-3 {
            animation: barHeight 2.5s ease-in-out infinite;
            animation-delay: 0.4s;
            transform-origin: bottom;
        }
        .animate-bar-4 {
            animation: barHeight 2.2s ease-in-out infinite;
            animation-delay: 0.6s;
            transform-origin: bottom;
        }
        .animate-bar-5 {
            animation: barHeight 2.4s ease-in-out infinite;
            animation-delay: 0.8s;
            transform-origin: bottom;
        }
        </style>
    </template>

</template>

<style>
/* Global Bar Chart Animations (not scoped to work properly) */
@keyframes barHeight {
    0%, 100% { 
        transform: scaleY(1);
    }
    50% { 
        transform: scaleY(0.5);
    }
}

.animate-bar-1 {
    animation: barHeight 2s ease-in-out infinite;
    transform-origin: bottom;
}
.animate-bar-2 {
    animation: barHeight 2.3s ease-in-out infinite;
    animation-delay: 0.2s;
    transform-origin: bottom;
}
.animate-bar-3 {
    animation: barHeight 2.5s ease-in-out infinite;
    animation-delay: 0.4s;
    transform-origin: bottom;
}
.animate-bar-4 {
    animation: barHeight 2.2s ease-in-out infinite;
    animation-delay: 0.6s;
    transform-origin: bottom;
}
.animate-bar-5 {
    animation: barHeight 2.4s ease-in-out infinite;
    animation-delay: 0.8s;
    transform-origin: bottom;
}

/* Floating Particles Animations */
@keyframes float {
    0%, 100% {
        transform: translateY(0) translateX(0);
        opacity: 0.3;
    }
    50% {
        transform: translateY(-20px) translateX(10px);
        opacity: 0.6;
    }
}

@keyframes floatReverse {
    0%, 100% {
        transform: translateY(0) translateX(0);
        opacity: 0.4;
    }
    50% {
        transform: translateY(20px) translateX(-10px);
        opacity: 0.7;
    }
}

.animate-float-1 {
    animation: float 4s ease-in-out infinite;
}
.animate-float-2 {
    animation: floatReverse 5s ease-in-out infinite;
    animation-delay: 0.5s;
}
.animate-float-3 {
    animation: float 6s ease-in-out infinite;
    animation-delay: 1s;
}
.animate-float-4 {
    animation: floatReverse 4.5s ease-in-out infinite;
    animation-delay: 1.5s;
}
.animate-float-5 {
    animation: float 5.5s ease-in-out infinite;
    animation-delay: 2s;
}
.animate-float-6 {
    animation: floatReverse 4.8s ease-in-out infinite;
    animation-delay: 2.5s;
}
.animate-float-7 {
    animation: float 5.2s ease-in-out infinite;
    animation-delay: 3s;
}
.animate-float-8 {
    animation: floatReverse 4.3s ease-in-out infinite;
    animation-delay: 3.5s;
}

/* Line Chart Animation */
@keyframes lineDraw {
    0% {
        stroke-dasharray: 0 500;
        opacity: 0.3;
    }
    50% {
        stroke-dasharray: 250 250;
        opacity: 0.8;
    }
    100% {
        stroke-dasharray: 0 500;
        opacity: 0.3;
    }
}

.animate-line-draw {
    stroke-dasharray: 500;
    animation: lineDraw 4s ease-in-out infinite;
}

/* Pie Chart Animations */
@keyframes spinSlow {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.animate-spin-slow {
    animation: spinSlow 20s linear infinite;
}

@keyframes pieSegment {
    0%, 100% {
        opacity: 0.4;
    }
    50% {
        opacity: 0.8;
    }
}

.animate-pie-1 {
    animation: pieSegment 3s ease-in-out infinite;
}

.animate-pie-2 {
    animation: pieSegment 3s ease-in-out infinite;
    animation-delay: 0.3s;
}

.animate-pie-3 {
    animation: pieSegment 3s ease-in-out infinite;
    animation-delay: 0.6s;
}
</style>
