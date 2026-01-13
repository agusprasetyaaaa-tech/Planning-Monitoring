<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanStatusLog extends Model
{
    protected $fillable = [
        'plan_id',
        'user_id',
        'field',
        'old_value',
        'new_value',
        'is_grace_period',
        'notes',
    ];

    protected $casts = [
        'is_grace_period' => 'boolean',
    ];

    /**
     * Grace period duration in minutes
     */
    const GRACE_PERIOD_MINUTES = 5;

    /**
     * Maximum changes allowed per plan (per field type)
     */
    const MAX_CHANGES_LIMIT = 3;

    // Relationships
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get logs that count toward the limit (excluding grace period reversals)
     */
    public function scopeCountable($query)
    {
        return $query->where('is_grace_period', false)
            ->whereIn('new_value', ['approved', 'rejected']);
    }

    /**
     * Check if a recent log entry can be undone (within grace period)
     */
    public static function canUndo(int $planId, string $field): bool
    {
        $lastLog = self::where('plan_id', $planId)
            ->where('field', $field)
            ->latest()
            ->first();

        if (!$lastLog) {
            return false;
        }

        $graceEndsAt = $lastLog->created_at->addMinutes(self::GRACE_PERIOD_MINUTES);
        return now()->lt($graceEndsAt);
    }

    /**
     * Get remaining grace period time in seconds
     */
    public static function getGraceTimeRemaining(int $planId, string $field): int
    {
        $lastLog = self::where('plan_id', $planId)
            ->where('field', $field)
            ->latest()
            ->first();

        if (!$lastLog) {
            return 0;
        }

        $graceEndsAt = $lastLog->created_at->addMinutes(self::GRACE_PERIOD_MINUTES);
        $remaining = now()->diffInSeconds($graceEndsAt, false);

        return max(0, $remaining);
    }

    /**
     * Get the count of changes made for a specific plan and field
     */
    public static function getChangeCount(int $planId, string $field): int
    {
        return self::where('plan_id', $planId)
            ->where('field', $field)
            ->countable()
            ->count();
    }

    /**
     * Get remaining changes allowed
     */
    public static function getRemainingChanges(int $planId, string $field): int
    {
        $used = self::getChangeCount($planId, $field);
        return max(0, self::MAX_CHANGES_LIMIT - $used);
    }

    /**
     * Check if more changes are allowed
     */
    public static function canMakeChange(int $planId, string $field): bool
    {
        return self::getRemainingChanges($planId, $field) > 0;
    }
}
