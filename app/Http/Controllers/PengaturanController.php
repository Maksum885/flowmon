<?php

namespace App\Http\Controllers;

use App\Models\AlertRule;
use App\Models\Setting;
use App\Support\AlertRuleCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PengaturanController extends Controller
{
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
        ];

        return view('pengaturan', $data);
    }

    /**
     * Saves the "General" section (day start time) and/or the "Advanced"
     * section (MQTT mapping, Net Acc cross-check). Only submitted fields change.
     */
    public function umum(Request $r): RedirectResponse
    {
        $d = $r->validate([
            'window_start' => ['sometimes', 'required', 'date_format:H:i'],
            'mapping' => ['sometimes', 'nullable', 'string'],
        ], [
            'window_start.required' => 'Please enter a start time.',
            'window_start.date_format' => 'Please enter the time as HH:MM, for example 09:00.',
        ]);

        if ($r->has('mapping')) {
            $raw = trim($d['mapping'] ?? '');
            $map = $raw === '' ? [] : json_decode($raw, true);
            if (!is_array($map) || collect($map)->contains(fn($v) => !is_string($v))) {
                return back()->withInput()->withErrors(['mapping' => 'The mapping must be a JSON object whose values are text paths, for example {"flow_rate": "data.flow"}.']);
            }
            $this->put('mqtt_mapping', $map);
        }
        if ($r->has('net_acc')) {
            $this->put('net_acc_check_enabled', $r->boolean('net_acc'));
        }
        if ($r->has('window_start')) {
            $this->put('window_start', $d['window_start']);

            return redirect()->to(route('pengaturan') . '#general')
                ->with('status', 'Day start time saved. It applies to days calculated from now on.');
        }

        return redirect()->to(route('pengaturan') . '#advanced')->with('status', 'Advanced settings saved.');
    }

    public function alert(Request $r): RedirectResponse
    {
        foreach ((array) $r->input('rules', []) as $id => $row) {
            $rule = AlertRule::find($id);
            if (!$rule) {
                continue;
            }
            $count = (int) ($row['count'] ?? $rule->consecutive_count ?? 1);
            $params = $this->params($rule);
            foreach (AlertRuleCatalog::fields($rule->code) as [$k]) {
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

        return redirect()->to(route('pengaturan') . '#alerts')->with('status', 'Alert rules saved.');
    }

    public function sandi(Request $r): RedirectResponse
    {
        $d = $r->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ], [
            'current_password.required' => 'Please enter your current password.',
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.required' => 'Please enter a new password.',
            'password.min' => 'The new password must be at least 10 characters.',
            'password.confirmed' => 'The two new passwords do not match.',
        ]);

        $r->user()->update(['password' => Hash::make($d['password'])]);

        return redirect()->to(route('pengaturan') . '#password')->with('status', 'Password changed.');
    }
}
