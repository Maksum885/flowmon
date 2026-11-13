<?php

namespace App\Services;

use App\Models\AlertRule;
use App\Models\Reading;
use App\Models\UsageWindow;
use Illuminate\Support\Facades\DB;

class AlertChecker
{
    public int $opened = 0;
    public int $closed = 0;

    public function run(): void
    {
        // Aturan yang dimatikan: tutup alert yang masih terbuka
        $this->closed += DB::table('alerts')
            ->whereIn('rule_id', AlertRule::where('enabled', false)->pluck('id'))
            ->whereNull('closed_at')
            ->update(['closed_at' => now()]);

        foreach (AlertRule::where('enabled', true)->get() as $rule) {
            $method = 'check' . str_replace('_', '', ucwords($rule->code, '_'));
            if (method_exists($this, $method)) {
                $this->$method($rule);
            }
        }
    }

    // ---------- aturan berbasis pembacaan terbaru ----------

    private function checkOffline(AlertRule $r): void
    {
        $last = Reading::orderByDesc('measured_at')->first();
        if (!$last) {
            return;
        }
        $age = now()->timestamp - $last->measured_at->timestamp;
        if ($age > (int) $this->p($r, 'menit', 5) * 60) {
            $this->open($r, null, [
                'last_reading_at' => $last->measured_at->toIso8601String(),
                'age_seconds' => $age,
            ]);
        } else {
            $this->close($r, null);
        }
    }

    private function checkErrorCode(AlertRule $r): void
    {
        $ok = (array) $this->p($r, 'ok_values', [0]);
        $this->consecutive($r, fn($x) => $x->error_code !== null && !in_array((int) $x->error_code, $ok, true), ['error_code']);
    }

    private function checkSignalQ(AlertRule $r): void
    {
        $min = (float) $this->p($r, 'batas', 50);
        $this->consecutive($r, fn($x) => $x->signal_quality !== null && (float) $x->signal_quality < $min, ['signal_quality']);
    }

    private function checkWeakSignal(AlertRule $r): void
    {
        $min = $this->p($r, 'batas');
        if ($min === null) {
            return;
        }
        $this->consecutive($r, fn($x) => ($x->signal_up !== null && (float) $x->signal_up < $min)
            || ($x->signal_down !== null && (float) $x->signal_down < $min), ['signal_up', 'signal_down']);
    }

    private function checkTimeRatio(AlertRule $r): void
    {
        $min = (float) $this->p($r, 'min', 95);
        $max = (float) $this->p($r, 'max', 105);
        $this->consecutive($r, fn($x) => $x->time_ratio !== null
            && ((float) $x->time_ratio < $min || (float) $x->time_ratio > $max), ['time_ratio']);
    }

    private function checkZeroFlow(AlertRule $r): void
    {
        $minutes = (int) $this->p($r, 'menit', 30);
        $cutoff = now()->subMinutes($minutes);
        $recent = Reading::where('measured_at', '>=', $cutoff)->get();
        if ($recent->isEmpty() || !Reading::where('measured_at', '<=', $cutoff)->exists()) {
            return;
        }
        $maxFlow = $recent->max(fn($x) => abs((float) $x->flow_rate));
        if ($maxFlow < 0.01) {
            $this->open($r, null, ['minutes' => $minutes]);
        } elseif (abs((float) $recent->sortByDesc('measured_at')->first()->flow_rate) >= 0.01) {
            $this->close($r, null);
        }
    }

    // ---------- aturan berbasis jendela 09:00 (7 hari terakhir) ----------

    private function checkBackflow(AlertRule $r): void
    {
        $limit = (float) $this->p($r, 'neg_naik_m3', 0.1);
        foreach ($this->recentWindows() as $w) {
            if ($w->neg_delta_m3 !== null && (float) $w->neg_delta_m3 > $limit) {
                $this->open($r, $w->id, ['neg_delta_m3' => (float) $w->neg_delta_m3]);
            } else {
                $this->close($r, $w->id);
            }
        }
    }

    private function checkIncompleteWindow(AlertRule $r): void
    {
        foreach ($this->recentWindows() as $w) {
            if ($w->quality !== 'complete') {
                $this->open($r, $w->id, [
                    'quality' => $w->quality,
                    'max_gap_seconds' => $w->max_gap_seconds,
                ]);
            } else {
                $this->close($r, $w->id);
            }
        }
    }

    // ---------- pembantu ----------

    private function recentWindows()
    {
        return UsageWindow::where('window_start_at', '>=', now()->subDays(7))->get();
    }

    /** Terbuka bila N pembacaan terakhir semuanya melanggar; tertutup bila pembacaan terbaru normal. */
    private function consecutive(AlertRule $r, callable $bad, array $fields): void
    {
        $n = max(1, (int) $r->consecutive_count);
        $rows = Reading::orderByDesc('measured_at')->limit($n)->get();
        if ($rows->count() < $n) {
            return;
        }
        if ($rows->every($bad)) {
            $this->open($r, null, ['measured_at' => $rows->first()->measured_at->toIso8601String()] + $rows->first()->only($fields));
        } elseif (!$bad($rows->first())) {
            $this->close($r, null);
        }
    }

    private function p(AlertRule $r, string $key, mixed $default = null): mixed
    {
        $p = is_array($r->params) ? $r->params : (json_decode((string) $r->params, true) ?: []);

        return $p[$key] ?? $default;
    }

    /**
     * Open alerts of this rule for the same subject: the same usage window
     * (matched by window start, so one alert per rule + day) or, for rules
     * that are not tied to a window, the single window-less alert.
     */
    private function openFor(AlertRule $r, ?int $windowId)
    {
        $q = DB::table('alerts')->where('rule_id', $r->id)->whereNull('closed_at');
        if (!$windowId) {
            return $q->whereNull('window_id');
        }
        $start = DB::table('usage_windows')->where('id', $windowId)->value('window_start_at');
        if ($start === null) {
            return $q->where('window_id', $windowId);
        }

        return $q->whereIn('window_id', DB::table('usage_windows')->select('id')->where('window_start_at', $start));
    }

    private function open(AlertRule $r, ?int $windowId, array $detail): void
    {
        if ($this->openFor($r, $windowId)->exists()) {
            return;
        }
        // insertOrIgnore: an overlapping run that already opened it hits the unique index and is skipped
        $this->opened += DB::table('alerts')->insertOrIgnore([
            'rule_id' => $r->id,
            'window_id' => $windowId,
            'opened_at' => now(),
            'detail' => json_encode($detail),
        ]);
    }

    private function close(AlertRule $r, ?int $windowId): void
    {
        $this->closed += $this->openFor($r, $windowId)->update(['closed_at' => now()]);
    }
}
