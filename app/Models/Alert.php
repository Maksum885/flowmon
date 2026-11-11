<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'seen_at' => 'datetime',
            'detail' => 'array',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'rule_id');
    }

    public function window(): BelongsTo
    {
        return $this->belongsTo(UsageWindow::class, 'window_id');
    }
}
