# ⏰ ANALISA TIMELINE & LOCK MECHANISM PENILAIAN

**Tanggal:** 2 Januari 2026  
**Topik:** Alur Penilaian, Time Limit, dan Lock Mechanism untuk Manager & BOD

---

## 🎯 PERTANYAAN KRUSIAL

**Q1:** Jika planning sudah **completed**, apakah toggle penilaian Manager dan BOD masih bisa dipilih?

**Q2:** Apakah ada **batas waktu penilaian**? Jika melewati batas, apakah toggle otomatis **ter-lock**?

**Q3:** Bagaimana alur lengkap dengan contoh timeline konkrit?

---

## 📅 CONTOH TIMELINE KONKRIT

### **Scenario:**
User membuat planning pada **Jumat, 2 Januari 2026**

```
┌─────────────────────────────────────────────────────────────────┐
│  TIMELINE LENGKAP - JANUARI 2026                                │
└─────────────────────────────────────────────────────────────────┘

JUMAT, 2 JANUARI 2026
├─ [10:00] User membuat Planning
│         • Customer: PT ABC
│         • Product: Software XYZ
│         • Activity: Visit ke kantor customer
│         • Planning Date: 5 Januari 2026 (Senin)
│         • Description: Presentasi produk baru
│         • Status: 'created'
│         • Manager Status: 'pending'
│         • BOD Status: 'pending'
│
└─ Planning ACTIVE, menunggu eksekusi

SABTU-MINGGU, 3-4 JANUARI 2026
└─ [Weekend] User prepare presentasi

SENIN-JUMAT, 5-9 JANUARI 2026
├─ [SENIN, 5 JAN 09:00] User eksekusi planning
│         • Visit ke PT ABC
│         • Meeting dengan Direktur
│         • Presentasi berjalan lancar
│
├─ [SENIN, 5 JAN 16:00] User submit REPORT
│         • Execution Date: 5 Januari 2026
│         • Location: Kantor PT ABC, Jakarta
│         • PIC: Pak Budi (Direktur)
│         • Position: Director
│         • Result: Presentasi diterima baik, diskusi lanjut minggu depan
│         • Progress: 30% - Initial Discussion
│         • Is Success: true
│         • Status berubah: 'created' → 'reported'
│         • Updated_at: 5 Jan 2026, 16:00
│
├─ [SELASA, 6 JAN 10:00] Manager review planning
│         • Melihat report yang sudah disubmit
│         • Membaca hasil: "Presentasi diterima baik"
│         • Progress: 30%
│         • Keputusan: APPROVE
│         • Manager Status: 'pending' → 'approved'
│         • Updated_at: 6 Jan 2026, 10:00
│
└─ Planning menunggu penilaian BOD

SENIN, 12 JANUARI 2026
├─ [10:00] BOD review planning
│         • Melihat report + approval manager
│         • Progress 30% dianggap good start
│         • Next follow-up sudah jelas
│         • Keputusan: SUCCESS
│         • BOD Status: 'pending' → 'success'
│         • Updated_at: 12 Jan 2026, 10:00
│
└─ Planning STATUS: COMPLETED ✅
    • Manager: approved
    • BOD: success
    • Lifecycle: Completed

RABU, 13 JANUARI 2026
└─ [Opsional] BOD masih bisa ubah penilaian?
    ❓ PERTANYAAN: Apakah toggle masih aktif?
```

---

## 🔒 OPSI LOCK MECHANISM

### **OPSI 1: SOFT LOCK (Bisa Diubah dengan Time Limit)**

**KONSEP:**
- Toggle masih bisa diklik selama dalam **grace period** tertentu
- Setelah grace period habis → **LOCK PERMANENT**
- Super Admin tetap bisa override

**IMPLEMENTASI:**

```javascript
// Manager Toggle Lock Logic
const canManagerChange = (plan) => {
    const isSuperAdmin = userRoles.includes('Super Admin');
    if (isSuperAdmin) return true; // Super Admin always can
    
    // 1. Cek apakah sudah completed
    if (plan.manager_status === 'approved' && plan.bod_status === 'success') {
        return false; // LOCK jika sudah fully completed
    }
    
    // 2. Cek grace period untuk manager (misal: 3 hari setelah report)
    if (!plan.report) return false; // Belum ada report, tidak bisa dinilai
    
    const reportDate = new Date(plan.updated_at); // Tanggal submit report
    const now = new Date();
    const gracePeriodDays = 3; // Manager punya 3 hari untuk nilai/ubah
    const daysSinceReport = (now - reportDate) / (1000 * 60 * 60 * 24);
    
    if (daysSinceReport > gracePeriodDays) {
        return false; // LOCK setelah 3 hari
    }
    
    return true; // Masih dalam grace period
};

// BOD Toggle Lock Logic
const canBODChange = (plan) => {
    const isSuperAdmin = userRoles.includes('Super Admin');
    if (isSuperAdmin) return true;
    
    // 1. Cek apakah sudah completed
    if (plan.bod_status === 'success' || plan.bod_status === 'failed') {
        // Cek grace period BOD (misal: 2 hari setelah BOD nilai)
        const bodReviewDate = plan.statusLogs
            .filter(log => log.field === 'bod_status')
            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))[0]?.created_at;
        
        if (!bodReviewDate) return true; // Belum pernah dinilai
        
        const daysSinceBodReview = (new Date() - new Date(bodReviewDate)) / (1000 * 60 * 60 * 24);
        const bodGracePeriodDays = 2;
        
        if (daysSinceBodReview > bodGracePeriodDays) {
            return false; // LOCK setelah 2 hari dari penilaian BOD
        }
    }
    
    // 2. BOD hanya bisa nilai jika Manager sudah approve
    if (plan.manager_status !== 'approved') {
        return false; // Harus menunggu manager approve dulu
    }
    
    return true;
};
```

**TIMELINE DENGAN SOFT LOCK:**

```
5 JAN (Senin) 16:00 → User submit report
                      ↓
6 JAN (Selasa) 10:00 → Manager APPROVE
                      ↓ Grace period Manager: 3 hari (sampai 9 Jan)
                      ↓ Manager masih bisa ubah sampai 9 Jan
                      ↓
9 JAN (Jumat) 23:59 → Grace period Manager HABIS
                      ↓ Manager toggle LOCK ❌
                      ↓
12 JAN (Senin) 10:00 → BOD SUCCESS
                       ↓ Grace period BOD: 2 hari (sampai 14 Jan)
                       ↓ BOD masih bisa ubah sampai 14 Jan
                       ↓
14 JAN (Rabu) 23:59 → Grace period BOD HABIS
                      ↓ BOD toggle LOCK ❌
                      ↓ Planning FULLY LOCKED ✅
```

**KEUNTUNGAN SOFT LOCK:**
✅ Manager/BOD punya waktu koreksi jika salah klik
✅ Ada grace period untuk review ulang
✅ Sistem lebih fleksibel untuk human error
✅ Time-based auto-lock memberikan fairness

**KERUGIAN:**
❌ Lebih kompleks implementasinya
❌ Perlu tracking grace period di UI
❌ User bisa "gaming system" dengan ubah-ubah status

---

### **OPSI 2: HARD LOCK (Langsung Lock Setelah BOD Nilai)**

**KONSEP:**
- Setelah BOD memberikan penilaian (success/failed) → **LOCK IMMEDIATELY**
- Tidak ada grace period
- Hanya Super Admin yang bisa ubah

**IMPLEMENTASI:**

```javascript
const canManagerChange = (plan) => {
    const isSuperAdmin = userRoles.includes('Super Admin');
    if (isSuperAdmin) return true;
    
    // LOCK jika BOD sudah final
    if (plan.bod_status === 'success' || plan.bod_status === 'failed') {
        return false; // ❌ LOCKED
    }
    
    // Manager masih bisa ubah selama BOD belum nilai
    return true;
};

const canBODChange = (plan) => {
    const isSuperAdmin = userRoles.includes('Super Admin');
    if (isSuperAdmin) return true;
    
    // LOCK jika sudah dinilai (success/failed)
    if (plan.bod_status === 'success' || plan.bod_status === 'failed') {
        return false; // ❌ LOCKED (kecuali ada undo dalam grace period 5 menit)
    }
    
    // BOD hanya bisa nilai jika Manager approved
    if (plan.manager_status !== 'approved') {
        return false;
    }
    
    return true;
};
```

**TIMELINE DENGAN HARD LOCK:**

```
5 JAN 16:00 → User submit report
              ↓
6 JAN 10:00 → Manager APPROVE
              ↓ Manager masih bisa ubah (selama BOD belum nilai)
              ↓
12 JAN 10:00 → BOD SUCCESS
               ↓ BOD toggle LOCK ❌ (langsung setelah klik)
               ↓ Manager toggle LOCK ❌ (karena BOD sudah final)
               ↓ Planning FULLY LOCKED ✅
```

**KEUNTUNGAN HARD LOCK:**
✅ Lebih sederhana dan jelas
✅ Tidak ada "grey area" waktu
✅ Audit trail lebih bersih
✅ Mencegah perubahan berulang-ulang

**KERUGIAN:**
❌ Tidak ada toleransi untuk salah klik
❌ Perlu Super Admin untuk koreksi

---

### **OPSI 3: HYBRID LOCK (Hard Lock BOD + Grace Period Manager)**

**KONSEP:** (⭐ **REKOMENDASI**)
- Manager punya grace period (misal 3 hari)
- BOD langsung lock setelah nilai (hard lock)
- Best of both worlds

**IMPLEMENTASI:**

```javascript
const canManagerChange = (plan) => {
    const isSuperAdmin = userRoles.includes('Super Admin');
    if (isSuperAdmin) return true;
    
    // 1. HARD LOCK jika BOD sudah final
    if (plan.bod_status === 'success' || plan.bod_status === 'failed') {
        return false;
    }
    
    // 2. SOFT LOCK untuk manager dengan grace period
    if (plan.manager_status !== 'pending') {
        const statusLog = plan.statusLogs
            .filter(log => log.field === 'manager_status')
            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))[0];
        
        if (statusLog) {
            const daysSince = (new Date() - new Date(statusLog.created_at)) / (1000 * 60 * 60 * 24);
            if (daysSince > 3) return false; // Grace period 3 hari
        }
    }
    
    return plan.status === 'reported'; // Harus sudah ada report
};

const canBODChange = (plan) => {
    const isSuperAdmin = userRoles.includes('Super Admin');
    if (isSuperAdmin) return true;
    
    // HARD LOCK setelah BOD nilai
    if (plan.bod_status === 'success' || plan.bod_status === 'failed') {
        // Hanya kasih grace 5 menit untuk undo (dari PlanStatusLog)
        const graceSeconds = PlanStatusLog.getGraceTimeRemaining(plan.id, 'bod_status');
        if (graceSeconds <= 0) return false;
    }
    
    // Harus manager approve dulu
    if (plan.manager_status !== 'approved') return false;
    
    return true;
};
```

**TIMELINE HYBRID:**

```
6 JAN 10:00 → Manager APPROVE
              ↓ Grace period: 3 hari
              ↓ Manager bisa ubah sampai 9 JAN 10:00
              ↓
9 JAN 10:01 → Manager toggle LOCK ❌ (grace habis)
              ↓
12 JAN 10:00 → BOD SUCCESS
               ↓ Grace period: 5 menit (undo emergency)
               ↓
12 JAN 10:05 → BOD toggle LOCK ❌
               ↓ Planning FULLY LOCKED ✅
```

---

## ✅ REKOMENDASI FINAL

### **⭐ OPSI TERBAIK: HYBRID LOCK**

**KENAPA?**
1. ✅ **Manager butuh waktu review** - 3 hari grace period cukup
2. ✅ **BOD adalah final decision** - Hard lock setelah nilai prevent changes
3. ✅ **Emergency undo** - 5 menit grace untuk koreksi salah klik
4. ✅ **Balance antara flexibility & security**

### **BUSINESS RULES:**

```
RULE 1: Manager Review Window
├─ Manager dapat mengubah penilaian dalam 3 hari setelah memberikan nilai
├─ Setelah 3 hari → Toggle LOCK (kecuali Super Admin)
└─ Jika BOD sudah nilai → Toggle LOCK immediately (hard lock)

RULE 2: BOD Final Decision
├─ BOD hanya bisa nilai jika Manager sudah approve
├─ Setelah BOD nilai (success/failed) → Planning COMPLETED
├─ BOD punya 5 menit grace period untuk undo
└─ Setelah 5 menit → Toggle LOCK permanent (kecuali Super Admin)

RULE 3: Super Admin Override
├─ Super Admin selalu bisa ubah status (no lock)
├─ Perubahan Super Admin tetap tercatat di audit log
└─ Super Admin mendapat warning message sebelum ubah completed plan

RULE 4: Plan Expiry Lock
├─ Jika plan expired (melewati expiry time) → Cannot be reviewed
├─ Manager/BOD toggle DISABLED untuk expired plans
└─ Status otomatis: manager_status='rejected', bod_status='failed'
```

---

## 🔄 ALUR LENGKAP DENGAN LOCK LOGIC

```
┌─────────────────────────────────────────────────────────────────┐
│  FASE 1: PLANNING CREATION                                      │
└─────────────────────────────────────────────────────────────────┘
JUMAT, 2 JAN → User buat planning
               • Status: 'created'
               • Manager toggle: DISABLED ❌ (belum ada report)
               • BOD toggle: DISABLED ❌ (belum ada report)

┌─────────────────────────────────────────────────────────────────┐
│  FASE 2: EXECUTION & REPORTING                                  │
└─────────────────────────────────────────────────────────────────┘
SENIN, 5 JAN → User submit report
               • Status: 'reported'
               • Manager toggle: ENABLED ✅ (bisa dinilai)
               • BOD toggle: DISABLED ❌ (manager belum approve)

┌─────────────────────────────────────────────────────────────────┐
│  FASE 3: MANAGER REVIEW (Grace Period: 3 Hari)                  │
└─────────────────────────────────────────────────────────────────┘
SELASA, 6 JAN 10:00 → Manager APPROVE
                      • Manager Status: 'approved'
                      • Manager toggle: ENABLED ✅ (grace: 3 hari)
                      • BOD toggle: ENABLED ✅ (manager approved)
                      • Grace until: 9 JAN 10:00

KAMIS, 8 JAN → Manager masih bisa ubah (dalam grace)
               • Manager toggle: ENABLED ✅

SABTU, 10 JAN → Grace period HABIS
                • Manager toggle: LOCKED ❌
                • BOD toggle: ENABLED ✅ (masih bisa)

┌─────────────────────────────────────────────────────────────────┐
│  FASE 4: BOD FINAL REVIEW (Grace: 5 Menit)                      │
└─────────────────────────────────────────────────────────────────┘
SENIN, 12 JAN 10:00 → BOD SUCCESS
                      • BOD Status: 'success'
                      • Planning: COMPLETED ✅
                      • Manager toggle: LOCKED ❌ (BOD final)
                      • BOD toggle: ENABLED ⚠️ (grace: 5 min)

SENIN, 12 JAN 10:04 → BOD masih bisa undo
                      • BOD toggle: ENABLED ✅ (grace aktif)

SENIN, 12 JAN 10:06 → Grace period HABIS
                      • BOD toggle: LOCKED ❌
                      • Planning FULLY LOCKED 🔒
                      • Hanya Super Admin bisa ubah
```

---

## 🚨 EDGE CASES & HANDLING

### **Case 1: Plan Expired Before Manager Review**

```
JUMAT, 2 JAN → User buat planning
               • Expiry: 7 hari (9 JAN)
               ↓
SENIN, 5 JAN → User submit report
               ↓ Manager delay review
               ↓
SABTU, 10 JAN → Planning EXPIRED ⏰
                • Status: 'expired'
                • Manager toggle: DISABLED ❌ (expired)
                • BOD toggle: DISABLED ❌ (expired)
                • Cannot be reviewed anymore
```

**HANDLING:**
- Show warning di UI: "This plan has expired and cannot be reviewed"
- Badge: Expired (red, blinking)
- Toggle greyed out dengan tooltip

### **Case 2: Manager Reject**

```
SELASA, 6 JAN → Manager REJECT
                • Manager Status: 'rejected'
                • BOD Status: auto set to 'failed'
                • Planning: FAILED ❌
                • Manager toggle: LOCKED ❌
                • BOD toggle: LOCKED ❌
                • Lifecycle: Failed
```

### **Case 3: BOD Nilai Sebelum Grace Manager Habis**

```
RABU, 7 JAN → Manager APPROVE (grace: 3 hari, until 10 JAN)
              ↓
KAMIS, 8 JAN → BOD SUCCESS (masih dalam grace manager)
               • Manager toggle: LOCKED ❌ (BOD final override)
               • BOD toggle: LOCKED ❌ (after 5 min)
               • Grace manager dibatalkan karena BOD sudah final
```

---

## 📊 UI/UX INDICATORS

### **Toggle Button States:**

```vue
<!-- Manager Toggle -->
<button 
    v-if="canEditControl"
    @click="confirmControl(plan.id, newStatus, customer.company_name)"
    :disabled="!canManagerChange(plan)"
    :class="{
        'opacity-50 cursor-not-allowed': !canManagerChange(plan),
        'hover:bg-gray-100': canManagerChange(plan)
    }"
>
    <!-- Show lock icon if locked -->
    <svg v-if="!canManagerChange(plan)" class="w-3 h-3 text-gray-400">
        <path d="M12 2C9.243 2 7 4.243 7 7v3H6a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2v-8a2 2 0 00-2-2h-1V7c0-2.757-2.243-5-5-5z"/>
    </svg>
    
    {{ statusLabel }}
</button>

<!-- Grace Period Countdown (if active) -->
<div v-if="managerGraceTimeRemaining > 0" class="text-xs text-amber-600 mt-1">
    ⏰ Grace period: {{ formatGraceTime(managerGraceTimeRemaining) }}
</div>

<!-- Locked Message -->
<div v-else-if="!canManagerChange(plan) && !isSuperAdmin" class="text-xs text-gray-500 mt-1 flex items-center gap-1">
    🔒 Locked (BOD has finalized or grace period expired)
</div>
```

### **Status Tooltip:**

```javascript
const getToggleTooltip = (plan, toggleType) => {
    if (toggleType === 'manager') {
        if (!plan.report) return "Cannot review: No report submitted yet";
        if (plan.bod_status !== 'pending') return "Locked: BOD has finalized this plan";
        
        const graceRemaining = calculateGraceRemaining(plan, 'manager');
        if (graceRemaining <= 0) return "Locked: Grace period expired (3 days)";
        if (graceRemaining > 0) return `You can change within ${formatDays(graceRemaining)}`;
    }
    
    if (toggleType === 'bod') {
        if (plan.manager_status !== 'approved') return "Waiting: Manager must approve first";
        if (plan.bod_status !== 'pending') {
            const graceRemaining = calculateGraceRemaining(plan, 'bod');
            if (graceRemaining <= 0) return "Locked: Grace period expired (5 minutes)";
            return `Undo available for ${graceRemaining} seconds`;
        }
    }
    
    return "Click to change status";
};
```

---

## 💻 IMPLEMENTASI CODE

### **Backend Validation (PlanningController.php):**

```php
public function updateControl(Request $request, Plan $plan)
{
    $user = Auth::user();
    $isSuperAdmin = $user->hasRole('Super Admin');
    
    // Validation 1: Check if BOD has finalized
    if (!$isSuperAdmin && in_array($plan->bod_status, ['success', 'failed'])) {
        return back()->with('error', 'Cannot change: BOD has finalized this plan.');
    }
    
    // Validation 2: Check grace period (3 days)
    if (!$isSuperAdmin && $plan->manager_status !== 'pending') {
        $lastChange = $plan->statusLogs()
            ->where('field', 'manager_status')
            ->latest()
            ->first();
        
        if ($lastChange) {
            $daysSince = now()->diffInDays($lastChange->created_at);
            if ($daysSince > 3) {
                return back()->with('error', 'Grace period expired (3 days). Contact Super Admin.');
            }
        }
    }
    
    // Validation 3: Check if plan is expired
    if ($plan->isExpired() && $plan->status === 'created') {
        return back()->with('error', 'Cannot review expired plan.');
    }
    
    // Proceed with update...
    $plan->update(['manager_status' => $request->manager_status]);
    
    PlanStatusLog::create([...]);
    
    return back()->with('success', 'Status updated successfully.');
}
```

---

## 📌 KESIMPULAN

### **JAWABAN Q1: Toggle Masih Bisa Diklik Setelah Completed?**

**TIDAK.** Setelah planning **completed** (manager approved + BOD success):
- ❌ Manager toggle: **LOCKED**
- ❌ BOD toggle: **LOCKED** (setelah 5 menit grace)
- ✅ Hanya Super Admin yang bisa ubah

### **JAWABAN Q2: Apakah Ada Batas Waktu Penilaian?**

**YA.** Ada 2 jenis time limit:
1. **Manager Grace Period:** 3 hari setelah review untuk ubah penilaian
2. **BOD Grace Period:** 5 menit untuk undo (emergency)
3. **Plan Expiry:** 7 hari (configurable) - jika expired, tidak bisa direview

### **JAWABAN Q3: Alur Lengkap dengan Timeline?**

```
2 JAN (Jumat)    → User buat planning
5 JAN (Senin)    → User submit report
6 JAN (Selasa)   → Manager APPROVE ✅ (grace: 3 hari)
9 JAN (Jumat)    → Manager grace HABIS → LOCK ❌
12 JAN (Senin)   → BOD SUCCESS ✅ (grace: 5 menit)
12 JAN (10:05)   → BOD grace HABIS → LOCK ❌
                 → Planning FULLY LOCKED 🔒
```

### **REKOMENDASI:**
✅ Gunakan **HYBRID LOCK**
✅ Manager: 3 hari grace period
✅ BOD: Hard lock + 5 menit undo
✅ Super Admin: Always can override
✅ Expired plans: Cannot be reviewed

📁 **File:** `.agent/ANALISA_TIMELINE_PENILAIAN.md`
