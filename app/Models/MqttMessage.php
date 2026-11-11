<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MqttMessage extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
