# 📊 ANALISA MENU PLANNING vs PLANNING REPORT

**Tanggal:** 2 Januari 2026  
**Topik:** Kombinasi dan Alur Menu Planning & Planning Report

---

## 🎯 EXECUTIVE SUMMARY

Sistem memiliki 2 menu terpisah:
1. **Planning** - Menu untuk create, view, dan manage planning (customer-centric)
2. **Planning Report** - Menu untuk view report detail (plan-centric dengan tabs filter)

**MASALAH UTAMA:**
- Kolom "Data Status" dan "Update Status" di Planning Report **ambigu** dan **overlapping**
- Tidak ada pemisahan jelas antara planning aktif vs history
- Tab filter (On Track, Warning, Failed, Completed, History) **tidak konsisten** dengan status database

---

## 📋 PERBANDINGAN KEDUA MENU

### 1️⃣ MENU PLANNING (`Planning/Index.vue`)

**TUJUAN:** Manajemen planning per customer

**STRUKTUR DATA:**
```javascript
// Data: Customers dengan latest_plan
customers: {
    data: [
        {
            id: 1,
            company_name: "Company A",
            latest_plan: {
                id: 10,
                status: 'created' | 'reported',
                manager_status: 'pending' | 'rejected' | 'escalated' | 'approved',
                bod_status: 'pending' | 'failed' | 'success',
                report: { ... }
            }
        }
    ]
}
```

**KOLOM/BADGE:**
- **PLAN Badge** (computed) - Status planning: none, created, reported, warning, expired
- **CTRL Badge** - Manager status: pending, rejected, escalated, approved
- **MON Badge** - BOD status: pending, failed, success

**TABS:**
- Week 1, Week 2, Week 3, Week 4, All Planning

**KARAKTERISTIK:**
✅ Customer-centric view
✅ Fokus pada latest plan per customer
✅ Action buttons: Create Plan, Create Report
✅ Manager/BOD dapat toggle status

---

### 2️⃣ MENU PLANNING REPORT (`PlanningReport/Index.vue`)

**TUJUAN:** View semua planning reports dengan detail

**STRUKTUR DATA:**
```javascript
// Data: All Plans (bukan customer)
plans: {
    data: [
        {
            id: 10,
            status: 'created' | 'reported',
            customer: { company_name: "..." },
            report: { pic, position, progress, ... },
            is_history: true/false
        }
    ]
}
```

**KOLOM UTAMA:**
1. **Activity Code** - Badge dengan warna (getBadgeColor)
2. **Created At** (Input Time)
3. **Updated At** (Last Update)
4. **Planning Date** (Activity Date)
5. **Data Status** ⚠️ - Status dari database (`created`, `reported`)
6. **Update Status** ⚠️ - Computed status (On Track, Warning, Expired, Completed, History)

**TABS:**
- All Reports
- On Track
- Warning
- Failed
- Completed
- History

**KARAKTERISTIK:**
✅ Plan-centric view
✅ Semua plans ditampilkan (bukan hanya latest)
✅ Column visibility toggle
✅ Group by customer
✅ Filter by customer/user

---

## ⚠️ MASALAH IDENTIFIKASI

### Problem #1: Kolom "Data Status" vs "Update Status" AMBIGU

**KOLOM "DATA STATUS" (Line 581-583):**
```vue
<td class="...">
    <span class="capitalize px-2 py-1 bg-gray-100 rounded text-gray-700 font-medium">
        {{ plan.status }}  <!-- 'created' atau 'reported' -->
    </span>
</td>
```

**NILAI:** `created` atau `reported` (dari database)

**KOLOM "UPDATE STATUS" (Line 609-616):**
```javascript
const getUpdateStatus = (plan) => {
    // 1. History
    if (plan.is_history) return 'History';
    
    // 2. Completed
    if (plan.status === 'reported') return 'Completed';
    
    // 3. Expired (jika lewat expiry)
    if (diffValue >= expiryValue) return 'Expired';
    
    // 4. On Track (default)
    return 'On Track';
}
```

**NILAI:** `History`, `Completed`, `Expired`, `On Track`

**❌ MASALAH:**
1. **Data Status** dan **Update Status** **redundant** dan **confusing**
2. User tidak jelas bedanya: "Status apa yang penting?"
3. `created` vs `On Track` - apa bedanya?
4. `reported` vs `Completed` - kenapa beda istilah?
5. Tidak ada status `Warning` di Update Status padahal ada di tabs

### Problem #2: TAB Filter Tidak Konsisten

**TABS YANG ADA:**
```javascript
const tabs = [
    { id: 'all', name: 'All Reports' },
    { id: 'on_track', name: 'On Track' },
    { id: 'warning', name: 'Warning' },
    { id: 'failed', name: 'Failed' },
    { id: 'completed', name: 'Completed' },
    { id: 'history', name: 'History' },
];
```

**BACKEND FILTER (PlanningReportController.php Line 89-112):**
```php
if ($currentTab !== 'all') {
    foreach ($allPlans as $plan) {
        $status = $this->calculatePlanStatus($plan, ...);
        
        if ($currentTab === 'on_track' && $status === 'on_track') {
            $filteredIds[] = $plan->id;
        } elseif ($currentTab === 'warning' && $status === 'warning') {
            $filteredIds[] = $plan->id;
        } elseif ($currentTab === 'failed' && $status === 'failed') {
            $filteredIds[] = $plan->id;
        } elseif ($currentTab === 'completed' && $status === 'completed') {
            $filteredIds[] = $plan->id;
        } elseif ($currentTab === 'history' && $plan->is_history) {
            $filteredIds[] = $plan->id;
        }
    }
}
```

**❌ MASALAH:**
1. Tab filter menggunakan `calculatePlanStatus()` di backend yang **berbeda** dengan `getUpdateStatus()` di frontend
2. Status `warning` tidak muncul di `getUpdateStatus()`
3. Status `failed` tidak ada di database, hanya computed
4. Inkonsistensi antara backend calculation dan frontend display

### Problem #3: Tidak Ada Status "Warning" di Frontend

**DI BACKEND (calculatePlanStatus):**
```php
if ($diffValue >= ($expiryValue * 0.8)) {
    return 'warning'; // 80% dari expiry
}
```

**DI FRONTEND (getUpdateStatus):**
```javascript
// ❌ TIDAK ADA logika untuk 'warning'
// Langsung dari 'On Track' → 'Expired'
```

### Problem #4: "Completed" vs "Reported" Confusion

**LOGIKA SAAT INI:**
```javascript
if (plan.status === 'reported' || plan.status === 'success' || plan.status === 'failed') {
    return { label: 'Completed', ... };
}
```

**❌ MASALAH:**
- `reported` = User sudah submit report
- `Completed` = Sudah dinilai (approved + success)?
- Tidak jelas kapan planning benar-benar "selesai"

---

## ✅ REKOMENDASI SOLUSI

### 🎯 SOLUSI #1: Sederhanakan & Perjelas Kolom Status

**HAPUS:** Kolom "Data Status" (redundant)
**PERTAHANKAN:** Kolom "Update Status" dengan perbaikan

**KOLOM BARU YANG DISARANKAN:**

| Kolom | Tujuan | Nilai |
|-------|--------|-------|
| **Activity Code** | Identitas aktivitas | C1, V2, OM3, dll |
| **Input Time** (`created_at`) | Kapan planning dibuat | 01/01/26 10:30 |
| **Last Update** (`updated_at`) | Update terakhir | 02/01/26 14:20 |
| **Activity Date** (`planning_date`) | Tanggal planning | 05/01/26 |
| **Lifecycle Status** ✨ NEW | Status lengkap planning | Draft, Active, Reported, Reviewed, Completed, Failed, Expired |
| **Review Status** ✨ NEW | Status penilaian | Pending, Approved, Rejected, Success, Failed |

### 🎯 SOLUSI #2: Perbaiki "Update Status" / "Lifecycle Status"

**LOGIC BARU:**
```javascript
const getLifecycleStatus = (plan) => {
    // 1. History - Planning lama yang sudah ada versi baru
    if (plan.is_history) {
        return { 
            label: 'History', 
            class: 'bg-gray-100 text-gray-600',
            icon: 'clock'
        };
    }
    
    // 2. Completed - Manager approved + BOD success (FINAL)
    if (plan.manager_status === 'approved' && plan.bod_status === 'success') {
        return { 
            label: 'Completed', 
            class: 'bg-emerald-100 text-emerald-700 font-bold',
            icon: 'check-circle'
        };
    }
    
    // 3. Failed - Manager rejected OR BOD failed (FINAL)
    if (plan.manager_status === 'rejected' || plan.bod_status === 'failed') {
        return { 
            label: 'Failed', 
            class: 'bg-red-100 text-red-700 font-bold',
            icon: 'x-circle'
        };
    }
    
    // 4. Under Review - Reported dan sedang direview
    if (plan.status === 'reported') {
        return { 
            label: 'Under Review', 
            class: 'bg-purple-100 text-purple-700',
            icon: 'eye'
        };
    }
    
    // 5. Expired - Belum reported dan lewat deadline
    const expiryValue = timeSettings.plan_expiry_value || 7;
    const expiryUnit = timeSettings.plan_expiry_unit || 'Days (Production)';
    const diffValue = calculateTimeDiff(plan.created_at, expiryUnit);
    
    if (plan.status === 'created' && diffValue >= expiryValue) {
        return { 
            label: 'Expired', 
            class: 'bg-red-100 text-red-800 animate-pulse',
            icon: 'exclamation'
        };
    }
    
    // 6. Warning - Mendekati deadline (80% dari expiry)
    if (plan.status === 'created' && diffValue >= (expiryValue * 0.8)) {
        return { 
            label: 'Warning', 
            class: 'bg-yellow-100 text-yellow-700 animate-pulse',
            icon: 'exclamation-triangle'
        };
    }
    
    // 7. Active / On Track - Planning aktif, belum reported, belum warning
    return { 
        label: 'On Track', 
        class: 'bg-blue-100 text-blue-700',
        icon: 'play'
    };
};
```

### 🎯 SOLUSI #3: Konsistensi Backend & Frontend

**UPDATE BACKEND (PlanningReportController.php):**
```php
private function calculatePlanStatus($plan, $expiryValue, $expiryUnit, $warningThreshold)
{
    // 1. History
    if ($plan->is_history) return 'history';
    
    // 2. Completed (Manager approved + BOD success)
    if ($plan->manager_status === 'approved' && $plan->bod_status === 'success') {
        return 'completed';
    }
    
    // 3. Failed (Manager rejected OR BOD failed)
    if ($plan->manager_status === 'rejected' || $plan->bod_status === 'failed') {
        return 'failed';
    }
    
    // 4. Under Review
    if ($plan->status === 'reported') {
        return 'under_review';
    }
    
    // 5. Calculate time diff
    $diffValue = $this->calculateTimeDiff($plan->created_at, $expiryUnit);
    
    // 6. Expired
    if ($plan->status === 'created' && $diffValue >= $expiryValue) {
        return 'expired';
    }
    
    // 7. Warning
    if ($plan->status === 'created' && $diffValue >= ($expiryValue * 0.8)) {
        return 'warning';
    }
    
    // 8. On Track
    return 'on_track';
}
```

### 🎯 SOLUSI #4: Update TAB Definitions

**TAB BARU:**
```javascript
const tabs = [
    { id: 'all', name: 'All Reports', description: 'Semua planning' },
    { id: 'active', name: 'Active', description: 'On Track + Warning' },
    { id: 'pending_review', name: 'Pending Review', description: 'Reported, menunggu penilaian' },
    { id: 'completed', name: 'Completed', description: 'Approved + Success' },
    { id: 'failed', name: 'Failed', description: 'Rejected atau Failed' },
    { id: 'expired', name: 'Expired', description: 'Kadaluarsa tanpa report' },
    { id: 'history', name: 'History', description: 'Planning versi lama' },
];
```

---

## 🔄 ALUR KERJA YANG DISARANKAN

### WORKFLOW LENGKAP (Planning → Planning Report)

```
┌─────────────────────────────────────────────────────────────────┐
│  MENU: PLANNING (Customer View)                                 │
│  Tujuan: Manage planning per customer                           │
└─────────────────────────────────────────────────────────────────┘
    ↓
[User Action] Create Planning untuk Customer A
    • Menu: Planning → Button "Create Plan"
    • Form: Pilih customer, product, activity type, description
    • Submit → Plan dibuat dengan status 'created'
    ↓
[Timeline] Planning aktif, user kerjakan aktivitas
    ↓
[User Action] Create Report
    • Menu: Planning → Button "Create Report" (muncul di row customer)
    • Form: Execution date, location, PIC, result, progress
    • Submit → Report dibuat, plan.status = 'reported'
    ↓
┌─────────────────────────────────────────────────────────────────┐
│  MENU: PLANNING REPORT (Plan View)                              │
│  Tujuan: View & track semua planning detail                     │
└─────────────────────────────────────────────────────────────────┘
    ↓
[Manager] Review di Planning atau Planning Report
    • Melihat planning yang 'Under Review' (tab: Pending Review)
    • Detail: Activity code, input time, last update, progress
    • Lifecycle Status: "Under Review"
    • Action: Approve, Reject, Escalate
    ↓
IF Manager APPROVE:
    • manager_status = 'approved'
    • Lifecycle Status berubah: "Under Review" (masih, menunggu BOD)
    ↓
[BOD] Final Review di Planning Report
    • Tab: Pending Review (yang sudah approved manager)
    • Lifecycle Status: "Under Review" (tapi manager_status = approved)
    • Action: Success atau Failed
    ↓
IF BOD SUCCESS:
    • bod_status = 'success'
    • Lifecycle Status: "Completed" ✅
    • Planning muncul di tab "Completed"
    • Tidak bisa diedit lagi
    ↓
[Planning Report] User bisa filter:
    • Tab "Active" → Planning yang ongoing (On Track + Warning)
    • Tab "Pending Review" → Reported, menunggu penilaian
    • Tab "Completed" → Approved + Success
    • Tab "Failed" → Rejected/Failed
    • Tab "Expired" → Kadaluarsa
    • Tab "History" → Planning versi lama
```

### PEMBAGIAN FUNGSI MENU

| Aspek | Menu PLANNING | Menu PLANNING REPORT |
|-------|---------------|----------------------|
| **View** | Customer-centric | Plan-centric |
| **Data** | Latest plan per customer | All plans (semua versi) |
| **Tujuan** | Create & manage planning | Track & review detail |
| **Action** | Create Plan, Create Report | View, Filter, Analyze |
| **Filter** | By Team, By User, By Week | By Tab Status, By Customer |
| **Best For** | User (staff) daily work | Manager/BOD review & reporting |

---

## 📊 STRUKTUR KOLOM YANG DIREKOMENDASIKAN

### Planning Report - Desktop Table

| # | Kolom | Sumber Data | Keterangan |
|---|-------|-------------|------------|
| 1 | **No** | Index | Nomor urut |
| 2 | **Activity Code** | Computed | Badge: C1, V2, OM3 (warna berdasarkan age) |
| 3 | **Input Time** | `created_at` | Kapan planning dibuat (DD/MM/YY HH:mm) |
| 4 | **Last Update** | `updated_at` | Update terakhir (DD/MM/YY HH:mm) |
| 5 | **Activity Date** | `planning_date` | Target tanggal aktivitas (DD/MM/YY) |
| 6 | **Lifecycle Status** ✨ | Computed | On Track, Warning, Reported, Completed, Failed, Expired, History |
| 7 | **Sales/Marketing** | `user.name` | Nama user yang buat planning |
| 8 | **Company** | `customer.company_name` | Nama customer |
| 9 | **Project** | `project_name` | Nama project |
| 10 | **Customer PIC** | `report.pic` | PIC dari customer |
| 11 | **Position** | `report.position` | Position PIC |
| 12 | **Location** | `report.location` | Lokasi aktivitas |
| 13 | **Product** | `product.name` | Produk yang ditawarkan |
| 14 | **Activity** | `activity_type` | Jenis aktivitas (Call, Visit, dll) |
| 15 | **Progress** | `report.progress` | Progress bar + percentage |

**HAPUS:** Kolom "Data Status" (redundant dengan Lifecycle Status)

---

## 🎨 BADGE COLOR SCHEME

### Activity Code Badge (getBadgeColor)
```javascript
// TETAP seperti sekarang - based on age
- Reported/Success/Failed → GREEN (sudah lapor)
- Mendekati threshold → RED BLINKING (warning)
- Normal/baru → BLUE (on track)
```

### Lifecycle Status Badge (NEW)
```javascript
- On Track → bg-blue-100 text-blue-700 (biru)
- Warning → bg-yellow-100 text-yellow-700 animate-pulse (kuning kedip)
- Under Review → bg-purple-100 text-purple-700 (ungu)
- Completed → bg-emerald-100 text-emerald-700 font-bold (hijau bold)
- Failed → bg-red-100 text-red-700 font-bold (merah bold)
- Expired → bg-red-100 text-red-800 animate-pulse (merah kedip)
- History → bg-gray-100 text-gray-600 (abu-abu)
```

---

## 💡 KESIMPULAN & NEXT STEPS

### Kesimpulan
1. **Kolom "Data Status" harus dihapus** - redundant dan membingungkan
2. **Kolom "Update Status" diganti "Lifecycle Status"** - lebih jelas dan lengkap
3. **TAB filter perlu update** - konsisten dengan lifecycle status
4. **Backend & Frontend harus sync** - gunakan logika yang sama

### Prioritas Implementasi

**HIGH PRIORITY:**
1. ✅ Perbaiki `getUpdateStatus()` menjadi `getLifecycleStatus()` dengan 7 status lengkap
2. ✅ Hapus kolom "Data Status" dari PlanningReport/Index.vue
3. ✅ Update backend `calculatePlanStatus()` agar konsisten dengan frontend
4. ✅ Tambahkan logic untuk status "Warning"

**MEDIUM PRIORITY:**
5. ✅ Update tab definitions menjadi lebih spesifik
6. ✅ Tambahkan tooltip/description untuk setiap tab
7. ✅ Sinkronisasi badge colors

**LOW PRIORITY:**
8. ✅ Tambahkan column sorting
9. ✅ Export to Excel/PDF
10. ✅ Advanced filters

### Rekomendasi Akhir

**UNTUK USER (Staff):**
- Gunakan menu **Planning** untuk daily work
- Create plan, create report dari sini
- Lihat badge PLAN/CTRL/MON untuk status cepat

**UNTUK MANAGER:**
- Gunakan menu **Planning** untuk review & approve (toggle CTRL)
- Gunakan menu **Planning Report** untuk tracking detail semua planning
- Filter tab "Pending Review" untuk planning yang perlu dinilai

**UNTUK BOD:**
- Gunakan menu **Planning** untuk final review (toggle MON)
- Gunakan menu **Planning Report** untuk analytics & reporting
- Tab "Completed" untuk success rate tracking

📁 **File ini:** `.agent/ANALISA_PLANNING_REPORT_MENU.md`
