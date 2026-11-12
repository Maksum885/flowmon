@extends('layouts.app', ['title' => 'Alert'])
@section('content')
<div class="head">
    <div><h1>Alert</h1><div class="sub">Peringatan otomatis dari aturan di Pengaturan. Dicek setiap menit.</div></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn {{ $tab === 'aktif' ? '' : 'sec' }}" href="{{ route('alert') }}">Perlu perhatian</a>
        <a class="btn {{ $tab === 'riwayat' ? '' : 'sec' }}" href="{{ route('alert', ['tab' => 'riwayat']) }}">Riwayat</a>
        <form method="POST" action="{{ route('alert.seenall') }}">@csrf<button class="btn sec" type="submit">Tandai semua dilihat</button></form>
    </div>
</div>
<div class="card" style="overflow-x:auto">
    <table class="tbl">
        <thead><tr><th>Alert</th><th>Mulai</th><th>Selesai</th><th>Status</th><th>Rincian</th><th></th></tr></thead>
        <tbody>
        @forelse ($alerts as $a)
            @php
                $d = is_array($a->detail) ? $a->detail : (json_decode((string) $a->detail, true) ?: []);
                $txt = collect($d)->map(fn ($v, $k) => $k.': '.(is_scalar($v) ? $v : json_encode($v)))->implode(' · ');
            @endphp
            <tr>
                <td><b>{{ $a->rule->name }}</b></td>
                <td>{{ $a->opened_at->copy()->setTimezone($tz)->format('d/m H:i') }}</td>
                <td>{{ $a->closed_at ? $a->closed_at->copy()->setTimezone($tz)->format('d/m H:i') : '—' }}</td>
                <td>
                    @if (!$a->closed_at)<span class="pill bad">Berlangsung</span>
                    @elseif (!$a->seen_at)<span class="pill warn">Selesai, belum dilihat</span>
                    @else<span class="pill ok">Selesai</span>@endif
                </td>
                <td style="white-space:normal;color:#9CBCCB;font-size:13px;max-width:360px">{{ $txt ?: '—' }}</td>
                <td>@if (!$a->seen_at)<form method="POST" action="{{ route('alert.seen', $a) }}">@csrf<button class="btn sec" style="min-height:34px;padding:0 14px" type="submit">Dilihat</button></form>@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" style="color:#9CBCCB">Tidak ada alert.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div style="display:flex;gap:10px;margin-top:14px">
        @if ($alerts->previousPageUrl())<a class="btn sec" href="{{ $alerts->previousPageUrl() }}">← Lebih baru</a>@endif
        @if ($alerts->nextPageUrl())<a class="btn sec" href="{{ $alerts->nextPageUrl() }}">Lebih lama →</a>@endif
    </div>
</div>
@endsection