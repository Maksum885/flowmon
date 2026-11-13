<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Services\WindowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $r, WindowService $svc): View
    {
        $tab = $r->query('tab') === 'riwayat' ? 'riwayat' : 'aktif';
        $q = Alert::with(['rule', 'window'])->orderByDesc('opened_at');
        if ($tab === 'aktif') {
            $q->where(fn($x) => $x->whereNull('closed_at')->orWhereNull('seen_at'));
        }
        $alerts = $q->simplePaginate(20)->withQueryString();
        $tz = $svc->tz();
        $unseen = Alert::whereNull('seen_at')->count();

        return view('alert', compact('tab', 'alerts', 'tz', 'unseen'));
    }

    public function seen(Alert $alert): RedirectResponse
    {
        $alert->update(['seen_at' => now()]);

        return back()->with('status', 'Alert marked as seen.');
    }

    public function seenAll(): RedirectResponse
    {
        $n = Alert::whereNull('seen_at')->update(['seen_at' => now()]);

        return back()->with('status', $n === 1 ? '1 alert marked as seen.' : "{$n} alerts marked as seen.");
    }
}
