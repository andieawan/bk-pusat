@extends('layouts.app', ['title' => 'Integrasi'])
@section('content')
<div class="head"><div><h1>Integrasi</h1><small>Sambungan ke Pusat Sekolah (akun & data induk) dan Absensi (kehadiran).</small></div></div>
@include('admin._pusat')
<div class="card"><h2>Absensi</h2>
    <p style="margin:6px 0 10px">Status: @if($absensiAktif)<span class="badge b-ok">Kunci API terisi</span>@else<span class="badge b-warn">Belum diatur — isi ABSENSI_URL dan ABSENSI_API_KEY di .env</span>@endif</p>
    <div class="scroll"><table class="tbl" style="font-size:14px">
        <tr><td style="width:190px" class="mut">Alamat Absensi</td><td>{{ $absensiUrl ?: '-' }}</td></tr>
        <tr><td class="mut">Izin kunci yang diperlukan</td><td><code>kehadiran:baca</code>, <code>kehadiran:tulis</code></td></tr>
    </table></div>
    <div class="row" style="margin-top:12px"><form method="post" action="{{ route('admin.integrasi.absensi') }}">@csrf<button class="btn">Uji koneksi Absensi</button></form></div>
</div>
<div class="card"><h2>PKL</h2>
    <p style="margin:6px 0 10px">Status: @if($pklAktif)<span class="badge b-ok">Kunci API terisi</span>@else<span class="badge b-warn">Belum diatur — isi PKL_URL dan PKL_API_KEY di .env</span>@endif</p>
    <div class="scroll"><table class="tbl" style="font-size:14px">
        <tr><td style="width:190px" class="mut">Alamat PKL</td><td>{{ $pklUrl ?: '-' }}</td></tr>
        <tr><td class="mut">Kunci</td><td>Sama dengan <code>PKL_API_KUNCI_BK</code> di .env aplikasi PKL (baca saja)</td></tr>
    </table></div>
    <div class="row" style="margin-top:12px"><form method="post" action="{{ route('admin.integrasi.pkl') }}">@csrf<button class="btn">Uji koneksi PKL</button></form></div>
</div>
@endsection
