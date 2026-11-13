@extends('layouts.app', ['title' => 'Overview'])
@use('App\Support\Display', 'D')
@php
    $updated = $last ? 'Updated ' . D::agoFrom($last->measured_at) : 'No data yet';
@endphp
@section('content')
<div class="head">
    <div>
        <h1>Overview</h1>
        <div class="sub">{{ $now->copy()->locale('en')->format('D, j M Y · H:i') }}</div>
    </div>
    <div class="actions">
        @if (!$last)
            <span class="pill info">Waiting for first data</span>
        @elseif ($online)
            <span class="pill ok">Online · last data {{ $ageText }}</span>
        @else
            <span class="pill bad">Offline · last data {{ D::smart($last->measured_at) }}</span>
        @endif
    </div>
</div>

@if (!$last)
    <div class="card" style="margin-bottom:16px">
        <div class="empty" style="padding:8px 0">No data yet. Readings will appear here once the meter is connected.</div>
    </div>
@endif

<div class="grid2">
    <div class="card">
        <div class="lbl">Today's volume so far</div>
        <div class="num big">{{ D::volume($runningVolume, false) }}@if ($runningVolume !== null) <small>m³</small>@endif</div>
        <div class="muted" style="font-size:13px">Since {{ $start->copy()->locale('en')->format('j M H:i') }} · {{ $elapsedText }}</div>
        <div class="bar" role="progressbar" aria-label="Day progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}"><div style="width:{{ $progress }}%"></div></div>
        <div class="muted" style="font-size:13px">{{ $windowEndLabel }}</div>
    </div>
    <div class="card">
        <div class="lbl">Last completed day</div>
        @if ($done)
            @php [$qc, $ql, $qt] = D::quality($done->quality); @endphp
            <div class="num big">{{ D::volume($done->volume_m3, false) }}@if ($done->volume_m3 !== null) <small>m³</small>@endif</div>
            <div class="muted" style="font-size:13px">{{ D::short($done->window_start_at) }} – {{ D::short($done->window_end_at) }}</div>
            <div style="margin-top:14px;display:flex;flex-wrap:wrap;gap:12px;align-items:center">
                <span class="pill {{ $qc }}" @if ($qt) title="{{ $qt }}" @endif>{{ $ql }}</span>
                <a href="{{ route('pemakaian') }}" style="font-size:13px">See all days</a>
            </div>
        @else
            <div class="empty">No completed day yet.</div>
        @endif
    </div>
</div>

<div class="grid4">
    <div class="card"><div class="lbl">Flow rate</div><div class="num mid">{{ D::num($last?->flow_rate, 2) }}@if ($last?->flow_rate !== null) <small>m³/h</small>@endif</div><div class="sub">{{ $updated }}</div></div>
    <div class="card"><div class="lbl">Velocity</div><div class="num mid">{{ D::num($last?->velocity, 2) }}@if ($last?->velocity !== null) <small>m/s</small>@endif</div><div class="sub">{{ $updated }}</div></div>
    <div class="card"><div class="lbl">Total forward</div><div class="num mid">{{ D::num($last?->pos_total, 3) }}@if ($last?->pos_total !== null) <small>m³</small>@endif</div><div class="sub">{{ $updated }}</div></div>
    <div class="card"><div class="lbl">Total reverse</div><div class="num mid">{{ D::num($last?->neg_total, 3) }}@if ($last?->neg_total !== null) <small>m³</small>@endif</div><div class="sub">{{ $updated }}</div></div>
</div>

<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:stretch">
    <div class="card" style="flex:2 1 520px">
        <div class="card-head">
            <div class="lbl">Flow rate, last 24 hours (m³/h)</div>
            @if ($chart['points'])<span class="legend"><i></i> Today</span>@endif
        </div>
        @if ($chart['points'])
            <svg viewBox="0 0 740 210" style="width:100%;height:auto;display:block" role="img" aria-label="Flow rate over the last 24 hours">
                <rect x="{{ $chart['startX'] }}" y="20" width="{{ 700 - $chart['startX'] }}" height="150" fill="rgba(56,189,248,.12)"/>
                @foreach ($chart['yTicks'] as $t)
                    <line x1="50" y1="{{ $t['y'] }}" x2="700" y2="{{ $t['y'] }}" stroke="rgba(160,215,235,.14)"/>
                    <text x="42" y="{{ $t['y'] + 4 }}" font-size="11" fill="#A9C6D3" text-anchor="end">{{ $t['label'] }}</text>
                @endforeach
                @foreach ($chart['xTicks'] as $t)
                    <text x="{{ $t['x'] }}" y="192" font-size="11" fill="#A9C6D3" text-anchor="middle">{{ $t['label'] }}</text>
                @endforeach
                <line x1="{{ $chart['startX'] }}" y1="20" x2="{{ $chart['startX'] }}" y2="170" stroke="#FBBF24" stroke-width="1.5" stroke-dasharray="5 4"/>
                <polyline fill="none" stroke="#38BDF8" stroke-width="2.5" stroke-linejoin="round" points="{{ $chart['points'] }}"/>
            </svg>
        @else
            <div class="empty">No readings in the last 24 hours.</div>
        @endif
    </div>

    <div style="flex:1 1 300px;display:flex;flex-direction:column;gap:16px">
        <div class="card">
            <div class="card-head">
                <div class="lbl">Meter health</div>
                @if ($last)
                    <span class="pill {{ $healthOk ? 'ok' : 'warn' }}">{{ $healthOk ? 'Healthy' : 'Check meter' }}</span>
                @endif
            </div>
            @if ($last)
                <table class="tbl"><tbody>
                    <tr><td>Signal quality</td><td class="num r">{{ D::num($last->signal_quality, 0) }}</td></tr>
                    <tr><td>Time ratio</td><td class="num r">{{ D::num($last->time_ratio, 1, '%') }}</td></tr>
                    <tr><td>Error code</td><td class="num r">{{ $last->error_code ?? D::DASH }}</td></tr>
                </tbody></table>
            @else
                <div class="empty" style="padding:12px 0">No data yet.</div>
            @endif
            <a href="{{ route('kesehatan') }}" style="display:inline-block;margin-top:10px;font-size:13px">View meter health</a>
        </div>
        <div class="card">
            <div class="card-head">
                <div class="lbl">Alerts</div>
                <span class="pill {{ $alertTotal > 0 ? 'warn' : 'ok' }}">{{ $alertTotal > 0 ? $alertTotal . ' need attention' : 'All clear' }}</span>
            </div>
            @forelse ($alerts as $a)
                <div style="font-size:14px;line-height:1.5;margin-top:8px">
                    <b>{{ $a->rule->name }}</b><br>
                    <span class="muted" style="font-size:13px">{{ D::alertDuration($a) }}</span>
                </div>
            @empty
                <div class="muted" style="font-size:14px">No active alerts.</div>
            @endforelse
            <a href="{{ route('alert') }}" style="display:inline-block;margin-top:10px;font-size:13px">View all alerts</a>
        </div>
    </div>
</div>
@endsection
