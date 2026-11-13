@extends('layouts.app', ['title' => 'Alerts'])
@use('App\Support\Display', 'D')
@section('content')
<div class="head">
    <div><h1>Alerts</h1></div>
    <div class="actions">
        <nav class="seg" aria-label="Alert list">
            <a @class(['on' => $tab === 'aktif']) href="{{ route('alert') }}" @if ($tab === 'aktif') aria-current="page" @endif>Needs attention</a>
            <a @class(['on' => $tab === 'riwayat']) href="{{ route('alert', ['tab' => 'riwayat']) }}" @if ($tab === 'riwayat') aria-current="page" @endif>History</a>
        </nav>
        @if ($unseen > 0)
            <form method="POST" action="{{ route('alert.seenall') }}">@csrf<button class="btn sec" type="submit">Mark all as seen</button></form>
        @endif
    </div>
</div>
<div class="card">
    <div class="tbl-wrap">
    <table class="tbl">
        <thead><tr><th scope="col">Alert</th><th scope="col">When</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead>
        <tbody>
        @forelse ($alerts as $a)
            @php [$sc, $sl] = D::alertStatus($a); @endphp
            <tr>
                <td style="white-space:normal;min-width:260px">
                    <b>{{ $a->rule?->name ?? 'Alert' }}</b>
                    <div class="muted" style="font-size:13px;margin-top:2px">{{ D::alertDetail($a) }}</div>
                </td>
                <td>{{ D::alertDuration($a) }}</td>
                <td><span class="pill {{ $sc }}">{{ $sl }}</span></td>
                <td class="r">
                    @if (!$a->seen_at)
                        <form method="POST" action="{{ route('alert.seen', $a) }}">@csrf<button class="btn sec sm" type="submit">Mark as seen</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="empty">{{ $tab === 'aktif' ? 'Nothing needs attention. Everything looks fine.' : 'No alerts yet.' }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    @if ($alerts->previousPageUrl() || $alerts->nextPageUrl())
        <div class="pager">
            @if ($alerts->previousPageUrl())<a class="btn sec sm" href="{{ $alerts->previousPageUrl() }}">← Newer</a>@endif
            <span class="muted">Page {{ $alerts->currentPage() }}</span>
            @if ($alerts->nextPageUrl())<a class="btn sec sm" href="{{ $alerts->nextPageUrl() }}">Older →</a>@endif
        </div>
    @endif
</div>
@endsection
