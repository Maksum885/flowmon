<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? 'Flow Meter Monitor' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/flowmon.css') }}">
</head>
<body>
@php
    $nav = [
        ['ringkasan', 'Ringkasan', 'grid'],
        ['pemakaian', 'Pemakaian 09:00', 'drop'],
        ['grafik', 'Grafik Aliran', 'chart'],
        ['kesehatan', 'Kesehatan', 'pulse'],
        ['alert', 'Alert', 'bell'],
        ['log', 'Log Data', 'list'],
        ['pengaturan', 'Pengaturan', 'sliders'],
    ];
    $alertCount = \App\Models\Alert::where(fn ($q) => $q->whereNull('closed_at')->orWhereNull('seen_at'))->count();
@endphp
<div class="shell">
<aside class="side">
    <div class="brand">
        <span class="logo">@include('partials.icon', ['name' => 'logo', 'size' => 22])</span>
        <div>
            <div style="font-weight:800;font-size:16px;letter-spacing:-.01em">Flow Meter Monitoring</div>
        </div>
    </div>
    <nav class="nav">
        @foreach ($nav as [$route, $label, $icon])
            <a href="{{ route($route) }}" @class(['on' => request()->routeIs($route)])>
                @include('partials.icon', ['name' => $icon])
                {{ $label }}
                @if ($route === 'alert' && $alertCount > 0)
                    <span class="pill warn" style="margin-left:auto">{{ $alertCount }}</span>
                @endif
            </a>
        @endforeach
    </nav>
    <div class="sidefoot">
        @if (isset($runningVolume))
            <div class="mini">
                <div style="font-size:12px;color:#9CBCCB">Volume jendela berjalan</div>
                <div class="num" style="font-size:26px;margin:4px 0 2px">{{ number_format($runningVolume, 1, ',', '.') }} <span style="font-size:14px;font-weight:600">m³</span></div>
                <div style="font-size:12px;color:#9CBCCB">Sampai {{ $windowEndLabel }}</div>
            </div>
        @endif
        <div class="user">
            <span class="av">{{ strtoupper(substr(auth()->user()->username, 0, 2)) }}</span>
            <div style="line-height:1.3">
                <div style="font-weight:700;font-size:13px">{{ auth()->user()->username }}</div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="linkbtn" type="submit">Keluar</button></form>
            </div>
        </div>
    </div>
</aside>
<main class="main">
    <div class="top">
        <label class="search">@include('partials.icon', ['name' => 'search'])<input type="text" placeholder="Cari jendela, alert, halaman" aria-label="Cari"></label>
        <div style="display:flex;gap:10px;align-items:center">
            <a class="rb" href="{{ route('alert') }}" aria-label="Alert">@include('partials.icon', ['name' => 'bell', 'size' => 20])@if ($alertCount > 0)<span class="dot"></span>@endif</a>
            <span class="av" style="width:44px;height:44px;font-size:14px">{{ strtoupper(substr(auth()->user()->username, 0, 2)) }}</span>
        </div>
    </div>
    @yield('content')
</main>
</div>
</body>
</html>