<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeSetting extends Model
{
    protected $fillable = [
        'time_offset_days',
        'planning_time_unit',
        'planning_warning_threshold',
        'plan_expiry_value',
        'plan_expiry_unit',
        'allowed_plan_creation_days',
        'testing_mode',
    ];

    protected $casts = [
        'allowed_plan_creation_days' => 'array',
        'testing_mode' => 'boolean',
        'time_offset_days' => 'integer',
    ];

    /**
     * Get current time with testing offset applied
     * Used for development/testing to simulate future dates
     */
    public static function testingNow()
    {
        $settings = self::first();
        $offset = $settings->time_offset_days ?? 0;

        return \Carbon\Carbon::now()->addDays($offset);
    }
}
