@extends('layouts.app', ['title' => 'Meter Health'])
@use('App\Support\Display', 'D')
@section('content')
<div class="head">
    <div>
        <h1>Meter Health</h1>
        <div class="sub">{{ $last ? 'Latest reading ' . D::agoFrom($last->measured_at) . ' · 24-hour summary below each value' : 'No data yet' }}</div>
    </div>
</div>

@if (!$last)
    <div class="card" style="margin-bottom:16px"><div class="empty" style="padding:8px 0">No data yet. Readings will appear here once the meter is connected.</div></div>
@endif

<div class="grid4">
    <div class="card">
        <div class="lbl">Signal quality</div>
        <div class="num mid">{{ D::num($last?->signal_quality, 0) }}</div>
        <div class="sub">24 h: average {{ D::num($s->q_avg, 0) }} · lowest {{ D::num($s->q_min, 0) }}</div>
    </div>
    <div class="card">
        <div class="lbl">Upstream / downstream signal</div>
        <div class="num mid">{{ D::num($last?->signal_up, 1) }} / {{ D::num($last?->signal_down, 1) }}</div>
        <div class="sub">Latest reading</div>
    </div>
    <div class="card">
        <div class="lbl">Time ratio</div>
        <div class="num mid">{{ D::num($last?->time_ratio, 1) }}@if ($last?->time_ratio !== null) <small>%</small>@endif</div>
        <div class="sub">24 h: {{ D::num($s->t_min, 1) }} – {{ D::num($s->t_max, 1) }} %</div>
    </div>
    <div class="card">
        <div class="lbl">Error code</div>
        <div class="num mid">{{ $last?->error_code ?? D::DASH }}</div>
        <div class="sub">24 h: {{ number_format((int) $s->errs) }} of {{ number_format((int) $s->n) }} readings had an error</div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <div class="lbl">Alert checks</div>
        <a href="{{ route('pengaturan') }}#alerts" style="font-size:13px">Change in Settings</a>
    </div>
    <ul class="statuslist">
        @foreach ($rules as $r)
            <li>
                <span>{{ $r->name }}</span>
                @if (!$r->enabled)
                    <span class="pill info">Off</span>
                @elseif ($r->open_count > 0)
                    <a class="pill bad" href="{{ route('alert') }}" style="text-decoration:none">Alert active</a>
                @else
                    <span class="pill ok">OK</span>
                @endif
            </li>
        @endforeach
    </ul>
</div>
@endsection
