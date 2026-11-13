<?php

namespace App\Http\Controllers;

use App\Models\AlertRule;
use App\Services\WindowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GrafikController extends Controller
{
    private const RANGES = [
        '24j' => ['label' => '24 hours', 'every' => '5 minutes', 'sec' => 86400, 'bucket' => '5 minutes', 'bsec' => 300, 'fmt' => 'H:i'],
        '7h' => ['label' => '7 days', 'every' => 'hour', 'sec' => 604800, 'bucket' => '1 hour', 'bsec' => 3600, 'fmt' => 'j M'],
        '30h' => ['label' => '30 days', 'every' => '3 hours', 'sec' => 2592000, 'bucket' => '3 hours', 'bsec' => 10800, 'fmt' => 'j M'],
    ];

    public function __invoke(Request $r, WindowService $svc): View
    {
        $key = array_key_exists((string) $r->query('r'), self::RANGES) ? $r->query('r') : '24j';
        $cfg = self::RANGES[$key];
        $tz = $svc->tz();
        $t1 = now()->timestamp;
        $t0 = $t1 - $cfg['sec'];

        // 'bucket' berasal dari daftar tetap di atas, bukan dari input pengguna
        $rows = DB::select(
            "select date_bin('{$cfg['bucket']}', measured_at, timestamptz '2000-01-01') as b,
                    avg(flow_rate) as f, avg(velocity) as v, min(signal_quality) as q, avg(time_ratio) as t
             from readings where measured_at >= ? group by b order by b",
            [now()->subSeconds($cfg['sec'])->utc()->format('Y-m-d H:i:s')]
        );

        $series = fn(string $col) => collect($rows)
            ->filter(fn($x) => $x->$col !== null)
            ->map(fn($x) => [Carbon::parse($x->b)->timestamp, (float) $x->$col])
            ->values()->all();

        $param = function (string $code, string $k, $d) {
            $rule = AlertRule::where('code', $code)->first();
            $p = $rule ? (is_array($rule->params) ? $rule->params : (json_decode((string) $rule->params, true) ?: [])) : [];

            return $p[$k] ?? $d;
        };
        $qMin = (float) $param('signal_q', 'batas', 50);
        $trMin = (float) $param('time_ratio', 'min', 95);
        $trMax = (float) $param('time_ratio', 'max', 105);

        $tr = $series('t');
        $trVals = array_column($tr, 1);
        $charts = [
            ['title' => 'Flow rate (m³/h)', 'points' => $series('f'), 'color' => '#38BDF8', 'dec' => 0, 'tipdec' => 2, 'unit' => 'm³/h', 'ymin' => 0, 'ymax' => null, 'lines' => []],
            ['title' => 'Velocity (m/s)', 'points' => $series('v'), 'color' => '#22D3EE', 'dec' => 2, 'tipdec' => 2, 'unit' => 'm/s', 'ymin' => 0, 'ymax' => null, 'lines' => []],
            ['title' => 'Signal quality (lowest per interval)', 'points' => $series('q'), 'color' => '#6EE7B7', 'dec' => 0, 'tipdec' => 0, 'unit' => '', 'ymin' => 0, 'ymax' => 100, 'lines' => [[$qMin, 'alert limit', '#FBBF24']]],
            [
                'title' => 'Time ratio (%)',
                'points' => $tr,
                'color' => '#A5B4FC',
                'dec' => 0,
                'tipdec' => 1,
                'unit' => '%',
                'ymin' => floor(min($trVals ?: [$trMin], [$trMin])[0] ?? $trMin) - 5,
                'ymax' => ceil(max(array_merge($trVals, [$trMax]))) + 5,
                'lines' => [[$trMin, 'min', '#FBBF24'], [$trMax, 'max', '#FBBF24']]
            ],
        ];
        $ranges = collect(self::RANGES)->map(fn($x) => $x['label'])->all();

        return view('grafik', compact('charts', 'key', 'ranges', 'cfg', 't0', 't1', 'tz'));
    }
}
