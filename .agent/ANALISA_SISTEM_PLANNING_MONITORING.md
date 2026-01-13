# 📊 ANALISA SISTEM PLANNING MONITORING - DETAIL & KOMPREHENSIF

**Tanggal Analisa:** 2 Januari 2026  
**Aplikasi:** Planning Monitoring System  
**Stack Teknologi:** Laravel 12, Vue.js, Tailwind CSS, Docker, WSL Ubuntu, Laravel Sail  

---

## 📋 DAFTAR ISI
1. [Executive Summary](#executive-summary)
2. [Analisa Struktur Database](#analisa-struktur-database)
3. [Analisa Business Logic](#analisa-business-logic)
4. [Analisa Workflow Planning](#analisa-workflow-planning)
5. [Status Indicator System](#status-indicator-system)
6. [Permasalahan Yang Diidentifikasi](#permasalahan-yang-diidentifikasi)
7. [Rekomendasi Solusi Profesional](#rekomendasi-solusi-profesional)
8. [Workflow Yang Disarankan](#workflow-yang-disarankan)
9. [Implementasi Yang Direkomendasikan](#implementasi-yang-direkomendasikan)

---

## 🎯 EXECUTIVE SUMMARY

### Tujuan Sistem
Sistem Planning Monitoring adalah aplikasi untuk mengelola perencanaan aktivitas sales/marketing yang melibatkan 3 level penilaian:
1. **User** (Staff) - Membuat planning dan melaporkan hasil
2. **Manager** - Melakukan kontrol/penilaian (Control)
3. **BOD/Board of Director** - Melakukan monitoring/review final (Monitoring)

### Aturan Bisnis Utama
- Planning **hanya bisa dibuat pada hari Jumat**
- Planning **dikerjakan pada hari Senin-Jumat** setelah dibuat
- Setiap planning memiliki **threshold waktu** (expiry) untuk diselesaikan
- Manager dan BOD memberikan penilaian terhadap planning yang telah dilaporkan

---

## 🗄️ ANALISA STRUKTUR DATABASE

### 1. Tabel `plans`
```php
Schema::create('plans', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained()->onDelete('cascade');
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
    $table->string('project_name')->nullable();
    $table->date('planning_date');                    // Tanggal planning dibuat
    $table->string('activity_type');                  // Jenis aktivitas (Call, Visit, etc)
    $table->text('description')->nullable();          // Deskripsi planning
    
    // STATUS FIELDS - INI KUNCI UTAMA
    $table->enum('status', ['created', 'reported'])->default('created');
    $table->enum('manager_status', ['pending', 'rejected', 'escalated', 'approved'])->default('pending');
    $table->enum('bod_status', ['pending', 'failed', 'success'])->default('pending');
    
    $table->timestamps();                             // created_at, updated_at
});
```

**⚠️ INSIGHT PENTING:**
- Field `status` menunjukkan apakah planning sudah **dilaporkan** atau masih **pending**
- Field `manager_status` berisi penilaian dari Manager
- Field `bod_status` berisi penilaian dari BOD
- Field `created_at` sangat penting untuk menghitung **expiry time**
- Field `updated_at` diupdate setiap ada perubahan pada plan

### 2. Tabel `reports`
```php
Schema::create('reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('plan_id')->constrained('plans')->onDelete('cascade');
    $table->date('execution_date');                   // Tanggal eksekusi planning
    $table->string('location');
    $table->string('pic');                            // Person In Charge
    $table->string('position');
    $table->text('result_description');               // Hasil dari planning
    $table->text('next_plan_description')->nullable(); // Follow-up untuk planning berikutnya
    $table->string('progress');                       // Progress (10%, 20%, dll)
    $table->boolean('is_success')->default(false);    // Apakah goal tercapai
    $table->timestamps();
});
```

**🔑 RELASI:**
- Setiap `plan` **hasOne** `report` (1:1 relationship)
- Report hanya dibuat setelah planning dikerjakan
- Ketika report dibuat, `plan.status` berubah dari `created` → `reported`

### 3. Tabel `plan_status_logs`
```php
// Model: App\Models\PlanStatusLog
protected $fillable = [
    'plan_id',
    'user_id',
    'field',              // 'manager_status' atau 'bod_status'
    'old_value',          // Status lama
    'new_value',          // Status baru
    'is_grace_period',    // Apakah dalam grace period
    'notes',              // Catatan dari reviewer
];
```

**📊 FUNGSI:**
- Mencatat setiap perubahan status (audit trail)
- Membatasi jumlah perubahan (max 3x per field)
- Grace period 5 menit untuk undo perubahan

### 4. Tabel `time_settings`
```php
protected $fillable = [
    'planning_time_unit',           // Unit waktu warning (Minutes/Hours/Days)
    'planning_warning_threshold',   // Threshold untuk warning (misal: 14 menit)
    'plan_expiry_value',            // Nilai expiry (misal: 7 atau 5)
    'plan_expiry_unit',             // Unit expiry (Minutes/Hours/Days)
    'allowed_plan_creation_days',   // Hari yang diizinkan buat plan (Jumat)
    'testing_mode',                 // Mode testing untuk development
];
```

**⏱️ TIME MANAGEMENT:**
- **Expiry Time**: Waktu maksimal planning harus dilaporkan (strict lockdown)
- **Warning Threshold**: Waktu untuk reminder follow-up (biasanya lebih lama dari expiry)

---

## ⚙️ ANALISA BUSINESS LOGIC

### 1. Status Plan (`plan.status`)
Sistem saat ini menggunakan **2 nilai status**:

```javascript
// Di PlanningController.php (store method)
Plan::create([
    'status' => 'created',      // Default saat planning dibuat
    'manager_status' => 'pending',
    'bod_status' => 'pending',
]);

// Di PlanningController.php (storeReport method)
$plan->update(['status' => 'reported']); // Setelah report dibuat
```

**❌ MASALAH SAAT INI:**
Status hanya 2 nilai: `created` dan `reported`
- ✅ `created`: Planning dibuat, belum ada laporan
- ✅ `reported`: Report sudah dibuat

**❓ TIDAK ADA STATUS:**
- ❌ `completed`: Planning yang sudah dinilai (approved/success)
- ❌ `failed`: Planning yang gagal/rejected
- ❌ `expired`: Planning yang melewati batas waktu

### 2. Logika getPlanningStatus (Frontend)

Frontend menggunakan **computed status** yang lebih kompleks di `Planning/Index.vue`:

```javascript
const getPlanningStatus = (customer) => {
    const plan = customer.latest_plan;
    
    // Status Calculations:
    // 1. 'none' - Tidak ada planning (Gray)
    // 2. 'no_plan_warning' - Customer lama tidak ada plan (Blinking Red)
    // 3. 'created' - Planning dibuat, belum lapor (Red)
    // 4. 'reported' - Sudah lapor, masih dalam threshold (Green)
    // 5. 'warning' - Sudah lapor TAPI lewat threshold (Blinking Red/Yellow)
    // 6. 'expired' - Belum lapor, sudah lewat expiry (Red)
    
    // LOGIKA:
    // - Cek apakah plan ada
    // - Cek apakah sudah ada report
    // - Hitung waktu dari created_at
    // - Bandingkan dengan expiry_value dan warning_threshold
    // - Return status sesuai kondisi
};
```

### 3. Logika getControlStatus (Manager)

```javascript
const getControlStatus = (customer) => {
    const plan = customer.latest_plan;
    const pStatus = getPlanningStatus(customer);
    
    // RULES:
    // 1. Jika manager sudah action (bukan pending) → gunakan status itu
    // 2. Jika planning bermasalah (none/expired/warning) → auto 'rejected'
    // 3. Default → 'pending'
    
    // MASALAH: Manager bisa approve plan yang expired!
};
```

### 4. Logika getMonitoringStatus (BOD)

```javascript
const getMonitoringStatus = (customer) => {
    // RULES:
    // 1. Jika planning bermasalah → auto 'failed'
    // 2. Jika manager reject → auto 'failed'
    // 3. Jika reported → 'pending' atau status BOD
    // 4. Default → 'pending'
};
```

---

## 🔄 ANALISA WORKFLOW PLANNING

### Current Workflow (Yang Berjalan Saat Ini)

```
┌─────────────────────────────────────────────────────────────────┐
│  FASE 1: PEMBUATAN PLANNING (User/Staff)                        │
└─────────────────────────────────────────────────────────────────┘
    ↓
[JUMAT] User membuat Planning
    • planning_date = tanggal target (Senin-Jumat)
    • status = 'created'
    • manager_status = 'pending'
    • bod_status = 'pending'
    • created_at = timestamp pembuatan ← PENTING untuk expiry!
    ↓
┌─────────────────────────────────────────────────────────────────┐
│  FASE 2: EKSEKUSI & LAPORAN (Senin-Jumat)                       │
└─────────────────────────────────────────────────────────────────┘
    ↓
[SENIN-JUMAT] User melaksanakan planning
    ↓
User membuat Report:
    • execution_date
    • location, pic, position
    • result_description
    • progress
    • is_success
    ↓
System update:
    • plan.status = 'reported' ← STATUS BERUBAH
    • plan.updated_at = timestamp report
    ↓
┌─────────────────────────────────────────────────────────────────┐
│  FASE 3: PENILAIAN MANAGER (Control)                            │
└─────────────────────────────────────────────────────────────────┘
    ↓
Manager review planning:
    • Approve → manager_status = 'approved'
    • Reject → manager_status = 'rejected'
    • Escalate → manager_status = 'escalated'
    ↓
┌─────────────────────────────────────────────────────────────────┐
│  FASE 4: MONITORING BOD (Final Review)                          │
└─────────────────────────────────────────────────────────────────┘
    ↓
BOD review:
    • Success → bod_status = 'success'
    • Failed → bod_status = 'failed'
    ↓
[SELESAI]
```

### ⏱️ Time Management Calculations

**1. Expiry Check (Strict Lockdown):**
```javascript
// Menggunakan created_at untuk presisi waktu
const planDate = new Date(plan.created_at).getTime();
const now = new Date().getTime();
const diffMs = now - planDate;

// Convert berdasarkan unit (Minutes/Hours/Days)
let diffExpiry = 0;
if (expiryUnit === 'Hours') diffExpiry = diffMs / (1000 * 60 * 60);
else if (expiryUnit === 'Minutes') diffExpiry = diffMs / (1000 * 60);
else diffExpiry = diffMs / (1000 * 60 * 60 * 24);

const isStrictExpired = diffExpiry >= expiryValue; // Misal: 5 menit
```

**2. Warning Check (Reminder):**
```javascript
// Menggunakan planning_date atau updated_at
const diffWarning = getDiff(plan.planning_date);
const isWarning = diffWarning >= warningThreshold; // Misal: 14 menit
```

---

## 🚦 STATUS INDICATOR SYSTEM

### Indikator Badge Planning (PLAN)

| Status | Warna | Animasi | Kondisi |
|--------|-------|---------|---------|
| `none` | Abu-abu (Gray) | Static | Belum ada planning |
| `no_plan_warning` | Merah (Red) | **Blinking** | Customer lama tidak ada plan (> threshold) |
| `created` | Merah (Red) | Static | Planning dibuat, belum ada report |
| `reported` | Hijau (Green) | Static | Report sudah dibuat, masih dalam waktu |
| `warning` | Merah/Kuning | **Blinking** | Sudah lapor TAPI lewat threshold |
| `expired` | Merah (Red) | Static | Belum lapor, sudah lewat expiry |

### Indikator Control (CTRL - Manager)

| Status | Warna | Keterangan |
|--------|-------|------------|
| `pending` | Abu-abu (Gray) | Menunggu penilaian manager |
| `approved` | Hijau (Green) | Manager approve |
| `rejected` | Merah (Red) | Manager reject |
| `escalated` | Kuning/Orange | Manager escalate untuk diskusi |
| `auto_rejected` | Merah (Red) | Otomatis reject karena planning bermasalah |

### Indikator Monitoring (MON - BOD)

| Status | Warna | Keterangan |
|--------|-------|------------|
| `pending` | Kuning (Yellow) | Menunggu review BOD |
| `success` | Hijau (Green) | BOD approve sebagai sukses |
| `failed` | Merah (Red) | BOD tandai sebagai gagal |
| `auto_failed` | Merah (Red) | Otomatis fail karena control reject |

---

## ⚠️ PERMASALAHAN YANG DIIDENTIFIKASI

### 🔴 Problem #1: Tidak Ada Status "COMPLETED" atau "CLOSED"

**MASALAH:**
Setelah planning dinilai oleh Manager dan BOD, planning masih memiliki status `reported`. Tidak ada cara untuk membedakan:
- ✅ Planning yang **sudah selesai dinilai** (approved + success)
- ⏳ Planning yang **masih dalam proses penilaian**

**DAMPAK:**
1. Planning yang sudah "selesai" masih muncul di daftar aktif
2. Sulit membuat laporan planning yang completed vs in-progress
3. Dashboard tidak bisa menghitung dengan akurat berapa planning yang benar-benar selesai
4. History tidak jelas antara yang masih berjalan vs yang sudah final

**CONTOH KASUS:**
```
Planning A:
- status: 'reported'
- manager_status: 'approved'
- bod_status: 'success'
→ Apakah ini "completed"? (YA, tapi tidak ada status eksplisit)

Planning B:
- status: 'reported'
- manager_status: 'pending'
- bod_status: 'pending'
→ Apakah ini "in progress"? (YA, tapi sama-sama 'reported')
```

### 🔴 Problem #2: Ambiguitas Waktu untuk Validasi

**MASALAH:**
Ada 3 timestamp yang digunakan untuk perhitungan berbeda:
1. `plan.created_at` - Untuk expiry check (strict)
2. `plan.planning_date` - Untuk warning check
3. `plan.updated_at` - Tidak digunakan secara konsisten

**DAMPAK:**
- Inkonsistensi perhitungan waktu
- Sulit tracking kapan planning benar-benar "aktif"
- Warning bisa muncul tidak akurat jika menggunakan planning_date yang hanya berisi tanggal (bukan waktu presisi)

### 🔴 Problem #3: Tidak Ada "Lifecycle State"

**MASALAH:**
Sistem tidak memiliki konsep **lifecycle state** yang jelas untuk membedakan fase-fase planning:

```
YANG DIBUTUHKAN:
1. DRAFT      → Planning dibuat tapi belum disubmit
2. ACTIVE     → Planning aktif, sedang dikerjakan
3. REPORTED   → Sudah dilaporkan, menunggu review
4. REVIEWED   → Manager sudah review
5. COMPLETED  → BOD sudah final review (success)
6. FAILED     → Planning gagal/rejected
7. EXPIRED    → Planning kadaluarsa tanpa laporan
```

**SAAT INI:**
Hanya ada: `created` dan `reported`

### 🔴 Problem #4: Manager/BOD Bisa Mengubah Status Plan Expired

**MASALAH:**
Meskipun frontend menampilkan badge "EXPIRED", Manager dan BOD masih bisa:
- Approve planning yang sudah expired
- Mark as success planning yang sudah kadaluarsa

**CONTOH:**
```php
// Tidak ada validasi di backend untuk cek expiry
public function updateControl(Request $request, Plan $plan)
{
    // ❌ TIDAK ADA CEK: Apakah plan sudah expired?
    $plan->update(['manager_status' => $request->manager_status]);
}
```

**SEHARUSNYA:**
```php
public function updateControl(Request $request, Plan $plan)
{
    // ✅ CEK EXPIRY DULU
    if ($this->isPlanExpired($plan)) {
        return back()->with('error', 'Cannot update expired plan.');
    }
    
    $plan->update(['manager_status' => $request->manager_status]);
}
```

### 🔴 Problem #5: Tidak Ada Automated Status Transition

**MASALAH:**
Status tidak berpindah secara otomatis berdasarkan kondisi. Semuanya manual.

**CONTOH:**
- Planning expired → Status masih `created`, tidak auto jadi `expired`
- Manager approve + BOD success → Status masih `reported`, tidak auto jadi `completed`

**IDEALNYA:**
```php
// Cron job atau observer yang auto-update status
if (isPlanExpired($plan) && $plan->status === 'created') {
    $plan->update(['status' => 'expired']);
}

if ($plan->manager_status === 'approved' && $plan->bod_status === 'success') {
    $plan->update(['status' => 'completed']);
}
```

---

## ✅ REKOMENDASI SOLUSI PROFESIONAL

### 🎯 Solusi #1: Tambah Status Lifecycle yang Lengkap

**REKOMENDASI:**
Ubah enum `status` di database dan tambahkan state yang lebih komprehensif:

```php
// Migration
$table->enum('status', [
    'draft',        // Planning dibuat tapi belum final
    'active',       // Planning submitted, sedang berjalan
    'reported',     // Report sudah dibuat, menunggu review
    'under_review', // Manager sedang review
    'completed',    // Approved + Success (FINAL STATE)
    'failed',       // Rejected atau Failed (FINAL STATE)
    'expired'       // Kadaluarsa tanpa report (FINAL STATE)
])->default('draft');
```

**MANFAAT:**
- ✅ Jelas membedakan planning yang masih aktif vs sudah selesai
- ✅ Mudah filter di query: `where('status', 'completed')`
- ✅ Dashboard bisa hitung planning completed dengan akurat
- ✅ History lebih terstruktur

### 🎯 Solusi #2: Implementasi State Machine

**REKOMENDASI:**
Gunakan package seperti `spatie/laravel-model-states` atau buat custom state machine.

```php
// app/States/PlanState.php
abstract class PlanState extends State
{
    abstract public function canTransitionTo(PlanState $state): bool;
}

class Draft extends PlanState 
{
    public function canTransitionTo(PlanState $state): bool {
        return $state instanceof Active;
    }
}

class Active extends PlanState 
{
    public function canTransitionTo(PlanState $state): bool {
        return $state instanceof Reported || $state instanceof Expired;
    }
}

class Reported extends PlanState 
{
    public function canTransitionTo(PlanState $state): bool {
        return $state instanceof UnderReview || $state instanceof Failed;
    }
}

class Completed extends PlanState 
{
    public function canTransitionTo(PlanState $state): bool {
        return false; // Final state
    }
}
```

**MANFAAT:**
- ✅ Transisi status terkontrol dan tidak bisa sembarangan
- ✅ Validasi otomatis sebelum perubahan status
- ✅ Audit trail yang jelas
- ✅ Business rule enforcement

### 🎯 Solusi #3: Automated Status Transition dengan Observer

**REKOMENDASI:**
Buat Observer untuk auto-update status berdasarkan kondisi:

```php
// app/Observers/PlanObserver.php
class PlanObserver
{
    public function updating(Plan $plan)
    {
        // Auto-transition: Report created → status jadi 'reported'
        if ($plan->hasReport() && $plan->status === 'active') {
            $plan->status = 'reported';
        }
        
        // Auto-transition: Manager approve + BOD success → 'completed'
        if ($plan->manager_status === 'approved' && 
            $plan->bod_status === 'success' && 
            $plan->status === 'reported') {
            $plan->status = 'completed';
        }
        
        // Auto-transition: Manager/BOD reject → 'failed'
        if (($plan->manager_status === 'rejected' || $plan->bod_status === 'failed') && 
            $plan->status !== 'failed') {
            $plan->status = 'failed';
        }
    }
}
```

**MANFAAT:**
- ✅ Status selalu sinkron dengan kondisi aktual
- ✅ Tidak perlu manual update di berbagai tempat
- ✅ Konsistensi data terjamin
- ✅ Mengurangi human error

### 🎯 Solusi #4: Validasi Backend untuk Expiry

**REKOMENDASI:**
Tambahkan helper method dan validasi di controller:

```php
// app/Models/Plan.php
class Plan extends Model
{
    /**
     * Check if plan is expired
     */
    public function isExpired(): bool
    {
        $timeSettings = TimeSetting::first();
        $expiryValue = $timeSettings->plan_expiry_value ?? 7;
        $expiryUnit = $timeSettings->plan_expiry_unit ?? 'Days (Production)';
        
        $createdAt = $this->created_at;
        $now = now();
        
        $diff = match($expiryUnit) {
            'Minutes' => $now->diffInMinutes($createdAt),
            'Hours' => $now->diffInHours($createdAt),
            default => $now->diffInDays($createdAt),
        };
        
        return $diff >= $expiryValue;
    }
    
    /**
     * Check if plan can be reviewed
     */
    public function canBeReviewed(): bool
    {
        // Tidak bisa review jika:
        // 1. Belum ada report
        // 2. Sudah expired
        // 3. Sudah completed/failed
        
        if (!$this->report) return false;
        if ($this->isExpired() && $this->status === 'active') return false;
        if (in_array($this->status, ['completed', 'failed', 'expired'])) return false;
        
        return true;
    }
}

// app/Http/Controllers/PlanningController.php
public function updateControl(Request $request, Plan $plan)
{
    // ✅ VALIDASI
    if (!$plan->canBeReviewed()) {
        return back()->with('error', 'This plan cannot be reviewed.');
    }
    
    // ... rest of the code
}
```

**MANFAAT:**
- ✅ Mencegah approve/reject planning yang tidak valid
- ✅ Business rule enforcement di backend
- ✅ Konsistensi antara UI dan backend
- ✅ API endpoint juga terlindungi

### 🎯 Solusi #5: Scheduled Job untuk Auto-Expire

**REKOMENDASI:**
Buat scheduled job yang berjalan setiap menit untuk mark planning yang expired:

```php
// app/Console/Commands/ExpirePlans.php
class ExpirePlans extends Command
{
    protected $signature = 'plans:expire';
    protected $description = 'Auto-expire plans that passed the deadline';
    
    public function handle()
    {
        $timeSettings = TimeSetting::first();
        $expiryValue = $timeSettings->plan_expiry_value ?? 7;
        $expiryUnit = $timeSettings->plan_expiry_unit ?? 'Days (Production)';
        
        // Get all active plans without report
        $plans = Plan::where('status', 'active')
            ->whereDoesntHave('report')
            ->get();
        
        foreach ($plans as $plan) {
            if ($plan->isExpired()) {
                $plan->update([
                    'status' => 'expired',
                    'manager_status' => 'rejected',
                    'bod_status' => 'failed'
                ]);
                
                // Optional: Send notification to user
                // $plan->user->notify(new PlanExpiredNotification($plan));
            }
        }
        
        $this->info("Expired {$plans->count()} plans.");
    }
}

// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('plans:expire')->everyMinute();
}
```

**MANFAAT:**
- ✅ Planning expired otomatis tertandai
- ✅ User langsung mendapat notifikasi
- ✅ Data selalu up-to-date
- ✅ Tidak perlu manual check

### 🎯 Solusi #6: Dashboard Status Separation

**REKOMENDASI:**
Pisahkan view untuk planning aktif vs history:

```php
// Controller method untuk active plans only
public function activeIndex()
{
    $plans = Plan::whereNotIn('status', ['completed', 'failed', 'expired'])
        ->with(['user', 'customer', 'product', 'report'])
        ->latest()
        ->paginate(20);
    
    return Inertia::render('Planning/ActiveIndex', compact('plans'));
}

// Controller method untuk history
public function historyIndex()
{
    $plans = Plan::whereIn('status', ['completed', 'failed', 'expired'])
        ->with(['user', 'customer', 'product', 'report'])
        ->latest('updated_at')
        ->paginate(20);
    
    return Inertia::render('Planning/HistoryIndex', compact('plans'));
}
```

**MANFAAT:**
- ✅ User fokus ke planning aktif
- ✅ History terpisah dan mudah diaudit
- ✅ Performance lebih baik (query lebih spesifik)
- ✅ UX lebih clean

---

## 🔄 WORKFLOW YANG DISARANKAN

### Workflow Baru dengan Lifecycle State

```
┌─────────────────────────────────────────────────────────────────┐
│  FASE 1: PEMBUATAN PLANNING                                     │
│  Status: DRAFT → ACTIVE                                         │
└─────────────────────────────────────────────────────────────────┘
    ↓
[JUMAT] User mulai buat planning
    • status = 'draft' (auto-save)
    • manager_status = 'pending'
    • bod_status = 'pending'
    ↓
User submit planning (final)
    • status = 'active' ← PLANNING AKTIF
    • created_at = timestamp submit
    • Mulai hitung waktu expiry dari sini
    ↓
┌─────────────────────────────────────────────────────────────────┐
│  FASE 2: EKSEKUSI & LAPORAN                                     │
│  Status: ACTIVE → REPORTED                                      │
└─────────────────────────────────────────────────────────────────┘
    ↓
[SENIN-JUMAT] User melaksanakan planning
    ↓
User buat Report:
    • execution_date
    • result_description
    • ...
    ↓
System auto-update (via Observer):
    • status = 'reported' ← AUTO TRANSITION
    • updated_at = timestamp report
    ↓
┌─────────────────────────────────────────────────────────────────┐
│  FASE 3: REVIEW MANAGER                                         │
│  Status: REPORTED → UNDER_REVIEW                                │
└─────────────────────────────────────────────────────────────────┘
    ↓
Manager klik salah satu action:
    • Approve
    • Reject
    • Escalate
    ↓
IF Manager APPROVE:
    • manager_status = 'approved'
    • status = 'under_review' ← MENUNGGU BOD
    ↓
IF Manager REJECT:
    • manager_status = 'rejected'
    • status = 'failed' ← LANGSUNG FAILED (AUTO)
    • bod_status = 'failed' (auto-set)
    • WORKFLOW SELESAI ❌
    ↓
┌─────────────────────────────────────────────────────────────────┐
│  FASE 4: MONITORING BOD                                         │
│  Status: UNDER_REVIEW → COMPLETED/FAILED                        │
└─────────────────────────────────────────────────────────────────┘
    ↓
BOD review (hanya jika manager approved):
    ↓
IF BOD SUCCESS:
    • bod_status = 'success'
    • status = 'completed' ← SELESAI ✅
    • WORKFLOW SELESAI ✅
    ↓
IF BOD FAILED:
    • bod_status = 'failed'
    • status = 'failed' ← GAGAL ❌
    • WORKFLOW SELESAI ❌
    ↓
┌─────────────────────────────────────────────────────────────────┐
│  BACKGROUND PROCESS: AUTO-EXPIRE                                │
│  Cron Job: Setiap Menit                                         │
└─────────────────────────────────────────────────────────────────┐
    ↓
Check semua planning dengan status 'active':
    IF (now - created_at) >= expiry_threshold:
        • status = 'expired' ← AUTO EXPIRED
        • manager_status = 'rejected' (auto)
        • bod_status = 'failed' (auto)
        • Send notification ke user
        • WORKFLOW SELESAI ⏱️
```

### State Transition Rules

```
ALLOWED TRANSITIONS:
draft → active
active → reported | expired
reported → under_review | failed
under_review → completed | failed
completed → [FINAL STATE]
failed → [FINAL STATE]
expired → [FINAL STATE]

BLOCKED TRANSITIONS:
completed → any (sudah final)
failed → any (sudah final)
expired → any (sudah final)
```

---

## 🛠️ IMPLEMENTASI YANG DIREKOMENDASIKAN

### Step 1: Update Database Schema

```php
// database/migrations/XXXX_update_plans_table_add_lifecycle_status.php
public function up()
{
    Schema::table('plans', function (Blueprint $table) {
        // Ubah enum status
        $table->enum('status', [
            'draft',
            'active',
            'reported',
            'under_review',
            'completed',
            'failed',
            'expired'
        ])->default('draft')->change();
        
        // Tambah field helper
        $table->timestamp('submitted_at')->nullable()->after('planning_date');
        $table->timestamp('reported_at')->nullable()->after('submitted_at');
        $table->timestamp('reviewed_at')->nullable()->after('reported_at');
        $table->timestamp('expired_at')->nullable()->after('reviewed_at');
    });
}
```

### Step 2: Update Model dengan Helper Methods

```php
// app/Models/Plan.php
class Plan extends Model
{
    // ... existing code
    
    /**
     * Scopes untuk filtering berdasarkan lifecycle
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['completed', 'failed', 'expired']);
    }
    
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
    
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'expired']);
    }
    
    public function scopePendingReview($query)
    {
        return $query->whereIn('status', ['reported', 'under_review']);
    }
    
    /**
     * Helper methods
     */
    public function isActive(): bool
    {
        return !in_array($this->status, ['completed', 'failed', 'expired']);
    }
    
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
    
    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'expired']);
    }
    
    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'active']);
    }
    
    public function canBeReported(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }
    
    public function canBeReviewed(): bool
    {
        return in_array($this->status, ['reported', 'under_review']) && $this->report;
    }
    
    /**
     * Status transition methods
     */
    public function submit()
    {
        if ($this->status !== 'draft') {
            throw new \Exception('Only draft plans can be submitted');
        }
        
        $this->update([
            'status' => 'active',
            'submitted_at' => now()
        ]);
    }
    
    public function markAsReported()
    {
        if ($this->status !== 'active') {
            throw new \Exception('Only active plans can be reported');
        }
        
        $this->update([
            'status' => 'reported',
            'reported_at' => now()
        ]);
    }
    
    public function markAsUnderReview()
    {
        if ($this->status !== 'reported') {
            throw new \Exception('Only reported plans can be reviewed');
        }
        
        $this->update([
            'status' => 'under_review',
            'reviewed_at' => now()
        ]);
    }
    
    public function markAsCompleted()
    {
        if ($this->manager_status !== 'approved' || $this->bod_status !== 'success') {
            throw new \Exception('Plan must be approved by manager and BOD');
        }
        
        $this->update(['status' => 'completed']);
    }
    
    public function markAsFailed()
    {
        $this->update(['status' => 'failed']);
    }
    
    public function markAsExpired()
    {
        if (!$this->isExpired()) {
            throw new \Exception('Plan is not expired yet');
        }
        
        $this->update([
            'status' => 'expired',
            'expired_at' => now(),
            'manager_status' => 'rejected',
            'bod_status' => 'failed'
        ]);
    }
}
```

### Step 3: Buat Observer

```php
// app/Observers/PlanObserver.php
class PlanObserver
{
    /**
     * Handle plan updating event
     */
    public function updating(Plan $plan)
    {
        // Auto-transition: Jika ada report → status jadi 'reported'
        if ($plan->isDirty('status') === false) { // Jika status tidak diubah manual
            if ($plan->report && $plan->status === 'active') {
                $plan->status = 'reported';
                $plan->reported_at = now();
            }
        }
        
        // Auto-transition: Manager approve → under_review
        if ($plan->isDirty('manager_status') && $plan->manager_status === 'approved') {
            if ($plan->status === 'reported') {
                $plan->status = 'under_review';
                $plan->reviewed_at = now();
            }
        }
        
        // Auto-transition: Manager reject → failed
        if ($plan->isDirty('manager_status') && $plan->manager_status === 'rejected') {
            $plan->status = 'failed';
            $plan->bod_status = 'failed';
        }
        
        // Auto-transition: BOD success + Manager approved → completed
        if ($plan->isDirty('bod_status') && $plan->bod_status === 'success') {
            if ($plan->manager_status === 'approved') {
                $plan->status = 'completed';
            }
        }
        
        // Auto-transition: BOD failed → failed
        if ($plan->isDirty('bod_status') && $plan->bod_status === 'failed') {
            $plan->status = 'failed';
        }
    }
}

// app/Providers/EventServiceProvider.php
protected $observers = [
    Plan::class => [PlanObserver::class],
];
```

### Step 4: Update Controller dengan Validasi

```php
// app/Http/Controllers/PlanningController.php
public function updateControl(Request $request, Plan $plan)
{
    // ✅ VALIDASI: Cek apakah plan bisa di-review
    if (!$plan->canBeReviewed()) {
        return back()->with('error', 'This plan cannot be reviewed at this time.');
    }
    
    // ✅ VALIDASI: Cek expiry
    if ($plan->isExpired() && $plan->status === 'active') {
        return back()->with('error', 'This plan has expired and cannot be reviewed.');
    }
    
    // ... rest of validation and update logic
}

public function updateMonitoring(Request $request, Plan $plan)
{
    // ✅ VALIDASI: BOD hanya bisa review jika manager sudah approve
    if ($plan->manager_status !== 'approved') {
        return back()->with('error', 'Manager must approve this plan first.');
    }
    
    // ✅ VALIDASI: Cek apakah plan bisa di-review
    if (!$plan->canBeReviewed()) {
        return back()->with('error', 'This plan cannot be reviewed at this time.');
    }
    
    // ... rest of validation and update logic
}
```

### Step 5: Buat Command untuk Auto-Expire

```php
// app/Console/Commands/ExpirePlans.php
namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\TimeSetting;
use Illuminate\Console\Command;

class ExpirePlans extends Command
{
    protected $signature = 'plans:expire';
    protected $description = 'Automatically expire plans that passed the deadline';

    public function handle()
    {
        $timeSettings = TimeSetting::first();
        if (!$timeSettings) {
            $this->error('Time settings not found.');
            return;
        }

        // Get all active plans (belum reported, belum expired)
        $plans = Plan::where('status', 'active')
            ->whereDoesntHave('report')
            ->get();

        $expiredCount = 0;

        foreach ($plans as $plan) {
            if ($plan->isExpired()) {
                try {
                    $plan->markAsExpired();
                    $expiredCount++;
                    
                    // Optional: Send notification
                    // $plan->user->notify(new PlanExpiredNotification($plan));
                    
                    $this->info("Plan #{$plan->id} marked as expired.");
                } catch (\Exception $e) {
                    $this->error("Failed to expire plan #{$plan->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("Total {$expiredCount} plans expired.");
    }
}

// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Jalankan setiap menit (atau sesuai kebutuhan)
    $schedule->command('plans:expire')->everyMinute();
    
    // Atau setiap 5 menit untuk mengurangi beban:
    // $schedule->command('plans:expire')->everyFiveMinutes();
}
```

### Step 6: Update Frontend untuk Menampilkan Status Baru

```vue
<!-- Planning/Index.vue -->
<script setup>
const getStatusBadge = (plan) => {
    const statusConfig = {
        'draft': {
            label: 'DRAFT',
            class: 'bg-slate-100 text-slate-700 border-slate-200',
            icon: 'pencil'
        },
        'active': {
            label: 'ACTIVE',
            class: 'bg-blue-100 text-blue-700 border-blue-200',
            icon: 'play'
        },
        'reported': {
            label: 'REPORTED',
            class: 'bg-purple-100 text-purple-700 border-purple-200',
            icon: 'document-check'
        },
        'under_review': {
            label: 'UNDER REVIEW',
            class: 'bg-amber-100 text-amber-700 border-amber-200',
            icon: 'eye'
        },
        'completed': {
            label: 'COMPLETED',
            class: 'bg-emerald-100 text-emerald-700 border-emerald-200',
            icon: 'check-circle'
        },
        'failed': {
            label: 'FAILED',
            class: 'bg-red-100 text-red-700 border-red-200',
            icon: 'x-circle'
        },
        'expired': {
            label: 'EXPIRED',
            class: 'bg-gray-100 text-gray-700 border-gray-200',
            icon: 'clock'
        }
    };
    
    return statusConfig[plan.status] || statusConfig['draft'];
};

const canPerformAction = (plan, action) => {
    // Logic untuk determine apakah action bisa dilakukan
    switch(action) {
        case 'edit':
            return ['draft', 'active'].includes(plan.status);
        case 'report':
            return plan.status === 'active' && !plan.report;
        case 'manager_review':
            return ['reported', 'under_review'].includes(plan.status);
        case 'bod_review':
            return plan.status === 'under_review' && plan.manager_status === 'approved';
        default:
            return false;
    }
};
</script>

<template>
    <!-- Badge Status Baru -->
    <span :class="[
        'px-3 py-1 rounded-full text-xs font-semibold border',
        getStatusBadge(plan).class
    ]">
        {{ getStatusBadge(plan).label }}
    </span>
    
    <!-- Conditional Actions -->
    <button 
        v-if="canPerformAction(plan, 'edit')"
        @click="editPlan(plan)"
        class="..."
    >
        Edit Plan
    </button>
    
    <button 
        v-if="canPerformAction(plan, 'report')"
        @click="createReport(plan)"
        class="..."
    >
        Create Report
    </button>
</template>
```

---

## 📊 PERBANDINGAN: SEBELUM vs SESUDAH

### SEBELUM (Current System)

```
Status Planning:
- created
- reported

Masalah:
❌ Tidak jelas mana yang sudah dinilai
❌ Planning expired masih bisa di-review
❌ Tidak ada automated process
❌ Sulit membedakan aktif vs selesai
❌ Dashboard tidak akurat
```

### SESUDAH (Recommended System)

```
Status Planning:
- draft
- active
- reported
- under_review
- completed ✅
- failed ✅
- expired ✅

Keunggulan:
✅ Jelas state lifecycle setiap planning
✅ Automated expiry dengan cron job
✅ Validasi ketat di backend
✅ State transition terkontrol
✅ Dashboard akurat dan terpisah (Active vs History)
✅ Audit trail lengkap
✅ Business rules enforced
```

---

## 📈 RENCANA IMPLEMENTASI BERTAHAP

### Phase 1: Database & Model (Week 1)
- [ ] Update migration untuk enum status baru
- [ ] Tambah helper timestamps (submitted_at, reported_at, dll)
- [ ] Buat helper methods di Plan model
- [ ] Buat unit tests untuk helper methods

### Phase 2: Business Logic & Validation (Week 2)
- [ ] Buat PlanObserver untuk auto-transition
- [ ] Update controller dengan validasi backend
- [ ] Buat ExpirePlans command
- [ ] Setup cron job untuk auto-expire
- [ ] Buat integration tests

### Phase 3: Frontend Update (Week 3)
- [ ] Update badge indicators di Planning/Index.vue
- [ ] Pisahkan view: Active Plans vs History
- [ ] Update action buttons dengan conditional logic
- [ ] Update PlanningReport/Index.vue
- [ ] Testing UI/UX

### Phase 4: Documentation & Training (Week 4)
- [ ] Dokumentasi API endpoints
- [ ] User manual untuk fitur baru
- [ ] Training untuk user tentang lifecycle baru
- [ ] Monitoring & bug fixing

---

## 🎓 KESIMPULAN

### Yang Sudah Baik:
1. ✅ Struktur database sudah solid dengan relasi yang jelas
2. ✅ Time management system sudah fleksibel (Minutes/Hours/Days)
3. ✅ Role-based access control sudah implementasi
4. ✅ Audit trail dengan PlanStatusLog sudah ada
5. ✅ Frontend indicator (badge) sudah informatif

### Yang Perlu Diperbaiki:
1. ❌ Status lifecycle tidak lengkap (hanya 2 status)
2. ❌ Tidak ada automated status transition
3. ❌ Backend validation untuk expiry tidak ada
4. ❌ Tidak bisa distinguish planning completed vs in-progress
5. ❌ Tidak ada background job untuk auto-expire

### Rekomendasi Utama:
1. **PRIORITAS TINGGI:** Tambah status lifecycle lengkap (draft, active, reported, under_review, completed, failed, expired)
2. **PRIORITAS TINGGI:** Implementasi Observer untuk auto-transition status
3. **PRIORITAS SEDANG:** Buat command untuk auto-expire planning
4. **PRIORITAS SEDANG:** Tambah validasi backend untuk prevent review expired plans
5. **PRIORITAS RENDAH:** Pisahkan view Active vs History

### Cara Membedakan Planning yang Sudah Dinilai vs Masih Berjalan:

**SOLUSI UTAMA:**
```sql
-- Planning yang SUDAH SELESAI dinilai (COMPLETED)
SELECT * FROM plans 
WHERE status = 'completed' 
  AND manager_status = 'approved' 
  AND bod_status = 'success';

-- Planning yang MASIH BERJALAN (IN PROGRESS)
SELECT * FROM plans 
WHERE status IN ('active', 'reported', 'under_review');

-- Planning yang GAGAL/EXPIRED
SELECT * FROM plans 
WHERE status IN ('failed', 'expired');
```

**IMPLEMENTASI DI FRONTEND:**
```vue
<template>
    <!-- Tab untuk Active Plans -->
    <Tab name="Active">
        <Plans :plans="activePlans" />
    </Tab>
    
    <!-- Tab untuk History (Completed + Failed) -->
    <Tab name="History">
        <Plans :plans="historyPlans" />
    </Tab>
</template>

<script setup>
const activePlans = computed(() => 
    props.plans.filter(p => 
        !['completed', 'failed', 'expired'].includes(p.status)
    )
);

const historyPlans = computed(() => 
    props.plans.filter(p => 
        ['completed', 'failed', 'expired'].includes(p.status)
    )
);
</script>
```

---

## 📞 NEXT STEPS

Untuk memulai implementasi, saya rekomendasikan:

1. **Review dan Approval:** Review dokumen analisa ini dengan tim management
2. **Prioritas:** Tentukan fitur mana yang paling urgent
3. **Planning:** Buat sprint plan untuk implementasi bertahap
4. **Development:** Mulai dari Phase 1 (Database & Model)
5. **Testing:** Extensive testing setelah setiap phase
6. **Deployment:** Deploy ke staging dulu, baru production

**Apakah ada bagian tertentu yang ingin didiskusikan lebih detail atau langsung mulai implementasi?**
