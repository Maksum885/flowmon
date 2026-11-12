<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login</title>
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
        </div>
    </div>
    <form class="card" method="POST" action="{{ url('/login') }}" style="padding:28px;display:flex;flex-direction:column;gap:16px;border-radius:26px">
        @csrf
        <div>
            <div style="font-size:20px;font-weight:700">Masuk</div>
            <div class="sub" style="margin-top:2px">Pantau volume meter dari jendela 09:00 ke 09:00.</div>
        </div>
        @error('username')<div class="pill bad" style="white-space:normal">{{ $message }}</div>@enderror
        <div><label for="u" style="display:block;font-weight:600;margin-bottom:6px">Nama pengguna</label><input id="u" name="username" type="text" value="{{ old('username') }}" autocomplete="username" required style="width:100%"></div>
        <div><label for="p" style="display:block;font-weight:600;margin-bottom:6px">Kata sandi</label><input id="p" name="password" type="password" autocomplete="current-password" required style="width:100%"></div>
        <button class="btn" type="submit">Masuk</button>
    </form>
</div>
</div>
</body>
</html>