@extends('layouts.app', ['title' => 'Settings'])
@use('App\Support\AlertRuleCatalog', 'Rules')
@section('content')
<div class="head">
    <div><h1>Settings</h1></div>
    <nav class="seg" aria-label="Settings sections">
        <a href="#general">General</a>
        <a href="#alerts">Alert rules</a>
        <a href="#password">Account</a>
        <a href="#advanced">Advanced</a>
    </nav>
</div>

{{-- General --}}
<form class="card section" id="general" method="POST" action="{{ route('pengaturan.umum') }}">
    @csrf
    <h2>General</h2>
    <p class="section-sub">Each day's usage is counted from this time until the same time the next day.</p>
    <div class="form-row">
        <div class="field">
            <label for="window_start">Day start time</label>
            <input id="window_start" type="time" name="window_start" value="{{ old('window_start', $windowStart) }}" required aria-describedby="window_start_hint">
            <span class="hint" id="window_start_hint">24-hour time, e.g. 09:00. Days already calculated are not changed.</span>
        </div>
        <div style="padding-bottom:24px"><button class="btn" type="submit">Save</button></div>
    </div>
    @error('window_start')<div class="err" role="alert">{{ $message }}</div>@enderror
</form>

{{-- Alert rules --}}
<form class="card section" id="alerts" method="POST" action="{{ route('pengaturan.alert') }}">
    @csrf
    <h2>Alert rules</h2>
    <p class="section-sub">Choose which checks are on and when they should alert you.</p>
    @foreach ($rules as $r)
        @php
            $fields = Rules::fields($r->code);
            $usesCount = Rules::usesCount($r->code);
            $desc = Rules::description($r->code);
        @endphp
        <div class="rule">
            <div>
                <div style="font-weight:700">{{ $r->name }}</div>
                @if ($desc)<div class="muted" style="font-size:13px;margin-top:2px">{{ $desc }}</div>@endif
            </div>
            <div class="params">
                @foreach ($fields as [$k, $label, $unit])
                    <label>
                        <span class="l">{{ $label }}</span>
                        <span class="unit-input">
                            <input type="number" step="any" name="rules[{{ $r->id }}][p][{{ $k }}]" value="{{ $r->p[$k] ?? '' }}" @if ($r->code === 'weak_signal') placeholder="Off" @endif>
                            @if ($unit !== '')<span>{{ $unit }}</span>@endif
                        </span>
                    </label>
                @endforeach
                @if ($usesCount)
                    <label>
                        <span class="l">After</span>
                        <span class="unit-input">
                            <input type="number" min="1" max="100" name="rules[{{ $r->id }}][count]" value="{{ $r->consecutive_count }}">
                            <span>readings in a row</span>
                        </span>
                    </label>
                @else
                    <input type="hidden" name="rules[{{ $r->id }}][count]" value="{{ $r->consecutive_count }}">
                @endif
            </div>
            <label class="check">
                <input type="checkbox" name="rules[{{ $r->id }}][enabled]" value="1" @checked($r->enabled)>
                <span>On</span>
            </label>
        </div>
    @endforeach
    <div style="margin-top:16px"><button class="btn" type="submit">Save</button></div>
</form>

{{-- Account --}}
<form class="card section" id="password" method="POST" action="{{ route('pengaturan.sandi') }}">
    @csrf
    <h2>Change password</h2>
    <p class="section-sub">Signed in as <b>{{ auth()->user()->username }}</b>.</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;max-width:900px">
        <div class="field">
            <label for="current_password">Current password</label>
            <input id="current_password" type="password" name="current_password" autocomplete="current-password" required style="width:100%">
            @error('current_password')<div class="err" role="alert">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="new_password">New password</label>
            <input id="new_password" type="password" name="password" autocomplete="new-password" required minlength="10" style="width:100%" aria-describedby="new_password_hint">
            <span class="hint" id="new_password_hint">At least 10 characters.</span>
            @error('password')<div class="err" role="alert">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="password_confirmation">Repeat new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required style="width:100%">
        </div>
    </div>
    <div style="margin-top:16px"><button class="btn" type="submit">Save</button></div>
</form>

{{-- Advanced --}}
<details class="card section adv" id="advanced" @if ($errors->has('mapping')) open @endif>
    <summary>Advanced</summary>
    <div class="warnline">Only change if instructed by your technician.</div>
    <form method="POST" action="{{ route('pengaturan.umum') }}" style="display:flex;flex-direction:column;gap:16px">
        @csrf
        <input type="hidden" name="net_acc" value="0">
        <label class="check">
            <input type="checkbox" name="net_acc" value="1" @checked($netAcc)>
            <span>Cross-check volume with the meter's net total (Net Acc)</span>
        </label>
        <div class="field">
            <label for="mapping">MQTT message mapping (JSON)</label>
            <textarea id="mapping" name="mapping" rows="8" style="font-family:'IBM Plex Mono',monospace;font-size:13px">{{ old('mapping', $mapping) }}</textarea>
            @error('mapping')<div class="err" role="alert">{{ $message }}</div>@enderror
        </div>
        <div><button class="btn" type="submit">Save</button></div>
    </form>
</details>
<script>
(function () {
    var adv = document.getElementById('advanced');
    function sync() { if (location.hash === '#advanced') { adv.open = true; } }
    sync();
    window.addEventListener('hashchange', sync);
})();
</script>
@endsection
