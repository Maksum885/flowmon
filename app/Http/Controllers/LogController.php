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

        $rows = null;
        if ($tab === 'pembacaan') {
            $q = Reading::orderByDesc('measured_at');
            if ($tanggal) {
                $d = Carbon::parse($tanggal, $tz);
                $q->whereBetween('measured_at', [$d->copy()->startOfDay(), $d->copy()->endOfDay()]);
            }
            $rows = $q->simplePaginate(50)->withQueryString();
        } else {
            $q = MqttMessage::orderByDesc('id');
            if ($status) {
                $q->where('status', $status);
            }
            $rows = $q->simplePaginate(30)->withQueryString();
        }

        return view('log', compact('tab', 'rows', 'tz', 'tanggal', 'status'));
    }
}
