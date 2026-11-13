<?php

namespace App\Console\Commands;

use App\Support\AlertRuleCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class InitApp extends Command
{
    protected $signature = 'flowmon:init {--username=admin}';
    protected $description = 'Seed production data: user, alert rules, settings';

    public function handle(): int
    {
        $u = $this->option('username');

        if (DB::table('users')->where('username', $u)->exists()) {
            $this->info("User {$u} already exists, skipped.");
        } else {
            $p = (string) $this->secret("Password for {$u} (at least 10 characters)");
            if (strlen($p) < 10) {
                $this->error('Password is too short.');

                return self::FAILURE;
            }
            if ($p !== (string) $this->secret('Repeat password')) {
                $this->error('Passwords do not match.');

                return self::FAILURE;
            }
            $cols = Schema::getColumnListing('users');
            $row = ['username' => $u, 'password' => Hash::make($p)];
            if (in_array('name', $cols, true)) {
                $row['name'] = $u;
            }
            foreach (['created_at', 'updated_at'] as $c) {
                if (in_array($c, $cols, true)) {
                    $row[$c] = now();
                }
            }
            DB::table('users')->insert($row);
            $this->info("User {$u} created.");
        }

        $rules = [
            ['offline', AlertRuleCatalog::name('offline'), true, 1, ['menit' => 5]],
            ['error_code', AlertRuleCatalog::name('error_code'), false, 1, []],
            ['signal_q', AlertRuleCatalog::name('signal_q'), true, 6, ['batas' => 50]],
            ['weak_signal', AlertRuleCatalog::name('weak_signal'), false, 6, ['batas' => null]],
            ['time_ratio', AlertRuleCatalog::name('time_ratio'), true, 6, ['min' => 95, 'max' => 105]],
            ['backflow', AlertRuleCatalog::name('backflow'), true, 1, ['neg_naik_m3' => 0.1]],
            ['zero_flow', AlertRuleCatalog::name('zero_flow'), true, 1, ['menit' => 30]],
            ['incomplete_window', AlertRuleCatalog::name('incomplete_window'), true, 1, []],
            ['clock_drift', AlertRuleCatalog::name('clock_drift'), false, 1, ['detik' => 60]],
        ];
        foreach ($rules as [$code, $name, $on, $n, $params]) {
            DB::table('alert_rules')->insertOrIgnore([
                'code' => $code,
                'name' => $name,
                'enabled' => $on,
                'consecutive_count' => $n,
                'params' => json_encode((object) $params),
            ]);
        }

        foreach (['window_start' => '09:00', 'mqtt_mapping' => new \stdClass, 'net_acc_check_enabled' => false] as $k => $v) {
            DB::table('settings')->insertOrIgnore(['key' => $k, 'value' => json_encode($v)]);
        }

        $this->info('Initial data ready.');

        return self::SUCCESS;
    }
}
