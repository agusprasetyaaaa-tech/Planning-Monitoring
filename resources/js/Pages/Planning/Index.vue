<script setup>
import NexusLayout from '@/Layouts/NexusLayout.vue';
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import { debounce } from 'lodash';
import { Menu, MenuButton, MenuItems, MenuItem, Dialog, DialogPanel, DialogTitle, TransitionRoot, TransitionChild, Switch } from '@headlessui/vue';
import Modal from '@/Components/Modal.vue';
import Toast from '@/Components/Toast.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import axios from 'axios';
import NexusCalendar from '@/Components/NexusCalendar.vue';


const page = usePage();
const isDetailOpen = ref(false);
const selectedPlan = ref(null);
const selectedCustomer = ref(null);

const openDetail = (customer) => {
    selectedCustomer.value = customer;
    selectedPlan.value = customer.latest_plan; // optional, mainly for single view if needed, but we use loop
    isDetailOpen.value = true;
};

const closeDetail = () => {
    isDetailOpen.value = false;
    setTimeout(() => {
        selectedPlan.value = null;
        selectedCustomer.value = null;
    }, 200);
};


const activityTypes = [
    'Call', 'Visit', 'Ent', 'Online Meeting', 'Survey', 
    'Presentation', 'Proposal', 'Negotiation', 'Admin/Tender', 'Other'
];

const progressOptions = [
    '10%-Introduction', '20%-Visit', '30%-Presentation', 
    '40%-Survey', '50%-Proposal', '75%-Confirm Budget', 
    '90%-Tender&Nego', '100%-Closing'
];

// --- REVISION LOGIC ---
const showRevisionModal = ref(false);
const revisionPlan = ref(null);
const revisionForm = useForm({
    planning_date: '',
    activity_type: '', // Added this
    description: '',
    has_report: false,
    
    // Report fields
    execution_date: '',
    result_description: '',
    location: '',
    pic: '',
    position: '',
    progress: '',
    is_success: true,
    
    // Next Plan fields
    next_planning_date: '',
    next_plan_description: '',
    next_activity_type: '',
});

const openRevisionModal = (plan) => {
    revisionPlan.value = plan;
    revisionForm.clearErrors();
    
    // Fill Base Plan
    revisionForm.planning_date = plan.planning_date;
    revisionForm.activity_type = plan.activity_type || ''; // Populate activity_type
    revisionForm.description = plan.description;

    // Fill Reporting Data if available
    if (plan.report) {
         revisionForm.has_report = true;
         // Use existing execution date or Today
         revisionForm.execution_date = plan.report.execution_date ? plan.report.execution_date.split('T')[0] : new Date().toISOString().split('T')[0];
         revisionForm.result_description = plan.report.result_description || '';
         revisionForm.location = plan.report.location || '';
         revisionForm.pic = plan.report.pic || '';
         revisionForm.position = plan.report.position || '';
         revisionForm.progress = plan.report.progress || '';
         revisionForm.is_success = plan.report.is_success !== undefined ? Boolean(plan.report.is_success) : true;

         // FORCE RESET NEXT PLAN (To ensure re-entry)
         revisionForm.next_planning_date = '';
         revisionForm.next_plan_description = '';
         revisionForm.next_activity_type = '';
    } else {
         revisionForm.has_report = false;
         revisionForm.execution_date = new Date().toISOString().split('T')[0]; // Default to Today
         revisionForm.result_description = '';
         revisionForm.location = '';
         revisionForm.pic = '';
         revisionForm.position = '';
         revisionForm.progress = '';
         revisionForm.is_success = true;
         revisionForm.next_planning_date = '';
         revisionForm.next_plan_description = '';
         revisionForm.next_activity_type = '';
    }
    
    showRevisionModal.value = true;
    closeDetail(); // Close detail if open
};

const closeRevisionModal = () => {
    showRevisionModal.value = false;
    revisionForm.reset();
    revisionPlan.value = null;
};

const submitRevision = () => {
    if (!revisionPlan.value) return;
    
    // Use regular PATCH since we removed file upload (no need for POST + _method: PATCH spoofing anymore, but standard patch is fine)
    revisionForm.patch(route('planning.revise', revisionPlan.value.id), {
        onSuccess: () => {
             closeRevisionModal();
        },
        preserveScroll: true,
    });
};

defineOptions({ layout: NexusLayout });

const props = defineProps({
    customers: Object,
    user_roles: Array,
    timeSettings: Object,
    currentSimulatedTime: String, // ISO format from backend
    reminderData: Object,
    filters: Object,
    teams: Array,
    users: Array,
});

const handleMonthChange = ({ month, year }) => {
    selectedMonth.value = month;
    selectedYear.value = year;
};

// Search and filter state
const search = ref(props.filters?.search || '');
const filterTeam = ref(props.filters?.team || '');
const filterUser = ref(props.filters?.user || '');
// Default perPage to 'all' for All Planning tab, otherwise use filter value or '10'
const currentTabValue = props.filters?.tab || 'all';
const perPage = ref(currentTabValue === 'all' ? 'all' : (props.filters?.perPage || '10'));

// View Mode (Table/Calendar)
const viewMode = ref('table');
const switchViewMode = (mode) => {
    viewMode.value = mode;
    
    if (mode === 'calendar' && currentTab.value !== 'all') {
        changeTab('all');
    } else if (mode === 'table' && currentTab.value === 'all') {
        // If moving back to table from 'all' tab, reset to default week (current week)
        // We do this by triggering a visit without the tab parameter 
        router.get(route('planning.index'), {
            ...props.filters,
            tab: undefined,
            perPage: 10,
        }, {
            preserveState: false,
            replace: true
        });
    }
};

// Month & Year state
const selectedMonth = ref(props.filters.month ? parseInt(props.filters.month) : new Date().getMonth() + 1);
const selectedYear = ref(props.filters.year ? parseInt(props.filters.year) : new Date().getFullYear());

const months = [
    { value: 1, label: 'Jan' },
    { value: 2, label: 'Feb' },
    { value: 3, label: 'Mar' },
    { value: 4, label: 'Apr' },
    { value: 5, label: 'May' },
    { value: 6, label: 'Jun' },
    { value: 7, label: 'Jul' },
    { value: 8, label: 'Aug' },
    { value: 9, label: 'Sep' },
    { value: 10, label: 'Oct' },
    { value: 11, label: 'Nov' },
    { value: 12, label: 'Dec' },
];

const currentYear = new Date().getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - 2 + i);

// Watchers
watch([selectedMonth, selectedYear], () => {
    updateParams();
});

const isBOD = computed(() => props.user_roles && (props.user_roles.includes('BOD') || props.user_roles.includes('Board of Director')));

const showCreateButton = computed(() => {
    // Check Super Admin via props directly to avoid TDZ
    if (props.user_roles && (props.user_roles.includes('Super Admin') || props.user_roles.includes('SuperAdmin'))) return true;

    // Check if user is BOD
    const roles = props.user_roles || [];
    const isBOD = roles.includes('BOD') || roles.includes('Board of Director');
    
    // If BOD, hide button. Everyone else (Manager, User, etc) shows button.
    if (isBOD) return false;
    
    return true;
});

// Sorting state
const sortField = ref(props.filters?.sort || '');
const sortDirection = ref(props.filters?.direction || 'asc');

// Computed: Filter users based on selected team
const filteredUsers = computed(() => {
    if (!filterTeam.value) {
        return props.users;
    }
    return props.users.filter(user => user.team_id == filterTeam.value);
});

const updateParams = debounce(() => {
    router.get(route('planning.index'), { 
        search: search.value, 
        team: filterTeam.value,
        user: filterUser.value,
        sort: sortField.value,
        direction: sortDirection.value,
        sort: sortField.value,
        direction: sortDirection.value,
        perPage: perPage.value,
        month: selectedMonth.value,
        year: selectedYear.value,
        tab: currentTab.value, // Use currentTab.value as source of truth, not props which might be stale
        page: 1, // Reset to first page when filters change
    }, { 
        preserveState: true, 
        preserveScroll: true,
        replace: true,
        only: ['customers', 'filters', 'timeSettings', 'reminderData']
    });
}, 300);

const toggleSort = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'asc';
    }
    updateParams();
};

watch(search, () => {
    updateParams();
});

// Watch filterTeam to reset filterUser if it's not in the filtered list
watch(filterTeam, (newTeam) => {
    if (newTeam && filterUser.value) {
        const userExists = filteredUsers.value.some(u => u.id == filterUser.value);
        if (!userExists) {
            filterUser.value = '';
        }
    }
    updateParams();
});

watch(filterUser, () => {
    updateParams();
});

watch(perPage, () => {
    updateParams();
});

/**
 * PLANNING STATUS LOGIC:
 * - 'none' (Gray): Tidak ada planning yang dibuat
 * - 'created' (Red): Planning dibuat tapi belum ada report
 * - 'reported' (Green): Report sudah dibuat DAN masih dalam threshold waktu
 * - 'expired' (Blinking Red): Planning sudah expired berdasarkan threshold time management DAN belum ada report
 * - 'warning' (Blinking Green/Yellow): Report sudah ada TAPI sudah melewati threshold → perlu buat plan baru
 */

// Helper: Get current time (with testing offset support)
const getCurrentTime = () => {
    if (props.currentSimulatedTime) {
        return new Date(props.currentSimulatedTime).getTime();
    }
    return new Date().getTime();
};

const getPlanningStatus = (customer) => {
    const plan = customer.latest_plan;
    const warningUnit = props.timeSettings?.planning_time_unit || 'Days (Production)';
    const warningThreshold = props.timeSettings?.planning_warning_threshold || 14;

    // Helper: Calculate diff using correct unit
    const getDiffValue = (dateStr) => {
         if (!dateStr) return 0;
         const d = new Date(dateStr).getTime();
         const n = getCurrentTime(); // Use simulated time
         const diffMs = n - d;
         
         if (warningUnit === 'Hours') return diffMs / (1000 * 60 * 60);
         if (warningUnit === 'Minutes') return diffMs / (1000 * 60);
         if (warningUnit === 'Minutes') return diffMs / (1000 * 60);
         return diffMs / (1000 * 60 * 60 * 24); // Default Days
    };

    // Check if plan has exceeded warning threshold (for badge blinking)
    const hasExceededThreshold = (plan) => {
        if (!plan || !plan.created_at) return false;
        
        const planTime = new Date(plan.created_at).getTime();
        const now = getCurrentTime(); // Use simulated time
        const diffMs = now - planTime;
        
        const warningUnit = props.timeSettings?.warning_time_unit || 'Days';
        const warningValue = props.timeSettings?.warning_threshold || 3;
        
        const getMs = (val, unit) => {
            if (unit === 'Hours') return val * 3600000;
            if (unit === 'Minutes') return val * 60000;
            return val * 86400000;
        };
        
        const warningMs = getMs(warningValue, warningUnit);
        return diffMs >= warningMs;
    };

    // 0. SPECIAL: CLOSED PLAN (100% Closing, fully approved)
    // If plan has report with progress 100%-Closing AND is approved by BOD -> CLOSED (Black Badge)
    if (plan && plan.report && plan.report.progress === '100%-Closing' && plan.bod_status === 'success') {
        return 'closed';
    }

    // 0b. SPECIAL: ESCALATION PLAN (Treat as No Plan for visual warning)
    if (plan && plan.activity_type === 'ESCALATION') {
        const age = getDiffValue(customer.created_at);
        if (age >= warningThreshold) return 'no_plan_warning';
        return 'none';
    }

    // 0c. SPECIAL: APPROVED BY MANAGER (Waiting BOD)
    // If Manager approved, stop blinking (Timer paused for User). 
    // Unless BOD failed/rejected it later.
    if (plan && plan.manager_status === 'approved' && plan.bod_status !== 'failed' && plan.bod_status !== 'success') {
        return 'approved'; // Static Green + "Approved"
    }

    // 1. KASUS TIDAK ADA PLAN SAMA SEKALI (Masalah Customer)
    if (!plan) {
        const age = getDiffValue(customer.created_at);
        // Jika customer baru tapi didiamkan > Threshold -> BLINKING RED (Warning)
        if (age >= warningThreshold) {
            return 'no_plan_warning'; 
        }
        return 'none'; // Abu-abu
    }

    // 1b. KASUS REJECTED untuk plan REPORTED - ONLY when BOD Failed
    if (plan.status === 'reported' && plan.bod_status === 'failed') {
        if (plan.manager_status !== 'approved') {
            const age = getDiffValue(plan.updated_at || plan.created_at);
            if (age >= warningThreshold) return 'rejected_warning';
            return 'rejected_final'; 
        }
    }
    
    // 2. KASUS PLAN BELUM REPORT (Masalah Plan Individu)
    if (plan.status === 'created') {
        const expiryUnit = props.timeSettings?.plan_expiry_unit || 'Days (Production)';
        const expiryValue = props.timeSettings?.plan_expiry_value || 7;
        
        const getMs = (val, unit) => {
            if (!unit) return val * 86400000; // Default Days
            const u = unit.trim().toLowerCase();
            if (u.includes('hour')) return val * 3600000;
            if (u.includes('minute')) return val * 60000;
            return val * 86400000; // Default Days
        };

        const now = getCurrentTime();
        const expiryMs = getMs(expiryValue, expiryUnit);
        const warningMs = getMs(warningThreshold, warningUnit);
        
        // =========================================================================
        // FIX: Check if planning_date is in the FUTURE
        // Plans scheduled for future dates should NOT trigger warning/expiry
        // They stay as static RED (created) until their planning_date arrives
        // =========================================================================
        const planningDateStr = plan.planning_date ? plan.planning_date.split('T')[0] : null;
        const todayStr = new Date(now).toISOString().split('T')[0];
        
        if (planningDateStr && planningDateStr > todayStr) {
            // Plan is for a FUTURE date - no warning/expiry applies yet
            // PRIORITY CHECK: Still check rejected status for future plans
            if (plan.lifecycle_status === 'rejected' || plan.bod_status === 'failed' || plan.manager_status === 'rejected') {
                return 'rejected_final'; // Static Red + "Rejected" (no blinking for future rejected)
            }
            return 'created'; // STATIC RED - it's a future plan, not late yet
        }
        
        // =========================================================================
        // planning_date has passed (or is today) - now calculate warning/expiry
        // Use planning_date as reference (when plan was supposed to be executed)
        // =========================================================================
        // For plans where planning_date has passed, calculate diff from planning_date
        // This way, warning starts counting from the scheduled execution date
        const planningDateMs = planningDateStr 
            ? new Date(planningDateStr + 'T23:59:59').getTime() // End of planning day
            : new Date(plan.created_at).getTime(); // Fallback to created_at
        
        const diffMs = now - planningDateMs;
        
        // If diffMs is negative, planning_date hasn't fully passed yet (still today)
        // In this case, don't trigger warning/expiry
        if (diffMs < 0) {
            if (plan.lifecycle_status === 'rejected' || plan.bod_status === 'failed' || plan.manager_status === 'rejected') {
                return 'rejected_final';
            }
            return 'created'; // STATIC RED - still on the execution day
        }

        // PRIORITY CHECK: REJECTED PLANS (after planning_date passed)
        // If rejected, verify if it should blink (Warning)
        if (plan.lifecycle_status === 'rejected' || plan.bod_status === 'failed' || plan.manager_status === 'rejected') {
            if (diffMs >= warningMs) {
                return 'rejected_warning'; // Blink Red + "Rejected"
            }
            return 'rejected_final'; // Static Red + "Rejected"
        }
        
        // 1. Check Expired FIRST
        if (diffMs >= expiryMs) {
            if (diffMs >= warningMs) {
                return 'expired_warning'; 
            }
            return 'expired'; 
        }
        
        // 2. Check Warning 
        if (diffMs >= warningMs) {
            return 'warning'; 
        }

        return 'created'; // MERAH DIAM
    }

    // 3. KASUS PLAN SUDAH REPORT (Masalah Customer - Follow Up)

    // CHECK REJECTION FIRST
    // Use updated_at to ensure precision for Minutes/Hours time units
    let lastActivityDate = plan.updated_at || plan.planning_date; 
    const inactivityScore = getDiffValue(lastActivityDate);

    if (plan.lifecycle_status === 'failed' || plan.bod_status === 'failed' || plan.manager_status === 'rejected') {
        if (inactivityScore >= warningThreshold) {
            return 'rejected_warning';
        }
        return 'rejected_final'; 
    }

    // CHECK STOP BLINKING CONDITIONS (History/Success)
    const isHistory = 
        plan.bod_status === 'success' || 
        plan.lifecycle_status === 'success' || 
        (plan.status_log && plan.status_log.length > 0 && plan.status_log[plan.status_log.length - 1].status.toLowerCase() === 'history');

    if (isHistory) {
         return 'reported'; // STATIC GREEN
    }

    // =========================================================================
    // FIX: EXCLUDE PLANS WAITING FOR APPROVAL FROM WARNING THRESHOLD
    // If plan is reported and waiting for Manager or BOD approval,
    // do NOT apply warning threshold - user has done their job!
    // Warning only applies after BOD finalizes (success/failed) for follow-up.
    // =========================================================================
    const isWaitingApproval = 
        plan.status === 'reported' && (
            // Case 1: Waiting for Manager review
            plan.manager_status === 'pending' ||
            // Case 2: Manager approved, waiting for BOD review
            (plan.manager_status === 'approved' && plan.bod_status === 'pending') ||
            // Case 3: Manager escalated (still pending BOD action)
            plan.manager_status === 'escalated'
        );

    if (isWaitingApproval) {
        // Stay GREEN (static) - user has completed their task
        // Manager/BOD needs to review, not the user's responsibility
        return 'reported'; // HIJAU DIAM
    }
    
    // Only apply warning threshold for:
    // 1. Plans that have been fully approved (BOD success) - user needs to create next plan
    // 2. This path shouldn't normally be reached due to isHistory check above
    if (inactivityScore >= warningThreshold) {
        return 'warning'; // MERAH KEDIP-KEDIP (for follow-up reminder after BOD success)
    }

    // 4. NORMAL
    if (plan.bod_status === 'success') {
        if (plan.report && plan.report.progress === '100%-Closing') {
            return 'closed';
        }
        return 'approved'; 
    }

    // If Manager approved (and fell through 0c above due to some reason? Unlikely), handle it
    if (plan.manager_status === 'approved') return 'approved';

    return 'reported'; // HIJAU
};

// "Semakin lama semakin kencang" - Dynamic Blinking Speed Logic
// UPGRADE: Use RATIO based on Threshold, not Hardcoded Days.
const getBlinkStyle = (source) => {
    if (!source) return {};
    
    const now = getCurrentTime();
    
    // If source is a Plan object, check if planning_date is in the future
    if (typeof source === 'object' && source.planning_date) {
        const planningDateStr = source.planning_date.split('T')[0];
        const todayStr = new Date(now).toISOString().split('T')[0];
        
        // If planning_date is in the future, no blinking animation
        if (planningDateStr > todayStr) {
            return {};
        }
    }
    
    let dateStr;
    
    // Determine date source - for blink speed calculation
    if (typeof source === 'object' && (source.planning_date || source.created_at)) {
        // It's a Plan object
        // For plans that have passed, use planning_date as reference (when it was due)
        // This is consistent with the warning threshold logic
        const planDateStr = source.planning_date ? source.planning_date.split('T')[0] : null;
        if (planDateStr) {
            dateStr = planDateStr + 'T23:59:59'; // End of planning day
        } else {
            dateStr = source.created_at;
        }
    } else {
        // It's likely a raw date string (e.g. from customer.created_at)
        dateStr = source;
    }
    
    if (!dateStr) return {};

    // Calculate Age
    const dateObj = new Date(dateStr);
    const diffMs = now - dateObj.getTime();
    
    // If diffMs is negative (date is in the future), no blink
    if (diffMs < 0) return {};

    // Get Threshold info
    const warningUnit = props.timeSettings?.planning_time_unit || 'Days (Production)';
    const warningThreshold = props.timeSettings?.planning_warning_threshold || 14;

    const getMs = (val, unit) => {
        if (!unit) return val * 86400000;
        const u = unit.trim().toLowerCase();
        if (u.includes('hour')) return val * 3600000;
        if (u.includes('minute')) return val * 60000;
        return val * 86400000;
    };

    const warningMs = getMs(warningThreshold, warningUnit);
    
    // Avoid division by zero
    if (warningMs <= 0) return { animationDuration: '2s' };

    // Ratio: 1.0 = Exactly at Threshold. 2.0 = Double the allowed time.
    const ratio = diffMs / warningMs;

    let duration = '2s'; // Default slow 
    
    // Adjusted Ratios:
    if (ratio >= 10.0) {
        duration = '0.15s'; // Panic (>10x threshold)
    } else if (ratio >= 5.0) {
        duration = '0.3s'; // Very Fast
    } else if (ratio >= 2.5) {
        duration = '0.6s'; // Fast
    } else if (ratio >= 1.0) {
        duration = '1s'; // Medium
    }
    
    // Only apply valid duration
    return { animationDuration: duration };
};

/**
 * CONTROL STATUS LOGIC (Manager Action):
 * - 'rejected' (Red): Reject - planning tidak ada atau failed
 * - 'escalated' (Blinking): Escalate - untuk follow up, eskalasi, discuss
 * - 'approved' (Green): Approve - planning berhasil (hanya jika planning sudah reported/green)
 * - 'auto_fail' (Red): Otomatis fail karena planning none/expired
 * 
 * RULES:
 * - Control column ONLY reflects Manager action
 * - BOD action does NOT affect Control column
 * - Jika Planning = none/gray atau expired → Control merah (no manager action possible on non-existent plan)
 * - Otherwise: Control = Manager action (reject/approve/escalate/pending)
 */
const getControlStatus = (customer) => {
    const plan = customer.latest_plan;
    const pStatus = getPlanningStatus(customer);
    
    // No plan at all -> Red (no action possible)
    // Note: For ESCALATION plans, pStatus will be 'none' or 'no_plan_warning',
    // but manager_status will be 'escalated', so it will be handled below
    if (!plan) {
        return 'rejected';
    }
    
    // BOD REJECTION OVERRIDES MANAGER APPROVAL
    // If BOD has failed/rejected, Control shows RED regardless of manager action
    if (plan.bod_status === 'failed') {
        return 'rejected';
    }
    
    // IMPORTANT: Control ONLY reflects manager_status
    // Manager decision takes priority over plan status
    if (plan.manager_status && plan.manager_status !== 'pending') {
        return plan.manager_status; // approved, rejected, or escalated
    }
    
    // No manager action yet and no plan -> Red
    if (pStatus === 'none' || pStatus === 'no_plan_warning') {
        return 'rejected';
    }
    
    // For expired plans without report -> Red (plan failed due to inactivity)
    // Only apply this if manager hasn't made a decision yet
    if (pStatus === 'expired' || pStatus === 'expired_warning' || pStatus === 'warning') {
        return 'rejected';
    }
    
    // Default: Pending (waiting for manager action)
    // Refinement: If Plan is Reported (Green) but Manager hasn't acted -> "Need Review" (Yellow)
    if (pStatus === 'reported' || pStatus === 'approved') { // Approved included for safety, though manager_status check above handles 'approved'
         return 'need_review';
    }

    return 'pending';
};

/**
 * MONITORING STATUS LOGIC (BOD Action):
 * - 'pending' (Yellow): Report exists, waiting for BOD review
 * - 'failed' (Red): BOD marked as failed
 * - 'success' (Green): BOD marked as success
 * 
 * RULES:
 * - Monitoring column ONLY reflects BOD action
 * - Manager action does NOT affect Monitoring column
 * - If no report exists -> Monitoring red (no BOD action possible)
 * - If report exists -> Monitoring = BOD action (success/failed/pending)
 */
const getMonitoringStatus = (customer) => {
    const plan = customer.latest_plan;
    const pStatus = getPlanningStatus(customer);
    
    // No plan or expired without report -> Red (no BOD action possible)
    if (!plan || pStatus === 'none' || pStatus === 'no_plan_warning' || 
        pStatus === 'expired' || pStatus === 'expired_warning' || pStatus === 'warning' || 
        pStatus === 'created') {
        return 'failed';
    }
    
    // Plan with report (approved/reported/rejected_final/rejected_warning)
    
    // BOD REJECTION TAKES PRIORITY
    // If BOD has failed, show RED regardless of manager status
    if (plan.bod_status === 'failed') {
        return 'failed';
    }
    
    // If Manager Rejected (before BOD reviewed) -> also show failed
    if (plan.manager_status === 'rejected' || plan.lifecycle_status === 'failed') {
        return 'failed';
    }
    
    // Check Control Status for "Wait Manager"
    const cStatus = getControlStatus(customer);
    
    // If Manager hasn't approved yet (Need Review or Pending with Report), BOD waits
    if (cStatus === 'need_review' || (cStatus === 'pending' && ['reported', 'approved'].includes(pStatus))) {
        return 'wait_manager';
    }

    // Show BOD status directly (Success)
    if (plan.bod_status === 'success') {
        return 'success';
    }
    
    // Default: Pending (waiting for BOD action after Manager Approved)
    return 'pending';
};

/**
 * Helper: Cek apakah toggle Approve bisa diklik
 * Approve hanya bisa jika Planning status = reported (green)
 */
const canApprove = (customer) => {
    const pStatus = getPlanningStatus(customer);
    // Show Approve toggle when plan has report (reported) OR already approved
    // This allows manager to sync toggle with BOD's decision
    return pStatus === 'reported' || pStatus === 'approved';
};

/**
 * Helper: Cek apakah toggle Monitoring (Success/Failed) bisa diklik
 * 
 * RULES:
 * - Plan harus punya report (status = 'reported')
 * - Manager harus sudah action APPROVE atau REJECTED
 * - BOD TIDAK bisa bertindak jika Manager memilih ESCALATE
 * - Escalate berarti masalah perlu review lebih tinggi, bukan finalisasi BOD
 */
const canToggleMonitoring = (customer) => {
    const plan = customer.latest_plan;
    
    // 1. Must have a plan with report
    if (!plan || plan.status !== 'reported') {
        // Special case: Allow BOD toggle for manually failed plans
        if (plan && plan.lifecycle_status === 'failed' && plan.status === 'created') {
            // But still require manager approved/rejected (not escalated)
            return plan.manager_status === 'approved' || plan.manager_status === 'rejected';
        }
        return false;
    }
    
    // 2. Manager MUST have acted with APPROVE or REJECTED
    // ESCALATE does NOT allow BOD to review
    const managerApprovedOrRejected = plan.manager_status === 'approved' || plan.manager_status === 'rejected';
    
    if (!managerApprovedOrRejected) {
        return false; // BOD must wait for Manager to approve/reject (not escalate)
    }
    
    // 3. Manager approved/rejected -> BOD can now review
    return true;
};

// ========================================
// ACTION CONFIRMATION MODAL STATE
// ========================================
const showActionConfirm = ref(false);
const actionType = ref(''); // 'control' or 'monitoring'
const actionStatus = ref(''); // 'rejected', 'escalated', 'approved', 'success', 'failed'
const actionPlanId = ref(null);
const actionCustomerName = ref('');
const actionLoading = ref(false);
const actionNotes = ref('');
const actionQuota = ref({ remaining: 3, max: 3, grace_seconds: 0, is_super_admin: false, can_change: true });

// Local toast state for limit messages
const showLocalToast = ref(false);
const localToastMessage = ref('');
const localToastType = ref('error');

// Reset All Limits Modal (Super Admin only)
const showResetLimitsModal = ref(false);
const resetLimitsLoading = ref(false);

const confirmResetAllLimits = () => {
    showResetLimitsModal.value = true;
};

const executeResetAllLimits = () => {
    resetLimitsLoading.value = true;
    router.delete(route('planning.reset-all-status-logs'), {
        onSuccess: () => {
            showResetLimitsModal.value = false;
            resetLimitsLoading.value = false;
        },
        onError: () => {
            resetLimitsLoading.value = false;
        }
    });
};

// Status label mapping
const statusLabels = {
    rejected: { text: 'Reject', color: 'text-red-600', bg: 'bg-red-100', border: 'border-red-200' },
    escalated: { text: 'Escalate', color: 'text-amber-600', bg: 'bg-amber-100', border: 'border-amber-200' },
    approved: { text: 'Approve', color: 'text-emerald-600', bg: 'bg-emerald-100', border: 'border-emerald-200' },
    success: { text: 'Next', color: 'text-emerald-600', bg: 'bg-emerald-100', border: 'border-emerald-200' },
    failed: { text: 'Failed', color: 'text-red-600', bg: 'bg-red-100', border: 'border-red-200' },
    pending: { text: 'Pending', color: 'text-gray-600', bg: 'bg-gray-100', border: 'border-gray-200' },
};

// Fetch quota info before showing confirmation
const fetchQuotaInfo = async (planId, type) => {
    try {
        const response = await axios.get(route('planning.status-info', planId));
        return type === 'control' ? response.data.manager : response.data.bod;
    } catch (error) {
        console.error('Failed to fetch quota info:', error);
        return { remaining: 0, max: 3, grace_seconds: 0, is_super_admin: false, can_change: false };
    }
};

// Show confirmation modal for Manager Action
const confirmControl = async (planId, status, customerName) => {
    actionType.value = 'control';
    actionStatus.value = status;
    actionPlanId.value = planId;
    actionCustomerName.value = customerName;
    actionNotes.value = ''; // Reset notes
    actionLoading.value = true;
    
    // Fetch current quota
    actionQuota.value = await fetchQuotaInfo(planId, 'control');
    actionLoading.value = false;
    
    // Check if can make change - DEPRECATED QUOTA CHECK
    // if (!actionQuota.value.can_change) { ... }
    
    showActionConfirm.value = true;
};

// Show confirmation modal for BOD Action
const confirmMonitoring = async (planId, status, customerName) => {
    actionType.value = 'monitoring';
    actionStatus.value = status;
    actionPlanId.value = planId;
    actionCustomerName.value = customerName;
    actionNotes.value = ''; // Reset notes
    actionLoading.value = true;
    
    // Fetch current quota
    actionQuota.value = await fetchQuotaInfo(planId, 'monitoring');
    actionLoading.value = false;
    
    // Check if can make change
    if (!actionQuota.value.can_change) {
        localToastMessage.value = 'You have reached the maximum status changes limit (3x). Contact Super Admin if needed.';
        localToastType.value = 'error';
        showLocalToast.value = true;
        return;
    }
    
    showActionConfirm.value = true;
};

// Execute the action after confirmation
const executeAction = () => {
    // Optimistic Update: Update UI immediately
    const customer = props.customers?.data?.find(c => c.latest_plan && c.latest_plan.id === actionPlanId.value);
    if (customer && customer.latest_plan) {
        if (actionType.value === 'control') {
            customer.latest_plan.manager_status = actionStatus.value;
            customer.latest_plan.manager_reviewed_at = props.currentSimulatedTime 
                ? new Date(props.currentSimulatedTime).toISOString() 
                : new Date().toISOString(); 
            
            // Sync with Backend Logic
            if (actionStatus.value === 'rejected') {
                customer.latest_plan.bod_status = 'failed';
                customer.latest_plan.lifecycle_status = 'failed';
            } else if (['approved', 'escalated', 'pending'].includes(actionStatus.value) && customer.latest_plan.bod_status === 'failed') {
                 // Reset Logic (Uni-directional from Manager Action)
                 customer.latest_plan.bod_status = 'pending';
                 if (customer.latest_plan.lifecycle_status === 'failed') {
                     customer.latest_plan.lifecycle_status = 'active'; // or 'active' enum equivalent
                 }
            }
        } else {
            customer.latest_plan.bod_status = actionStatus.value;
            customer.latest_plan.bod_reviewed_at = props.currentSimulatedTime 
                ? new Date(props.currentSimulatedTime).toISOString() 
                : new Date().toISOString();
        }
    }

    if (actionType.value === 'control') {
        router.patch(route('planning.update-control', actionPlanId.value), {
            manager_status: actionStatus.value,
            notes: actionNotes.value
        }, { 
            preserveScroll: true,
            only: ['customers'], // Force reload customers data with fresh timestamps
            onSuccess: () => {
                showActionConfirm.value = false;
                actionNotes.value = '';
            }
        });
    } else {
        router.patch(route('planning.update-monitoring', actionPlanId.value), {
            bod_status: actionStatus.value,
            notes: actionNotes.value
        }, { 
            preserveScroll: true,
            only: ['customers'], // Force reload customers data with fresh timestamps
            onSuccess: () => {
                showActionConfirm.value = false;
                actionNotes.value = '';
            }
        });
    }
};

// Close modal
const closeActionModal = () => {
    showActionConfirm.value = false;
    actionPlanId.value = null;
    actionStatus.value = '';
    actionType.value = '';
};

// ========================================
// LOCK MECHANISM & CONSTRAINTS (NEW)
// ========================================

// 1. Create Plan Constraint (Friday Only)
const canCreatePlanToday = computed(() => {
    // 1. If Testing Mode is ON, allow any day
    if (props.timeSettings?.testing_mode) return true;

    const today = new Date();
    const dayOfWeek = today.getDay(); // 0=Sun, 6=Sat
    
    // Default to Friday (5) if settings not loaded
    let allowedDays = props.timeSettings?.allowed_plan_creation_days || ['Friday'];
    
    // Handle JSON string case
    if (typeof allowedDays === 'string') {
        try { allowedDays = JSON.parse(allowedDays); } catch(e) { allowedDays = ['Friday']; }
    }
    
    if (!Array.isArray(allowedDays)) allowedDays = ['Friday'];

    // Map Day Names to Integers (JS Date.getDay())
    const dayNameMap = {
        'Sunday': 0, 'Monday': 1, 'Tuesday': 2, 'Wednesday': 3, 'Thursday': 4, 'Friday': 5, 'Saturday': 6
    };

    // Convert mixed input to valid integers
    const allowedDaysNum = allowedDays.map(val => {
        // If it's a number/numeric string
        if (!isNaN(parseInt(val))) return parseInt(val);
        // If it's a string name
        return dayNameMap[val] ?? -1;
    });

    return allowedDaysNum.includes(dayOfWeek);
});

const getAllowedDaysText = () => {
    let allowedDays = props.timeSettings?.allowed_plan_creation_days || ['Friday'];
    
    if (typeof allowedDays === 'string') {
        try { allowedDays = JSON.parse(allowedDays); } catch(e) { allowedDays = ['Friday']; }
    }
    
    if (!Array.isArray(allowedDays)) return 'Friday';
    if (allowedDays.length === 0) return 'None';
    if (allowedDays.length === 7) return 'Any Day';

    // If array check if numbers or strings
    // If numbers, map to names. If strings, just join.
    const daysMap = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
    return allowedDays.map(d => {
        if (!isNaN(parseInt(d))) return daysMap[parseInt(d)];
        return d;
    }).join(', ');
};

// 2. Create Report Constraint (Mon-Fri)
const canCreateReport = (plan) => {
    if (!plan || plan.status !== 'created') return false;
    
    // Check if expired (visual check)
    // Reuse basic expired check logic or rely on backend validation mostly, 
    // but for UI, if visual status is 'expired', disable it.
    // For simplicity, we just check day constraints here.
    
    const today = new Date();
    const dayOfWeek = today.getDay();
    // Monday(1) to Friday(5)
    return dayOfWeek >= 1 && dayOfWeek <= 5;
};



// Helper to calculate expiry (reused logic)
const checkPlanExpiry = (plan) => {
    if (!plan) return false; // Allow checking for any status
    const expiryUnit = props.timeSettings?.plan_expiry_unit || 'Days (Production)';
    const expiryValue = props.timeSettings?.plan_expiry_value || 7;
    
    const planTime = new Date(plan.created_at || plan.planning_date).getTime();
    const now = new Date().getTime();
    const diffMs = now - planTime;

    let diffExpiry = 0;
    if (expiryUnit === 'Hours') diffExpiry = diffMs / (1000 * 60 * 60);
    else if (expiryUnit === 'Minutes') diffExpiry = diffMs / (1000 * 60);
    else diffExpiry = diffMs / (1000 * 60 * 60 * 24);

    return diffExpiry >= expiryValue;
};

// 3. Manager Lock Logic
const isManagerLocked = (plan) => {
    // Allow unlocked even if no plan exists (so we can Escalate)
    if (!plan) return false;
    if (props.user_roles && props.user_roles.includes('Super Admin')) return false;

    // Never fully lock manager toggles - specific toggle control handled by canToggleManagerAction
    return false;
};

// 3b. Manager Toggle Control - which specific toggle can be clicked
// Logic based on Planning status:
// - GRAY (none, no_plan_warning): Only Escalate
// - BLINKING (warning, expired_warning, rejected_warning): Only Escalate
// - RED (created, expired, rejected_final): Reject and Escalate
// - GREEN (reported, approved): Only Reject and Approve (no Escalate)
const canToggleManagerAction = (plan, targetAction, customer = null) => {
    // If no plan exists, allow Only Escalate
    if (!plan) {
        return targetAction === 'escalated';
    }
    if (props.user_roles && props.user_roles.includes('Super Admin')) return true;
    
    // Get Planning status for restrictions
    if (customer) {
        const pStatus = getPlanningStatus(customer);
        
        // --- 1. MANAGER FINALIZED DECISION LOCK ---
        // If Manager has already Approved or Rejected, they cannot switch to Escalate.
        // (They are effectively locked from changing mainly by backend/action-lock, but this UI lock reinforces it)
        if (['approved', 'rejected'].includes(plan.manager_status) && targetAction === 'escalated') {
            return false;
        }

        // --- 2. REPORT EXISTENCE LOGIC ---
        // "Reported" or "Approved" (Green) means a report exists -> Ready for Manager Decision
        const hasReport = ['reported', 'approved'].includes(pStatus);

        if (hasReport) {
            // Plan has Report: Manager can Approve or Reject. 
            // Escalate is NOT allowed (why escalate if report is ready? Just reject if bad).
            return targetAction === 'approved' || targetAction === 'rejected';
        } else {
            // Plan has NO Report (Created, Warning, Expired, None, etc.):
            // Manager CANNOT Approve/Reject (nothing to approve).
            // Manager CAN ONLY Escalate (Remind user).
            return targetAction === 'escalated';
        }
    }
    
    // If BOD has NOT made decision yet, all toggles are available
    if (!plan.bod_status || plan.bod_status === 'pending') {
        return true;
    }
    
    // Manager chose ESCALATE and BOD has decided:
    // - BOD Failed → Allow ALL actions (Manager can override/change mind)
    // - BOD Success → Only Approve toggle can be selected (to sync with BOD)
    if (plan.bod_status === 'failed') {
        return true;
    }
    
    if (plan.bod_status === 'success') {
        // Allow clicking Approve to match BOD's Success decision
        // Also allow clicking Escalate to toggle off
        return targetAction === 'approved' || targetAction === 'escalated';
    }
    
    return true;
};

// Helper: Visibility logic for Manager buttons
// - Approved/Rejected: Hide other buttons (Clean Final Decision)
// - Pending/Escalated: Show all buttons
const shouldShowManagerAction = (plan, actionType) => {
    if (!plan) return true;
    
    const status = plan.manager_status || 'pending';
    
    // Always show all options if pending or escalated (allow changing mind if escalated)
    if (status === 'pending' || status === 'escalated') {
        return true;
    }
    
    // If finalized (approved/rejected), ONLY show the matching action
    return status === actionType;
};

// 4. BOD Lock Logic
// 4. BOD Lock Logic
const isBODLocked = (plan) => {
    if (!plan) return true;
    
    // We remove the frontend time-lock to prevent timezone issues.
    // The backend in confirmMonitoring -> fetchQuotaInfo will handle the strict validation
    // and show a Toast error if the limit (3x) or time (5min) is exceeded.
    return false;
};

// 5. Get Grace Period Text
const getGracePeriodText = (plan, type) => {
    if (!plan) return null;
    if (type === 'manager') {
        if (isManagerLocked(plan)) return null;
        if (plan.manager_status === 'pending') return null;
        if (!plan.manager_reviewed_at) return null;

        // Calculate expire time
        const reviewDate = new Date(plan.manager_reviewed_at);
        const expiryDate = new Date(reviewDate);
        expiryDate.setDate(reviewDate.getDate() + 7);
        
        const now = new Date();
        const diffMs = expiryDate - now;
        if (diffMs <= 0) return null;
        
        const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        return `${days}d ${hours}h left`;
    }
    
    if (type === 'bod') {
        if (isBODLocked(plan)) return null;
        if (plan.bod_status === 'pending') return null;
        if (!plan.bod_reviewed_at) return null;
        
        const reviewDate = new Date(plan.bod_reviewed_at);
        const expiryDate = new Date(reviewDate);
        expiryDate.setMinutes(reviewDate.getMinutes() + 5);
        
        const now = new Date();
        const diffMs = expiryDate - now;
        if (diffMs <= 0) return null;
        
        const minutes = Math.floor(diffMs / (1000 * 60));
        const seconds = Math.floor((diffMs % (1000 * 60)) / 1000);
        return `${minutes}m ${seconds}s left`;
    }
    
    return null;
};

// 5. Handle Escalate on No Plan
const handleNoPlanEscalate = (customer) => {
    // 1. Get Product ID safely
    let productId = null;
    if (customer.product) {
        productId = customer.product.id;
    } else if (customer.products && customer.products.length > 0) {
        productId = customer.products[0].id;
    } else {
        localToastMessage.value = `Cannot Escalate: Customer has no product assigned.`;
        localToastType.value = 'error';
        showLocalToast.value = true;
        return;
    }

    // 2. Create Real Plan (Persistent)
    router.post(route('planning.store'), {
        customer_id: customer.id,
        product_id: productId,
        planning_date: new Date().toISOString().split('T')[0],
        activity_type: 'ESCALATION',
        description: 'Manager Escalation Reminder',
        manager_status: 'escalated'
    }, {
        preserveScroll: true,
        // Backend already sends success flash message, no need for duplicate toast
        onError: (errors) => {
             localToastMessage.value = `Failed to escalate: ${Object.values(errors).join(', ')}`;
             localToastType.value = 'error';
             showLocalToast.value = true;
        }
    });
};

// Legacy functions for backward compatibility (direct call without confirmation - now deprecated)
const updateControl = (planId, status) => {
    router.patch(route('planning.update-control', planId), {
        manager_status: status
    }, { preserveScroll: true });
};

const updateMonitoring = (planId, status) => {
    router.patch(route('planning.update-monitoring', planId), {
        bod_status: status
    }, { preserveScroll: true });
};

const showFailConfirm = ref(false);
const failPlanId = ref(null);
const failLoading = ref(false);

const markPlanFailed = (planId) => {
    failPlanId.value = planId;
    showFailConfirm.value = true;
};

const executeFailPlan = () => {
    if (!failPlanId.value) return;
    failLoading.value = true;
    router.patch(route('planning.fail', failPlanId.value), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showFailConfirm.value = false;
            failPlanId.value = null;
            failLoading.value = false;
        },
        onError: () => {
            failLoading.value = false;
        }
    });
};

// BOD Reminder Modal Logic (Moved here to access helpers)
// BOD Reminder Modal Logic
const showReminderModal = ref(false);
const reminderState = ref({ type: '', count: 0 });

// --- TABS LOGIC ---
const currentTab = ref(props.filters.tab || 'all');
const tabs = [
    { id: 'all', name: 'All Planning' },
    { id: 'week1', name: 'Week 1' },
    { id: 'week2', name: 'Week 2' },
    { id: 'week3', name: 'Week 3' },
    { id: 'week4', name: 'Week 4' },
    { id: 'week5', name: 'Week 5' },
];

const isTabSwitching = ref(false);

const changeTab = (tabId) => {
    // Guard: Prevent redundant changes and rapid switching (Lock for 500ms)
    if (currentTab.value === tabId || isTabSwitching.value) {
        console.log('[changeTab] Blocked: Already on tab or switching. Target:', tabId);
        return;
    }
    
    // Lock tab switching
    isTabSwitching.value = true;
    setTimeout(() => { isTabSwitching.value = false; }, 500);
    
    // Cancel any pending param updates from watchers (like perPage)
    updateParams.cancel(); 

    console.log('[changeTab] Switching to:', tabId, 'Current tab:', currentTab.value);
    currentTab.value = tabId;
    
    // Auto set perPage to 'all' for All Planning tab
    const newPerPage = tabId === 'all' ? 'all' : perPage.value;
    perPage.value = newPerPage;
    
    // Exclude 'tab' from filters to prevent conflict with new tabId
    const { tab: oldTab, ...otherFilters } = props.filters || {};
    
    console.log('[changeTab] perPage:', newPerPage, 'New tab:', tabId, 'Old tab was:', oldTab);
    
    router.get(route('planning.index'), {
        ...otherFilters,  // Spread other filters (search, team, user, etc) WITHOUT tab
        tab: tabId,       // Use NEW tab value
        perPage: newPerPage,
        page: 1 // Reset pagination
    }, {
        preserveState: false, // Don't preserve state - fetch fresh data for new tab
        preserveScroll: false, // Scroll to top when changing tabs
        replace: true
    });
};

const getDetailBadge = (plan) => {
    // 1. Check Strict Expiry for 'created' plans (Unreported)
    if (plan.status === 'created') {
        const expiryUnit = props.timeSettings?.plan_expiry_unit || 'Days (Production)';
        const expiryValue = props.timeSettings?.plan_expiry_value || 7;
        const planDate = new Date(plan.created_at || plan.planning_date).getTime();
        const now = new Date().getTime();
        const diffMs = now - planDate;

        let diffExpiry = 0;
        if (expiryUnit === 'Hours') diffExpiry = diffMs / (1000 * 60 * 60);
        else if (expiryUnit === 'Minutes') diffExpiry = diffMs / (1000 * 60);
        else diffExpiry = diffMs / (1000 * 60 * 60 * 24);

        if (diffExpiry >= expiryValue) {
            return { 
                label: 'EXPIRED', 
                class: 'bg-red-100 text-red-800 border-red-200' 
            };
        }
        
        // Check Warning
        const warningThreshold = props.timeSettings?.planning_warning_threshold || 14;
        // Calculation for warning might be different unit (Planning Badge Unit), but let's assume consistent for now or just stick to 'Pending' if not expired.
        // Actually, if it's created and not expired, it's Pending.
        return { 
            label: 'PENDING', 
            class: 'bg-gray-100 text-gray-600 border-gray-200' 
        };
    }

    // 2. Reported Plans - Use Manager Status & BOD Status
    const managerStatus = plan.manager_status || 'pending';
    const bodStatus = plan.bod_status || 'pending';

    // If Manager Rejected -> Final Reject
    if (managerStatus === 'rejected') return 'rejected_final';

    // If Manager Approved AND BOD Success -> Approved
    if (managerStatus === 'approved' && bodStatus === 'success') return 'approved';

    // If Manager Approved AND BOD Failed -> Rejected Final (BOD veto)
    // Wait, if BOD fails it, it's rejected too.
    if (managerStatus === 'approved' && bodStatus === 'failed') return 'rejected_final';
    
    // If Manager Approved (BOD pending) -> Reported/Waiting
    if (managerStatus === 'approved') return 'reported';

    switch (managerStatus) {
        // 'rejected' handled above
        case 'escalated':
            return 'warning'; // or 'reported' but yellow? 
        default:
            return 'reported'; // Created + Reported (since checks above passed)
    }
};


const updateHistoryStatus = (plan, type, status) => {
    const routeName = type === 'control' ? 'planning.update-control' : 'planning.update-monitoring';
    const field = type === 'control' ? 'manager_status' : 'bod_status';
    
    // Optimistic UI update (Instant Feedback)
    if (type === 'control') plan.manager_status = status;
    else plan.bod_status = status;

    router.patch(route(routeName, plan.id), {
        [field]: status
    }, {
        preserveScroll: true,
        onSuccess: (page) => {
            // Refresh modal data with fresh data from server
            if (selectedCustomer.value) {
                const freshCustomer = page.props.customers.data.find(c => c.id === selectedCustomer.value.id);
                if (freshCustomer) {
                    selectedCustomer.value = freshCustomer;
                }
            }
        }
    });
};
// REMOVED: Watch for props.filters.tab was causing auto-switch back to default tab
// currentTab is already properly updated in changeTab() function


const pendingReviewCount = computed(() => {
    if (!props.customers?.data) return 0;
    return props.customers.data.filter(customer => getMonitoringStatus(customer) === 'pending').length;
});

const pendingManagerCount = computed(() => {
    if (!props.customers?.data) return 0;
    return props.customers.data.filter(c => {
       const pStatus = getPlanningStatus(c);
       const plan = c.latest_plan;
       return (pStatus === 'reported' || pStatus === 'warning') && (!plan?.manager_status || plan.manager_status === 'pending');
    }).length;
});

const expiredCount = computed(() => {
    if (!props.customers?.data) return 0;
    return props.customers.data.filter(c => getPlanningStatus(c) === 'expired').length;
});

/* ========================
   AUTO REFRESH (POLLING)
   ======================== */
const autoRefreshTimer = ref(null);

const startAutoRefresh = () => {
    // Refresh interval: 5 seconds (Optimal balance between Real-time & Performance)
    autoRefreshTimer.value = setInterval(() => {
        // Pause if any modal is open or tab is switching
        if (isDetailOpen.value || showActionConfirm.value || showResetLimitsModal.value || showReminderModal.value || isTabSwitching.value) {
            return;
        }
        
        // Removed document.hidden check to allow refresh even in background
        // if (document.hidden) return;

        router.reload({
            only: ['customers', 'reminderData'],
            preserveScroll: true,
            preserveState: true,
        });
    }, 5000);
};

const stopAutoRefresh = () => {
    if (autoRefreshTimer.value) clearInterval(autoRefreshTimer.value);
};

onUnmounted(() => {
    stopAutoRefresh();
});

onMounted(() => {
    // Add check: If initial viewMode is calendar, ensure currentTab is 'all'
    if (viewMode.value === 'calendar' && currentTab.value !== 'all') {
        changeTab('all');
    }

    startAutoRefresh();
    setTimeout(() => {
        // Prevent showing if:
        // 1. Dismissed in this session (window var)
        // 2. Detail modal is open
        // 3. Action modal is open
        if (window.planningPopupDismissed) return;
        
        if (isDetailOpen.value || showActionConfirm.value) return;

        // Consolidate ALL reminders into one Summary Popup
        const expired = props.reminderData?.plansNeedingReport || 0;
        const missing = props.reminderData?.missing_plans_count || 0;
        const rejected = props.reminderData?.plansRejected || 0;
        const late = props.reminderData?.plansLateFollowUp || 0;
        
        // Role specific counts (use canEditMonitoring for BOD reviews as it includes Super Admin)
        const reviews = canEditMonitoring.value ? (props.reminderData?.bod_review_count || 0) : 0;
        const approvals = canEditControl.value ? (props.reminderData?.manager_approval_count || 0) : 0;

        const total = expired + missing + rejected + late + reviews + approvals;

        if (total > 0) {
             reminderState.value = { 
                 type: 'summary', 
                 count: total,
                 data: { expired, missing, rejected, late, reviews, approvals }
             };
             showReminderModal.value = true;
        }

    }, 1500); 
});

const dismissReminder = () => {
    window.planningPopupDismissed = true;
    showReminderModal.value = false;
};

// Helper checks
const isSuperAdmin = computed(() => props.user_roles && props.user_roles.includes('Super Admin'));

// Logic: Who can edit control?
const canEditControl = computed(() => isSuperAdmin.value || (props.user_roles && props.user_roles.includes('Manager'))); 
const canEditMonitoring = computed(() => isSuperAdmin.value || (props.user_roles && (props.user_roles.includes('BOD') || props.user_roles.includes('Board of Director'))));

const canCreateContent = (customer) => {
    // Super Admin can always create/edit
    if (isSuperAdmin.value) return true;

    // BOD cannot create content
    if (props.user_roles && (props.user_roles.includes('BOD') || props.user_roles.includes('Board of Director'))) {
        return false;
    }
    
    // Check ownership: Current user must be the assigned Marketing/Sales
    const currentUserId = page.props.auth.user.id;
    const ownerId = customer.marketing_sales_id || customer.marketing?.id;
    
    return currentUserId === ownerId;
};

const canViewFilters = computed(() => {
    if (isSuperAdmin.value) return true;
    return props.user_roles && (props.user_roles.includes('Manager') || props.user_roles.includes('BOD') || props.user_roles.includes('Board of Director'));
});

const showActionColumn = computed(() => {
    // Hide action column for BOD users, unless they are also Super Admin
    if (isBOD.value && !isSuperAdmin.value) {
        return false;
    }
    return true;
});

const getMonitoringStatusCode = (status) => {
    if (status === 'pending' || status === null) return 'P';
    if (status === 'success') return 'S';
    if (status === 'failed') return 'F';
    return '-';
};

// --- Revision Date Logic (14 Days Offset) ---
const todayStr = new Date().toISOString().split('T')[0];

const revisionMaxPlanningDate = computed(() => {
    const d = new Date();
    d.setDate(d.getDate() + 14);
    return d.toISOString().split('T')[0];
});

const revisionMinPlanningDate = computed(() => {
    if (revisionPlan.value && revisionPlan.value.planning_date) {
        // Return original planning date (or YYYY-MM-DD part)
        return revisionPlan.value.planning_date.split('T')[0];
    }
    return todayStr;
});

const revisionMaxExecutionDate = computed(() => todayStr);

const revisionMinNextPlanDate = computed(() => revisionForm.execution_date || todayStr);

const revisionMaxNextPlanDate = computed(() => {
    const base = revisionForm.execution_date ? new Date(revisionForm.execution_date) : new Date();
    base.setDate(base.getDate() + 14);
    return base.toISOString().split('T')[0];
});

// Check if revision form progress is Closing (100%)
const isRevisionClosing = computed(() => {
    return revisionForm.progress === '100%-Closing';
});

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = String(d.getFullYear()).slice(-2);
    return `${day}/${month}/${year}`;
};

</script>

<template>
    <Head title="Planning" />

    <!-- Planning Reminder Popup -->


    <div class="space-y-4 font-sans p-4 sm:p-6 max-w-[1920px] mx-auto">
        <!-- Header -->
        <div class="flex items-center gap-2 sm:gap-4 mb-2">
            <!-- Back Button for Non-SuperAdmin (Mobile Users) -->
            <Link v-if="!isSuperAdmin" :href="route('dashboard')" class="hidden lg:inline-flex group items-center gap-2 px-4 py-2 rounded-xl bg-white border border-gray-200 shadow-sm hover:shadow hover:border-gray-300 text-gray-600 hover:text-gray-900 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 active:scale-[0.98]">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform duration-200">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                <span class="text-xs sm:text-sm font-bold">Back</span>
            </Link>
            <h2 class="text-[24px] leading-[32px] font-bold text-gray-900">Planning</h2>
        </div>

        <!-- Table Container -->
        <div class="flow-root rounded-xl sm:rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <!-- Tabs -->
            <!-- Tabs - Enhanced -->
            <div v-if="viewMode === 'table'" class="border-b border-gray-200 bg-white px-4 sm:px-6">
                <nav class="-mb-px flex space-x-6 sm:space-x-8 overflow-x-auto scrollbar-none" aria-label="Tabs">
                    <button v-for="tab in tabs" :key="tab.id"
                        @click="changeTab(tab.id)"
                        :class="[
                            currentTab === tab.id
                                ? 'border-emerald-500 text-emerald-600 font-bold bg-emerald-50/30'
                                : 'border-transparent text-gray-500 sm:hover:border-gray-300 sm:hover:text-gray-700 font-medium',
                            'whitespace-nowrap border-b-2 py-4 px-3 text-sm transition-all duration-200 flex items-center gap-2 rounded-t-lg'
                        ]"
                    >
                         <!-- Icon for All Planning -->
                        <svg v-if="tab.id === 'all'" class="h-4 w-4" :class="currentTab === 'all' ? 'text-emerald-500' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <!-- Icon for Weeks -->
                        <svg v-else class="h-4 w-4" :class="currentTab === tab.id ? 'text-emerald-500' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        {{ tab.name }}
                    </button>
                </nav>
            </div>

            <!-- Toolbar - Redesigned -->
            <div class="bg-white border-b border-gray-200">
                <!-- Search Section -->
                <div class="px-4 sm:px-6 pt-4 pb-3">
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                            <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input v-model="search" type="text" placeholder="Search customers, products..." 
                            class="block w-full rounded-lg border-gray-300 py-2.5 pl-11 pr-3 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-all"
                        />
                    </div>
                </div>

                <!-- Filters & Actions Section -->
                <div class="px-4 sm:px-6 pb-4">
                    <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                        <!-- Left: Filters Group -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Date Filters -->
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider hidden sm:inline">Period:</span>
                                <select v-model="selectedMonth" class="block rounded-lg border-gray-300 py-2 pl-3 pr-9 text-sm text-gray-900 shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white transition-all">
                                    <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                                </select>
                                <select v-model="selectedYear" class="block rounded-lg border-gray-300 py-2 pl-3 pr-9 text-sm text-gray-900 shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white transition-all">
                                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                                </select>
                            </div>
                            
                            <!-- Divider -->
                            <div v-if="canViewFilters" class="hidden sm:block w-px h-8 bg-gray-200"></div>
                            
                            <!-- Team & User Filters -->
                            <div v-if="canViewFilters" class="flex items-center gap-2">
                                <!-- Team Filter -->
                                <select v-if="isSuperAdmin || (user_roles && user_roles.includes('BOD'))" v-model="filterTeam" class="block rounded-lg border-gray-300 py-2 pl-3 pr-9 text-sm text-gray-900 shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white transition-all">
                                    <option value="">All Teams</option>
                                    <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                                </select>

                                <!-- User Filter -->
                                <select v-model="filterUser" class="block rounded-lg border-gray-300 py-2 pl-3 pr-9 text-sm text-gray-900 shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white transition-all">
                                    <option value="">All Users</option>
                                    <option v-for="user in filteredUsers" :key="user.id" :value="user.id">
                                        {{ user.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Right: Actions Group -->
                        <div class="flex items-center gap-3">
                            <!-- View Mode Toggle -->
                            <div class="flex p-1 bg-gray-100 rounded-xl">
                                <button @click="switchViewMode('table')" 
                                        :class="viewMode === 'table' ? 'bg-white text-emerald-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                                        class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg transition-all">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                    </svg>
                                    <span class="hidden md:inline">Table</span>
                                </button>
                                <button @click="switchViewMode('calendar')" 
                                        :class="viewMode === 'calendar' ? 'bg-white text-emerald-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                                        class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg transition-all">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span class="hidden md:inline">Calendar</span>
                                </button>
                            </div>

                            <div class="w-px h-6 bg-gray-200 hidden sm:block"></div>
                            <!-- Reset Limits Button (Super Admin Only) -->
                            <button v-if="isSuperAdmin" 
                                    @click="confirmResetAllLimits"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 hover:border-gray-400 rounded-lg transition-all shadow-sm">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                                <span class="hidden sm:inline">Reset Limits</span>
                            </button>

                            <!-- Create Button -->
                            <div v-if="showCreateButton">
                                <Link v-if="canCreatePlanToday" :href="route('planning.create')" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 hover:shadow-md active:scale-[0.98] transition-all">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                                    </svg>
                                    Create Plan
                                </Link>
                                <div v-else class="inline-flex items-center gap-2 rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-500 cursor-not-allowed" title="Planning can only be created on Friday">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">\
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="relative truncate">Friday Only</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View Content -->
            <div v-show="viewMode === 'table'">
                <!-- Mobile Card View (Modern Professional) -->
                <div class="block sm:hidden space-y-4 pb-12">
                <div v-for="(customer, index) in customers.data" :key="customer.id" 
                     @click="openDetail(customer)"
                     class="bg-white rounded-2xl shadow-[0_2px_15px_-3px_rgba(0,0,0,0.07),0_10px_20px_-2px_rgba(0,0,0,0.04)] ring-1 ring-gray-100 overflow-hidden relative transition-transform active:scale-[0.99] duration-200"
                >
                    <!-- Decorative Number (Watermark) -->
                    <div class="absolute -right-2 -top-4 text-[80px] font-black text-gray-50 pointer-events-none select-none z-0">
                        {{ (customers.from || 1) + index }}
                    </div>

                    <div class="relative z-10 p-5">
                        <!-- Header -->
                        <div class="flex justify-between items-start mb-6">
                            <div class="pr-8 relative"> <!-- Padding right to avoid overlap with product badge if long text -->
                                <h3 class="text-lg font-bold text-gray-800 leading-tight tracking-tight">{{ customer.company_name }}</h3>
                                <p class="text-xs font-medium text-gray-400 mt-1 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                    {{ customer.marketing?.name || 'Unassigned' }}
                                </p>
                            </div>
                            <span class="shrink-0 text-[10px] uppercase font-bold bg-blue-50 text-blue-600 px-3 py-1 rounded-full ring-1 ring-blue-100/50 shadow-sm">
                                {{ customer.product?.name || 'GEN' }}
                            </span>
                        </div>

                        <!-- Status Indicators (Centering) -->
                        <div class="flex justify-around items-start mb-6 bg-gray-50/50 rounded-xl p-3 border border-gray-100/50">
                             <!-- Plan -->
                             <div class="flex flex-col items-center flex-1">
                                <span class="text-[9px] font-extrabold text-gray-400 uppercase tracking-widest mb-2">Plan</span>
                                <!-- Badge -->
                                <div class="relative group flex flex-col items-center">
                                    <div v-if="getPlanningStatus(customer) === 'none'" class="h-10 w-10 rounded-full bg-gray-200 border border-gray-300"></div>
                                    <div v-else-if="getPlanningStatus(customer) === 'no_plan_warning'" :style="getBlinkStyle(customer.created_at)" class="h-10 w-10 rounded-full bg-red-600 animate-glow-red shadow-md border-2 border-red-400"></div>
                                    <div v-else-if="customer.latest_plan" 
                                         class="h-10 w-10 rounded-full flex items-center justify-center text-[10px] font-bold text-white shadow-sm transition-all duration-300"
                                         :class="{
                                            'bg-red-500': getPlanningStatus(customer) === 'created' || getPlanningStatus(customer) === 'rejected_final' || getPlanningStatus(customer) === 'expired',
                                            'bg-emerald-500': getPlanningStatus(customer) === 'reported' || getPlanningStatus(customer) === 'approved',
                                            'bg-gray-800': getPlanningStatus(customer) === 'closed',
                                            'animate-glow-red bg-red-600': getPlanningStatus(customer) === 'warning' || getPlanningStatus(customer) === 'no_plan_warning' || getPlanningStatus(customer) === 'expired_warning' || getPlanningStatus(customer) === 'rejected_warning'
                                         }"
                                         :style="getBlinkStyle(customer.latest_plan)"
                                    >
                                        {{ customer.latest_plan.activity_type === 'ESCALATION' ? '' : customer.latest_plan.activity_code }}
                                    </div>
                                    <span v-if="customer.latest_plan && customer.latest_plan.activity_type !== 'ESCALATION'" 
                                          class="text-[9px] font-medium mt-1 text-center whitespace-nowrap"
                                          :class="{
                                              'text-red-600 font-bold': ['rejected_final', 'rejected_warning', 'expired', 'expired_warning'].includes(getPlanningStatus(customer)),
                                              'text-emerald-600 font-bold': getPlanningStatus(customer) === 'approved',
                                              'text-gray-800 font-bold': getPlanningStatus(customer) === 'closed',
                                              'text-gray-500': ['created', 'reported', 'warning'].includes(getPlanningStatus(customer))
                                          }">
                                        {{ 
                                            getPlanningStatus(customer) === 'closed' ? 'Closing' :
                                            getPlanningStatus(customer) === 'approved' ? 'Approved' : 
                                            (['rejected_final', 'rejected_warning'].includes(getPlanningStatus(customer)) ? 'Rejected' :
                                            (['expired', 'expired_warning'].includes(getPlanningStatus(customer)) ? 'Expired' :
                                            formatDate(customer.latest_plan.planning_date)))
                                        }}
                                    </span>
                                </div>
                             </div>

                             <!-- Control -->
                             <div class="flex flex-col items-center flex-1">
                                <span class="text-[9px] font-extrabold text-gray-400 uppercase tracking-widest mb-2">Ctrl</span>
                                <div class="relative group flex flex-col items-center">
                                    <!-- No planning at all - red badge (empty) -->
                                    <div v-if="(getPlanningStatus(customer) === 'none' || getPlanningStatus(customer) === 'no_plan_warning') && customer.latest_plan?.activity_type !== 'ESCALATION'" 
                                         class="h-10 w-10 rounded-full bg-red-500 shadow-sm"></div>
                                    <!-- Has plan (show activity code with appropriate color based on manager action) -->
                                    <template v-else-if="customer.latest_plan">
                                        <div 
                                            @click="canCreateContent(customer) && getControlStatus(customer) === 'rejected' ? openRevisionModal(customer.latest_plan) : null"
                                            class="h-10 w-10 rounded-full flex items-center justify-center text-[10px] font-bold text-white shadow-sm transition-all duration-300"
                                            :class="{
                                                'cursor-pointer hover:ring-2 hover:ring-offset-1 hover:ring-red-500': canCreateContent(customer) && getControlStatus(customer) === 'rejected',
                                                'animate-glow-red bg-red-600 border-2 border-red-400': getControlStatus(customer) === 'escalated',
                                                'bg-red-500': getControlStatus(customer) === 'rejected' || (getControlStatus(customer) === 'pending' && !['reported', 'approved', 'closed'].includes(getPlanningStatus(customer))),
                                                'bg-yellow-500': getControlStatus(customer) === 'need_review' || (getControlStatus(customer) === 'pending' && ['reported', 'approved'].includes(getPlanningStatus(customer))),
                                                'bg-emerald-500': getControlStatus(customer) === 'approved' && getPlanningStatus(customer) !== 'closed',
                                                'bg-gray-800': getPlanningStatus(customer) === 'closed'
                                            }"
                                        >
                                            {{ customer.latest_plan.activity_type === 'ESCALATION' ? '' : customer.latest_plan.activity_code }}
                                        </div>
                                        <!-- Show 'Need Review' or Date (like Desktop) -->
                                        <span v-if="(getControlStatus(customer) === 'need_review' || (getControlStatus(customer) === 'pending' && getPlanningStatus(customer) === 'reported')) && customer.latest_plan.activity_type !== 'ESCALATION'" class="text-[9px] text-gray-500 font-medium mt-1 text-center whitespace-nowrap">
                                            Need Review
                                        </span>
                                        <!-- Only Show Date if manager has actually taken action (approved/rejected/escalated but NOT need_review) -->
                                        <span v-else-if="customer.latest_plan.manager_status && customer.latest_plan.manager_status !== 'pending' && getControlStatus(customer) !== 'need_review'" class="text-[9px] text-gray-500 font-medium mt-1 text-center whitespace-nowrap">
                                            {{ formatDate(customer.latest_plan.manager_reviewed_at || customer.latest_plan.updated_at) }}
                                        </span>
                                    </template>
                                    <!-- No plan fallback -->
                                    <div v-else class="h-10 w-10 rounded-full bg-gray-100 border border-gray-200"></div>
                                </div>
                             </div>

                            <!-- Monitoring -->
                             <div class="flex flex-col items-center flex-1">
                                <span class="text-[9px] font-extrabold text-gray-400 uppercase tracking-widest mb-2">Mon</span>
                                <div class="relative group flex flex-col items-center">
                                    <!-- 1. No Plan (None / No Plan Warning) -> Red Empty -->
                                    <div v-if="getPlanningStatus(customer) === 'none' || getPlanningStatus(customer) === 'no_plan_warning'" 
                                         class="h-10 w-10 rounded-full bg-red-500 shadow-sm"></div>
                                         
                                    <!-- 2. Has Plan -->
                                    <template v-else-if="customer.latest_plan">
                                        <div class="h-10 w-10 rounded-full flex items-center justify-center text-[10px] font-bold text-white shadow-sm transition-all duration-300"
                                            :class="{
                                                'bg-red-500': (getMonitoringStatus(customer) === 'failed' || customer.latest_plan?.activity_type === 'ESCALATION') && getPlanningStatus(customer) !== 'closed',
                                                'bg-emerald-500': getMonitoringStatus(customer) === 'success' && getPlanningStatus(customer) !== 'closed',
                                                'bg-yellow-500': (getMonitoringStatus(customer) === 'pending' || getMonitoringStatus(customer) === 'wait_manager') && customer.latest_plan?.activity_type !== 'ESCALATION' && getPlanningStatus(customer) !== 'closed',
                                                'bg-gray-800': getPlanningStatus(customer) === 'closed'
                                            }"
                                        >
                                            {{ customer.latest_plan.activity_type === 'ESCALATION' ? '' : customer.latest_plan.activity_code }}
                                        </div>
                                        
                                        <!-- 1. Wait Manager -->
                                        <span v-if="getMonitoringStatus(customer) === 'wait_manager'" class="text-[9px] text-gray-400 font-medium mt-1 text-center whitespace-nowrap">
                                            Wait Manager
                                        </span>
                                        <!-- 2. Need Review -->
                                        <span v-else-if="getMonitoringStatus(customer) === 'pending' && customer.latest_plan.activity_type !== 'ESCALATION'" class="text-[9px] text-gray-500 font-medium mt-1 text-center whitespace-nowrap">
                                            Need Review
                                        </span>
                                        <!-- BOD Date -->
                                        <span v-else-if="customer.latest_plan.bod_status && customer.latest_plan.bod_status !== 'pending' && customer.latest_plan.bod_reviewed_at && getPlanningStatus(customer) !== 'expired' && getPlanningStatus(customer) !== 'expired_warning'" class="text-[9px] text-gray-500 font-medium mt-1 text-center whitespace-nowrap">
                                            {{ formatDate(customer.latest_plan.bod_reviewed_at) }}
                                        </span>
                                    </template>
                                    
                                    <!-- Fallback -->
                                    <div v-else class="h-10 w-10 rounded-full bg-red-400 flex items-center justify-center text-[10px] font-bold text-white shadow-sm">F</div>
                                </div>
                             </div>
                        </div>

                        <!-- Manager Actions (Mobile) -->
                        <div v-if="canEditControl" @click.stop class="mb-4 bg-gray-50/80 rounded-xl p-3 border border-gray-100">
                            <h4 class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3 text-center">Manager Actions</h4>
                            
                            <!-- Manager Actions Buttons (Mobile) -->
                            <div class="flex items-center justify-center gap-3">
                                <!-- REJECT -->
                                <button 
                                    v-if="shouldShowManagerAction(customer.latest_plan, 'rejected')"
                                    @click.stop="confirmControl(customer.latest_plan?.id, customer.latest_plan?.manager_status === 'rejected' ? 'pending' : 'rejected', customer.company_name)"
                                    :disabled="!customer.latest_plan || ['none', 'no_plan_warning', 'created'].includes(getPlanningStatus(customer)) || customer.latest_plan.activity_type === 'ESCALATION' || isManagerLocked(customer.latest_plan) || !canToggleManagerAction(customer.latest_plan, 'rejected', customer)"
                                    class="flex items-center justify-center rounded-full transition-all duration-200 border"
                                    :class="[
                                        customer.latest_plan?.manager_status === 'rejected' && getPlanningStatus(customer) !== 'warning'
                                            ? 'bg-red-500 text-white border-red-600 shadow-sm px-4 py-1.5 h-8 gap-1.5' 
                                            : 'w-8 h-8 border-red-200 text-red-300 bg-white hover:bg-red-500 hover:text-white hover:border-red-500 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-white disabled:hover:text-red-300'
                                    ]"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span v-if="customer.latest_plan?.manager_status === 'rejected' && getPlanningStatus(customer) !== 'warning'" class="text-[10px] font-bold uppercase tracking-wide">Reject</span>
                                </button>

                                <!-- ESCALATE -->
                                <button 
                                    v-if="shouldShowManagerAction(customer.latest_plan, 'escalated')"
                                    @click.stop="customer.latest_plan 
                                        ? confirmControl(customer.latest_plan.id, customer.latest_plan.manager_status === 'escalated' ? 'pending' : 'escalated', customer.company_name)
                                        : handleNoPlanEscalate(customer)"
                                    :disabled="isManagerLocked(customer.latest_plan) || (customer.latest_plan && !canToggleManagerAction(customer.latest_plan, 'escalated', customer))"
                                    class="flex items-center justify-center rounded-full transition-all duration-200 border"
                                    :class="[
                                        customer.latest_plan?.manager_status === 'escalated'
                                            ? 'bg-amber-500 text-white border-amber-600 shadow-sm px-4 py-1.5 h-8 gap-1.5'
                                            : 'w-8 h-8 border-amber-200 text-amber-300 bg-white hover:bg-amber-500 hover:text-white hover:border-amber-500 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-white disabled:hover:text-amber-300'
                                    ]"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                    </svg>
                                    <span v-if="customer.latest_plan?.manager_status === 'escalated'" class="text-[10px] font-bold uppercase tracking-wide">Escalate</span>
                                </button>

                                <!-- APPROVE -->
                                <button 
                                    v-if="shouldShowManagerAction(customer.latest_plan, 'approved')"
                                    @click.stop="confirmControl(customer.latest_plan?.id, customer.latest_plan?.manager_status === 'approved' ? 'pending' : 'approved', customer.company_name)"
                                    :disabled="!customer.latest_plan || ['none', 'no_plan_warning', 'created'].includes(getPlanningStatus(customer)) || customer.latest_plan.activity_type === 'ESCALATION' || isManagerLocked(customer.latest_plan) || !canToggleManagerAction(customer.latest_plan, 'approved', customer)"
                                    class="flex items-center justify-center rounded-full transition-all duration-200 border"
                                    :class="[
                                        customer.latest_plan?.manager_status === 'approved' && getPlanningStatus(customer) !== 'warning'
                                            ? 'bg-emerald-500 text-white border-emerald-600 shadow-sm px-4 py-1.5 h-8 gap-1.5'
                                            : 'w-8 h-8 border-emerald-200 text-emerald-300 bg-white hover:bg-emerald-500 hover:text-white hover:border-emerald-500 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-white disabled:hover:text-emerald-300'
                                    ]"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                    <span v-if="customer.latest_plan?.manager_status === 'approved' && getPlanningStatus(customer) !== 'warning'" class="text-[10px] font-bold uppercase tracking-wide">Approve</span>
                                </button>
                            </div>

                        </div>



                        <!-- BOD Actions (Mobile) -->
                        <div v-if="canEditMonitoring" @click.stop class="mb-4 bg-gray-50/80 rounded-xl p-3 border border-gray-100">
                            <h4 class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3 text-center">BOD Actions</h4>
                            <div class="flex items-center justify-center gap-6">
                                <!-- Success -->
                                <div class="flex flex-col items-center gap-1.5" :class="{ 'opacity-50': !canToggleMonitoring(customer), '!opacity-100': canToggleMonitoring(customer) }">
                                    <span class="text-[9px] font-bold text-emerald-500">SUCCESS</span>
                                    <Switch
                                        :model-value="getMonitoringStatus(customer) === 'success'"
                                        @update:model-value="val => confirmMonitoring(customer.latest_plan?.id, val ? 'success' : 'pending', customer.company_name)"
                                        :disabled="!canToggleMonitoring(customer)"
                                        :class="getMonitoringStatus(customer) === 'success' ? 'bg-emerald-500' : 'bg-gray-200'"
                                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500/20 cursor-pointer disabled:cursor-not-allowed"
                                    >
                                        <span class="sr-only">Success</span>
                                        <span
                                            :class="getMonitoringStatus(customer) === 'success' ? 'translate-x-5' : 'translate-x-1'"
                                            class="inline-block h-3 w-3 transform rounded-full bg-white transition-transform"
                                        />
                                    </Switch>
                                </div>

                                <!-- Failed -->
                                <div class="flex flex-col items-center gap-1.5" :class="{ 'opacity-50': !canToggleMonitoring(customer), '!opacity-100': canToggleMonitoring(customer) }">
                                    <span class="text-[9px] font-bold text-red-500">FAILED</span>
                                    <Switch
                                        :model-value="getMonitoringStatus(customer) === 'failed'"
                                        @update:model-value="val => confirmMonitoring(customer.latest_plan?.id, val ? 'failed' : 'pending', customer.company_name)"
                                        :disabled="!canToggleMonitoring(customer)"
                                        :class="getMonitoringStatus(customer) === 'failed' ? 'bg-red-500' : 'bg-gray-200'"
                                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-red-500/20 cursor-pointer disabled:cursor-not-allowed"
                                    >
                                        <span class="sr-only">Failed</span>
                                        <span
                                            :class="getMonitoringStatus(customer) === 'failed' ? 'translate-x-5' : 'translate-x-1'"
                                            class="inline-block h-3 w-3 transform rounded-full bg-white transition-transform"
                                        />
                                    </Switch>
                                </div>
                            </div>
                        </div>

                        <!-- Action Button -->
                         <div @click.stop>
                             <!-- Revise Plan (Rejected) -->
                             <button v-if="customer.latest_plan && (customer.latest_plan.manager_status === 'rejected' || customer.latest_plan.bod_status === 'failed') && canCreateContent(customer)"
                                     @click="openRevisionModal(customer.latest_plan)"
                                     class="w-full flex justify-center items-center gap-2 text-center text-sm font-bold text-white bg-red-500 hover:bg-red-600 active:scale-[0.98] py-3.5 rounded-xl shadow-lg shadow-red-200/50 ring-1 ring-red-500/20 transition-all animate-pulse">
                                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                                  </svg>
                                  Revise Plan
                             </button>

                             <!-- Late Report (Warning/Expired) -->
                            <Link v-else-if="customer.latest_plan && (getPlanningStatus(customer) === 'warning' || getPlanningStatus(customer) === 'expired' || getPlanningStatus(customer) === 'expired_warning') && canCreateContent(customer) && customer.latest_plan.activity_type !== 'ESCALATION' && customer.latest_plan.status === 'created'" 
                                  :href="route('planning.report.create', customer.latest_plan.id)"
                                  class="w-full flex justify-center items-center gap-2 text-center text-sm font-bold text-white bg-gradient-to-r from-amber-500 to-amber-400 hover:from-amber-400 hover:to-amber-300 active:scale-[0.98] py-3.5 rounded-xl shadow-lg shadow-amber-200/50 ring-1 ring-amber-500/20 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 opacity-90">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                Late Report
                            </Link>

                             <!-- Create Report (Fresh plan) -->
                            <Link v-else-if="customer.latest_plan && getPlanningStatus(customer) === 'created' && canCreateContent(customer) && customer.latest_plan.activity_type !== 'ESCALATION'" 
                                  :href="route('planning.report.create', customer.latest_plan.id)"
                                  class="w-full flex justify-center items-center gap-2 text-center text-sm font-bold text-white bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 active:scale-[0.98] py-3.5 rounded-xl shadow-lg shadow-blue-200/50 ring-1 ring-blue-500/20 transition-all">
                                <svg class="w-5 h-5 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Create Report
                            </Link>

                            <!-- Wait Day Restriction (Mobile) -->
                            <div v-else-if="(['none', 'no_plan_warning', 'reported', 'warning', 'failed_history', 'approved'].includes(getPlanningStatus(customer)) || customer.latest_plan?.activity_type === 'ESCALATION') && canCreateContent(customer) && (!canCreatePlanToday && !timeSettings?.testing_mode)"
                                 class="w-full py-3.5 rounded-xl border border-gray-100 bg-gray-50/50 text-center text-xs font-bold text-gray-400 uppercase tracking-wide">
                                wait {{ getAllowedDaysText() }}
                            </div>

                            <!-- New Opportunity (Closed Deal - Mobile) -->
                            <Link v-else-if="getPlanningStatus(customer) === 'closed' && canCreateContent(customer)"
                                  :href="route('planning.create', { customer: customer.id })"
                                  class="w-full flex justify-center items-center gap-2 text-center text-sm font-bold text-white bg-gradient-to-r from-gray-700 to-gray-800 hover:from-gray-600 hover:to-gray-700 active:scale-[0.98] py-3.5 rounded-xl shadow-lg shadow-gray-300/50 ring-1 ring-gray-600/20 transition-all">
                                <svg class="w-5 h-5 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                New Opportunity
                            </Link>

                            <!-- Create Plan - Include approved, and manually failed expired plans (Exclude Rejected) -->
                            <Link v-else-if="(['none', 'no_plan_warning'].includes(getPlanningStatus(customer)) || customer.latest_plan?.activity_type === 'ESCALATION' || (customer.latest_plan?.lifecycle_status === 'failed' && customer.latest_plan?.status === 'created')) && canCreateContent(customer)"
                                  :href="route('planning.create', { customer: customer.id })"
                                  class="w-full flex justify-center items-center gap-2 text-center text-sm font-bold text-white bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 active:scale-[0.98] py-3.5 rounded-xl shadow-lg shadow-emerald-200/50 ring-1 ring-emerald-500/20 transition-all">
                                <svg class="w-5 h-5 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Create Plan
                            </Link>
                            
                            <!-- No Action (Hide for BOD) -->
                             <div v-else-if="!canEditMonitoring" class="w-full py-3.5 rounded-xl border border-gray-100 bg-gray-50/50 text-center text-xs font-bold text-gray-400 uppercase tracking-wide">
                                No Action Required
                             </div>
                        </div>
                    </div>
                </div>
                
                 <!-- Empty State Mobile -->
                <div v-if="customers.data.length === 0" class="flex flex-col items-center justify-center p-12 text-center">
                    <div class="h-20 w-20 bg-gray-50 rounded-2xl flex items-center justify-center mb-6 shadow-sm border border-gray-100">
                        <svg class="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <h3 class="text-gray-900 font-bold text-lg">No Plans Found</h3>
                    <p class="text-gray-500 text-sm mt-2 mb-8 max-w-xs mx-auto">It looks like there are no planning for this category yet.</p>
                </div>
            </div>

            <!-- Table Wrapper with Scroll Indicator (Desktop Only) -->
            <div class="hidden sm:block relative">
                <!-- Scroll shadow indicators for mobile -->
                <div class="absolute left-0 top-0 bottom-0 w-4 bg-gradient-to-r from-white to-transparent z-10 pointer-events-none sm:hidden" style="opacity: 0;"></div>
                <div class="absolute right-0 top-0 bottom-0 w-4 bg-gradient-to-l from-white to-transparent z-10 pointer-events-none sm:hidden" style="opacity: 0.5;"></div>
                
                <div class="overflow-x-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100">
                    <table class="w-full min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider align-bottom w-14">No</th>
                            <th rowspan="2" @click="toggleSort('company_name')" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider align-bottom cursor-pointer group select-none hover:bg-gray-50 transition-colors min-w-[250px]">
                                <div class="flex items-center gap-1">
                                    Customer
                                    <div class="p-1 rounded-md" :class="{'bg-gray-100': sortField === 'company_name'}">
                                        <svg v-if="sortField === 'company_name' && sortDirection === 'asc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" />
                                        </svg>
                                        <svg v-else-if="sortField === 'company_name' && sortDirection === 'desc'" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                           <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h5.25m5.25-5.25v12m0 0 3.75-3.75M13.5 21l-3.75-3.75" /> 
                                        </svg>
                                        <svg v-else class="h-4 w-4 text-gray-400 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                                        </svg>
                                    </div>
                                </div>
                            </th>
                            <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider align-bottom w-48">Product</th>
                            <th rowspan="2" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider align-bottom w-48">Marketing</th>
                            <th rowspan="2" class="px-2 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider align-bottom w-24">Planning</th>
                            <th rowspan="2" class="px-2 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider align-bottom w-24">Control</th>
                            <th rowspan="2" class="px-2 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider align-bottom w-24">Monitoring</th>
                            <th v-if="canEditControl" rowspan="2" class="px-2 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider align-bottom w-[200px]">Manager Action</th>
                            <th v-if="canEditMonitoring" rowspan="2" class="px-2 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider align-bottom w-[160px]">BOD Action</th>
                            <th v-if="showActionColumn" rowspan="2" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider align-bottom w-36">Action</th>
                        </tr>
                        <tr>
                            <!-- Manager Sub-headers REMOVED -->
                            <!-- BOD Sub-headers REMOVED -->
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="(customer, index) in customers.data" :key="customer.id" 
                            @click="openDetail(customer)"
                            class="hover:bg-gray-50/60 transition-colors cursor-pointer"
                        >
                            <td class="px-4 py-4 text-xs text-gray-500">{{ (customers.from || 1) + index }}</td>
                            <td class="px-4 py-4">
                                <div class="text-xs leading-4 font-medium text-gray-900">{{ customer.company_name }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="text-xs leading-4 bg-blue-100 text-blue-700 px-2.5 py-1 rounded-md font-semibold border border-blue-200">
                                    {{ customer.product?.name || customer.latest_plan?.product?.name || '-' }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <span class="text-xs text-gray-500">{{ customer.marketing?.name || '-' }}</span>
                            </td>
                            
                            <!-- Planning Status (Display Only) -->
                            <td class="px-2 py-4 text-center align-middle">
                                <div class="flex flex-col items-center justify-center gap-1">
                                    <!-- 2. NO PLAN (GRAY) -->
                                <div v-if="getPlanningStatus(customer) === 'none'" class="h-10 w-10 rounded-full bg-gray-200 border border-gray-300"></div>
                                    <!-- 2. No Plan Warning (Inactive Customer) -->
                                    <div v-else-if="getPlanningStatus(customer) === 'no_plan_warning'" :style="getBlinkStyle(customer.created_at)" class="h-10 w-10 rounded-full bg-red-500 animate-glow-red border border-red-300 shadow-md"></div>
                                    
                                    <!-- 3. Existing Plan (Created, Reported, Expired, Warning) -->
                                    <div v-else-if="customer.latest_plan" 
                                         class="h-10 w-10 rounded-full flex items-center justify-center text-[10px] font-bold text-white shadow-sm transition-all duration-300"
                                         :class="{
                                            'bg-red-500': getPlanningStatus(customer) === 'created' || getPlanningStatus(customer) === 'rejected_final' || getPlanningStatus(customer) === 'expired',
                                            'bg-emerald-500': getPlanningStatus(customer) === 'reported' || getPlanningStatus(customer) === 'approved',
                                            'bg-gray-800': getPlanningStatus(customer) === 'closed',
                                            'animate-glow-red bg-red-600': getPlanningStatus(customer) === 'warning' || getPlanningStatus(customer) === 'expired_warning' || getPlanningStatus(customer) === 'rejected_warning'
                                         }"
                                         :style="getBlinkStyle(customer.latest_plan)"
                                    >
                                        {{ customer.latest_plan.activity_code }}
                                    </div>
                                        <!-- Approved: Show 'Approved' text -->
                                        <span v-if="getPlanningStatus(customer) === 'approved'" class="text-[10px] text-emerald-600 block mt-1 font-medium">
                                        Approved
                                    </span>
                                    <!-- Closed: Show 'Closing' text -->
                                    <span v-else-if="getPlanningStatus(customer) === 'closed'" class="text-[9px] font-bold text-gray-900">
                                        Closing
                                    </span>
                                    <!-- Rejected: Show 'Rejected' text -->
                                    <span v-else-if="getPlanningStatus(customer) === 'rejected_final' || getPlanningStatus(customer) === 'rejected_warning'" 
                                          class="text-[10px] text-red-500 block mt-1 font-medium">
                                        Rejected
                                    </span>
                                    <!-- Expired: Show 'Expired' text (only for expired/expired_warning, NOT warning) -->
                                    <span v-else-if="['expired', 'expired_warning'].includes(getPlanningStatus(customer))" 
                                          class="text-[10px] text-red-500 block mt-1 font-medium">
                                        Expired
                                    </span>
                                    <!-- Warning: No text, just blinking badge -->
                                    <!-- Warning: Blinking badge + Date -->
                                    <!-- Created/Reported/Warning: Show PLANNING DATE only (NOT for ESCALATION plans) -->
                                    <span v-else-if="customer.latest_plan && customer.latest_plan.activity_type !== 'ESCALATION'" 
                                          class="text-[10px] text-gray-500 block mt-1 font-medium">
                                        {{ formatDate(customer.latest_plan.planning_date) }}
                                    </span>
                                </div>
                            </td>

                            <!-- Control Status (Display Only) -->
                            <!-- Control Status (Display Only) -->
                            <td class="px-2 py-4 text-center align-middle">
                                <div class="flex flex-col items-center justify-center gap-1">
                                    <!-- 1. No Plan at all (None) OR No Plan Warning -> Static Red Empty -->
                                    <!-- Jika 'none' atau 'no_plan_warning', di Control jadi Merah Static Kosong -->
                                    <!-- 1. No Plan at all (None) OR No Plan Warning -> Static Red Empty -->
                                    <!-- Jika 'none' atau 'no_plan_warning', di Control jadi Merah Static Kosong -->
                                    <!-- Escalation Plan skips this and goes to Has Plan -->
                                    <div v-if="(getPlanningStatus(customer) === 'none' || getPlanningStatus(customer) === 'no_plan_warning') && customer.latest_plan?.activity_type !== 'ESCALATION'" 
                                         class="h-10 w-10 rounded-full bg-red-500 shadow-sm"></div>

                                    <!-- 2. Has Plan (Created, Reported, Expired, Warning) -->
                                    <template v-else-if="customer.latest_plan">
                                        <div 
                                            @click="canCreateContent(customer) && getControlStatus(customer) === 'rejected' ? openRevisionModal(customer.latest_plan) : null"
                                            class="h-10 w-10 rounded-full flex items-center justify-center text-[10px] font-bold text-white shadow-sm transition-all duration-300"
                                            :class="{
                                                'cursor-pointer hover:ring-2 hover:ring-offset-1 hover:ring-red-500': canCreateContent(customer) && getControlStatus(customer) === 'rejected',
                                                'animate-glow-red bg-red-600 border-2 border-red-400': getControlStatus(customer) === 'escalated' && getPlanningStatus(customer) !== 'closed',
                                                'bg-red-500': (getControlStatus(customer) === 'rejected' || (getControlStatus(customer) === 'pending' && !['reported', 'approved'].includes(getPlanningStatus(customer)))) && getPlanningStatus(customer) !== 'closed',
                                                'bg-yellow-500': (getControlStatus(customer) === 'need_review' || (getControlStatus(customer) === 'pending' && ['reported', 'approved'].includes(getPlanningStatus(customer)))) && getPlanningStatus(customer) !== 'closed',
                                                'bg-emerald-500': getControlStatus(customer) === 'approved' && getPlanningStatus(customer) !== 'closed',
                                                'bg-gray-800': getPlanningStatus(customer) === 'closed'
                                            }"
                                        >
                                            {{ customer.latest_plan.activity_type === 'ESCALATION' ? '' : customer.latest_plan.activity_code }}
                                        </div>
                                        
                                        <!-- Text below badge -->
                                        <!-- If Need Review (Escalated + Reported OR Pending + Reported) -> Show 'Need Review' -->
                                        <span v-if="getControlStatus(customer) === 'need_review' || (getControlStatus(customer) === 'pending' && getPlanningStatus(customer) === 'reported')" class="text-[10px] text-gray-500 font-medium">
                                            Need Review
                                        </span>
                                        <!-- Only Show Date if manager has actually taken action (approved/rejected/escalated but NOT need_review) -->
                                        <span v-else-if="customer.latest_plan.manager_status && customer.latest_plan.manager_status !== 'pending' && getControlStatus(customer) !== 'need_review'" class="text-[10px] text-gray-500 font-medium">
                                            {{ formatDate(customer.latest_plan.manager_reviewed_at || customer.latest_plan.updated_at) }}
                                        </span>
                                    </template>
                                    
                                    <!-- Fallback -->
                                    <div v-else class="h-10 w-10 rounded-full bg-gray-100 border border-gray-200"></div>
                                </div>
                            </td>

                            <!-- Monitoring Status (Display Only) -->
                            <td class="px-2 py-4 text-center align-middle">
                                <div class="flex flex-col items-center justify-center gap-1">
                                    <!-- 1. No Plan (None / No Plan Warning) -> Red Empty -->
                                    <div v-if="getPlanningStatus(customer) === 'none' || getPlanningStatus(customer) === 'no_plan_warning'" 
                                         class="h-10 w-10 rounded-full bg-red-500 shadow-sm"></div>
                                         
                                    <!-- 2. Has Plan -->
                                    <template v-else-if="customer.latest_plan">
                                        <div class="h-10 w-10 rounded-full flex items-center justify-center text-[10px] font-bold text-white shadow-sm transition-all duration-300"
                                            :class="{
                                                'bg-red-500': (getMonitoringStatus(customer) === 'failed' || customer.latest_plan?.activity_type === 'ESCALATION') && getPlanningStatus(customer) !== 'closed', 
                                                'bg-emerald-500': getMonitoringStatus(customer) === 'success' && getPlanningStatus(customer) !== 'closed',
                                                'bg-yellow-500': (getMonitoringStatus(customer) === 'pending' || getMonitoringStatus(customer) === 'wait_manager') && customer.latest_plan?.activity_type !== 'ESCALATION' && getPlanningStatus(customer) !== 'closed',
                                                'bg-gray-800': getPlanningStatus(customer) === 'closed'
                                            }"
                                        >
                                            {{ customer.latest_plan.activity_type === 'ESCALATION' ? '' : customer.latest_plan.activity_code }}
                                        </div>
                                        
                                        <!-- Status Text Logic -->
                                        <!-- 1. Wait Manager: Report exists, but Manager hasn't approved yet -->
                                        <span v-if="getMonitoringStatus(customer) === 'wait_manager'" class="text-[10px] text-gray-400 font-medium">
                                            Wait Manager
                                        </span>
                                        
                                        <!-- 2. Need Review: Manager Approved, Waiting for BOD -->
                                        <span v-else-if="getMonitoringStatus(customer) === 'pending' && customer.latest_plan.activity_type !== 'ESCALATION'" class="text-[10px] text-gray-500 font-medium">
                                            Need Review
                                        </span>
                                        <!-- Show Date ONLY if BOD has taken action (Strict Check) -->
                                        <span v-else-if="customer.latest_plan.bod_status && customer.latest_plan.bod_status !== 'pending'" class="text-[10px] text-gray-500 font-medium">
                                            {{ formatDate(customer.latest_plan.bod_reviewed_at || customer.latest_plan.updated_at) }}
                                        </span>
                                    </template>
                                    
                                    <!-- Fallback -->
                                    <div v-else class="h-10 w-10 rounded-full bg-red-400 flex items-center justify-center text-[10px] font-bold text-white shadow-sm">F</div>
                                </div>
                            </td>

                            <!-- Manager Actions (Toggle Group) -->
                            <td v-if="canEditControl" class="px-2 py-4 text-center align-middle">
                                <div class="flex items-center justify-center gap-2">
                                    
                                    <!-- SUPER ADMIN: RESET TO PENDING (UN-REJECT / UN-APPROVE) -->
                                    <!-- Only show this if status is NOT pending. This forces a reset step before changing to another final status. -->
                                    <button v-if="isSuperAdmin && customer.latest_plan?.manager_status && customer.latest_plan.manager_status !== 'pending'"
                                        @click.stop="confirmControl(customer.latest_plan?.id, 'pending', customer.company_name)"
                                        class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 text-gray-500 border border-gray-300 hover:bg-gray-200 hover:text-gray-700 shadow-sm transition-all"
                                        title="Reset to Pending (Un-Reject/Un-Approve)"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                        </svg>
                                    </button>

                                    <!-- STANDARD MANAGER ACTIONS (Reject, Escalate, Approve) -->
                                    <!-- Visible if status is pending OR if user is regular manager (who can't reset) -->
                                    <template v-else>
                                        <!-- REJECT -->
                                        <button 
                                            v-if="shouldShowManagerAction(customer.latest_plan, 'rejected')"
                                            @click.stop="confirmControl(customer.latest_plan?.id, customer.latest_plan?.manager_status === 'rejected' ? 'pending' : 'rejected', customer.company_name)"
                                            :disabled="!customer.latest_plan || ['none', 'no_plan_warning', 'created'].includes(getPlanningStatus(customer)) || customer.latest_plan.activity_type === 'ESCALATION' || isManagerLocked(customer.latest_plan) || !canToggleManagerAction(customer.latest_plan, 'rejected', customer)"
                                            class="flex items-center justify-center rounded-full transition-all duration-200 border"
                                            :class="[
                                                customer.latest_plan?.manager_status === 'rejected' && getPlanningStatus(customer) !== 'warning'
                                                    ? 'bg-red-500 text-white border-red-600 shadow-sm px-3 py-1 h-7 gap-1.5' 
                                                    : 'w-7 h-7 border-red-200 text-red-300 bg-white hover:bg-red-500 hover:text-white hover:border-red-500 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-white disabled:hover:text-red-300'
                                            ]"
                                            :title="getGracePeriodText(customer.latest_plan, 'manager') || 'Reject'"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            <span v-if="customer.latest_plan?.manager_status === 'rejected' && getPlanningStatus(customer) !== 'warning'" class="text-[10px] font-bold uppercase tracking-wide">Reject</span>
                                        </button>

                                        <!-- ESCALATE -->
                                        <button 
                                            v-if="shouldShowManagerAction(customer.latest_plan, 'escalated')"
                                            @click.stop="customer.latest_plan 
                                                ? confirmControl(customer.latest_plan.id, customer.latest_plan.manager_status === 'escalated' ? 'pending' : 'escalated', customer.company_name)
                                                : handleNoPlanEscalate(customer)"
                                            :disabled="isManagerLocked(customer.latest_plan) || (customer.latest_plan && !canToggleManagerAction(customer.latest_plan, 'escalated', customer))"
                                            class="flex items-center justify-center rounded-full transition-all duration-200 border"
                                            :class="[
                                                customer.latest_plan?.manager_status === 'escalated'
                                                    ? 'bg-amber-500 text-white border-amber-600 shadow-sm px-3 py-1 h-7 gap-1.5'
                                                    : 'w-7 h-7 border-amber-200 text-amber-300 bg-white hover:bg-amber-500 hover:text-white hover:border-amber-500 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-white disabled:hover:text-amber-300'
                                            ]"
                                            :title="getGracePeriodText(customer.latest_plan, 'manager') || 'Escalate'"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                            </svg>
                                            <span v-if="customer.latest_plan?.manager_status === 'escalated'" class="text-[10px] font-bold uppercase tracking-wide">Escalate</span>
                                        </button>

                                        <!-- APPROVE -->
                                        <button 
                                            v-if="shouldShowManagerAction(customer.latest_plan, 'approved')"
                                            @click.stop="confirmControl(customer.latest_plan?.id, customer.latest_plan?.manager_status === 'approved' ? 'pending' : 'approved', customer.company_name)"
                                            :disabled="!customer.latest_plan || ['none', 'no_plan_warning', 'created'].includes(getPlanningStatus(customer)) || customer.latest_plan.activity_type === 'ESCALATION' || isManagerLocked(customer.latest_plan) || !canToggleManagerAction(customer.latest_plan, 'approved', customer)"
                                            class="flex items-center justify-center rounded-full transition-all duration-200 border"
                                            :class="[
                                                customer.latest_plan?.manager_status === 'approved' && getPlanningStatus(customer) !== 'warning'
                                                    ? 'bg-emerald-500 text-white border-emerald-600 shadow-sm px-3 py-1 h-7 gap-1.5'
                                                    : 'w-7 h-7 border-emerald-200 text-emerald-300 bg-white hover:bg-emerald-500 hover:text-white hover:border-emerald-500 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-white disabled:hover:text-emerald-300'
                                            ]"
                                            :title="getGracePeriodText(customer.latest_plan, 'manager') || 'Approve'"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                            <span v-if="customer.latest_plan?.manager_status === 'approved' && getPlanningStatus(customer) !== 'warning'" class="text-[10px] font-bold uppercase tracking-wide">Approve</span>
                                        </button>
                                    </template>

                                </div>
                            </td>

                            <!-- BOD Actions (Goal Achievement Slider Style) -->
                             <td v-if="canEditMonitoring" class="px-2 py-4 text-center align-middle" @click.stop>
                                <div class="flex items-center justify-center w-full">
                                    <template v-if="customer.latest_plan && canToggleMonitoring(customer)">
                                        <!-- ACTIVE STATE: FAILED -->
                                        <div v-if="getMonitoringStatus(customer) === 'failed'"
                                             @click.stop="confirmMonitoring(customer.latest_plan.id, 'pending', customer.company_name)"
                                             class="flex items-center justify-between w-24 h-8 bg-red-500 rounded-full px-3 shadow-md cursor-pointer hover:bg-red-600 active:scale-95 transition-all group select-none"
                                             :class="{ 'opacity-50 cursor-not-allowed': isBODLocked(customer.latest_plan) }"
                                        >
                                            <div class="bg-white/20 rounded-full p-0.5">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-3 h-3 text-white">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                                                </svg>
                                            </div>
                                            <span class="text-[10px] font-bold text-white tracking-wider uppercase">FAILED</span>
                                        </div>

                                        <!-- ACTIVE STATE: SUCCESS -->
                                        <div v-else-if="getMonitoringStatus(customer) === 'success'"
                                             @click.stop="confirmMonitoring(customer.latest_plan.id, 'pending', customer.company_name)"
                                             class="flex items-center justify-between w-24 h-8 bg-emerald-500 rounded-full px-3 shadow-md cursor-pointer hover:bg-emerald-600 active:scale-95 transition-all group select-none"
                                             :class="{ 'opacity-50 cursor-not-allowed': isBODLocked(customer.latest_plan) }"
                                        >
                                            <span class="text-[10px] font-bold text-white tracking-wider uppercase">NEXT</span>
                                            <div class="bg-white/20 rounded-full p-0.5">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-3 h-3 text-white">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                                </svg>
                                            </div>
                                        </div>

                                        <!-- PENDING STATE: SPLIT BUTTONS -->
                                        <div v-else 
                                             class="flex items-center justify-center w-24 h-8 bg-white border border-gray-200 rounded-full shadow-sm overflow-hidden select-none"
                                             :class="{ 'opacity-50 cursor-not-allowed': isBODLocked(customer.latest_plan) }"
                                        >
                                            <!-- Fail Button -->
                                            <button 
                                                @click.stop="!isBODLocked(customer.latest_plan) && confirmMonitoring(customer.latest_plan.id, 'failed', customer.company_name)"
                                                :disabled="isBODLocked(customer.latest_plan)"
                                                class="w-1/2 h-full flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-500 active:bg-red-100 transition-colors border-r border-gray-100 group/fail"
                                                title="Mark as Failed"
                                            >
                                                <span class="text-[9px] font-bold uppercase mr-1 group-hover/fail:scale-110 transition-transform">Fail</span>
                                            </button>
                                            
                                            <!-- Success Button -->
                                            <button 
                                                @click.stop="!isBODLocked(customer.latest_plan) && confirmMonitoring(customer.latest_plan.id, 'success', customer.company_name)"
                                                :disabled="isBODLocked(customer.latest_plan)"
                                                class="w-1/2 h-full flex items-center justify-center text-gray-400 hover:bg-emerald-50 hover:text-emerald-500 active:bg-emerald-100 transition-colors group/next"
                                                title="Mark as Next"
                                            >
                                                <span class="text-[9px] font-bold uppercase ml-1 group-hover/next:scale-110 transition-transform">Next</span>
                                            </button>
                                        </div>
                                    </template>
                                    
                                    <!-- DISABLED / PLACEHOLDER STATE -->
                                    <template v-else>
                                         <div class="w-24 h-8 rounded-full bg-gray-50 border border-gray-100 flex items-center justify-center opacity-60 cursor-not-allowed">
                                            <div class="w-1.5 h-1.5 rounded-full bg-gray-300"></div>
                                         </div>
                                    </template>
                                </div>
                            </td>

                            <!-- Action Column -->
                            <td v-if="showActionColumn" class="px-6 py-4 text-center align-middle" @click.stop>
                                <div class="flex justify-center gap-2">
                                    <!-- ACTION BUTTONS LOGIC -->
                                    <template v-if="customer.latest_plan && canCreateContent(customer)">
                                        <!-- 1. WARNING / EXPIRED -> LATE REPORT -->
                                        <!-- Allow user to submit a late report for plans that have passed warning threshold -->
                                        <Link v-if="(getPlanningStatus(customer) === 'warning' || getPlanningStatus(customer) === 'expired' || getPlanningStatus(customer) === 'expired_warning') && customer.latest_plan.activity_type !== 'ESCALATION' && customer.latest_plan.status === 'created'" 
                                            :href="route('planning.report.create', customer.latest_plan.id)"
                                            class="w-32 justify-center text-xs font-semibold text-white bg-amber-500 hover:bg-amber-600 hover:shadow-md px-3 py-1.5 rounded-lg shadow-sm transition-all whitespace-nowrap flex items-center gap-1.5 mx-auto">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                            </svg>
                                            Late Report
                                        </Link>

                                        <!-- 2. CREATED / WARNING -> CREATE REPORT -->
                                        <!-- Include 'warning' here so they can report instead of being forced to create new plan -->
                                        <!-- 2. CREATED / WARNING -> CREATE REPORT -->
                                        <!-- Only if NOT reported yet. If reported (green/warning blinking because old), checking 'created' covers it? No. -->
                                        <!-- getPlanningStatus returns 'warning' if reported but old. In that case, we want Create Plan, NOT Report. -->
                                        <!-- So Create Report only if 'created' OR ('warning' AND NOT reported yet - wait, warning IS reported but old). -->
                                        <!-- Actually, 'warning' means reported but inactive. So for 'warning', we want CREATE PLAN. -->
                                        <!-- 'created' means made plan but no report. So CREATE REPORT. -->
                                        <!-- What if 'created' becomes OLD? It becomes 'expired'. -->
                                        
                                        <!-- SO: Only 'created' needs Create Report. 'warning' needs Create Plan. -->
                                        <!-- Check if today is arguably before the planned date (Future Plan) -->
                                        <!-- Logic: If today < planning_date, prevent report. -->
                                        <div v-else-if="getPlanningStatus(customer) === 'created' && customer.latest_plan.activity_type !== 'ESCALATION' && new Date(getCurrentTime()).setHours(0,0,0,0) < new Date(customer.latest_plan.planning_date).setHours(0,0,0,0)"
                                             class="w-32 justify-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed whitespace-nowrap shadow-sm flex items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                            </svg>
                                            Wait {{ formatDate(customer.latest_plan.planning_date) }}
                                        </div>

                                        <!-- Otherwise, if 'created' and date reached, Allow Report -->
                                        <Link v-else-if="getPlanningStatus(customer) === 'created' && customer.latest_plan.activity_type !== 'ESCALATION'" 
                                            :href="route('planning.report.create', customer.latest_plan.id)"
                                            class="w-32 justify-center text-xs font-semibold text-white bg-blue-500 hover:bg-blue-600 hover:shadow-md px-3 py-1.5 rounded-lg shadow-sm transition-all whitespace-nowrap flex items-center gap-1.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                            Create Report
                                        </Link>
                                        <!-- 3. REVISE PLAN (Manager/BOD Rejected) -->
                                        <button v-else-if="(customer.latest_plan.manager_status === 'rejected' || customer.latest_plan.bod_status === 'failed')"
                                            @click="openRevisionModal(customer.latest_plan)"
                                            class="w-32 justify-center text-xs font-bold text-white bg-red-500 hover:bg-red-600 hover:shadow-md px-3 py-1.5 rounded-lg shadow-sm transition-all whitespace-nowrap flex items-center gap-1.5 animate-pulse">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                                            </svg>
                                            Revise Plan
                                        </button>
                                        
                                        <!-- 4. NEW OPPORTUNITY (Closed Deal) -->
                                        <Link v-else-if="getPlanningStatus(customer) === 'closed'"
                                            :href="route('planning.create', { customer: customer.id })"
                                            class="w-32 justify-center text-xs font-bold text-white bg-gradient-to-r from-gray-700 to-gray-800 hover:from-gray-600 hover:to-gray-700 hover:shadow-md px-3 py-1.5 rounded-lg shadow-sm transition-all whitespace-nowrap flex items-center gap-1.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                            </svg>
                                            New Opportunity
                                        </Link>
                                    </template>

                                    <!-- Create Plan Logic (None, Warning, Manually Failed Expired, No Plan Warning) -->
                                    <!-- Excludes 'rejected' plans which are handled by Revise Plan button above -->
                                    <template v-if="(['none', 'no_plan_warning'].includes(getPlanningStatus(customer)) || customer.latest_plan?.activity_type === 'ESCALATION' || (customer.latest_plan?.lifecycle_status === 'failed' && customer.latest_plan?.status === 'created' && customer.latest_plan?.manager_status !== 'rejected' && customer.latest_plan?.bod_status !== 'failed')) && canCreateContent(customer)">
                                        
                                        <!-- BLOCKED: Waiting for Manager Review (Control = Yellow/Pending) -->
                                        <!-- Only applies if plan exists, is 'reported' (not 'approved'), AND review is pending -->
                                        <!-- 'approved' status means BOD Success - so Create Plan is ALLOWED -->
                                        
                                        <div v-if="customer.latest_plan && getPlanningStatus(customer) === 'reported' && 
                                                  (!customer.latest_plan.manager_status || customer.latest_plan.manager_status === 'pending' || 
                                                  (customer.latest_plan.manager_status === 'approved' && (!customer.latest_plan.bod_status || customer.latest_plan.bod_status === 'pending'))) &&
                                                  customer.latest_plan.bod_status !== 'success' && customer.latest_plan.bod_status !== 'failed' && customer.latest_plan.manager_status !== 'rejected'"
                                             class="w-32 justify-center flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-yellow-50 text-yellow-600 border border-yellow-200 cursor-not-allowed whitespace-nowrap shadow-sm opacity-90"
                                             title="Cannot create new plan while previous plan is waiting for Manager/BOD review">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Wait Review
                                        </div>

                                        <!-- BLOCKED: Plan Creation Not Allowed Today -->
                                        <div v-else-if="!canCreatePlanToday && !timeSettings?.testing_mode" 
                                             class="w-32 justify-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed whitespace-nowrap shadow-sm flex items-center gap-1"
                                             :title="'Plan creation is only allowed on: ' + getAllowedDaysText()">
                                             <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                                            </svg>
                                            Wait {{ getAllowedDaysText() }}
                                        </div>

                                        <!-- ALLOW: Create Plan -->
                                        <Link v-else
                                            :href="route('planning.create', { customer: customer.id })"
                                            class="w-32 justify-center text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 hover:shadow-md px-3 py-1.5 rounded-lg shadow-sm transition-all whitespace-nowrap flex items-center gap-1.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-3">
                                                 <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                            </svg>
                                            Create Plan
                                        </Link>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
            
            <!-- Pagination -->
            <div class="border-t border-gray-200 px-4 py-3 sm:px-6 sm:py-4 flex flex-col sm:flex-row items-center justify-between gap-4" v-if="customers.links">
                <!-- Left Side: Showing Info & Per Page -->
                <div class="flex flex-col xs:flex-row items-center gap-3 w-full sm:w-auto justify-between sm:justify-start">
                    <div class="text-xs text-gray-500 whitespace-nowrap">
                         Showing <span class="font-medium">{{ customers.from || 1 }}</span> to <span class="font-medium">{{ customers.to || customers.total }}</span> of <span class="font-medium">{{ customers.total }}</span>
                    </div>
                    
                    <div class="flex items-center border border-gray-300 rounded-md overflow-hidden shadow-sm">
                        <div class="px-3 py-1.5 text-xs font-medium text-slate-500 bg-slate-50 border-r border-gray-300 whitespace-nowrap">
                            Per page
                        </div>
                        <select v-model="perPage" class="border-none py-1.5 pl-2 pr-8 text-xs font-medium text-gray-700 bg-white focus:ring-0 cursor-pointer hover:bg-gray-50 transition-colors">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                </div>

                <!-- Right Side: Pagination Links -->
                <div class="flex flex-wrap justify-center gap-1 w-full sm:w-auto" v-if="customers.links && customers.links.length > 3 && perPage !== 'all'">
                     <Link v-for="(link, k) in customers.links" :key="k"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        class="px-2 py-1 sm:px-3 sm:py-1.5 rounded-md border text-xs font-medium transition-all active:scale-95"
                        :class="link.active 
                            ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' 
                            : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                     />
                </div>
            </div>
            </div>

            <!-- Calendar View -->
            <div v-if="viewMode === 'calendar'" class="p-4 sm:p-6 bg-gray-50/50">
                <NexusCalendar 
                    :customers="customers.data" 
                    :month="selectedMonth" 
                    :year="selectedYear"
                    :getStatus="getPlanningStatus"
                    :getBlinkStyle="getBlinkStyle"
                    @open-detail="openDetail"
                    @change-month="handleMonthChange"
                    @create-plan="(date) => router.get(route('planning.create'), { customer: selectedCustomer?.id, date })"
                />
            </div>
        </div>

        <!-- Detail Modal -->
        <TransitionRoot appear :show="isDetailOpen" as="template">
            <Dialog as="div" @close="closeDetail" class="relative z-50">
                <TransitionChild
                    as="template"
                    enter="duration-300 ease-out"
                    enter-from="opacity-0"
                    enter-to="opacity-100"
                    leave="duration-200 ease-in"
                    leave-from="opacity-100"
                    leave-to="opacity-0"
                >
                    <div class="fixed inset-0 bg-black/25 backdrop-blur-sm" />
                </TransitionChild>

                <div class="fixed inset-0 overflow-y-auto">
                    <div class="flex min-h-full items-center justify-center p-4 text-center">
                        <TransitionChild
                            as="template"
                            enter="duration-300 ease-out"
                            enter-from="opacity-0 scale-95"
                            enter-to="opacity-100 scale-100"
                            leave="duration-200 ease-in"
                            leave-from="opacity-100 scale-100"
                            leave-to="opacity-0 scale-95"
                        >
                            <DialogPanel class="w-full max-w-4xl transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-xl transition-all sm:rounded-3xl">
                                <!-- Header -->
                                <div class="sticky top-0 z-10 bg-white/95 backdrop-blur-sm border-b border-gray-100 px-4 sm:px-6 py-4">
                                    <div class="flex items-start sm:items-center justify-between gap-3">
                                        <DialogTitle as="h3" class="text-base sm:text-xl font-bold leading-tight text-gray-900">
                                            <span class="block sm:inline">Planning History:</span>
                                            <span class="text-emerald-600 block sm:inline mt-1 sm:mt-0 sm:ml-1 text-sm sm:text-xl">{{ selectedCustomer?.company_name }}</span>
                                        </DialogTitle>
                                        <button @click="closeDetail" class="flex-shrink-0 p-2 -m-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-all">
                                            <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="max-h-[65vh] sm:max-h-[70vh] overflow-y-auto px-4 sm:px-6 py-4 space-y-4 sm:space-y-6 scrollbar-thin scrollbar-thumb-gray-200 scrollbar-track-transparent">
                                    <div v-if="selectedCustomer?.plans?.length" v-for="plan in selectedCustomer.plans.filter(p => p.activity_type !== 'ESCALATION')" :key="plan.id" class="border border-gray-200 rounded-xl sm:rounded-2xl overflow-hidden shadow-sm bg-white hover:shadow-md transition-shadow duration-300">
                                         <!-- Plan Header / Title -->
                                        <div class="bg-gradient-to-r from-gray-50 to-white px-3 sm:px-4 py-3 border-b border-gray-100">
                                            <!-- Mobile: Stack layout -->
                                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                                <div class="flex items-center gap-2 sm:gap-3">
                                                    <span class="text-base sm:text-lg font-bold text-emerald-600 bg-emerald-50 px-2.5 sm:px-3 py-1 rounded-lg border border-emerald-100 shadow-sm">
                                                        {{ plan.activity_code }}
                                                    </span>
                                                    <div class="flex flex-col">
                                                         <span class="text-[10px] sm:text-xs text-gray-500 font-medium uppercase tracking-wider">{{ formatDate(plan.planning_date) }}</span>
                                                         <span class="text-[10px] sm:text-xs text-gray-600 font-semibold">{{ plan.activity_type }}</span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <div class="flex items-center gap-1.5 bg-white/80 px-2 py-1 rounded-full border border-gray-100 shadow-sm">
                                                        <img v-if="plan.user?.avatar_url" :src="plan.user.avatar_url" :alt="plan.user?.name" class="h-5 w-5 sm:h-6 sm:w-6 rounded-full object-cover">
                                                        <div v-else class="h-5 w-5 sm:h-6 sm:w-6 rounded-full bg-emerald-100 flex items-center justify-center text-[9px] sm:text-[10px] font-bold text-emerald-600">
                                                            {{ plan.user?.name?.charAt(0) || 'U' }}
                                                        </div>
                                                        <span class="text-[10px] sm:text-xs text-gray-600 font-medium">{{ plan.user?.name }}</span>
                                                    </div>
                                                    
                                                    <!-- Manager Status Badge -->
                                                    <span v-if="plan.manager_status" 
                                                          class="px-2 sm:px-2.5 py-0.5 sm:py-1 rounded-full text-[9px] sm:text-[10px] font-bold uppercase tracking-wide border shadow-sm"
                                                          :class="{
                                                              'bg-green-100 text-green-700 border-green-200': plan.manager_status === 'approved',
                                                              'bg-red-100 text-red-700 border-red-200': plan.manager_status === 'rejected',
                                                              'bg-orange-100 text-orange-700 border-orange-200': plan.manager_status === 'escalated',
                                                              'bg-gray-100 text-gray-600 border-gray-200': plan.manager_status === 'pending'
                                                          }"
                                                    >
                                                        {{ plan.manager_status }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Plan & Report Content -->
                                        <div class="grid grid-cols-1 md:grid-cols-2">
                                            <!-- Plan Information -->
                                            <div class="p-3 sm:p-4 space-y-3 border-b md:border-b-0 md:border-r border-gray-100">
                                                <h4 class="text-[10px] sm:text-xs font-bold text-emerald-600 uppercase tracking-wider flex items-center gap-2">
                                                    <svg class="h-3.5 w-3.5 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                    </svg>
                                                    Planning Details
                                                </h4>
                                                
                                                <div class="space-y-2.5">
                                                    <!-- Product & Project - Grid on larger screens -->
                                                    <div class="grid grid-cols-2 gap-2 sm:gap-3">
                                                        <div class="bg-gray-50 p-3 rounded-xl">
                                                            <label class="block text-[9px] sm:text-[10px] text-gray-400 uppercase font-medium">Product</label>
                                                            <div class="text-xs sm:text-sm font-semibold text-gray-900 mt-0.5">{{ selectedCustomer?.product?.name || plan.product?.name || '-' }}</div>
                                                        </div>
                                                        <div class="bg-gray-50 p-3 rounded-xl">
                                                            <label class="block text-[9px] sm:text-[10px] text-gray-400 uppercase font-medium">Project</label>
                                                            <div class="text-xs sm:text-sm text-gray-900 mt-0.5 truncate" :title="plan.project_name">{{ plan.project_name || '-' }}</div>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[9px] sm:text-[10px] text-gray-400 uppercase font-medium mb-1">Description</label>
                                                        <div class="text-xs sm:text-sm text-gray-700 bg-gray-50 p-3 rounded-xl leading-relaxed">
                                                            {{ plan.description }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Report Information -->
                                            <!-- Report Information -->
                                            <div class="p-3 sm:p-4 space-y-3 bg-gray-50/50">
                                                <h4 class="text-[10px] sm:text-xs font-bold text-blue-600 uppercase tracking-wider flex items-center justify-between">
                                                    <span class="flex items-center gap-2">
                                                        <svg class="h-3.5 w-3.5 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                        Report Details
                                                    </span>
                                                    <span v-if="plan.report">
                                                        <span v-if="plan.lifecycle_status_label === 'Late Report' || (plan.report.execution_date && new Date(plan.report.execution_date).setHours(0,0,0,0) > new Date(plan.planning_date).setHours(0,0,0,0))" class="bg-yellow-100 text-yellow-700 border-yellow-200 text-[9px] sm:text-[10px] font-bold px-2 py-0.5 rounded-full border shadow-sm">
                                                            LATE REPORT
                                                        </span>
                                                        <span v-else :class="plan.report.is_success ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-red-100 text-red-700 border-red-200'" class="text-[9px] sm:text-[10px] font-bold px-2 py-0.5 rounded-full border shadow-sm">
                                                            {{ plan.report.is_success ? 'SUCCESS' : 'FAILED' }}
                                                        </span>
                                                    </span>
                                                </h4>
                                                
                                                <div v-if="plan.report" class="space-y-2.5">
                                                    <!-- Execution & Location -->
                                                    <div class="grid grid-cols-2 gap-2 sm:gap-3">
                                                        <div class="bg-white p-3 rounded-xl border border-gray-100/50 shadow-sm">
                                                            <label class="block text-[9px] sm:text-[10px] text-gray-400 uppercase font-medium">Execution</label>
                                                            <div class="text-xs sm:text-sm font-semibold text-gray-900 mt-0.5">{{ formatDate(plan.report.execution_date) }}</div>
                                                        </div>
                                                        <div class="bg-white p-3 rounded-xl border border-gray-100/50 shadow-sm">
                                                                <label class="block text-[9px] sm:text-[10px] text-gray-400 uppercase font-medium">Location</label>
                                                            <div class="text-xs sm:text-sm text-gray-900 mt-0.5 truncate" :title="plan.report.location">{{ plan.report.location }}</div>
                                                        </div>
                                                    </div>
                                                    <!-- PIC & Position -->
                                                    <div class="grid grid-cols-2 gap-2 sm:gap-3">
                                                        <div class="bg-white p-3 rounded-xl border border-gray-100/50 shadow-sm">
                                                            <label class="block text-[9px] sm:text-[10px] text-gray-400 uppercase font-medium">PIC</label>
                                                            <div class="text-xs sm:text-sm text-gray-900 font-medium mt-0.5">{{ plan.report.pic }}</div>
                                                        </div>
                                                        <div class="bg-white p-3 rounded-xl border border-gray-100/50 shadow-sm">
                                                            <label class="block text-[9px] sm:text-[10px] text-gray-400 uppercase font-medium">Position</label>
                                                            <div class="text-xs sm:text-sm text-gray-900 mt-0.5 truncate" :title="plan.report.position">{{ plan.report.position }}</div>
                                                        </div>
                                                    </div>
                                                    <!-- Progress Bar -->
                                                    <div class="bg-white p-2.5 sm:p-3 rounded-xl border border-gray-100/50 shadow-sm">
                                                        <div class="flex items-center justify-between mb-1.5 sm:mb-2">
                                                            <label class="block text-[9px] sm:text-[10px] text-gray-400 uppercase font-medium">Progress</label>
                                                            <span class="text-[10px] sm:text-xs font-bold"
                                                                :class="{
                                                                    'text-gray-800': plan.report.progress === '100%-Closing',
                                                                    'text-emerald-600': plan.report.progress && plan.report.progress.includes('100') && plan.report.progress !== '100%-Closing',
                                                                    'text-blue-600': plan.report.progress && !plan.report.progress.includes('100'),
                                                                    'text-gray-400': !plan.report.progress
                                                                }">
                                                                {{ plan.report.progress || '-' }}
                                                            </span>
                                                        </div>
                                                        <div class="w-full bg-gray-100 rounded-full h-2.5 sm:h-3 overflow-hidden">
                                                            <div class="h-full rounded-full transition-all duration-500 ease-out"
                                                                :class="{
                                                                    'bg-gray-800': plan.report.progress === '100%-Closing',
                                                                    'bg-emerald-500': plan.report.progress && plan.report.progress.includes('100') && plan.report.progress !== '100%-Closing',
                                                                    'bg-blue-500': plan.report.progress && parseInt(plan.report.progress) >= 75 && !plan.report.progress.includes('100'),
                                                                    'bg-amber-500': plan.report.progress && parseInt(plan.report.progress) >= 50 && parseInt(plan.report.progress) < 75,
                                                                    'bg-orange-500': plan.report.progress && parseInt(plan.report.progress) >= 25 && parseInt(plan.report.progress) < 50,
                                                                    'bg-red-400': plan.report.progress && parseInt(plan.report.progress) < 25,
                                                                    'bg-gray-200': !plan.report.progress
                                                                }"
                                                                :style="{ width: plan.report.progress ? (parseInt(plan.report.progress) || 0) + '%' : '0%' }">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[9px] sm:text-[10px] text-gray-400 uppercase font-medium mb-1">Result</label>
                                                        <div class="text-xs sm:text-sm text-gray-700 bg-white p-3 rounded-xl border border-gray-100/50 shadow-sm leading-relaxed">
                                                            {{ plan.report.result_description }}
                                                        </div>
                                                    </div>
                                                    <div v-if="plan.report.next_plan_description">
                                                        <label class="block text-[9px] sm:text-[10px] text-gray-400 uppercase font-medium mb-1">Next Plan</label>
                                                            <div class="text-xs sm:text-sm text-gray-600 bg-white p-2.5 sm:p-3 rounded-lg border-2 border-gray-100 border-dashed italic leading-relaxed">
                                                            {{ plan.report.next_plan_description }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div v-else class="flex flex-col items-center justify-center text-gray-400 py-6 sm:py-8 bg-white/50 rounded-lg border border-dashed border-gray-200">
                                                    <svg class="h-8 w-8 sm:h-10 sm:w-10 mb-2 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    <span class="text-[10px] sm:text-xs font-medium">No report created yet</span>
                                                </div>
                                            </div>
                                            <!-- Review Actions (Inside Modal) -->


                                            <!-- Status Notes (Manager / BOD) - Moved to bottom for mobile, full width on desktop -->
                                            <div v-if="plan.status_logs && plan.status_logs.length > 0 && plan.status_logs.some(log => log.notes)" class="p-3 sm:p-4 space-y-3 border-t md:border-t-0 md:border-t border-gray-100 bg-amber-50/20 md:col-span-2">
                                                <h4 class="text-[10px] sm:text-xs font-bold text-amber-600 uppercase tracking-wider flex items-center gap-2">
                                                    <svg class="h-3.5 w-3.5 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                                    </svg>
                                                    Status Notes
                                                </h4>
                                                
                                                <div class="space-y-3">
                                                    <div v-for="log in plan.status_logs.filter(l => l.notes).sort((a, b) => new Date(a.created_at) - new Date(b.created_at))" :key="log.id" class="bg-white p-2.5 rounded-lg border border-amber-100 shadow-sm">
                                                        <div class="flex items-center justify-between mb-1.5">
                                                            <span class="text-[10px] font-bold text-gray-700 uppercase">{{ log.user?.name || (log.field === 'manager_status' ? 'Manager' : 'BOD') }}</span>
                                                            <span class="text-[9px] text-gray-400">{{ formatDate(log.created_at) }}</span>
                                                        </div>
                                                        <div class="text-[10px] sm:text-xs text-gray-600 leading-relaxed italic">
                                                            "{{ log.notes }}"
                                                        </div>
                                                        <div class="mt-1.5 flex items-center justify-between">
                                                            <span class="text-[9px] px-1.5 py-0.5 rounded-full font-medium" 
                                                                  :class="{
                                                                      'bg-red-50 text-red-600': log.new_value === 'rejected' || log.new_value === 'failed',
                                                                      'bg-emerald-50 text-emerald-600': log.new_value === 'approved' || log.new_value === 'success',
                                                                      'bg-amber-50 text-amber-600': log.new_value === 'escalated'
                                                                  } + ' uppercase tracking-wide'">
                                                                {{ log.new_value }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Empty State -->
                                    <div v-if="!selectedCustomer?.plans?.length" class="text-center py-12 sm:py-16">
                                        <div class="inline-flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-full bg-gray-100 mb-4">
                                            <svg class="h-7 w-7 sm:h-8 sm:w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-base sm:text-lg font-medium text-gray-900">No Planning History</h3>
                                        <p class="mt-1 text-xs sm:text-sm text-gray-500">This customer has no recorded plans or reports.</p>
                                    </div>
                                </div>
                                
                                <!-- Footer -->
                                <div class="sticky bottom-0 bg-white/95 backdrop-blur-sm border-t border-gray-100 px-4 sm:px-6 py-3 sm:py-4 flex justify-end">
                                    <button
                                        type="button"
                                        class="inline-flex justify-center items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 sm:px-5 py-2 sm:py-2.5 text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 shadow-sm transition-all duration-200"
                                        @click="closeDetail"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Close Details
                                    </button>
                                </div>
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </Dialog>
        </TransitionRoot>

        <!-- BOD Pending Review Modal -->
        <!-- Reminder Modal - Premium Design -->
        <div v-if="showReminderModal" class="fixed inset-0 z-[100] flex items-center justify-center px-4 sm:px-6">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity" @click="dismissReminder"></div>
            
            <!-- Modal Panel -->
            <div class="relative w-full max-w-lg transform overflow-hidden rounded-xl bg-white shadow-2xl transition-all">
                <div class="flex flex-col sm:flex-row">
                    <!-- Left Icon Panel - Solid Color -->
                    <div class="relative flex-shrink-0 w-full sm:w-20 flex items-center justify-center py-6 sm:py-0 sm:min-h-[200px] sm:rounded-l-xl overflow-hidden"
                         :class="{
                             'bg-yellow-500': reminderState.type === 'bod' || reminderState.type === 'manager',
                             'bg-red-600': true
                         }">
                        <!-- Professional Diagonal Lines Pattern -->
                        <div class="absolute inset-0 opacity-10">
                            <div class="absolute inset-0" style="background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, white 10px, white 20px);"></div>
                        </div>
                        
                        <!-- Icon Container (Clean) -->
                        <div class="relative z-10">
                            <!-- Icon container -->
                            <div class="relative w-12 h-12 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center shadow-lg ring-2 ring-white/30">
                                <svg class="w-6 h-6 text-white drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Right Content Panel -->
                    <div class="flex-1 bg-white p-5 sm:p-6 sm:rounded-r-xl">
                        <!-- Close Button (Top Right) -->
                        <button @click="dismissReminder" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>

                        <!-- Title -->
                        <h3 class="text-lg font-bold text-gray-900 tracking-tight pr-8">
                            <span>Action Required</span>
                        </h3>
                        
                        <!-- Message with Count Badge -->
                        <div class="mt-2.5">
                            <div class="text-sm text-gray-600 leading-relaxed">
                                <p class="mb-3">
                                    You have 
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-red-100 text-red-800 font-bold text-base ring-1 ring-red-200">
                                        <span class="animate-pulse">{{ reminderState.count }}</span>
                                    </span>
                                    items needing attention:
                                </p>
                                <ul class="space-y-2 bg-gray-50 rounded-lg p-3 border border-gray-100">
                                    <li v-if="reminderState.data.reviews > 0" class="flex justify-between items-center text-xs">
                                        <span class="text-gray-600 font-medium flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span>
                                            Pending Reviews (BOD)
                                        </span>
                                        <span class="font-bold text-gray-900 bg-white px-2 py-0.5 rounded shadow-sm border border-gray-200">{{ reminderState.data.reviews }}</span>
                                    </li>
                                    <li v-if="reminderState.data.approvals > 0" class="flex justify-between items-center text-xs">
                                        <span class="text-gray-600 font-medium flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span>
                                            Pending Approvals
                                        </span>
                                        <span class="font-bold text-gray-900 bg-white px-2 py-0.5 rounded shadow-sm border border-gray-200">{{ reminderState.data.approvals }}</span>
                                    </li>
                                    <li v-if="reminderState.data.missing > 0" class="flex justify-between items-center text-xs">
                                        <span class="text-gray-600 font-medium flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>
                                            Customers No Planning
                                        </span>
                                        <span class="font-bold text-gray-900 bg-white px-2 py-0.5 rounded shadow-sm border border-gray-200">{{ reminderState.data.missing }}</span>
                                    </li>
                                    <li v-if="reminderState.data.rejected > 0" class="flex justify-between items-center text-xs">
                                        <span class="text-gray-600 font-medium flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>
                                            Rejected Plans
                                        </span>
                                        <span class="font-bold text-gray-900 bg-white px-2 py-0.5 rounded shadow-sm border border-gray-200">{{ reminderState.data.rejected }}</span>
                                    </li>
                                    <li v-if="reminderState.data.expired > 0" class="flex justify-between items-center text-xs">
                                        <span class="text-gray-600 font-medium flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                            Expired Plans
                                        </span>
                                        <span class="font-bold text-gray-900 bg-white px-2 py-0.5 rounded shadow-sm border border-gray-200">{{ reminderState.data.expired }}</span>
                                    </li>
                                    <li v-if="reminderState.data.late > 0" class="flex justify-between items-center text-xs">
                                        <span class="text-gray-600 font-medium flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                            Late Follow-up
                                        </span>
                                        <span class="font-bold text-gray-900 bg-white px-2 py-0.5 rounded shadow-sm border border-gray-200">{{ reminderState.data.late }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="mt-3.5 flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5">
                            <button @click="dismissReminder" 
                                    class="px-4 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition-all shadow-sm hover:shadow">
                                Close
                            </button>
                            <button @click="dismissReminder" 
                                    class="px-4 py-2 text-sm font-bold text-white rounded-lg shadow-lg hover:shadow-xl transition-all transform hover:scale-[1.02] active:scale-[0.98] bg-red-600 hover:bg-red-700">
                                Don't Remind Today
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Confirmation Modal - Premium Design (Optimized) -->
    <Modal :show="showActionConfirm" @close="closeActionModal" max-width="lg" :noPadding="true">
        <div class="flex flex-col sm:flex-row overflow-hidden">
            <!-- Left Icon Panel - Solid Color -->\n            <div class="relative flex-shrink-0 w-full sm:w-20 flex items-center justify-center py-6 sm:py-0 sm:min-h-[200px] sm:rounded-l-xl overflow-hidden"
                 :class="{
                     'bg-red-600': actionStatus === 'rejected' || actionStatus === 'failed',
                     'bg-amber-500': actionStatus === 'escalated',
                     'bg-emerald-600': actionStatus === 'approved' || actionStatus === 'success',
                     'bg-slate-500': actionStatus === 'pending'
                 }">
                <!-- Professional Diagonal Lines Pattern -->
                <div class="absolute inset-0 opacity-10">
                    <div class="absolute inset-0" style="background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, white 10px, white 20px);"></div>
                </div>
                
                <!-- Icon Container (Clean) -->
                <div class="relative z-10">
                    <!-- Icon container -->
                    <div class="relative w-12 h-12 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center shadow-lg ring-2 ring-white/30">
                        <svg v-if="actionStatus === 'rejected' || actionStatus === 'failed'" class="w-6 h-6 text-white drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                        <svg v-else-if="actionStatus === 'escalated'" class="w-6 h-6 text-white drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        <svg v-else class="w-6 h-6 text-white drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Right Content Panel (More Compact) -->
            <div class="flex-1 bg-white p-5 sm:p-6 sm:rounded-r-xl">
                <!-- Title -->
                <h3 class="text-lg font-bold text-gray-900 tracking-tight">
                    {{ actionType === 'control' ? 'Confirm Manager Action' : 'Confirm BOD Action' }}
                </h3>
                
                <!-- Description - Ultra Compact -->
                <div class="mt-2.5 space-y-1.5">
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Change status to 
                        <!-- Status Badge - Inline with pulse -->
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md font-semibold text-xs shadow-sm transition-all"
                             :class="{
                                 'bg-red-50 text-red-700 ring-1 ring-red-200': actionStatus === 'rejected' || actionStatus === 'failed',
                                 'bg-amber-50 text-amber-700 ring-1 ring-amber-200': actionStatus === 'escalated',
                                 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200': actionStatus === 'approved' || actionStatus === 'success',
                                 'bg-slate-50 text-slate-700 ring-1 ring-slate-200': actionStatus === 'pending'
                             }">
                            <span class="w-1.5 h-1.5 rounded-full"
                                  :class="{
                                      'bg-red-500 animate-pulse': actionStatus === 'rejected' || actionStatus === 'failed',
                                      'bg-amber-500 animate-pulse': actionStatus === 'escalated',
                                      'bg-emerald-500 animate-pulse': actionStatus === 'approved' || actionStatus === 'success',
                                      'bg-slate-500': actionStatus === 'pending'
                                  }"></span>
                            {{ statusLabels[actionStatus]?.text }}
                        </span>
                        for planning
                    </p>
                    
                    <!-- Customer Name - Premium Box with Icon -->
                    <div class="group px-3 py-2.5 bg-gradient-to-r from-gray-50 via-gray-50 to-gray-100/50 rounded-lg border border-gray-200/60 hover:border-gray-300/60 transition-all">
                        <div class="flex items-center gap-2">
                            <!-- Building/Company Icon -->
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                            <p class="font-bold text-gray-900 text-sm truncate">{{ actionCustomerName }}</p>
                        </div>
                    </div>
                </div>

                <!-- Professional Note - Final Decision Warning -->
                <div class="mt-2.5" v-if="!isSuperAdmin && actionStatus !== 'pending' && actionStatus !== 'escalated'">
                     <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-medium text-xs bg-amber-50 text-amber-700 ring-1 ring-amber-200/50 backdrop-blur-sm shadow-sm">
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        Attention: This decision is final and cannot be changed.
                    </div>
                </div>

                <!-- Notes (Optional) - Compact -->
                <div class="mt-2.5">
                    <label for="actionNotes" class="block text-xs font-semibold text-gray-700 mb-1.5">Notes (Optional)</label>
                    <textarea 
                        id="actionNotes" 
                        v-model="actionNotes" 
                        rows="2" 
                        class="block w-full rounded-lg border-gray-200 shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 text-sm resize-none transition-all placeholder:text-gray-400"
                        placeholder="Add a note..."
                    ></textarea>
                </div>

                <!-- Buttons - Polished -->
                <div class="mt-3.5 flex justify-end gap-2.5">
                    <button @click="closeActionModal" 
                            class="px-4 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition-all shadow-sm hover:shadow">
                        Cancel
                    </button>
                    <button @click="executeAction"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-bold text-white rounded-lg shadow-lg hover:shadow-xl transition-all transform hover:scale-[1.02] active:scale-[0.98]"
                        :class="{
                            'bg-red-600 hover:bg-red-700': actionStatus === 'rejected' || actionStatus === 'failed',
                            'bg-amber-500 hover:bg-amber-600': actionStatus === 'escalated',
                            'bg-emerald-600 hover:bg-emerald-700': actionStatus === 'approved' || actionStatus === 'success',
                            'bg-slate-500 hover:bg-slate-600': actionStatus === 'pending'
                        }">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        Yes, {{ statusLabels[actionStatus]?.text }}
                    </button>
                </div>
            </div>
        </div>
    </Modal>

    <!-- Local Toast for Limit Messages -->
    <Toast 
        :show="showLocalToast" 
        :message="localToastMessage" 
        :type="localToastType" 
        @close="showLocalToast = false" 
    />

    <!-- Reset All Limits Confirmation Modal -->
    <Modal :show="showResetLimitsModal" @close="showResetLimitsModal = false" max-width="md" :noPadding="true">
        <div class="flex flex-col sm:flex-row">
            <!-- Left Icon Panel -->
            <div class="flex-shrink-0 w-full sm:w-20 flex items-center justify-center py-5 sm:py-0 sm:min-h-[180px] sm:rounded-l-lg bg-amber-500">
                <div class="w-11 h-11 rounded-lg bg-white/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </div>
            </div>

            <!-- Right Content Panel -->
            <div class="flex-1 bg-white p-5 sm:rounded-r-lg">
                <h3 class="text-base font-semibold text-gray-900">Reset All Toggle Limits</h3>
                
                <p class="mt-2 text-sm text-gray-600">
                    Are you sure you want to reset all status change limits? This will allow 
                    <span class="font-semibold text-gray-900">all users</span> to make 
                    <span class="font-semibold px-1.5 py-0.5 rounded text-xs bg-amber-100 text-amber-700">3 new changes</span>
                    for each plan.
                </p>

                <p class="mt-2 text-xs text-gray-500">
                    This action is irreversible.
                </p>

                <!-- Buttons -->
                <div class="mt-5 flex justify-end gap-3">
                    <button @click="showResetLimitsModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button @click="executeResetAllLimits"
                            :disabled="resetLimitsLoading"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-lg bg-amber-500 hover:bg-amber-600 disabled:opacity-50">
                        <svg v-if="resetLimitsLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        {{ resetLimitsLoading ? 'Resetting...' : 'Yes, Reset All' }}
                    </button>
                </div>
            </div>
        </div>
    </Modal>
    <!-- Plan Failed Confirmation Modal -->
    <div v-if="showFailConfirm" class="relative z-[60]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity" @click="showFailConfirm = false"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-red-100">
                    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-lg font-bold leading-6 text-gray-900" id="modal-title">Mark Plan as Failed?</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Are you sure you want to mark this plan as <span class="font-bold text-red-600">FAILED</span>?
                                    </p>
                                    <p class="text-sm text-gray-500 mt-2">
                                        This action cannot be undone. This will close the current plan as 'Failed' and allow you to create a new plan.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button type="button" 
                            @click="executeFailPlan"
                            :disabled="failLoading"
                            class="inline-flex w-full justify-center rounded-xl bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed items-center gap-2">
                            <svg v-if="failLoading" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ failLoading ? 'Processing...' : 'Yes, Mark as Failed' }}
                        </button>
                        <button type="button" 
                            @click="showFailConfirm = false"
                            :disabled="failLoading"
                            class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Revision Modal -->
    <Modal :show="showRevisionModal" @close="closeRevisionModal" max-width="2xl" :noPadding="true">
        <div class="bg-white px-6 py-4 flex justify-between items-center border-b border-gray-100">
             <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                 <span class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center text-amber-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                 </span>
                 Revise Plan
             </h3>
             <button @click="closeRevisionModal" class="text-gray-400 hover:text-gray-500">
                 <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                 </svg>
             </button>
        </div>

        <div class="p-4 sm:p-6 overflow-y-auto max-h-[80vh] bg-white">
            <form @submit.prevent="submitRevision" class="space-y-8">
                
                <!-- 1. ORIGINAL PLAN -->
                <div class="bg-white p-4 sm:p-6 rounded-3xl border border-gray-100 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-amber-400"></div>
                    <h3 class="text-base font-bold text-gray-900 mb-6 flex items-center gap-2 pl-2">
                        Original Plan Details
                    </h3>
                    
                    <div class="space-y-5">
                         <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <!-- Date -->
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Planning Date</label>
                                <input v-model="revisionForm.planning_date" type="date" :min="revisionMinPlanningDate" :max="revisionMaxPlanningDate" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 outline-none transition-all text-sm shadow-sm font-medium text-gray-600">
                                <p v-if="revisionForm.errors.planning_date" class="mt-1 text-xs font-bold text-red-500">{{ revisionForm.errors.planning_date }}</p>
                            </div>
                            <!-- Activity Type (New) -->
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Activity Type</label>
                                <select v-model="revisionForm.activity_type" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 outline-none transition-all text-sm bg-white shadow-sm cursor-pointer font-medium text-gray-600">
                                     <option value="" disabled>Select Activity</option>
                                     <option v-for="type in activityTypes" :key="type" :value="type">{{ type }}</option>
                                </select>
                                <p v-if="revisionForm.errors.activity_type" class="mt-1 text-xs font-bold text-red-500">{{ revisionForm.errors.activity_type }}</p>
                            </div>
                         </div>
                        <!-- Desc -->
                         <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Description</label>
                            <textarea v-model="revisionForm.description" rows="3" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 outline-none transition-all text-sm resize-none shadow-sm font-medium text-gray-600 placeholder:text-gray-300" placeholder="Enter plan description..."></textarea>
                            <p v-if="revisionForm.errors.description" class="mt-1 text-xs font-bold text-red-500">{{ revisionForm.errors.description }}</p>
                        </div>
                    </div>
                </div>

                <!-- Toggle Report -->
                <div>
                     <div class="flex items-center gap-4 mb-6 px-2 py-3 bg-white rounded-2xl border border-dashed border-gray-200 justify-between">
                          <span class="text-sm font-bold text-gray-700 pl-2">Is the plan executed? (Include Report)</span>
                          <Switch v-model="revisionForm.has_report" 
                                  :class="[revisionForm.has_report ? 'bg-emerald-500' : 'bg-gray-300', 'relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2']">
                              <span class="sr-only">Include Report</span>
                              <span aria-hidden="true" :class="[revisionForm.has_report ? 'translate-x-5' : 'translate-x-0', 'pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out']" />
                          </Switch>
                     </div>

                    <!-- 2. REPORT SECTION -->
                    <div v-show="revisionForm.has_report" class="space-y-8">
                        <div class="bg-white p-4 sm:p-6 rounded-3xl border border-gray-100 shadow-sm relative overflow-hidden">
                             <div class="absolute top-0 left-0 w-1.5 h-full bg-emerald-500"></div>
                             <h3 class="text-base font-bold text-gray-900 mb-6 flex items-center gap-2 pl-2">
                                Execution Report
                            </h3>
                             
                             <div class="space-y-5">
                                  <!-- Row 1: Execution Date & Location -->
                                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                      <div>
                                         <label class="block text-sm font-bold text-gray-700 mb-2">Execution Date</label>
                                         <input v-model="revisionForm.execution_date" type="date" readonly class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-100 text-gray-500 cursor-not-allowed focus:outline-none shadow-sm font-medium">
                                         <p v-if="revisionForm.errors.execution_date" class="mt-1 text-xs font-bold text-red-500">{{ revisionForm.errors.execution_date }}</p>
                                      </div>
                                      <div>
                                         <label class="block text-sm font-bold text-gray-700 mb-2">Location</label>
                                         <input v-model="revisionForm.location" type="text" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm shadow-sm font-medium text-gray-600 placeholder:text-gray-300" placeholder="e.g. Client Office">
                                         <p v-if="revisionForm.errors.location" class="mt-1 text-xs font-bold text-red-500">{{ revisionForm.errors.location }}</p>
                                      </div>
                                  </div>

                                  <!-- Row 2: PIC & Position -->
                                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                      <div>
                                         <label class="block text-sm font-bold text-gray-700 mb-2">PIC</label>
                                         <input v-model="revisionForm.pic" type="text" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm shadow-sm font-medium text-gray-600 placeholder:text-gray-300" placeholder="Person met">
                                         <p v-if="revisionForm.errors.pic" class="mt-1 text-xs font-bold text-red-500">{{ revisionForm.errors.pic }}</p>
                                      </div>
                                      <div>
                                         <label class="block text-sm font-bold text-gray-700 mb-2">Position</label>
                                         <input v-model="revisionForm.position" type="text" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm shadow-sm font-medium text-gray-600 placeholder:text-gray-300" placeholder="PIC Position">
                                         <p v-if="revisionForm.errors.position" class="mt-1 text-xs font-bold text-red-500">{{ revisionForm.errors.position }}</p>
                                      </div>
                                  </div>

                                  <!-- Result -->
                                  <div>
                                     <label class="block text-sm font-bold text-gray-700 mb-2">Result Description</label>
                                     <textarea v-model="revisionForm.result_description" rows="3" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm resize-none shadow-sm font-medium text-gray-600 placeholder:text-gray-300" placeholder="Describe the outcome..."></textarea>
                                     <p v-if="revisionForm.errors.result_description" class="mt-1 text-xs font-bold text-red-500">{{ revisionForm.errors.result_description }}</p>
                                  </div>

                                  <!-- Row 3: Progress & Goal Achievement -->
                                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 items-start">
                                      <div>
                                         <label class="block text-sm font-bold text-gray-700 mb-2">Progress</label>
                                         <select v-model="revisionForm.progress" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all text-sm bg-white shadow-sm cursor-pointer font-medium text-gray-600">
                                             <option value="" disabled>Select Progress</option>
                                             <option v-for="opt in progressOptions" :key="opt" :value="opt">{{ opt }}</option>
                                         </select>
                                         <p v-if="revisionForm.errors.progress" class="mt-1 text-xs font-bold text-red-500">{{ revisionForm.errors.progress }}</p>
                                      </div>
                                      
                                      <div>
                                          <label class="block text-sm font-bold text-gray-700 mb-2">Goal Achievement?</label>
                                          <Switch v-model="revisionForm.is_success" 
                                                  :class="[revisionForm.is_success ? 'bg-emerald-500' : 'bg-red-500', 'relative inline-flex h-7 w-14 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2']">
                                              <span class="sr-only">Goal Achievement</span>
                                              <span aria-hidden="true" :class="[revisionForm.is_success ? 'translate-x-7' : 'translate-x-0', 'pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out']" />
                                              <span class="absolute text-[9px] font-bold text-white left-1.5 top-1.5" v-if="!revisionForm.is_success">FAIL</span>
                                              <span class="absolute text-[9px] font-bold text-white right-1.5 top-1.5" v-if="revisionForm.is_success">SUCC</span>
                                          </Switch>
                                           <p v-if="revisionForm.errors.is_success" class="mt-1 text-xs font-bold text-red-500">{{ revisionForm.errors.is_success }}</p>
                                      </div>
                                  </div>
                             </div>
                        </div>

                         <!-- 3. NEXT PLAN SECTION (Hidden when Closing) -->
                        <div v-if="revisionForm.has_report && !isRevisionClosing" class="bg-blue-50/30 p-4 sm:p-6 rounded-3xl border border-blue-100 shadow-sm relative overflow-hidden">
                             <div class="absolute top-0 left-0 w-1.5 h-full bg-blue-500"></div>
                             <h3 class="text-base font-bold text-gray-900 mb-2 flex items-center gap-2 pl-2">
                                Next Plan (Mandatory)
                             </h3>
                             <p class="text-xs text-blue-800 mb-6 font-medium italic pl-2">Since you're reporting, please define the next plan.</p>
                             
                             <div class="space-y-5">
                                  <!-- Row 1: Next Date & Activity Type -->
                                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                      <div>
                                         <label class="block text-sm font-bold text-gray-700 mb-2">Next Activity Type</label>
                                         <select v-model="revisionForm.next_activity_type" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition-all text-sm bg-white shadow-sm cursor-pointer font-medium text-gray-600">
                                             <option value="" disabled>Select Activity</option>
                                             <option v-for="type in activityTypes" :key="type" :value="type">{{ type }}</option>
                                         </select>
                                         <p v-if="revisionForm.errors.next_activity_type" class="mt-1 text-xs font-bold text-red-500">{{ revisionForm.errors.next_activity_type }}</p>
                                      </div>
                                      <div>
                                         <label class="block text-sm font-bold text-gray-700 mb-2">Next Planning Date</label>
                                         <input v-model="revisionForm.next_planning_date" type="date" :min="revisionMinNextPlanDate" :max="revisionMaxNextPlanDate" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition-all text-sm shadow-sm bg-white font-medium text-gray-600">
                                         <p v-if="revisionForm.errors.next_planning_date" class="mt-1 text-xs font-bold text-red-500">{{ revisionForm.errors.next_planning_date }}</p>
                                      </div>
                                  </div>
                                  
                                   <!-- Next Desc -->
                                  <div>
                                     <label class="block text-sm font-bold text-gray-700 mb-2">Next Plan Description</label>
                                     <textarea v-model="revisionForm.next_plan_description" rows="3" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition-all text-sm resize-none shadow-sm font-medium text-gray-600 placeholder:text-gray-300" placeholder="Plan description for next meeting..."></textarea>
                                     <p v-if="revisionForm.errors.next_plan_description" class="mt-1 text-xs font-bold text-red-500">{{ revisionForm.errors.next_plan_description }}</p>
                                  </div>
                             </div>
                        </div>

                        <!-- Closing Confirmation Message (Revise Plan) -->
                        <div v-if="revisionForm.has_report && isRevisionClosing" class="bg-gradient-to-br from-gray-800 to-gray-900 p-4 sm:p-6 rounded-2xl border border-gray-700 shadow-lg">
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
                    </div>
                </div>

                <div class="flex items-center justify-end pt-6 border-t border-gray-50 mt-8 gap-3">
                     <button type="button" @click="closeRevisionModal" class="px-5 py-2.5 text-sm font-bold text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="px-6 py-2.5 text-sm font-bold text-white bg-emerald-500 rounded-xl hover:bg-emerald-600 hover:shadow-lg hover:shadow-emerald-500/20 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-all duration-200"
                        :class="{'opacity-75 cursor-wait': revisionForm.processing}"
                        :disabled="revisionForm.processing">
                        Update Revision
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>

<style>
@keyframes glow-red {
  0% {
    box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7);
    transform: scale(1);
  }
  50% {
    box-shadow: 0 0 0 6px rgba(220, 38, 38, 0);
    transform: scale(1.05);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(220, 38, 38, 0);
    transform: scale(1);
  }
}

@keyframes glow-orange {
  0% {
    box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.7);
    transform: scale(1);
  }
  50% {
    box-shadow: 0 0 0 6px rgba(249, 115, 22, 0);
    transform: scale(1.05);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(249, 115, 22, 0);
    transform: scale(1);
  }
}

.animate-glow-red {
  animation: glow-red 2s infinite;
}

.animate-glow-orange {
  animation: glow-orange 2s infinite;
}

@keyframes bounce-in {
  0% { transform: scale(0.9); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}

.animate-bounce-in {
  animation: bounce-in 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
</style>
