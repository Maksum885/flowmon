@extends('layouts.app', ['title' => 'Grafik Aliran'])
@section('content')
<div class="head">
    <div><h1>Grafik Aliran</h1><div class="sub">Rata-rata per {{ $cfg['bucket'] === '5 minutes' ? '5 menit' : ($cfg['bucket'] === '1 hour' ? '1 jam' : '3 jam') }}. Garis putus-putus pada data berarti ada celah.</div></div>
    <div style="display:flex;gap:8px">
        @foreach ($ranges as $k => $label)
            <a class="btn {{ $key === $k ? '' : 'sec' }}" href="{{ route('grafik', ['r' => $k]) }}">{{ $label }}</a>
        @endforeach
    </div>
</div>
<div class="grid2">
    @foreach ($charts as $c)
        <div class="card">
            @include('partials.linechart', ['c' => $c, 't0' => $t0, 't1' => $t1, 'bsec' => $cfg['bsec'], 'xfmt' => $cfg['fmt'], 'tz' => $tz])
        </div>
    @endforeach
</div>
@endsection