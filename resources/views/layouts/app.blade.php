<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ isset($title) ? $title . ' · ' : '' }}Flow Meter Monitoring</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/flowmon.css') }}">
</head>
<body>
@php
    $nav = [
        ['ringkasan', 'Overview', 'grid'],
        ['pemakaian', 'Daily Usage', 'drop'],
        ['grafik', 'Flow Charts', 'chart'],
        ['kesehatan', 'Meter Health', 'pulse'],
        ['alert', 'Alerts', 'bell'],
        ['log', 'Data Log', 'list'],
        ['pengaturan', 'Settings', 'sliders'],
    ];
    // One number for both the sidebar badge and the bell: alerts not yet seen
    $unseenAlerts = \App\Models\Alert::whereNull('seen_at')->count();
    $username = auth()->user()->username;
    $initials = strtoupper(substr($username, 0, 2));
@endphp
<div class="shell">
<aside class="side" id="sidebar" aria-label="Main navigation">
    <div class="brand">
        <span class="logo">@include('partials.icon', ['name' => 'logo', 'size' => 22])</span>
        <div>
            <div style="font-weight:800;font-size:16px;letter-spacing:-.01em">Flow Meter Monitoring</div>
            <div class="brand-sub">{{ config('app.location_name') }}</div>
        </div>
    </div>
    <nav class="nav">
        @foreach ($nav as [$route, $label, $icon])
            <a href="{{ route($route) }}" @class(['on' => request()->routeIs($route)]) @if (request()->routeIs($route)) aria-current="page" @endif>
                @include('partials.icon', ['name' => $icon])
                {{ $label }}
                @if ($route === 'alert' && $unseenAlerts > 0)
                    <span class="pill warn" style="margin-left:auto" aria-label="{{ $unseenAlerts }} unseen">{{ $unseenAlerts }}</span>
                @endif
            </a>
        @endforeach
    </nav>
</aside>
<div class="scrim" data-nav-close></div>
<main class="main">
    <div class="top">
        <div class="top-left">
            <button type="button" class="rb menu-btn" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false" data-nav-toggle>@include('partials.icon', ['name' => 'menu', 'size' => 20])</button>
        </div>
        <div class="top-right">
            <a class="rb" href="{{ route('alert') }}" aria-label="Alerts{{ $unseenAlerts ? ' (' . $unseenAlerts . ' unseen)' : '' }}">
                @include('partials.icon', ['name' => 'bell', 'size' => 20])
                @if ($unseenAlerts > 0)<span class="count" aria-hidden="true">{{ $unseenAlerts > 99 ? '99+' : $unseenAlerts }}</span>@endif
            </a>
            <details class="usermenu">
                <summary aria-label="Account menu"><span class="av">{{ $initials }}</span></summary>
                <div class="menu">
                    <div class="who"><div style="font-weight:700">{{ $username }}</div><div class="brand-sub">Administrator</div></div>
                    <a href="{{ route('pengaturan') }}#password">Change password</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Log out</button></form>
                </div>
            </details>
        </div>
    </div>
    @yield('content')
</main>
</div>

@if (session('status'))
    <div class="toast" role="status" data-toast>
        <span>{{ session('status') }}</span>
        <button type="button" aria-label="Dismiss" data-toast-close>&times;</button>
    </div>
@endif

<script>
(function () {
    var body = document.body;
    var toggle = document.querySelector('[data-nav-toggle]');
    function setNav(open) {
        body.classList.toggle('nav-open', open);
        if (toggle) { toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); }
    }
    if (toggle) { toggle.addEventListener('click', function () { setNav(!body.classList.contains('nav-open')); }); }
    document.querySelectorAll('[data-nav-close]').forEach(function (el) { el.addEventListener('click', function () { setNav(false); }); });

    // Close the account menu when clicking elsewhere or pressing Escape
    var menu = document.querySelector('.usermenu');
    document.addEventListener('click', function (e) { if (menu && menu.open && !menu.contains(e.target)) { menu.open = false; } });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        if (menu) { menu.open = false; }
        setNav(false);
    });

    var toast = document.querySelector('[data-toast]');
    if (toast) {
        var hide = function () { toast.classList.add('hide'); };
        toast.querySelector('[data-toast-close]').addEventListener('click', hide);
        setTimeout(hide, 6000);
    }
})();
</script>
</body>
</html>
