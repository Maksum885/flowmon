@php
    $x0 = 50; $x1 = 700; $y0 = 20; $y1 = 170;
    $vals = array_column($c['points'], 1);
    $lo = $c['ymin'];
    $hi = $c['ymax'] ?? ($vals ? max(max($vals) * 1.15, $lo + 1) : $lo + 1);
    $sx = fn ($t) => $x0 + ($t - $t0) / max(1, $t1 - $t0) * ($x1 - $x0);
    $sy = fn ($v) => $y1 - ($v - $lo) / max(1e-9, $hi - $lo) * ($y1 - $y0);
    $tipDec = $c['tipdec'] ?? $c['dec'];
    $unit = $c['unit'] ?? '';
    $tip = fn ($t, $v) => \Carbon\Carbon::createFromTimestamp($t, $tz)->format('j M H:i') . ': '
        . number_format($v, $tipDec, '.', ',') . ($unit !== '' ? ($unit === '%' ? ' %' : ' ' . $unit) : '');
    $segs = []; $cur = []; $prev = null;
    foreach ($c['points'] as [$t, $v]) {
        if ($prev !== null && $t - $prev > $bsec * 3) { $segs[] = $cur; $cur = []; }
        $cur[] = [round($sx($t), 1), round($sy($v), 1), $tip($t, $v)];
        $prev = $t;
    }
    if ($cur) { $segs[] = $cur; }
@endphp
<div class="lbl" style="margin-bottom:6px">{{ $c['title'] }}</div>
@if ($vals)
<svg viewBox="0 0 740 210" style="width:100%;height:auto;display:block" role="img" aria-label="{{ $c['title'] }}">
    @for ($i = 0; $i <= 4; $i++)
        @php $v = $lo + ($hi - $lo) * $i / 4; @endphp
        <line x1="50" y1="{{ round($sy($v), 1) }}" x2="700" y2="{{ round($sy($v), 1) }}" stroke="rgba(160,215,235,.14)"/>
        <text x="42" y="{{ round($sy($v), 1) + 4 }}" font-size="11" fill="#A9C6D3" text-anchor="end">{{ number_format($v, $c['dec'], '.', ',') }}</text>
    @endfor
    @for ($i = 0; $i <= 4; $i++)
        @php $t = $t0 + ($t1 - $t0) * $i / 4; @endphp
        <text x="{{ round($sx($t), 1) }}" y="192" font-size="11" fill="#A9C6D3" text-anchor="middle">{{ \Carbon\Carbon::createFromTimestamp($t, $tz)->format($xfmt) }}</text>
    @endfor
    @foreach ($c['lines'] as [$lv, $ll, $lc])
        @if ($lv >= $lo && $lv <= $hi)
            <line x1="50" y1="{{ round($sy($lv), 1) }}" x2="700" y2="{{ round($sy($lv), 1) }}" stroke="{{ $lc }}" stroke-width="1.2" stroke-dasharray="5 4"/>
            <text x="698" y="{{ round($sy($lv), 1) - 4 }}" font-size="11" fill="{{ $lc }}" text-anchor="end">{{ $ll }}</text>
        @endif
    @endforeach
    @foreach ($segs as $s)
        @if (count($s) === 1)
            <circle cx="{{ $s[0][0] }}" cy="{{ $s[0][1] }}" r="2.5" fill="{{ $c['color'] }}"/>
        @else
            <polyline fill="none" stroke="{{ $c['color'] }}" stroke-width="2.2" stroke-linejoin="round" points="{{ collect($s)->map(fn ($p) => $p[0].','.$p[1])->implode(' ') }}"/>
        @endif
        {{-- invisible hover targets: the browser shows the time and value as a tooltip --}}
        @foreach ($s as $p)
            <circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="6" fill="transparent"><title>{{ $p[2] }}</title></circle>
        @endforeach
    @endforeach
</svg>
@else
<div class="empty">No data in this period.</div>
@endif
