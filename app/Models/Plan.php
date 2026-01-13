<?php

namespace App\Models;

use App\Models\TimeSetting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Models\Report;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'user_id',
        'product_id',
        'project_name',
        'planning_date',
        'activity_type',
        'description',
        'status',
        'manager_status',
        'bod_status',
        'lifecycle_status',
        'submitted_at',
        'manager_reviewed_at',
        'bod_reviewed_at',
        'expired_at',
    ];

    protected $casts = [
        'planning_date' => 'date',
        'manager_reviewed_at' => 'datetime',
        'bod_reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'manager_reviewed_at' => 'datetime',
        'bod_reviewed_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function report()
    {
        return $this->hasOne(Report::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(PlanStatusLog::class);
    }

    protected $appends = ['activity_code', 'is_history', 'lifecycle_status_label'];

    public function getIsHistoryAttribute()
    {
        return Plan::where('customer_id', $this->customer_id)
            ->where('product_id', $this->product_id)
            ->where('id', '>', $this->id)
            ->exists();
    }

    // --- LOCK MECHANISM & GRACE PERIOD LOGIC ---

    /**
     * Check if Manager can change control status
     */
    public function canManagerChange($user = null)
    {
        if ($user && $user->hasRole('Super Admin'))
            return true;

        // 1. HARD LOCK: Cannot change if BOD has finalized
        if ($this->bod_status === 'success' || $this->bod_status === 'failed') {
            return false;
        }

        // 2. Grace period check (7 days after first manager review)
        if ($this->manager_status !== 'pending' && $this->manager_reviewed_at) {
            $daysSince = Carbon::now()->diffInDays($this->manager_reviewed_at);
            if ($daysSince > 7)
                return false;
        }

        // 3. Allow change at any stage (Created/Reported/Expired)
        // Manager can Escalate active plans as reminder, or Reject reported plans.
        return true;
    }

    /**
     * Get remaining grace time for Manager (in seconds)
     */
    public function getManagerGraceRemaining()
    {
        if ($this->manager_status === 'pending' || !$this->manager_reviewed_at)
            return null;

        $expiryTime = $this->manager_reviewed_at->copy()->addDays(7);
        $remaining = Carbon::now()->diffInSeconds($expiryTime, false);

        return max(0, $remaining);
    }

    /**
     * Check if BOD can change monitoring status
     */
    public function canBODChange($user = null)
    {
        if ($user && $user->hasRole('Super Admin'))
            return true;

        // OLD LOGIC REMOVED: Cannot change if Manager hasn't approved
        // BOD sekarang bisa review SEMUA status Manager (Reject/Escalate/Approve)
        // Ini implementasi "BOD Action DIPERLUKAN" untuk checks & balances

        // Grace period check (5 minutes after finalizing)
        if ($this->bod_status === 'success' || $this->bod_status === 'failed') {
            // Check grace period from bod_reviewed_at or last status log 
            // We'll use bod_reviewed_at for simplicity as the 'finalization time'
            if (!$this->bod_reviewed_at)
                return true; // Should be set, but safety first

            $minutesSince = Carbon::now()->diffInMinutes($this->bod_reviewed_at);
            if ($minutesSince > 5)
                return false;
        }

        return true;
    }

    /**
     * Check if Plan is Expired
     */
    public function isExpired()
    {
        // Only makes sense if status is created (not reported yet)
        if ($this->status !== 'created')
            return false;

        $settings = TimeSetting::first();
        if (!$settings)
            return false; // Default safe

        $expiryValue = $settings->plan_expiry_value ?? 7;
        $expiryUnit = $settings->plan_expiry_unit ?? 'Days';

        $createdAt = $this->created_at;
        $now = Carbon::now();

        switch ($expiryUnit) {
            case 'Hours':
                return $now->diffInMinutes($createdAt) / 60 >= $expiryValue;
            case 'Minutes':
                return $now->diffInMinutes($createdAt) >= $expiryValue;
            default: // Days
                // Use minutes to convert to days for float precision (avoid integer rounding)
                // e.g., 6.9 days should be treated accurately vs integer 6
                return $now->diffInMinutes($createdAt) / (60 * 24) >= $expiryValue;
        }
    }

    /**
     * Determine Comprehensive Lifecycle Status
     */
    public function getLifecycleStatusLabelAttribute()
    {
        // 1. History (If it has been revised/replaced)
        if ($this->is_history)
            return 'History';

        // 2. Completed (Final Success)
        if ($this->manager_status === 'approved' && $this->bod_status === 'success') {
            return 'Completed';
        }

        // 3. REJECTED (Explicitly Rejected by Manager or Failed by BOD)
        // Added 'Rejected' status as requested - Mandatory for clarity
        if ($this->manager_status === 'rejected' || $this->bod_status === 'failed') {
            return 'Rejected';
        }

        // 4. LATE REPORT (Report submitted but overdue)
        // Added 'Late Report' status as requested
        if ($this->status === 'reported' && $this->report) {
            // Check if execution date is after planning date (simple day comparison)
            $planningDate = $this->planning_date->startOfDay();
            // Handle string or carbon for execution date just in case
            $execDate = $this->report->execution_date instanceof Carbon
                ? $this->report->execution_date->startOfDay()
                : Carbon::parse($this->report->execution_date)->startOfDay();

            if ($execDate->gt($planningDate)) {
                return 'Late Report';
            }
        }

        // 5. EXPIRED (No report, time limit exceeded)
        if ($this->status === 'created' && $this->isExpired()) {
            return 'Expired';
        }

        // 6. Under Review (Reported, but not finalized)
        if ($this->status === 'reported') {
            return 'Under Review';
        }

        // 7. Warning
        if ($this->isWarning()) {
            return 'Warning';
        }

        // 8. Active / On Track (Default 'created')
        return 'Created';
    }

    public function isWarning()
    {
        if ($this->status !== 'created')
            return false;

        $settings = TimeSetting::first();
        if (!$settings)
            return false;

        $expiryValue = $settings->plan_expiry_value ?? 7;
        $expiryUnit = $settings->plan_expiry_unit ?? 'Days';

        $createdAt = $this->created_at;
        $now = Carbon::now();

        // Warning at 80% usage of time
        $threshold = $expiryValue * 0.8;

        switch ($expiryUnit) {
            case 'Hours':
                return $now->diffInHours($createdAt) >= $threshold;
            case 'Minutes':
                return $now->diffInMinutes($createdAt) >= $threshold;
            default: // Days
                return $now->diffInDays($createdAt) >= $threshold;
        }
    }

    // --- ACTIVITY CODE LOGIC ---

    public function getActivityCodeAttribute()
    {
        $prefixMap = [
            'Call' => 'C',
            'Visit' => 'V',
            'Ent' => 'E',
            'Entertainment' => 'E',
            'Online Meeting' => 'OM',
            'Survey' => 'S',
            'Presentation' => 'PR',
            'Proposal' => 'PP',
            'Negotiation' => 'N',
            'Admin/Tender' => 'AT',
            'Tender' => 'AT',
            'Other' => 'O',
        ];

        $prefix = $prefixMap[$this->activity_type] ?? $this->generateDefaultPrefix($this->activity_type);

        // Fetch all plans of this type for this customer & product, ordered by creation (ID)
        // Optimization: Use whereIn to reduce queries if batch processing (future)
        $plans = Plan::where('customer_id', $this->customer_id)
            ->where('product_id', $this->product_id)
            ->where('activity_type', $this->activity_type)
            ->orderBy('id', 'asc')
            ->get();

        $counter = 1;
        $myCode = $prefix . $counter; // Default fallback

        // Iterate to simulate the sequence
        foreach ($plans as $index => $plan) {
            // Current Plan assigned the current counter value
            if ($plan->id == $this->id) {
                $myCode = $prefix . $counter;
            }

            // Logic to increment or hold counter
            if ($plan->status === 'reported') {
                // Success plan consumes the number
                $counter++;
            } else {
                // Status is 'created' (Pending/Expired)
                if ($index === count($plans) - 1) {
                    // Latest created plan consumes number
                    $counter++;
                }
            }
        }

        return $myCode;
    }

    private function generateDefaultPrefix($type)
    {
        $words = explode(' ', $type);
        if (count($words) > 1) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($type, 0, 2));
    }
}
