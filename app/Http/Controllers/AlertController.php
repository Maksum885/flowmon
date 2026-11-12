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
        $q = Alert::with('rule')->orderByDesc('opened_at');
        if ($tab === 'aktif') {
            $q->where(fn($x) => $x->whereNull('closed_at')->orWhereNull('seen_at'));
        }
        $alerts = $q->simplePaginate(20)->withQueryString();
        $tz = $svc->tz();

        return view('alert', compact('tab', 'alerts', 'tz'));
    }

    public function seen(Alert $alert): RedirectResponse
    {
        $alert->update(['seen_at' => now()]);

        return back();
    }

    public function seenAll(): RedirectResponse
    {
        Alert::whereNull('seen_at')->update(['seen_at' => now()]);

        return back();
    }
}
