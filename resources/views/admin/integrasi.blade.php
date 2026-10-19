@extends('layouts.app', ['title' => 'Integrasi'])
@section('content')
<div class="head"><div><h1>Integrasi</h1><small>Sambungan ke Pusat Sekolah (akun & data induk) dan Absensi (kehadiran).</small></div></div>
@if($perluSinkron)<div class="alert alert-warn"><span class="alert-txt">Ada perubahan dari Pusat yang belum lengkap (mis. siswa dipindah ke kelas baru yang belum tersalin). Klik <b>Sinkron sekarang</b>; bila tidak, sinkron berjalan otomatis dalam satu jam.</span></div>@endif
<div class="card"><h2>Pusat Sekolah</h2>
    <p>Status: @if($pusatAktif && $siap)<span class="badge b-ok">Aktif</span>@elseif($pusatAktif)<span class="badge b-warn">Aktif, database belum diperbarui</span>@else<span class="badge">Nonaktif (PUSAT_ENABLED=false)</span>@endif</p>
    <table class="kv"><tr><td>Alamat Pusat</td><td>{{ $pusatUrl ?: '-' }}</td></tr>
    <tr><td>Redirect URI</td><td><code>{{ $callback }}</code></td></tr>
    <tr><td>URL webhook</td><td><code>{{ $webhook }}</code></td></tr>
    <tr><td>Tahun ajaran aktif</td><td>{{ $tahun ?: '-' }}</td></tr>
    <tr><td>Sinkron terakhir</td><td>{{ $terakhir ?: '-' }}</td></tr>
    <tr><td>Data tersalin</td><td>{{ $jumlah['kelas'] }} kelas · {{ $jumlah['siswa'] }} siswa aktif</td></tr></table>
    <div class="row" style="margin-top:10px">
        <form method="post" action="{{ route('admin.integrasi.pusat') }}">@csrf<button class="btn">Uji koneksi</button></form>
        <form method="post" action="{{ route('admin.integrasi.sinkron') }}">@csrf<button class="btn btn-pri" data-busy="Menyinkron…">Sinkron sekarang</button></form>
    </div>
    @if(session('hasil'))<pre class="mono" style="font-size:12px;margin-top:10px;white-space:pre-wrap">{{ json_encode(session('hasil'), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>@endif
</div>
<div class="card"><h2>Absensi</h2>
    <p>Status: @if($absensiAktif)<span class="badge b-ok">Kunci API terisi</span>@else<span class="badge b-warn">Belum diatur</span>@endif</p>
    <table class="kv"><tr><td>Alamat Absensi</td><td>{{ $absensiUrl ?: '-' }}</td></tr>
    <tr><td>Izin kunci yang diperlukan</td><td><code>kehadiran:baca</code>, <code>kehadiran:tulis</code></td></tr></table>
    <form method="post" action="{{ route('admin.integrasi.absensi') }}" style="margin-top:10px">@csrf<button class="btn">Uji koneksi Absensi</button></form>
</div>
@endsection
