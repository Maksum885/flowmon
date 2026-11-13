@extends('layouts.app', ['title' => 'Daily Usage'])
@use('App\Support\Display', 'D')
@php
    $fd = fn ($d) => D::dateTime($d);
    $enDate = fn (?string $ymd) => $ymd && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)
        ? \Carbon\Carbon::createFromFormat('Y-m-d', $ymd)->format('D, j M Y') : '';
@endphp
@section('content')
<div class="head">
    <div>
        <h1>Daily Usage</h1>
        <div class="sub">Each day runs {{ $windowStart }} – {{ $windowStart }} the next day.</div>
    </div>
    <nav class="seg" aria-label="View">
        <a @class(['on' => $tab === 'jendela']) href="{{ route('pemakaian') }}" @if ($tab === 'jendela') aria-current="page" @endif>By day</a>
        <a @class(['on' => $tab === 'rentang']) href="{{ route('pemakaian', ['tab' => 'rentang']) }}" @if ($tab === 'rentang') aria-current="page" @endif>Custom range</a>
    </nav>
</div>

@if ($tab === 'jendela')
    <div class="card" style="margin-bottom:16px">
        <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th scope="col">Start</th><th scope="col">End</th><th scope="col" class="r">Volume (m³)</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @forelse ($windows as $w)
                @php [$qc, $ql, $qt] = D::quality($w->quality); @endphp
                <tr>
                    <td>{{ $fd($w->window_start_at) }}</td>
                    <td>{{ $fd($w->window_end_at) }}</td>
                    <td class="num r">{{ D::volume($w->volume_m3, false) }}</td>
                    <td><span class="pill {{ $qc }}" @if ($qt) title="{{ $qt }}" @endif>{{ $ql }}</span>@if ($qt)<span class="sr-only"> – {{ $qt }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No completed days yet. The first day appears here after it ends.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        @if ($windows->previousPageUrl() || $windows->nextPageUrl())
            <div class="pager">
                @if ($windows->previousPageUrl())<a class="btn sec sm" href="{{ $windows->previousPageUrl() }}">← Newer</a>@endif
                <span class="muted">Page {{ $windows->currentPage() }}</span>
                @if ($windows->nextPageUrl())<a class="btn sec sm" href="{{ $windows->nextPageUrl() }}">Older →</a>@endif
            </div>
        @endif
    </div>

    <form class="card" method="GET" action="{{ route('pemakaian.export') }}">
        <div class="lbl" style="margin-bottom:12px">Export to Excel (CSV)</div>
        <div class="form-row">
            <div class="field">
                <label for="xf">From</label>
                <input id="xf" type="date" name="xf" value="{{ old('xf', $exportFrom) }}" placeholder="YYYY-MM-DD" required data-date-hint="xf-hint">
                <span class="hint" id="xf-hint">{{ $enDate(old('xf', $exportFrom)) }}</span>
            </div>
            <div class="field">
                <label for="xt">To</label>
                <input id="xt" type="date" name="xt" value="{{ old('xt', $exportTo) }}" placeholder="YYYY-MM-DD" required data-date-hint="xt-hint">
                <span class="hint" id="xt-hint">{{ $enDate(old('xt', $exportTo)) }}</span>
            </div>
            <div style="padding-bottom:24px"><button class="btn" type="submit">Download CSV</button></div>
        </div>
        @if ($errors->has('xf') || $errors->has('xt'))
            <div class="err" role="alert">{{ $errors->first('xf') ?: $errors->first('xt') }}</div>
        @endif
    </form>
    <script>
    (function () {
        var fmt = new Intl.DateTimeFormat('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
        document.querySelectorAll('[data-date-hint]').forEach(function (input) {
            var hint = document.getElementById(input.getAttribute('data-date-hint'));
            input.addEventListener('change', function () {
                var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(input.value);
                hint.textContent = m ? fmt.format(new Date(+m[1], m[2] - 1, +m[3])).replace(/^(\w+) /, '$1, ') : '';
            });
        });
    })();
    </script>
@else
    <form class="card" method="GET" action="{{ route('pemakaian') }}" style="margin-bottom:16px">
        <input type="hidden" name="tab" value="rentang">
        <div class="form-row">
            <div class="field"><label for="from">From</label><input id="from" type="datetime-local" name="from" value="{{ $from }}" required></div>
            <div class="field"><label for="to">To</label><input id="to" type="datetime-local" name="to" value="{{ $to }}" required></div>
            <button class="btn" type="submit">Calculate</button>
        </div>
        <span class="hint">Up to 31 days. Times are local (24-hour).</span>
    </form>

    @if ($error)
        <div class="card"><div class="err" role="alert" style="margin:0">{{ $error }}</div></div>
    @elseif ($result)
        @php [$qc, $ql, $qt] = D::quality($result['quality'] ?? null); @endphp
        <div class="card">
            <div class="lbl">Volume {{ $fd($a) }} – {{ $fd($b) }}</div>
            <div class="num big">{{ D::volume($result['volume_m3'] ?? null, false) }} <small>m³</small></div>
            @if (!empty($result['quality']))
                <span class="pill {{ $qc }}" @if ($qt) title="{{ $qt }}" @endif>{{ $ql }}</span>
            @endif
        </div>
    @endif
@endif
@endsection
