<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecuritySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'rate_limit',
        'blocked_ips',
        'blocked_countries',
        'is_active',
    ];

    protected $casts = [
        'blocked_ips' => 'array',
        'blocked_countries' => 'array',
        'is_active' => 'boolean',
    ];
}
