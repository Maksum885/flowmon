@extends('layouts.app', ['title' => 'Pengaturan'])
@section('content')
<div class="head"><div><h1>Pengaturan</h1><div class="sub">Jendela pemakaian, aturan alert, pemetaan data MQTT, dan akun.</div></div></div>
@if (session('status'))<div class="pill ok" style="white-space:normal;margin-bottom:16px">{{ session('status') }}</div>@endif

<form class="card" method="POST" action="{{ route('pengaturan.umum') }}" style="margin-bottom:16px;display:flex;flex-direction:column;gap:14px">
    @csrf
    <div class="lbl" style="font-size:16px">Umum</div>
    <div>
        <label style="display:block;font-weight:600;margin-bottom:6px">Jam mulai jendela pemakaian</label>
        <input type="time" name="window_start" value="{{ old('window_start', $windowStart) }}" required>
        @error('window_start')<div class="pill bad" style="margin-top:6px">{{ $message }}</div>@enderror
        <div class="sub">Jendela berjalan dari jam ini sampai jam yang sama keesokan harinya. Riwayat lama tidak diubah.</div>
    </div>
    <label style="display:flex;gap:10px;align-items:center"><input type="checkbox" name="net_acc" value="1" @checked($netAcc) style="min-height:auto"> Pakai pemeriksaan silang dengan register akumulasi neto (Net Acc)</label>
    <div>
        <label style="display:block;font-weight:600;margin-bottom:6px">Pemetaan pesan MQTT (JSON)</label>
        <textarea name="mapping" rows="8" style="width:100%;box-sizing:border-box;font-family:'IBM Plex Mono',monospace;font-size:13px;padding:12px;border-radius:12px;border:1px solid rgba(160,215,235,.28);background:rgba(255,255,255,.06);color:#EAF6FB">{{ old('mapping', $mapping) }}</textarea>
        @error('mapping')<div class="pill bad" style="margin-top:6px;white-space:normal">{{ $message }}</div>@enderror
        <div class="sub">Kunci = nama kolom tabel readings, nilai = jalur dengan titik di JSON pesan. Kosong ({}) berarti nama kunci pesan sama dengan nama kolom. Mulai ulang <code>flowmon:listen</code> setelah mengubah ini.</div>
    </div>
    <div><button class="btn" type="submit">Simpan</button></div>
</form>

<form class="card" method="POST" action="{{ route('pengaturan.alert') }}" style="margin-bottom:16px;overflow-x:auto">
    @csrf
    <div class="lbl" style="font-size:16px;margin-bottom:8px">Aturan alert</div>
    <table class="tbl">
        <thead><tr><th>Aturan</th><th>Aktif</th><th>Berturut-turut</th><th>Parameter</th></tr></thead>
        <tbody>
        @foreach ($rules as $r)
            <tr>
                <td><b>{{ $r->name }}</b><div class="sub num" style="font-size:12px">{{ $r->code }}</div></td>
                <td><input type="checkbox" name="rules[{{ $r->id }}][enabled]" value="1" @checked($r->enabled) style="min-height:auto"></td>
                <td><input type="number" min="1" max="100" name="rules[{{ $r->id }}][count]" value="{{ $r->consecutive_count }}" style="width:80px"></td>
                <td style="white-space:normal">
                    @forelse ($fields[$r->code] ?? [] as [$k, $label])
                        <label style="display:inline-block;margin:0 14px 6px 0;font-size:13px">{{ $label }}<br>
                            <input type="number" step="any" name="rules[{{ $r->id }}][p][{{ $k }}]" value="{{ $r->p[$k] ?? '' }}" style="width:130px">
                        </label>
                    @empty
                        <span class="sub">Tanpa parameter</span>
                    @endforelse
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div style="margin-top:14px"><button class="btn" type="submit">Simpan aturan</button></div>
</form>

<form class="card" method="POST" action="{{ route('pengaturan.sandi') }}" style="display:flex;flex-direction:column;gap:14px;max-width:460px">
    @csrf
    <div class="lbl" style="font-size:16px">Ganti kata sandi</div>
    <div><label style="display:block;margin-bottom:6px">Kata sandi saat ini</label><input type="password" name="current_password" autocomplete="current-password" required style="width:100%">@error('current_password')<div class="pill bad" style="margin-top:6px">{{ $message }}</div>@enderror</div>
    <div><label style="display:block;margin-bottom:6px">Kata sandi baru (min. 10 karakter)</label><input type="password" name="password" autocomplete="new-password" required style="width:100%">@error('password')<div class="pill bad" style="margin-top:6px">{{ $message }}</div>@enderror</div>
    <div><label style="display:block;margin-bottom:6px">Ulangi kata sandi baru</label><input type="password" name="password_confirmation" autocomplete="new-password" required style="width:100%"></div>
    <div><button class="btn" type="submit">Ganti kata sandi</button></div>
</form>
@endsection