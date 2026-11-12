@extends('layouts.app', ['title' => 'Log Data'])
@php $f = fn ($v, $d = 2) => $v === null ? '—' : number_format((float) $v, $d, ',', '.'); @endphp
@section('content')
<div class="head">
    <div><h1>Log Data</h1><div class="sub">Pembacaan meter dan pesan MQTT yang masuk.</div></div>
    <div style="display:flex;gap:8px">
        <a class="btn {{ $tab === 'pembacaan' ? '' : 'sec' }}" href="{{ route('log') }}">Pembacaan</a>
        <a class="btn {{ $tab === 'pesan' ? '' : 'sec' }}" href="{{ route('log', ['tab' => 'pesan']) }}">Pesan masuk</a>
    </div>
</div>

<form class="card" method="GET" action="{{ route('log') }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:16px">
    <input type="hidden" name="tab" value="{{ $tab }}">
    @if ($tab === 'pembacaan')
        <div><label style="display:block;font-size:13px;margin-bottom:6px">Tanggal</label><input type="date" name="tanggal" value="{{ $tanggal }}"></div>
    @else
        <div><label style="display:block;font-size:13px;margin-bottom:6px">Status</label>
            <select name="status">
                <option value="">Semua</option>
                @foreach (['baru', 'ok', 'diabaikan', 'gagal'] as $s)<option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>@endforeach
            </select></div>
    @endif
    <button class="btn" type="submit">Terapkan</button>
</form>

<div class="card" style="overflow-x:auto">
@if ($tab === 'pembacaan')
    <table class="tbl">
        <thead><tr><th>Waktu</th><th style="text-align:right">Aliran</th><th style="text-align:right">Kecepatan</th><th style="text-align:right">POS</th><th style="text-align:right">NEG</th><th style="text-align:right">Q</th><th style="text-align:right">Time ratio</th><th>Error</th></tr></thead>
        <tbody>
        @forelse ($rows as $r)
            <tr class="num">
                <td>{{ $r->measured_at->copy()->setTimezone($tz)->format('d/m H:i:s') }}</td>
                <td style="text-align:right">{{ $f($r->flow_rate) }}</td>
                <td style="text-align:right">{{ $f($r->velocity) }}</td>
                <td style="text-align:right">{{ $f($r->pos_total) }}</td>
                <td style="text-align:right">{{ $f($r->neg_total) }}</td>
                <td style="text-align:right">{{ $r->signal_quality ?? '—' }}</td>
                <td style="text-align:right">{{ $f($r->time_ratio, 1) }}</td>
                <td>{{ $r->error_code ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="color:#9CBCCB">Tidak ada pembacaan.</td></tr>
        @endforelse
        </tbody>
    </table>
@else
    @php $cols = $rows->isNotEmpty() ? array_keys($rows->first()->getAttributes()) : []; @endphp
    <table class="tbl">
        <thead><tr>@foreach ($cols as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($rows as $m)
            <tr>@foreach ($cols as $c)<td style="max-width:420px;overflow:hidden;text-overflow:ellipsis">{{ \Illuminate\Support\Str::limit(is_scalar($m->getAttribute($c)) ? (string) $m->getAttribute($c) : json_encode($m->getAttribute($c)), 90) }}</td>@endforeach</tr>
        @empty
            <tr><td style="color:#9CBCCB">Tidak ada pesan.</td></tr>
        @endforelse
        </tbody>
    </table>
@endif
    <div style="display:flex;gap:10px;margin-top:14px">
        @if ($rows->previousPageUrl())<a class="btn sec" href="{{ $rows->previousPageUrl() }}">← Lebih baru</a>@endif
        @if ($rows->nextPageUrl())<a class="btn sec" href="{{ $rows->nextPageUrl() }}">Lebih lama →</a>@endif
    </div>
</div>
@endsection