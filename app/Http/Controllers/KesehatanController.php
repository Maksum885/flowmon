<?php

namespace App\Http\Controllers;

use App\Models\AlertRule;
use App\Models\Reading;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KesehatanController extends Controller
{
    public function __invoke(): View
    {
        $last = Reading::orderByDesc('measured_at')->first();
        $s = DB::selectOne(
            "select count(*) as n, avg(signal_quality) as q_avg, min(signal_quality) as q_min,
                    avg(time_ratio) as t_avg, min(time_ratio) as t_min, max(time_ratio) as t_max,
                    count(*) filter (where error_code is not null and error_code <> 0) as errs
             from readings where measured_at >= ?",
            [now()->subDay()->utc()->format('Y-m-d H:i:s')]
        );
        $rules = AlertRule::orderBy('id')->get()->map(function ($r) {
            $r->open_count = DB::table('alerts')->where('rule_id', $r->id)->whereNull('closed_at')->count();

            return $r;
        });

        return view('kesehatan', compact('last', 's', 'rules'));
    }
}
