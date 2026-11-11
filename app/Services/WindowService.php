<?php

namespace App\Services;

use App\Models\Reading;
use App\Models\Setting;
use App\Models\UsageWindow;
use Carbon\Carbon;

class WindowService
{
    public const CALC_VERSION = 1;

    public function __construct(private WindowCalculator $calc) {}

    public function tz(): string
    {
        return config('app.display_timezone', 'Asia/Jakarta');
    }

    /** Jendela 24 jam yang BERAKHIR pada tanggal lokal $day di jam mulai pengaturan. */
    public function boundsFor(Carbon $day): array
    {
        [$h, $m] = array_map('intval', explode(':', (string) Setting::read('window_start', '09:00')));
        $end = Carbon::create($day->year, $day->month, $day->day, $h, $m, 0, $this->tz());

        return [$end->copy()->subDay(), $end];
    }

    public function compute(Carbon $start, Carbon $end): array
    {
        $rows = Reading::query()
            ->whereBetween('measured_at', [
                $start->copy()->subHour()->utc(),
                $end->copy()->addHour()->utc(),
            ])
            ->orderBy('measured_at')
            ->get(['measured_at', 'pos_total', 'neg_total', 'flow_rate']);

        $readings = $rows->map(fn($r) => [
            't' => $r->measured_at->getTimestamp(),
            'pos' => $r->pos_total === null ? null : (float) $r->pos_total,
            'neg' => $r->neg_total === null ? null : (float) $r->neg_total,
            'flow' => $r->flow_rate === null ? null : (float) $r->flow_rate,
        ])->all();

        return $this->calc->compute($readings, $start->getTimestamp(), $end->getTimestamp());
    }

    public function store(Carbon $start, Carbon $end): UsageWindow
    {
        $r = $this->compute($start, $end);
        $startUtc = $start->copy()->utc();
        $existing = UsageWindow::where('window_start_at', $startUtc)->first();

        // Jendela yang sudah punya hasil tidak ditimpa oleh no_data
        if ($r['quality'] === 'no_data' && $existing && $existing->quality !== 'no_data') {
            return $existing;
        }

        return UsageWindow::updateOrCreate(
            ['window_start_at' => $startUtc],
            [
                'window_end_at' => $end->copy()->utc(),
                'volume_m3' => $r['volume_m3'],
                'pos_delta_m3' => $r['pos_delta_m3'],
                'neg_delta_m3' => $r['neg_delta_m3'],
                'integrated_m3' => $r['integrated_m3'],
                'quality' => $r['quality'],
                'flags' => $r['flags'] ?: new \stdClass(),
                'reading_count' => $r['reading_count'],
                'max_gap_seconds' => $r['max_gap_seconds'],
                'max_gap_start_at' => $r['max_gap_start_at'] === null
                    ? null
                    : Carbon::createFromTimestampUTC($r['max_gap_start_at']),
                'calc_version' => self::CALC_VERSION,
                'calculated_at' => now()->utc(),
            ]
        );
    }

    /** Hitung ulang $days jendela terakhir yang sudah selesai. Mengembalikan jumlah yang dihitung. */
    public function recalcRecent(int $days = 3): int
    {
        $today = now($this->tz())->startOfDay();
        $done = 0;
        for ($i = 0; $i <= $days && $done < $days; $i++) {
            [$start, $end] = $this->boundsFor($today->copy()->subDays($i));
            if ($end->isFuture()) {
                continue;
            }
            $this->store($start, $end);
            $done++;
        }

        return $done;
    }
}
