<?php

namespace App\Http\Controllers;

use App\Models\AlertRule;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PengaturanController extends Controller
{
    /** kode aturan => daftar [kunci parameter, label] */
    public const FIELDS = [
        'offline' => [['menit', 'Batas tanpa data (menit)']],
        'signal_q' => [['batas', 'Q minimum']],
        'weak_signal' => [['batas', 'Sinyal hulu/hilir minimum (kosong = tidak dipakai)']],
        'time_ratio' => [['min', 'Time ratio minimum (%)'], ['max', 'Time ratio maksimum (%)']],
        'backflow' => [['neg_naik_m3', 'Kenaikan NEG yang memicu (m³)']],
        'zero_flow' => [['menit', 'Aliran nol selama (menit)']],
        'clock_drift' => [['detik', 'Selisih jam (detik)']],
    ];

    private function get(string $key, mixed $default = null): mixed
    {
        $s = Setting::find($key);

        return $s ? $s->value : $default;
    }

    private function put(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'updated_at' => now()]);
    }

    private function params(AlertRule $r): array
    {
        return is_array($r->params) ? $r->params : (json_decode((string) $r->params, true) ?: []);
    }

    public function index(): View
    {
        $mapping = $this->get('mqtt_mapping', []);
        $data = [
            'windowStart' => $this->get('window_start', '09:00'),
            'netAcc' => (bool) $this->get('net_acc_check_enabled', false),
            'mapping' => json_encode((object) (is_array($mapping) ? $mapping : []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'rules' => AlertRule::orderBy('id')->get()->map(function ($r) {
                $r->p = $this->params($r);

                return $r;
            }),
            'fields' => self::FIELDS,
        ];

        return view('pengaturan', $data);
    }

    public function umum(Request $r): RedirectResponse
    {
        $d = $r->validate([
            'window_start' => ['required', 'date_format:H:i'],
            'mapping' => ['nullable', 'string'],
        ]);

        $raw = trim($d['mapping'] ?? '');
        $map = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($map) || collect($map)->contains(fn($v) => !is_string($v))) {
            return back()->withInput()->withErrors(['mapping' => 'Pemetaan harus berupa objek JSON, nilainya teks jalur (contoh: {"flow_rate": "data.flow"}).']);
        }

        $this->put('window_start', $d['window_start']);
        $this->put('net_acc_check_enabled', $r->boolean('net_acc'));
        $this->put('mqtt_mapping', $map);

        return back()->with('status', 'Pengaturan umum disimpan. Jam mulai baru berlaku untuk jendela yang dihitung berikutnya.');
    }

    public function alert(Request $r): RedirectResponse
    {
        foreach ((array) $r->input('rules', []) as $id => $row) {
            $rule = AlertRule::find($id);
            if (!$rule) {
                continue;
            }
            $count = (int) ($row['count'] ?? 1);
            $params = $this->params($rule);
            foreach (self::FIELDS[$rule->code] ?? [] as [$k]) {
                $v = $row['p'][$k] ?? null;
                if ($v === null || $v === '') {
                    if ($rule->code === 'weak_signal') {
                        $params[$k] = null;
                    }
                } elseif (is_numeric($v)) {
                    $params[$k] = $v + 0;
                }
            }
            DB::table('alert_rules')->where('id', $rule->id)->update([
                'enabled' => isset($row['enabled']),
                'consecutive_count' => max(1, min(100, $count)),
                'params' => json_encode((object) $params),
            ]);
        }

        return back()->with('status', 'Aturan alert disimpan.');
    }

    public function sandi(Request $r): RedirectResponse
    {
        $d = $r->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ], [
            'current_password.current_password' => 'Kata sandi saat ini salah.',
            'password.min' => 'Kata sandi baru minimal 10 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ]);

        $r->user()->update(['password' => Hash::make($d['password'])]);

        return back()->with('status', 'Kata sandi diganti.');
    }
}
