@extends('layouts.app', ['title' => 'Data Log'])
@use('App\Support\Display', 'D')
@php $t = fn ($d) => $d ? D::local($d)->format('j M Y H:i:s') : D::DASH; @endphp
@section('content')
<div class="head">
    <div><h1>Data Log</h1></div>
    <nav class="seg" aria-label="Log type">
        <a @class(['on' => $tab === 'pembacaan']) href="{{ route('log') }}" @if ($tab === 'pembacaan') aria-current="page" @endif>Readings</a>
        <a @class(['on' => $tab === 'pesan']) href="{{ route('log', ['tab' => 'pesan']) }}" @if ($tab === 'pesan') aria-current="page" @endif>Incoming messages</a>
    </nav>
</div>

<form class="card" method="GET" action="{{ route('log') }}" style="margin-bottom:16px">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="form-row">
        <div class="field"><label for="tanggal">Date</label><input id="tanggal" type="date" name="tanggal" value="{{ $tanggal }}" placeholder="YYYY-MM-DD"></div>
        @if ($tab === 'pesan')
            <div class="field"><label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All</option>
                    @foreach (D::MESSAGE_STATUSES as $value => [, $label])<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
        @endif
        <button class="btn" type="submit">Apply</button>
        @if ($tanggal || $status)<a class="btn sec" href="{{ route('log', $tab === 'pesan' ? ['tab' => 'pesan'] : []) }}">Reset</a>@endif
    </div>
</form>

<div class="card">
<div class="tbl-wrap">
@if ($tab === 'pembacaan')
    <table class="tbl">
        <thead><tr>
            <th scope="col">Time</th>
            <th scope="col" class="r">Flow rate (m³/h)</th>
            <th scope="col" class="r">Velocity (m/s)</th>
            <th scope="col" class="r">Total forward (m³)</th>
            <th scope="col" class="r">Total reverse (m³)</th>
            <th scope="col" class="r">Signal quality</th>
            <th scope="col" class="r">Time ratio (%)</th>
            <th scope="col" class="r">Error code</th>
        </tr></thead>
        <tbody>
        @forelse ($rows as $r)
            <tr class="num">
                <td>{{ $t($r->measured_at) }}</td>
                <td class="r">{{ D::num($r->flow_rate, 2) }}</td>
                <td class="r">{{ D::num($r->velocity, 2) }}</td>
                <td class="r">{{ D::num($r->pos_total, 3) }}</td>
                <td class="r">{{ D::num($r->neg_total, 3) }}</td>
                <td class="r">{{ D::num($r->signal_quality, 0) }}</td>
                <td class="r">{{ D::num($r->time_ratio, 1) }}</td>
                <td class="r">{{ $r->error_code ?? D::DASH }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">{{ $tanggal ? 'No readings on this date.' : 'No readings yet.' }}</td></tr>
        @endforelse
        </tbody>
    </table>
@else
    <table class="tbl">
        <thead><tr><th scope="col">Received</th><th scope="col">Status</th><th scope="col">Reason</th><th scope="col">Message</th></tr></thead>
        <tbody>
        @forelse ($rows as $m)
            @php [$sc, $sl] = D::messageStatus($m->status); $payload = (string) $m->payload; @endphp
            <tr>
                <td class="num">{{ $t($m->received_at) }}</td>
                <td><span class="pill {{ $sc }}">{{ $sl }}</span></td>
                <td style="white-space:normal;min-width:200px">{{ D::messageReason($m->error) ?? D::DASH }}</td>
                <td style="max-width:420px">
                    <div class="muted" style="font-size:12px">{{ $m->topic }}</div>
                    <code title="{{ \Illuminate\Support\Str::limit($payload, 2000) }}" style="display:block;overflow:hidden;text-overflow:ellipsis;font-size:12px">{{ \Illuminate\Support\Str::limit($payload, 90) }}</code>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="empty">No messages found.</td></tr>
        @endforelse
        </tbody>
    </table>
@endif
</div>
    @if ($rows->previousPageUrl() || $rows->nextPageUrl())
        <div class="pager">
            @if ($rows->previousPageUrl())<a class="btn sec sm" href="{{ $rows->previousPageUrl() }}">← Newer</a>@endif
            <span class="muted">Page {{ $rows->currentPage() }}</span>
            @if ($rows->nextPageUrl())<a class="btn sec sm" href="{{ $rows->nextPageUrl() }}">Older →</a>@endif
        </div>
    @endif
</div>
@endsection
