<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'execution_date',
        'location',
        'pic',
        'position',
        'result_description',
        'next_plan_description',
        'progress',
        'is_success',
        'next_planning_date',
        'next_activity_type',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
