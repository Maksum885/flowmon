<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Reading;
use App\Models\UsageWindow;
use App\Services\WindowService;
use App\Support\Display;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(WindowService $svc): View
    {
        $tz = $svc->tz();
        $now = now($tz);
        $last = Reading::orderByDesc('measured_at')->first();

        // Jendela berjalan: yang berakhir pada jam mulai berikutnya
        [$start, $end] = $svc->boundsFor($now->copy()->startOfDay());
        if ($end->isPast()) {
            [$start, $end] = $svc->boundsFor($now->copy()->addDay()->startOfDay());
        }
        $runningVolume = null;
        if ($last && $last->measured_at->gt($start)) {
            $runningVolume = $svc->compute($start, $last->measured_at->copy())['volume_m3'];
        }
        $elapsed = max(0, min(86400, $now->timestamp - $start->timestamp));
        $progress = (int) round($elapsed / 864);
        $elapsedText = intdiv($elapsed, 3600) . ' h ' . intdiv($elapsed % 3600, 60) . ' min of 24 h';
        $endDay = match (true) {
            $end->isSameDay($now) => 'today',
            $end->isSameDay($now->copy()->addDay()) => 'tomorrow',
            default => $end->format('j M'),
        };
        $windowEndLabel = 'Ends ' . $endDay . ' ' . $end->format('H:i');

        // Jendela terakhir yang sudah dihitung
        $done = UsageWindow::orderByDesc('window_start_at')->first();

        // Umur data terakhir
        $age = $last ? max(0, now()->timestamp - $last->measured_at->timestamp) : null;
        $online = $age !== null && $age <= 300;
        $ageText = Display::ago($age);

        // Kesehatan meter
        $healthOk = $last
            && (int) $last->signal_quality >= 50
            && $last->time_ratio !== null
            && (float) $last->time_ratio >= 95
            && (float) $last->time_ratio <= 105;

        // Grafik laju aliran 24 jam terakhir (rata-rata per 15 menit)
        $t1 = $now->timestamp;
        $t0 = $t1 - 86400;
        $rows = DB::select(
            "select date_bin('15 minutes', measured_at, timestamptz '2000-01-01') as b, avg(flow_rate) as f
             from readings where measured_at >= ? and flow_rate is not null group by b order by b",
            [now()->subDay()->utc()->format('Y-m-d H:i:s')]
        );
        $vals = array_map(fn($r) => (float) $r->f, $rows);
        $max = max(50, (int) (ceil(($vals ? max($vals) : 0) / 50) * 50));
        $x = fn(int $t) => 50 + ($t - $t0) / 86400 * 650;
        $y = fn(float $v) => 170 - $v / $max * 150;
        $points = [];
        foreach ($rows as $r) {
            $points[] = round($x(Carbon::parse($r->b)->timestamp), 1) . ',' . round($y((float) $r->f), 1);
        }
        $yTicks = [];
        for ($i = 0; $i <= 4; $i++) {
            $v = $max * $i / 4;
            $yTicks[] = ['label' => number_format($v), 'y' => round($y((float) $v), 1)];
        }
        $xTicks = [];
        for ($i = 0; $i <= 4; $i++) {
            $t = $t0 + $i * 21600;
            $xTicks[] = ['label' => Carbon::createFromTimestamp($t, $tz)->format('H:i'), 'x' => round($x($t), 1)];
        }
        $chart = [
            'points' => implode(' ', $points),
            'yTicks' => $yTicks,
            'xTicks' => $xTicks,
            'startX' => round($x($start->timestamp), 1),
        ];

        // Alert aktif: belum dilihat atau masih berlangsung
        $alertQuery = Alert::where(fn($q) => $q->whereNull('closed_at')->orWhereNull('seen_at'));
        $alertTotal = (clone $alertQuery)->count();
        $alerts = $alertQuery->with(['rule', 'window'])->orderByDesc('opened_at')->limit(3)->get();

        return view('ringkasan', compact(
            'tz',
            'now',
            'last',
            'start',
            'end',
            'runningVolume',
            'progress',
            'elapsedText',
            'windowEndLabel',
            'done',
            'age',
            'online',
            'ageText',
            'healthOk',
            'chart',
            'alerts',
            'alertTotal'
        ));
    }
}
