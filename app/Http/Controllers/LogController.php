<?php

namespace App\Http\Controllers;

use App\Models\MqttMessage;
use App\Models\Reading;
use App\Services\WindowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    public function __invoke(Request $r, WindowService $svc): View
    {
        $tz = $svc->tz();
        $tab = $r->query('tab') === 'pesan' ? 'pesan' : 'pembacaan';
        $tanggal = $r->query('tanggal');
        $status = $r->query('status');

        $day = null;
        if ($tanggal) {
            try {
                $day = Carbon::createFromFormat('Y-m-d', (string) $tanggal, $tz);
            } catch (\Throwable) {
                $tanggal = null;
            }
        }
        $range = $day ? [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()] : null;

        if ($tab === 'pembacaan') {
            $q = Reading::orderByDesc('measured_at');
            if ($range) {
                $q->whereBetween('measured_at', $range);
            }
            $rows = $q->simplePaginate(50)->withQueryString();
        } else {
            $q = MqttMessage::orderByDesc('id');
            if ($status) {
                $q->where('status', $status);
            }
            if ($range) {
                $q->whereBetween('received_at', $range);
            }
            $rows = $q->simplePaginate(30)->withQueryString();
        }

        return view('log', compact('tab', 'rows', 'tz', 'tanggal', 'status'));
    }
}
