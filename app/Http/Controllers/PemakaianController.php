<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\UsageWindow;
use App\Services\WindowService;
use App\Support\Display;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class PemakaianController extends Controller
{
    public function index(Request $r, WindowService $svc): View
    {
        $tz = $svc->tz();
        $tab = $r->query('tab') === 'rentang' ? 'rentang' : 'jendela';

        $windows = UsageWindow::orderByDesc('window_start_at')->simplePaginate(15)->withQueryString();

        [$defStart, $defEnd] = $svc->boundsFor(now($tz)->subDay()->startOfDay());
        $from = $r->query('from', $defStart->format('Y-m-d\TH:i'));
        $to = $r->query('to', $defEnd->format('Y-m-d\TH:i'));

        $result = null;
        $error = null;
        $a = $b = null;

        if ($tab === 'rentang' && $r->filled('from') && $r->filled('to')) {
            try {
                $a = Carbon::parse($from, $tz);
                $b = Carbon::parse($to, $tz);
            } catch (\Throwable) {
                $error = 'Please enter a valid date and time.';
            }
            if (!$error && $b->lte($a)) {
                $error = 'The end must be after the start.';
            } elseif (!$error && $a->diffInDays($b) > 31) {
                $error = 'The range can be at most 31 days.';
            }
            if (!$error) {
                $result = $svc->compute($a, $b);
            }
        }

        $exportFrom = $r->query('xf', now($tz)->subDays(30)->format('Y-m-d'));
        $exportTo = $r->query('xt', now($tz)->format('Y-m-d'));

        $windowStart = (string) Setting::read('window_start', '09:00');

        return view('pemakaian', compact('tz', 'tab', 'windows', 'from', 'to', 'result', 'error', 'a', 'b', 'exportFrom', 'exportTo', 'windowStart'));
    }

    public function export(Request $r, WindowService $svc)
    {
        $tz = $svc->tz();
        $r->validate([
            'xf' => ['required', 'date_format:Y-m-d'],
            'xt' => ['required', 'date_format:Y-m-d', 'after_or_equal:xf'],
        ], [
            'xf.required' => 'Please choose a start date.',
            'xt.required' => 'Please choose an end date.',
            'xf.date_format' => 'Please enter the start date as YYYY-MM-DD.',
            'xt.date_format' => 'Please enter the end date as YYYY-MM-DD.',
            'xt.after_or_equal' => 'The end date must be on or after the start date.',
        ]);
        $from = Carbon::parse($r->query('xf'), $tz)->startOfDay();
        $to = Carbon::parse($r->query('xt'), $tz)->endOfDay();

        $rows = UsageWindow::where('window_start_at', '>=', $from)
            ->where('window_start_at', '<=', $to)
            ->orderBy('window_start_at')
            ->get();

        $name = 'daily-usage_' . $from->format('Ymd') . '_' . $to->format('Ymd') . '.csv';

        return Response::streamDownload(function () use ($rows, $tz) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, ['Start', 'End', 'Volume (m3)', 'Status'], ';');
            foreach ($rows as $w) {
                fputcsv($out, [
                    $w->window_start_at->copy()->setTimezone($tz)->format('Y-m-d H:i'),
                    $w->window_end_at->copy()->setTimezone($tz)->format('Y-m-d H:i'),
                    number_format((float) $w->volume_m3, 3, ',', ''),
                    Display::quality($w->quality)[1],
                ], ';');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
