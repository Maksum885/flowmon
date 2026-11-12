<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class InitApp extends Command
{
    protected $signature = 'flowmon:init {--username=admin}';
    protected $description = 'Isi data awal produksi: pengguna, aturan alert, pengaturan';

    public function handle(): int
    {
        $u = $this->option('username');

        if (DB::table('users')->where('username', $u)->exists()) {
            $this->info("Pengguna {$u} sudah ada, dilewati.");
        } else {
            $p = (string) $this->secret("Kata sandi untuk {$u} (min. 10 karakter)");
            if (strlen($p) < 10) {
                $this->error('Kata sandi terlalu pendek.');

                return self::FAILURE;
            }
            if ($p !== (string) $this->secret('Ulangi kata sandi')) {
                $this->error('Konfirmasi tidak sama.');

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
            $this->info("Pengguna {$u} dibuat.");
        }

        $rules = [
            ['offline', 'Meter offline', true, 1, ['menit' => 5]],
            ['error_code', 'Kode error meter', false, 1, []],
            ['signal_q', 'Kualitas sinyal rendah', true, 6, ['batas' => 50]],
            ['weak_signal', 'Sinyal hulu/hilir lemah', false, 6, ['batas' => null]],
            ['time_ratio', 'Time ratio di luar rentang', true, 6, ['min' => 95, 'max' => 105]],
            ['backflow', 'Aliran balik', true, 1, ['neg_naik_m3' => 0.1]],
            ['zero_flow', 'Aliran nol terlalu lama', true, 1, ['menit' => 30]],
            ['incomplete_window', 'Jendela data tidak lengkap', true, 1, []],
            ['clock_drift', 'Jam meter melenceng', false, 1, ['detik' => 60]],
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

        $this->info('Data awal siap.');

        return self::SUCCESS;
    }
}
