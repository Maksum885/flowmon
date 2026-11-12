@extends('layouts.app', ['title' => 'Ringkasan'])
@php
    $fmt = fn ($v, $d = 1) => $v === null ? '—' : number_format((float) $v, $d, ',', '.');
    $qual = [
        'complete' => ['ok', 'Data lengkap'],
        'gap' => ['warn', 'Ada celah'],
        'mismatch' => ['bad', 'Tidak cocok'],
        'no_data' => ['bad', 'Tidak ada data'],
    ];
@endphp
@section('content')
<div class="head">
    <div>
        <h1>Ringkasan</h1>
        <div class="sub">{{ $now->copy()->locale('id')->translatedFormat('l, j F Y · H:i') }} WIB</div>
    </div>
    <div>
        @if ($online)
            <span class="pill ok">Online · data {{ $ageText }}</span>
        @else
            <span class="pill bad">Offline · data terakhir {{ $ageText }}</span>
        @endif
    </div>
</div>

<div class="grid2">
    <div class="card">
        <div class="lbl">Volume jendela berjalan (POS − NEG)</div>
        <div class="num" style="font-size:44px;margin:8px 0 2px">{{ $fmt($runningVolume) }} <span style="font-size:20px">m³</span></div>
        <div style="color:#9CBCCB;font-size:13px">{{ $start->copy()->locale('id')->translatedFormat('j M H:i') }} → sekarang ({{ $elapsedText }})</div>
        <div class="bar"><div style="width:{{ $progress }}%"></div></div>
        <div style="font-size:13px;color:#9CBCCB">Berakhir {{ $windowEndLabel }}</div>
    </div>
    <div class="card">
        <div class="lbl">Jendela terakhir yang sudah dihitung</div>
        @if ($done)
            <div class="num" style="font-size:44px;margin:8px 0 2px">{{ $fmt($done->volume_m3) }} <span style="font-size:20px">m³</span></div>
            <div style="color:#9CBCCB;font-size:13px">
                {{ $done->window_start_at->copy()->setTimezone($tz)->locale('id')->translatedFormat('j M H:i') }}
                → {{ $done->window_end_at->copy()->setTimezone($tz)->locale('id')->translatedFormat('j M H:i') }}
            </div>
            <div style="margin-top:14px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
                <span class="pill {{ $qual[$done->quality][0] }}">{{ $qual[$done->quality][1] }}</span>
                <a href="{{ route('pemakaian') }}" style="font-size:13px">Lihat semua jendela</a>
            </div>
        @else
            <div style="margin-top:12px;color:#9CBCCB">Belum ada jendela yang dihitung. Jalankan <code>php artisan flowmon:recalc</code>.</div>
        @endif
    </div>
</div>

<div class="grid4">
    <div class="card"><div class="lbl">Laju aliran</div><div class="num" style="font-size:28px;margin-top:6px">{{ $fmt($last?->flow_rate) }}</div><div class="sub">m³/jam</div></div>
    <div class="card"><div class="lbl">Kecepatan</div><div class="num" style="font-size:28px;margin-top:6px">{{ $fmt($last?->velocity, 2) }}</div><div class="sub">m/s</div></div>
    <div class="card"><div class="lbl">Total POS</div><div class="num" style="font-size:28px;margin-top:6px">{{ $fmt($last?->pos_total, 2) }}</div><div class="sub">m³</div></div>
    <div class="card"><div class="lbl">Total NEG</div><div class="num" style="font-size:28px;margin-top:6px">{{ $fmt($last?->neg_total, 2) }}</div><div class="sub">m³ (aliran balik)</div></div>
</div>

<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:stretch">
    <div class="card" style="flex:2 1 520px">
        <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;margin-bottom:6px">
            <div class="lbl">Laju aliran 24 jam terakhir (m³/jam)</div>
            <div style="font-size:12px;color:#9CBCCB">Area biru = jendela berjalan</div>
        </div>
        @if ($chart['points'])
            <svg viewBox="0 0 740 210" style="width:100%;height:auto;display:block" role="img" aria-label="Grafik laju aliran 24 jam terakhir">
                <rect x="{{ $chart['startX'] }}" y="20" width="{{ 700 - $chart['startX'] }}" height="150" fill="rgba(56,189,248,.12)"/>
                @foreach ($chart['yTicks'] as $t)
                    <line x1="50" y1="{{ $t['y'] }}" x2="700" y2="{{ $t['y'] }}" stroke="rgba(160,215,235,.14)"/>
                    <text x="42" y="{{ $t['y'] + 4 }}" font-size="11" fill="#9CBCCB" text-anchor="end">{{ $t['label'] }}</text>
                @endforeach
                @foreach ($chart['xTicks'] as $t)
                    <text x="{{ $t['x'] }}" y="192" font-size="11" fill="#9CBCCB" text-anchor="middle">{{ $t['label'] }}</text>
                @endforeach
                <line x1="{{ $chart['startX'] }}" y1="20" x2="{{ $chart['startX'] }}" y2="170" stroke="#FBBF24" stroke-width="1.5" stroke-dasharray="5 4"/>
                <polyline fill="none" stroke="#38BDF8" stroke-width="2.5" stroke-linejoin="round" points="{{ $chart['points'] }}"/>
            </svg>
        @else
            <div style="color:#9CBCCB;padding:40px 0">Belum ada data dalam 24 jam terakhir.</div>
        @endif
    </div>

    <div style="flex:1 1 300px;display:flex;flex-direction:column;gap:16px">
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <div class="lbl">Kesehatan meter</div>
                @if ($last)
                    <span class="pill {{ $healthOk ? 'ok' : 'warn' }}">{{ $healthOk ? 'Sehat' : 'Perlu dicek' }}</span>
                @endif
            </div>
            <table class="tbl"><tbody>
                <tr><td>Kualitas sinyal (Q)</td><td class="num" style="text-align:right">{{ $last?->signal_quality ?? '—' }}</td></tr>
                <tr><td>Sinyal hulu / hilir</td><td class="num" style="text-align:right">{{ $fmt($last?->signal_up) }} / {{ $fmt($last?->signal_down) }}</td></tr>
                <tr><td>Time ratio</td><td class="num" style="text-align:right">{{ $fmt($last?->time_ratio) }} %</td></tr>
                <tr><td>Kode error</td><td class="num" style="text-align:right">{{ $last?->error_code ?? '—' }}</td></tr>
            </tbody></table>
            <a href="{{ route('kesehatan') }}" style="display:inline-block;margin-top:10px;font-size:13px">Detail kesehatan</a>
        </div>
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <div class="lbl">Alert aktif</div>
                <span class="pill {{ $alertTotal > 0 ? 'warn' : 'ok' }}">{{ $alertTotal }}</span>
            </div>
            @forelse ($alerts as $a)
                <div style="font-size:14px;line-height:1.5;margin-top:8px">
                    <b>{{ $a->rule->name }}</b><br>
                    <span style="color:#9CBCCB;font-size:13px">{{ $a->opened_at->copy()->setTimezone($tz)->locale('id')->translatedFormat('j M H:i') }}</span>
                </div>
            @empty
                <div style="color:#9CBCCB;font-size:14px">Tidak ada alert aktif.</div>
            @endforelse
            <a href="{{ route('alert') }}" style="display:inline-block;margin-top:10px;font-size:13px">Buka semua alert</a>
        </div>
    </div>
</div>
@endsection