<?php

namespace Database\Seeders;

use App\Models\AlertRule;
use App\Support\AlertRuleCatalog;
use App\Models\Reading;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Akun operator (kata sandi awal, ganti nanti di Pengaturan)
        $user = User::where('username', 'admin')->first() ?? new User();
        $user->username = 'admin';
        $user->password = Hash::make('admin12345');
        $user->save();

        // Sembilan aturan alert
        $rules = [
            ['offline', AlertRuleCatalog::name('offline'), true, ['menit' => 5], 1],
            ['error_code', AlertRuleCatalog::name('error_code'), false, [], 1],
            ['signal_q', AlertRuleCatalog::name('signal_q'), true, ['batas' => 50], 6],
            ['weak_signal', AlertRuleCatalog::name('weak_signal'), false, ['batas' => null], 6],
            ['time_ratio', AlertRuleCatalog::name('time_ratio'), true, ['min' => 95, 'max' => 105], 6],
            ['backflow', AlertRuleCatalog::name('backflow'), true, ['neg_naik_m3' => 0.1], 1],
            ['zero_flow', AlertRuleCatalog::name('zero_flow'), true, ['menit' => 30], 1],
            ['incomplete_window', AlertRuleCatalog::name('incomplete_window'), true, [], 1],
            ['clock_drift', AlertRuleCatalog::name('clock_drift'), false, ['detik' => 60], 1],
        ];
        foreach ($rules as [$code, $name, $enabled, $params, $count]) {
            AlertRule::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'enabled' => $enabled, 'params' => $params, 'consecutive_count' => $count]
            );
        }

        // Pengaturan awal
        Setting::updateOrCreate(['key' => 'window_start'], ['value' => '09:00']);
        Setting::updateOrCreate(['key' => 'mqtt_mapping'], ['value' => new \stdClass()]);
        Setting::updateOrCreate(['key' => 'net_acc_check_enabled'], ['value' => false]);

        // Pembacaan CONTOH, satu per menit selama 4 hari terakhir (UTC)
        Reading::query()->delete();
        $now = now()->startOfMinute();
        $total = 308000.0;
        $rows = [];
        for ($t = $now->copy()->subDays(4); $t <= $now; $t->addMinute()) {
            $minute = $t->hour * 60 + $t->minute;
            $flow = 140 + 40 * sin(2 * M_PI * ($minute - 420) / 1440);
            $total += $flow / 60;
            $int = (int) floor($total);
            $rows[] = [
                'measured_at' => $t->format('Y-m-d H:i:s'),
                'flow_rate' => round($flow, 3),
                'velocity' => round($flow / 198.6, 3),
                'sound_speed' => 1480,
                'pos_int' => $int,
                'pos_frac' => round($total - $int, 6),
                'neg_int' => 1,
                'neg_frac' => 0.2,
                'signal_quality' => 87,
                'signal_up' => 70.8,
                'signal_down' => 68.5,
                'time_ratio' => 100.3,
            ];
            if (count($rows) === 500) {
                Reading::insert($rows);
                $rows = [];
            }
        }
        if ($rows) {
            Reading::insert($rows);
        }
    }
}
