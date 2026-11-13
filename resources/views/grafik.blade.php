@extends('layouts.app', ['title' => 'Flow Charts'])
@section('content')
<div class="head">
    <div><h1>Flow Charts</h1><div class="sub">Averaged every {{ $cfg['every'] }}.</div></div>
    <nav class="seg" aria-label="Time range">
        @foreach ($ranges as $k => $label)
            <a @class(['on' => $key === $k]) href="{{ route('grafik', ['r' => $k]) }}" @if ($key === $k) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
</div>
<div class="grid2">
    @foreach ($charts as $c)
        <div class="card">
            @include('partials.linechart', ['c' => $c, 't0' => $t0, 't1' => $t1, 'bsec' => $cfg['bsec'], 'xfmt' => $cfg['fmt'], 'tz' => $tz])
        </div>
    @endforeach
</div>
@endsection
