<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertRule extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'params' => 'array',
        ];
    }
}
