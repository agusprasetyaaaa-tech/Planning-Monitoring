# 🔐 ANALISA USER ACTION CONSTRAINTS & LOCK MECHANISM

**Tanggal:** 2 Januari 2026  
**Topik:** Pemisahan Lock Penilaian vs User Action Constraints

---

## ⚠️ KOREKSI PENTING

**KESALAHAN ANALISA SEBELUMNYA:**
Saya mencampur antara:
- ❌ Lock toggle penilaian (Manager/BOD review)
- ❌ Constraint user untuk create plan/report

**YANG BENAR:**
- ✅ Lock toggle hanya untuk **PENILAIAN planning yang sudah ada**
- ✅ User masih bisa **CREATE REPORT** dan **CREATE PLAN** baru (sesuai aturan waktu)

---

## 📋 PEMISAHAN KONSEP

### **KONSEP 1: LOCK TOGGLE PENILAIAN**

**Apa yang Di-lock?**
- Toggle Manager (Approve/Reject/Escalate) untuk planning **yang sudah dinilai**
- Toggle BOD (Success/Failed) untuk planning **yang sudah dinilai**

**Kapan Lock?**
- Manager: Grace period habis (3 hari) ATAU BOD sudah final
- BOD: Grace period habis (5 menit)

**Apa yang TIDAK di-lock?**
- ❌ BUKAN kemampuan user create plan baru
- ❌ BUKAN kemampuan user create report baru
- ❌ BUKAN akses ke menu Planning

---

### **KONSEP 2: USER ACTION CONSTRAINTS**

**Apa yang Di-constraint?**
- **Create Plan** → Hanya bisa di hari Jumat (sesuai time configuration)
- **Create Report** → Hanya bisa Senin-Jumat (setelah planning date)

**Kapan Bisa?**
- Create Plan: Hanya **JUMAT** (configurable di time_settings)
- Create Report: **SENIN-JUMAT** untuk planning yang **ACTIVE** (belum reported)

**Apa yang TIDAK di-constraint?**
- ❌ BUKAN kemampuan view planning
- ❌ BUKAN kemampuan Manager/BOD review planning lama

---

## 📅 TIMELINE LENGKAP YANG BENAR

```
┌─────────────────────────────────────────────────────────────────┐
│  MINGGU 1: 2-9 JANUARI 2026 (Planning A)                        │
└─────────────────────────────────────────────────────────────────┘

JUMAT, 2 JANUARI
├─ [10:00] USER: Create Planning A
│         • Customer: PT ABC
│         • Product: Software XYZ
│         • Planning Date: 5 Jan 2026
│         • Status: 'created'
│         ✅ USER bisa create plan (hari Jumat)
│
└─ [ACTION AVAILABILITY]
    ├─ Create Plan: ✅ Available (today is Friday)
    ├─ Create Report: ❌ Not available (no active unreported plan ready)
    ├─ Manager Toggle: ❌ Disabled (no report yet)
    └─ BOD Toggle: ❌ Disabled (no report yet)

SABTU-MINGGU, 3-4 JANUARI
└─ [ACTION AVAILABILITY]
    ├─ Create Plan: ❌ Not available (not Friday)
    ├─ Create Report: ❌ Not available (weekend)
    ├─ Manager Toggle: ❌ Disabled (no report)
    └─ BOD Toggle: ❌ Disabled (no report)

SENIN, 5 JANUARI
├─ [09:00] USER: Eksekusi Planning A
│         • Visit ke PT ABC
│         • Meeting berhasil
│
├─ [16:00] USER: Create Report untuk Planning A
│         • Execution date: 5 Jan
│         • Result: Success
│         • Progress: 30%
│         • Planning A Status: 'created' → 'reported'
│         ✅ USER bisa create report (Senin, planning sudah executed)
│
└─ [ACTION AVAILABILITY setelah report]
    ├─ Create Plan: ❌ Not available (not Friday)
    ├─ Create Report: ✅ Available for OTHER plans (if any)
    ├─ Manager Toggle: ✅ Enabled (Planning A ready to review)
    └─ BOD Toggle: ❌ Disabled (manager belum approve)

SELASA, 6 JANUARI
├─ [10:00] MANAGER: Approve Planning A
│         • Manager Status: 'pending' → 'approved'
│         • Grace period dimulai: 3 hari (sampai 9 Jan 10:00)
│
└─ [ACTION AVAILABILITY]
    ├─ Create Plan: ❌ Not available (not Friday)
    ├─ Create Report: ✅ Available for OTHER active plans
    ├─ Manager Toggle Planning A: ✅ Enabled (grace: 3 hari)
    └─ BOD Toggle Planning A: ✅ Enabled (manager approved)

RABU-KAMIS, 7-8 JANUARI
└─ [ACTION AVAILABILITY]
    ├─ Create Plan: ❌ Not available (not Friday)
    ├─ Create Report: ✅ Available for other plans
    ├─ Manager Toggle Planning A: ✅ Enabled (masih grace)
    └─ BOD Toggle Planning A: ✅ Enabled

JUMAT, 9 JANUARI
├─ [10:00] MANAGER Grace Period HABIS (Planning A)
│         • Manager Toggle Planning A: ❌ LOCKED
│         • Planning A tidak bisa diubah oleh Manager lagi
│
├─ [10:30] USER: Create Planning B (NEW PLAN!)
│         • Customer: PT DEF (customer lain)
│         • Product: Hardware ABC
│         • Planning Date: 12 Jan 2026
│         • Status: 'created'
│         ✅ USER bisa create plan BARU (hari Jumat)
│         ⚠️ Planning A tetap locked, tapi user bisa buat planning baru!
│
└─ [ACTION AVAILABILITY]
    ├─ Create Plan: ✅ Available (today is Friday) → PLANNING B DIBUAT
    ├─ Create Report: ✅ Available for Planning B (next week)
    ├─ Manager Toggle Planning A: ❌ LOCKED (grace habis)
    ├─ BOD Toggle Planning A: ✅ Enabled (masih bisa)
    ├─ Manager Toggle Planning B: ❌ Disabled (belum ada report)
    └─ BOD Toggle Planning B: ❌ Disabled (belum ada report)

┌─────────────────────────────────────────────────────────────────┐
│  MINGGU 2: 12-16 JANUARI 2026 (Planning A final, Planning B     │
│  active)                                                         │
└─────────────────────────────────────────────────────────────────┘

SENIN, 12 JANUARI
├─ [10:00] BOD: Success pada Planning A
│         • BOD Status: 'pending' → 'success'
│         • Planning A Status: COMPLETED ✅
│         • Grace period BOD: 5 menit
│
├─ [10:05] BOD Grace Period HABIS (Planning A)
│         • BOD Toggle Planning A: ❌ LOCKED
│         • Planning A FULLY LOCKED 🔒
│
├─ [14:00] USER: Eksekusi Planning B
│         • Visit ke PT DEF
│
├─ [16:00] USER: Create Report untuk Planning B
│         • Planning B Status: 'created' → 'reported'
│         ✅ USER bisa create report untuk Planning B
│         ⚠️ Planning A tetap locked, tidak terpengaruh
│
└─ [ACTION AVAILABILITY]
    ├─ Create Plan: ❌ Not available (not Friday)
    ├─ Create Report: ✅ Created for Planning B (done)
    ├─ Planning A: 🔒 FULLY LOCKED (completed)
    ├─ Manager Toggle Planning B: ✅ Enabled (bisa review)
    └─ BOD Toggle Planning B: ❌ Disabled (manager belum approve)

SELASA, 13 JANUARI
├─ [MANAGER: Review Planning B]
│  ✅ Manager bisa approve/reject Planning B
│  ⚠️ Planning A tetap locked (completed)
│
└─ [ACTION AVAILABILITY]
    ├─ Create Plan: ❌ Not available (not Friday)
    ├─ Create Report: ✅ Available for other active plans (if any)
    ├─ Planning A: 🔒 LOCKED (completed)
    └─ Planning B: ✅ Manager bisa review

JUMAT, 16 JANUARI
├─ [USER: Create Planning C]
│  ✅ User bisa create planning BARU lagi (hari Jumat)
│  ⚠️ Planning A & B tetap dengan status masing-masing
│
└─ [ACTION AVAILABILITY]
    ├─ Create Plan: ✅ Available (Friday) → PLANNING C
    ├─ Planning A: 🔒 LOCKED (completed)
    ├─ Planning B: Status sesuai progress review
    └─ Planning C: 'created' (baru dibuat)
```

---

## 🔑 KEY INSIGHTS

### **INSIGHT 1: Lock Hanya untuk Planning SPECIFIC**

```
Planning A (2 Jan):
├─ 9 Jan: Manager toggle LOCK ❌
├─ 12 Jan: BOD toggle LOCK ❌
└─ Status: COMPLETED, tidak bisa diubah

Planning B (9 Jan):
├─ Manager toggle: ✅ ENABLED (belum lock)
├─ BOD toggle: Status tergantung manager
└─ Independent dari Planning A!

Planning C (16 Jan):
├─ Baru dibuat, status 'created'
└─ Tidak terpengaruh Planning A atau B
```

**PENTING:** Lock tidak "global" - hanya untuk planning yang specific!

---

### **INSIGHT 2: User Selalu Bisa Create Plan (di Jumat)**

```
Meskipun ada planning yang completed/locked:
├─ User tetap bisa create plan BARU
├─ Hanya di hari JUMAT (configurable)
└─ Tidak terpengaruh status planning lama

Constraint:
├─ allowed_plan_creation_days = [5] // 5 = Friday
├─ Check hari sekarang = Friday? → Allow
└─ Bukan Friday? → Disable button "Create Plan"
```

---

### **INSIGHT 3: User Selalu Bisa Create Report (untuk Active Plan)**

```
User bisa create report jika:
├─ Ada planning dengan status 'created' (belum reported)
├─ Planning date sudah lewat (atau hari yang sama)
├─ Hari ini Senin-Jumat (bukan weekend)
└─ Planning belum expired

Tidak peduli:
├─ Berapa banyak planning yang sudah completed
├─ Berapa banyak planning yang locked
└─ Status penilaian Manager/BOD planning lain
```

---

## ✅ BUSINESS RULES YANG BENAR

### **RULE 1: Create Plan Constraints**

```javascript
const canCreatePlan = () => {
    const today = new Date();
    const dayOfWeek = today.getDay(); // 0=Sunday, 5=Friday
    
    // Check time settings
    const allowedDays = timeSettings.allowed_plan_creation_days || [5]; // Default: Friday
    
    if (!allowedDays.includes(dayOfWeek)) {
        return {
            allowed: false,
            reason: 'Planning can only be created on Friday'
        };
    }
    
    return {
        allowed: true,
        reason: null
    };
};
```

**UI Implementation:**
```vue
<Link 
    v-if="showCreateButton"
    :href="route('planning.create')" 
    :class="{
        'opacity-50 pointer-events-none': !canCreatePlan().allowed
    }"
>
    Create Plan
    
    <!-- Tooltip jika disabled -->
    <span v-if="!canCreatePlan().allowed" class="tooltip">
        {{ canCreatePlan().reason }}
    </span>
</Link>
```

---

### **RULE 2: Create Report Constraints**

```javascript
const canCreateReport = (plan) => {
    // 1. Check if plan already has report
    if (plan.report) {
        return {
            allowed: false,
            reason: 'Report already submitted'
        };
    }
    
    // 2. Check if plan status is 'created'
    if (plan.status !== 'created') {
        return {
            allowed: false,
            reason: 'Plan already reported'
        };
    }
    
    // 3. Check if plan is expired
    if (isPlanExpired(plan)) {
        return {
            allowed: false,
            reason: 'Plan has expired'
        };
    }
    
    // 4. Check day of week (Senin-Jumat)
    const today = new Date();
    const dayOfWeek = today.getDay(); // 1=Monday, 5=Friday
    
    if (dayOfWeek === 0 || dayOfWeek === 6) { // Sunday or Saturday
        return {
            allowed: false,
            reason: 'Reports can only be created on weekdays (Mon-Fri)'
        };
    }
    
    // 5. Optional: Check if planning date has passed
    const planningDate = new Date(plan.planning_date);
    if (today < planningDate) {
        return {
            allowed: false,
            reason: 'Cannot report before planning date'
        };
    }
    
    return {
        allowed: true,
        reason: null
    };
};
```

---

### **RULE 3: Manager Toggle Lock**

```javascript
const canManagerChangeStatus = (plan) => {
    const isSuperAdmin = userRoles.includes('Super Admin');
    if (isSuperAdmin) return { allowed: true, reason: null };
    
    // 1. Cannot change if no report
    if (!plan.report) {
        return {
            allowed: false,
            reason: 'No report submitted yet'
        };
    }
    
    // 2. Cannot change if BOD has finalized
    if (['success', 'failed'].includes(plan.bod_status)) {
        return {
            allowed: false,
            reason: 'BOD has finalized this plan'
        };
    }
    
    // 3. Cannot change if grace period expired
    if (plan.manager_status !== 'pending') {
        const lastLog = plan.statusLogs
            .filter(log => log.field === 'manager_status')
            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))[0];
        
        if (lastLog) {
            const daysSince = daysBetween(new Date(lastLog.created_at), new Date());
            const gracePeriodDays = 3;
            
            if (daysSince > gracePeriodDays) {
                return {
                    allowed: false,
                    reason: 'Grace period expired (3 days)'
                };
            }
        }
    }
    
    // 4. Cannot change if plan expired before reporting
    if (isPlanExpired(plan) && plan.status === 'created') {
        return {
            allowed: false,
            reason: 'Plan expired before report'
        };
    }
    
    return {
        allowed: true,
        reason: null
    };
};
```

**⚠️ PENTING:** Lock ini hanya untuk **PLAN SPECIFIC**, tidak mempengaruhi:
- Create plan baru
- Create report untuk plan lain
- Review plan lain

---

### **RULE 4: BOD Toggle Lock**

```javascript
const canBODChangeStatus = (plan) => {
    const isSuperAdmin = userRoles.includes('Super Admin');
    if (isSuperAdmin) return { allowed: true, reason: null };
    
    // 1. Cannot change if manager hasn't approved
    if (plan.manager_status !== 'approved') {
        return {
            allowed: false,
            reason: 'Manager must approve first'
        };
    }
    
    // 2. Cannot change if grace period expired
    if (['success', 'failed'].includes(plan.bod_status)) {
        const graceSeconds = getGraceTimeRemaining(plan.id, 'bod_status');
        
        if (graceSeconds <= 0) {
            return {
                allowed: false,
                reason: 'Grace period expired (5 minutes)'
            };
        }
    }
    
    return {
        allowed: true,
        reason: null,
        graceRemaining: ['success', 'failed'].includes(plan.bod_status) 
            ? getGraceTimeRemaining(plan.id, 'bod_status') 
            : null
    };
};
```

---

## 🎨 UI/UX IMPLEMENTATION

### **Create Plan Button**

```vue
<template>
    <div class="flex items-center gap-2">
        <Link 
            v-if="showCreateButton"
            :href="route('planning.create')" 
            :class="[
                'group relative flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-white shadow-lg transition-all',
                canCreatePlanToday 
                    ? 'bg-gradient-to-r from-emerald-600 to-teal-500 hover:shadow-emerald-500/30 active:scale-[0.98]' 
                    : 'bg-gray-400 cursor-not-allowed opacity-60'
            ]"
            :disabled="!canCreatePlanToday"
        >
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
            </svg>
            <span>Create Plan</span>
            
            <!-- Tooltip when disabled -->
            <div 
                v-if="!canCreatePlanToday"
                class="absolute -bottom-12 left-1/2 -translate-x-1/2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10"
            >
                📅 Planning can only be created on <strong>Friday</strong>
                <div class="absolute -top-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-gray-900 rotate-45"></div>
            </div>
        </Link>
        
        <!-- Day indicator -->
        <div v-if="!canCreatePlanToday" class="text-xs text-gray-500">
            Next planning day: <strong class="text-emerald-600">{{ nextFriday }}</strong>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const canCreatePlanToday = computed(() => {
    const today = new Date();
    const dayOfWeek = today.getDay();
    const allowedDays = props.timeSettings?.allowed_plan_creation_days || [5];
    return allowedDays.includes(dayOfWeek);
});

const nextFriday = computed(() => {
    const today = new Date();
    const daysUntilFriday = (5 - today.getDay() + 7) % 7 || 7;
    const friday = new Date(today);
    friday.setDate(today.getDate() + daysUntilFriday);
    return friday.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'short' });
});
</script>
```

---

### **Create Report Button (Per Plan)**

```vue
<template>
    <button
        v-if="canShowReportButton(customer.latest_plan)"
        @click="createReport(customer.latest_plan)"
        :disabled="!canCreateReportNow(customer.latest_plan)"
        :class="[
            'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all',
            canCreateReportNow(customer.latest_plan)
                ? 'bg-blue-600 text-white hover:bg-blue-700'
                : 'bg-gray-300 text-gray-500 cursor-not-allowed'
        ]"
    >
        Create Report
        
        <!-- Reason tooltip -->
        <span 
            v-if="!canCreateReportNow(customer.latest_plan).allowed"
            class="tooltip"
        >
            {{ canCreateReportNow(customer.latest_plan).reason }}
        </span>
    </button>
</template>

<script setup>
const canShowReportButton = (plan) => {
    if (!plan) return false;
    if (plan.status === 'reported') return false; // Already reported
    return true;
};

const canCreateReportNow = (plan) => {
    if (!plan) return { allowed: false, reason: 'No active plan' };
    
    if (plan.report) {
        return { allowed: false, reason: 'Report already submitted' };
    }
    
    if (plan.status !== 'created') {
        return { allowed: false, reason: 'Plan already reported' };
    }
    
    const today = new Date();
    const dayOfWeek = today.getDay();
    
    if (dayOfWeek === 0 || dayOfWeek === 6) {
        return { allowed: false, reason: 'Reports: Mon-Fri only' };
    }
    
    if (isPlanExpired(plan)) {
        return { allowed: false, reason: 'Plan expired' };
    }
    
    return { allowed: true, reason: null };
};
</script>
```

---

### **Manager/BOD Toggle with Lock Indicator**

```vue
<template>
    <div class="flex items-center gap-2">
        <!-- Manager Toggle -->
        <button
            v-if="canEditControl"
            @click="handleManagerToggle(plan)"
            :disabled="!canManagerChange(plan).allowed"
            :class="[
                'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all',
                canManagerChange(plan).allowed
                    ? 'bg-white border border-gray-300 hover:bg-gray-50'
                    : 'bg-gray-100 text-gray-400 cursor-not-allowed'
            ]"
        >
            <span v-if="!canManagerChange(plan).allowed" class="mr-1">🔒</span>
            {{ getManagerStatusLabel(plan.manager_status) }}
        </button>
        
        <!-- Lock reason tooltip -->
        <div 
            v-if="!canManagerChange(plan).allowed"
            class="text-[10px] text-gray-500 flex items-center gap-1"
        >
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
            {{ canManagerChange(plan).reason }}
        </div>
        
        <!-- Grace period countdown -->
        <div
            v-else-if="managerGraceRemaining(plan) > 0"
            class="text-[10px] text-amber-600 flex items-center gap-1"
        >
            ⏰ {{ formatGraceTime(managerGraceRemaining(plan)) }} to change
        </div>
    </div>
</template>
```

---

## 📊 SUMMARY TABLE

| Action | Who | When Allowed | Affected By Lock? |
|--------|-----|--------------|-------------------|
| **Create Plan** | User, Manager, Supervisor | **Jumat** (configurable) | ❌ NO - Independent |
| **Create Report** | User, Manager, Supervisor | **Senin-Jumat**, plan active | ❌ NO - Per plan |
| **Manager Review** | Manager | After report submitted | ✅ YES - Grace 3 days |
| **BOD Review** | BOD | After manager approve | ✅ YES - Grace 5 min |
| **View Plans** | All | Anytime | ❌ NO |
| **View Reports** | All | Anytime | ❌ NO |

---

## 💡 KESIMPULAN FINAL

### **Yang BENAR:**

✅ **Lock Toggle ≠ Lock User Actions**
- Lock toggle hanya mempengaruhi penilaian planning **yang sudah completed**
- User tetap bisa create plan & report **baru** sesuai aturan waktu

✅ **Create Plan**: Available **every Friday**
- Tidak terpengaruh berapa banyak planning yang locked/completed
- Constraint: Hanya hari yang diizinkan (default: Jumat)

✅ **Create Report**: Available **Mon-Fri for active plans**
- User bisa create report untuk planning **lain** yang active
- Tidak terpengaruh status planning yang sudah completed

✅ **Toggle Lock**: Specific per planning
- Planning A locked ≠ Planning B locked
- Setiap planning punya lifecycle independent

### **Contoh Konkrit:**

```
9 JAN (Jumat):
├─ Planning A: Manager toggle LOCKED ❌ (grace habis)
├─ User: Create Planning B ✅ (hari Jumat, allowed!)
└─ User: Bisa create report untuk Planning B next week ✅

12 JAN (Senin):
├─ Planning A: BOD toggle available ✅ (belum lock)
├─ Planning B: User create report ✅ (active plan)
└─ Planning A: BOD nilai → lock ❌

16 JAN (Jumat):
├─ Planning A: Fully locked 🔒
├─ Planning B: Masih dalam review
└─ User: Create Planning C ✅ (hari Jumat, allowed!)
```

📁 **File:** `.agent/ANALISA_USER_ACTION_CONSTRAINTS.md`
