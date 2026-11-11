<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reading extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['measured_at' => 'datetime'];
    }
}