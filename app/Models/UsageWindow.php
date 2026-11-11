<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageWindow extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'window_start_at' => 'datetime',
            'window_end_at' => 'datetime',
            'max_gap_start_at' => 'datetime',
            'calculated_at' => 'datetime',
            'flags' => 'array',
        ];
    }
}
