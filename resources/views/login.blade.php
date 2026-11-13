<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · Flow Meter Monitoring</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/flowmon.css') }}">
</head>
<body>
<div class="login-wrap">
<div style="width:100%;max-width:420px">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:24px">
        <span class="logo">@include('partials.icon', ['name' => 'logo', 'size' => 22])</span>
        <div>
            <div style="font-size:24px;font-weight:800;letter-spacing:-.02em">Flow Meter Monitoring</div>
            <div class="muted" style="font-size:14px;margin-top:2px">{{ config('app.location_name') }}</div>
        </div>
    </div>
    <form class="card" method="POST" action="{{ url('/login') }}" style="padding:28px;display:flex;flex-direction:column;gap:16px;border-radius:26px" novalidate>
        @csrf
        <div style="font-size:20px;font-weight:700">Sign in</div>
        @if ($errors->any())
            <div class="pill bad" role="alert" style="white-space:normal;padding:10px 14px;font-size:13px">{{ $errors->first() }}</div>
        @endif
        <div class="field">
            <label for="u">Username</label>
            <input id="u" name="username" type="text" value="{{ old('username') }}" autocomplete="username" autocapitalize="none" required autofocus style="width:100%">
        </div>
        <div class="field">
            <label for="p">Password</label>
            <div class="pw-wrap">
                <input id="p" name="password" type="password" autocomplete="current-password" required>
                <button type="button" class="pw-toggle" aria-controls="p" aria-pressed="false" data-pw-toggle>Show</button>
            </div>
        </div>
        <button class="btn" type="submit">Sign in</button>
    </form>
</div>
</div>
<script>
(function () {
    var btn = document.querySelector('[data-pw-toggle]');
    var input = document.getElementById('p');
    btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'Hide' : 'Show';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
    btn.setAttribute('aria-label', 'Show password');
})();
</script>
</body>
</html>
