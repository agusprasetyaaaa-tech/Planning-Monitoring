<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\HasActivityCode;

class DailyReport extends Model
{
    use HasFactory, HasActivityCode;

    protected $appends = ['activity_code', 'is_daily_report'];

    public function getActivityCodeAttribute()
    {
        return $this->calculateActivityCode();
    }

    public function getIsDailyReportAttribute()
    {
        return true;
    }

    protected $fillable = [
        'user_id',
        'customer_id',
        'product_id',
        'report_date',
        'activity_type',
        'description',
        'location',
        'pic',
        'position',
        'result_description',
        'progress',
        'is_success',
        'next_plan',
    ];

    protected $casts = [
        'report_date' => 'date',
        'is_success' => 'boolean',
    ];

    /**
     * Get the user that created the daily report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the customer associated with the daily report.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the product associated with the daily report.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
