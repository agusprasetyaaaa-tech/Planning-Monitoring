<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanReschedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'user_id',
        'old_planning_date',
        'new_planning_date',
        'old_activity_type',
        'new_activity_type',
        'reason',
    ];

    protected $casts = [
        'old_planning_date' => 'date',
        'new_planning_date' => 'date',
    ];

    /**
     * Get the plan that owns the reschedule.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the user who rescheduled the plan.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
