<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rename the stored alert rules to English. Matched by code; params,
 * enabled flags and counts are left untouched.
 */
return new class extends Migration
{
    private const EN = [
        'offline' => 'Meter offline',
        'error_code' => 'Meter error code',
        'signal_q' => 'Low signal quality',
        'weak_signal' => 'Weak upstream / downstream signal',
        'time_ratio' => 'Time ratio out of range',
        'backflow' => 'Reverse flow',
        'zero_flow' => 'No flow for too long',
        'incomplete_window' => 'Incomplete daily data',
        'clock_drift' => 'Meter clock is off',
    ];

    private const ID = [
        'offline' => 'Meter offline',
        'error_code' => 'Kode error meter',
        'signal_q' => 'Kualitas sinyal rendah',
        'weak_signal' => 'Sinyal hulu/hilir lemah',
        'time_ratio' => 'Time ratio di luar rentang',
        'backflow' => 'Aliran balik',
        'zero_flow' => 'Aliran nol terlalu lama',
        'incomplete_window' => 'Jendela data tidak lengkap',
        'clock_drift' => 'Jam meter melenceng',
    ];

    public function up(): void
    {
        foreach (self::EN as $code => $name) {
            DB::table('alert_rules')->where('code', $code)->update(['name' => $name]);
        }
    }

    public function down(): void
    {
        foreach (self::ID as $code => $name) {
            DB::table('alert_rules')->where('code', $code)->update(['name' => $name]);
        }
    }
};
