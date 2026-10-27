@extends('layouts.app', ['title' => 'PKL '.$s->nama])
@section('content')
<a class="back-link" href="{{ route('pkl') }}">‹ Siswa PKL</a>
<div class="head"><div><h1>{{ $s->nama }}</h1><small>{{ $s->nis }} · {{ $s->kelas->nama ?? '-' }} · riwayat PKL (dari aplikasi PKL)</small></div>
    <a class="btn" href="{{ route('catatan', ['kasus', 'siswa' => $s->id]) }}">Catatan BK</a></div>
@if($galat)<div class="alert alert-error"><span class="alert-txt">{{ $galat }}</span></div>@endif
@forelse($rows as $b)
    <div class="card">
        <h2>{{ $b['dudi'] ?? '-' }} <span class="badge {{ ($b['status'] ?? '') === 'aktif' ? 'b-ok' : (($b['status'] ?? '') === 'ditarik' ? 'b-bad' : '') }}">{{ ucfirst($b['status'] ?? '') }}</span></h2>
        <p class="mut">{{ $b['periode'] ?? '' }} · {{ \App\Support\Dates::human($b['mulai']) }} – {{ \App\Support\Dates::human($b['selesai']) }} · pembimbing sekolah {{ $b['guru_pembimbing'] ?? '-' }}, industri {{ $b['pembimbing_industri'] ?? '-' }}</p>
        <p>@include('pkl._rekap', ['b' => $b])</p>
        <p>@if(($b['jurnal_tertunda'] ?? 0) > 0)<span class="badge b-warn">Jurnal {{ $b['jurnal_tertunda'] }} hari belum diisi</span> @endif
            Nilai akhir: <b>{{ $b['nilai_akhir'] ?? '–' }}</b> {{ $b['predikat'] ?? '' }}
            @if(! empty($b['tautan'])) · <a href="{{ $b['tautan'] }}" target="_blank" rel="noopener">Buka di PKL ↗</a>@endif</p>
    </div>
@empty
    @unless($galat)<div class="card"><div class="empty">Siswa ini belum punya data PKL.</div></div>@endunless
@endforelse
@endsection
