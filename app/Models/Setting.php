<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function read(string $key, mixed $default = null): mixed
    {
        $row = static::find($key);

        return $row ? $row->value : $default;
    }
}
