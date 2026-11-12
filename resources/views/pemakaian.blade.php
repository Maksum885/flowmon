@extends('layouts.app', ['title' => 'Pemakaian 09:00'])
@php
    $fmt = fn ($v, $d = 1) => $v === null ? '—' : number_format((float) $v, $d, ',', '.');
    $qual = [
        'complete' => ['ok', 'Data lengkap'],
        'gap' => ['warn', 'Ada celah'],
        'mismatch' => ['bad', 'Tidak cocok'],
        'no_data' => ['bad', 'Tidak ada data'],
    ];
    $fd = fn ($d) => $d->copy()->setTimezone($tz)->locale('id')->translatedFormat('D, j M Y H:i');
@endphp
@section('content')
<div class="head">
    <div>
        <h1>Pemakaian 09:00</h1>
        <div class="sub">Volume = (POS akhir − POS awal) − (NEG akhir − NEG awal), dihitung dari riwayat data.</div>
    </div>
    <div style="display:flex;gap:8px">
        <a class="btn {{ $tab === 'jendela' ? '' : 'sec' }}" href="{{ route('pemakaian') }}">Per jendela</a>
        <a class="btn {{ $tab === 'rentang' ? '' : 'sec' }}" href="{{ route('pemakaian', ['tab' => 'rentang']) }}">Rentang bebas</a>
    </div>
</div>

@if ($tab === 'jendela')
    <div class="card" style="margin-bottom:16px;overflow-x:auto">
        <table class="tbl">
            <thead><tr><th>Mulai</th><th>Selesai</th><th style="text-align:right">Volume (m³)</th><th>Kualitas</th></tr></thead>
            <tbody>
            @forelse ($windows as $w)
                <tr>
                    <td>{{ $fd($w->window_start_at) }}</td>
                    <td>{{ $fd($w->window_end_at) }}</td>
                    <td class="num" style="text-align:right">{{ $fmt($w->volume_m3, 1) }}</td>
                    <td><span class="pill {{ $qual[$w->quality][0] ?? 'info' }}">{{ $qual[$w->quality][1] ?? $w->quality }}</span></td>
                </tr>
            @empty
                <tr><td colspan="4" style="color:#9CBCCB">Belum ada jendela. Jalankan <code>php artisan flowmon:recalc</code>.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="display:flex;gap:10px;margin-top:14px">
            @if ($windows->previousPageUrl())<a class="btn sec" href="{{ $windows->previousPageUrl() }}">← Lebih baru</a>@endif
            @if ($windows->nextPageUrl())<a class="btn sec" href="{{ $windows->nextPageUrl() }}">Lebih lama →</a>@endif
        </div>
    </div>

    <form class="card" method="GET" action="{{ route('pemakaian.export') }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
        <div><label style="display:block;font-size:13px;margin-bottom:6px">Ekspor dari tanggal</label><input type="date" name="xf" value="{{ $exportFrom }}" required></div>
        <div><label style="display:block;font-size:13px;margin-bottom:6px">Sampai tanggal</label><input type="date" name="xt" value="{{ $exportTo }}" required></div>
        <button class="btn" type="submit">Unduh CSV (Excel)</button>
    </form>
@else
    <form class="card" method="GET" action="{{ route('pemakaian') }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:16px">
        <input type="hidden" name="tab" value="rentang">
        <div><label style="display:block;font-size:13px;margin-bottom:6px">Dari</label><input type="datetime-local" name="from" value="{{ $from }}" required></div>
        <div><label style="display:block;font-size:13px;margin-bottom:6px">Sampai</label><input type="datetime-local" name="to" value="{{ $to }}" required></div>
        <button class="btn" type="submit">Hitung</button>
    </form>

    @if ($error)
        <div class="pill bad" style="white-space:normal">{{ $error }}</div>
    @elseif ($result)
        <div class="card">
            <div class="lbl">Volume {{ $fd($a) }} → {{ $fd($b) }}</div>
            <div class="num" style="font-size:44px;margin:8px 0 2px">{{ $fmt($result['volume_m3'] ?? null, 1) }} <span style="font-size:20px">m³</span></div>
            @if (!empty($result['quality']))
                <span class="pill {{ $qual[$result['quality']][0] ?? 'info' }}">{{ $qual[$result['quality']][1] ?? $result['quality'] }}</span>
            @endif
        </div>
    @endif
@endif
@endsection