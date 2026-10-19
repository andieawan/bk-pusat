@extends('layouts.app', ['title' => 'Presensi BK'])
@section('content')
<div class="head"><div><h1>Presensi BK</h1><small>Rekap ketidakhadiran dibaca dari Absensi; input manual dan tautan ketua kelas ditulis ke Absensi.</small></div></div>
@if($galat)<div class="alert alert-error"><span class="alert-txt">{{ $galat }}</span></div>@endif
<form method="get" action="{{ route('presensi') }}" class="card">
    <div class="fields">
        <div><label for="kelas">Kelas</label><select id="kelas" name="kelas" data-auto>@foreach($kelas as $c)<option value="{{ $c->id }}" @selected($k && $c->id === $k->id)>{{ $c->nama }}</option>@endforeach</select></div>
        <div><label for="dari">Dari</label><input id="dari" type="date" name="dari" value="{{ $dari }}"></div>
        <div><label for="sampai">Sampai</label><input id="sampai" type="date" name="sampai" value="{{ $sampai }}"></div>
        <div style="align-self:end"><button class="btn">Tampilkan</button></div>
    </div>
</form>
@if($k)
<form method="post" action="{{ route('presensi.manual') }}" class="card">@csrf
    <h2>Input Absensi Manual (BK)</h2>
    <div class="fields">
        <div><label for="siswa">Siswa</label><select id="siswa" name="siswa">@foreach($siswa as $s)<option value="{{ $s->id }}" @selected(old('siswa') == $s->id)>{{ $s->nama }} ({{ $s->nis }})</option>@endforeach</select></div>
        <div><label for="status">Status</label><select id="status" name="status"><option value="I">Izin (I)</option><option value="S">Sakit (S)</option><option value="A">Alpa (A)</option><option value="H">Hadir (H)</option></select></div>
        <div><label for="tanggal">Tanggal</label><input id="tanggal" type="date" name="tanggal" value="{{ old('tanggal', $hari) }}" max="{{ $hari }}" required></div>
        <div style="grid-column:span 2"><label for="catatan">Catatan</label><input id="catatan" type="text" name="catatan" maxlength="200" value="{{ old('catatan') }}" placeholder="mis. Konseling BK: izin dispensasi pendampingan keluarga"></div>
        <div><button class="btn btn-pri" @disabled($siswa->isEmpty())>Simpan Absensi BK</button></div>
    </div>
</form>

<div class="card">
    <h2>Tautan Ketua Kelas — {{ $k->nama }}</h2>
    <p class="mut" style="font-size:12px">Tautan sementara (maks. 24 jam) untuk ketua kelas mengisi absensi harian di aplikasi Absensi, mis. saat wali kelas berhalangan.</p>
    @if($tokenBaru)
        <div class="share-box">
            <b>✓ Tautan siap dibagikan ke ketua kelas</b>
            <code class="link" id="lnk">{{ $tokenBaru }}</code>
            <div class="row">
                <button type="button" class="btn btn-sm" data-copy="#lnk">Salin Tautan</button>
                <a class="btn btn-sm btn-wa" target="_blank" rel="noopener" href="{{ \App\Support\WhatsApp::share('Link absensi harian kelas '.$k->nama.' (berlaku sementara): '.$tokenBaru) }}">Kirim via WhatsApp</a>
            </div>
        </div>
    @endif
    <form method="post" action="{{ route('presensi.delegasi') }}" class="row">@csrf <input type="hidden" name="kelas" value="{{ $k->id }}">
        <select name="jam" style="width:auto" aria-label="Masa berlaku">@foreach([1, 3, 6, 12, 24] as $h)<option value="{{ $h }}" @selected($h === 24)>Berlaku {{ $h }} jam</option>@endforeach</select>
        <button class="btn btn-pri">Buat Tautan Delegasi</button>
    </form>
    @if(count($tautan))
        <div style="margin-top:12px"><small class="mut">Tautan yang masih aktif:</small>
        @foreach($tautan as $t)
            <div class="row" style="justify-content:space-between;border-top:1px solid var(--line2);padding:6px 0">
                <span><code class="mono" style="font-size:11px">{{ substr($t['token'] ?? '', 0, 12) }}…</code> <small>berlaku sampai {{ $t['berlaku_sampai'] ?? '-' }}</small></span>
                <form method="post" action="{{ route('presensi.cabut') }}" class="inline" data-confirm="Cabut tautan ini? Ketua kelas tidak bisa memakainya lagi.">@csrf<input type="hidden" name="token" value="{{ $t['token'] ?? '' }}"><input type="hidden" name="kelas" value="{{ $k->id }}"><button class="btn btn-sm btn-danger">Cabut</button></form>
            </div>
        @endforeach</div>
    @endif
</div>

@if($rekap !== null)
<div class="card card-tight"><div style="padding:14px 16px"><h2>Rekap Ketidakhadiran Kelas</h2><small>{{ \App\Support\Dates::human($dari) }} – {{ \App\Support\Dates::human($sampai) }} · diurutkan dari ketidakhadiran terbanyak · batas peringatan alpa: {{ $batas }}</small></div>
    @if($rekap->isEmpty())<div class="empty">Tidak ada data.</div>@else
    <div class="scroll"><table class="tbl tbl-cards"><thead><tr><th>Nama</th><th>NIS</th><th class="c">H</th><th class="c">I</th><th class="c">S</th><th class="c">A</th><th class="c">Total Absen</th><th class="r">Aksi</th></tr></thead><tbody>
    @foreach($rekap as $r)
        @php($s = $peta[$r['siswa_uuid'] ?? ''] ?? null)
        <tr><td class="card-title"><b>{{ $r['nama'] }}</b></td><td class="mono mut" data-label="NIS">{{ $r['nis'] }}</td><td class="c mono" data-label="Hadir">{{ $r['hadir'] }}</td><td class="c mono" data-label="Izin">{{ $r['izin'] }}</td><td class="c mono warn" data-label="Sakit">{{ $r['sakit'] }}</td><td class="c mono @if($r['alpa'] >= $batas) bad @endif" data-label="Alpa">{{ $r['alpa'] }}</td><td class="c mono" data-label="Total absen"><b>{{ $r['absen'] }}</b></td>
            <td class="r">@if($s)<a href="{{ route('catatan', ['kasus', 'siswa' => $s->id]) }}">Catatan BK</a> · @include('surat._menu', ['s' => $s])@endif</td></tr>
    @endforeach
    </tbody></table></div>@endif
</div>
@endif
@endif
@endsection
