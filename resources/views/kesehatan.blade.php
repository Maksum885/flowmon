@extends('layouts.app', ['title' => 'Kesehatan'])
@php $f = fn ($v, $d = 1) => $v === null ? '—' : number_format((float) $v, $d, ',', '.'); @endphp
@section('content')
<div class="head"><div><h1>Kesehatan</h1><div class="sub">Kondisi meter dari pembacaan terakhir dan ringkasan 24 jam.</div></div></div>

<div class="grid4">
    <div class="card"><div class="lbl">Kualitas sinyal (Q)</div><div class="num" style="font-size:28px;margin-top:6px">{{ $last?->signal_quality ?? '—' }}</div><div class="sub">24 j: rata-rata {{ $f($s->q_avg) }} · terendah {{ $f($s->q_min, 0) }}</div></div>
    <div class="card"><div class="lbl">Sinyal hulu / hilir</div><div class="num" style="font-size:28px;margin-top:6px">{{ $f($last?->signal_up) }} / {{ $f($last?->signal_down) }}</div><div class="sub">dari pembacaan terakhir</div></div>
    <div class="card"><div class="lbl">Time ratio</div><div class="num" style="font-size:28px;margin-top:6px">{{ $f($last?->time_ratio) }} %</div><div class="sub">24 j: {{ $f($s->t_min) }} – {{ $f($s->t_max) }} %</div></div>
    <div class="card"><div class="lbl">Kode error</div><div class="num" style="font-size:28px;margin-top:6px">{{ $last?->error_code ?? '—' }}</div><div class="sub">24 j: {{ $s->errs }} pembacaan error · {{ $s->n }} pembacaan</div></div>
</div>

<div class="card" style="overflow-x:auto">
    <div class="lbl" style="margin-bottom:8px">Aturan alert</div>
    <table class="tbl">
        <thead><tr><th>Kode</th><th>Nama</th><th>Aktif</th><th>Berturut-turut</th><th>Parameter</th><th>Alert terbuka</th></tr></thead>
        <tbody>
        @foreach ($rules as $r)
            <tr>
                <td class="num">{{ $r->code }}</td>
                <td>{{ $r->name }}</td>
                <td><span class="pill {{ $r->enabled ? 'ok' : 'info' }}">{{ $r->enabled ? 'Aktif' : 'Mati' }}</span></td>
                <td>{{ $r->consecutive_count }}</td>
                <td class="num" style="font-size:12px;color:#9CBCCB">{{ is_array($r->params) ? json_encode($r->params) : $r->params }}</td>
                <td>{{ $r->open_count }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection