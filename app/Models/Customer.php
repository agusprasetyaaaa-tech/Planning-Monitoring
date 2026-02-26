<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['company_name', 'product_id', 'marketing_sales_id', 'planning_start_date'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function marketing()
    {
        return $this->belongsTo(User::class, 'marketing_sales_id');
    }
    public function plans()
    {
        return $this->hasMany(Plan::class);
    }

    public function latestPlan()
    {
        return $this->hasOne(Plan::class)->latestOfMany();
    }
}
