@extends('layouts.app', ['title' => 'Impor catatan lama'])
@section('content')
<div class="head"><div><h1>Impor Catatan Lama</h1><small>Memasukkan catatan BK dari buku atau Excel sebelum aplikasi dipakai.</small></div></div>

@if(session('galat'))
    <div class="card"><h2>Baris yang perlu diperbaiki</h2><ul class="galat">@foreach(session('galat') as $g)<li>{{ $g }}</li>@endforeach</ul></div>
@endif

<div class="card">
    <h2>1. Unduh templat</h2>
    <p class="mut" style="margin:0 0 10px">Satu berkas berisi lembar PETUNJUK, satu lembar per jenis catatan, dan DAFTAR SISWA (NIS &amp; nama siswa aktif untuk disalin).</p>
    <a class="btn" href="{{ route('impor.templat') }}">Unduh templat (.xlsx)</a>
    <div class="scroll" style="margin-top:12px"><table class="tbl">
        <thead><tr><th>Lembar</th><th>Kolom</th></tr></thead>
        <tbody>@foreach($kolom as $k)<tr><td><b>{{ $k['label'] }}</b></td><td>{{ implode(' · ', $k['kolom']) }}</td></tr>@endforeach</tbody>
    </table></div>
</div>

<div class="card">
    <h2>2. Isi lalu unggah</h2>
    <ul>
        <li>Satu baris = satu catatan. NIS mencocokkan siswa; bila kosong, nama harus sama persis.</li>
        <li>Status kosong = <b>Selesai</b>. Catatan yang sama (siswa, tanggal, judul) yang sudah ada dilewati, jadi aman diulang.</li>
        <li>Bila ada satu baris salah, tidak ada yang disimpan. Tekan <b>Periksa saja</b> dulu.</li>
    </ul>
    <form method="post" action="{{ route('impor.unggah') }}" enctype="multipart/form-data" class="fields" style="margin-top:10px">
        @csrf
        <div style="grid-column:span 2"><label for="berkas">Berkas templat (.xlsx)</label><input id="berkas" type="file" name="berkas" accept=".xlsx" required></div>
        <div class="row" style="align-self:end"><button class="btn" name="uji" value="1">Periksa saja</button><button class="btn btn-pri" name="uji" value="0">Impor</button></div>
    </form>
</div>
@endsection
