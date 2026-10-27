@extends('layouts.app', ['title' => 'PKL'])
@section('content')
<div class="head"><div><h1>Siswa PKL Perlu Perhatian</h1><small>Dibaca dari aplikasi PKL: siswa yang sedang PKL dengan alpa/tanpa keterangan ≥ {{ $meta['batas'] ?? 3 }} hari atau jurnal tertunda.</small></div></div>
@if($galat)<div class="alert alert-error"><span class="alert-txt">{{ $galat }}</span></div>@endif
@unless($galat)<p class="mut">{{ count($rows) }} dari {{ $meta['jumlah_pkl_aktif'] ?? 0 }} siswa yang sedang PKL.</p>@endunless
<div class="card card-tight">
@forelse($rows as $b)
    @php($s = $peta[$b['siswa']['uuid'] ?? ''] ?? null)
    <div class="stu" style="align-items:flex-start">
        <span class="nm"><b>{{ $b['siswa']['nama'] ?? '-' }}</b> <small class="mono">{{ $b['siswa']['nis'] ?? '' }}</small>
            <small>{{ $b['siswa']['kelas'] ?? '' }} · {{ $b['dudi'] ?? '-' }} · pembimbing {{ $b['guru_pembimbing'] ?? '-' }}</small>
            <small>@include('pkl._rekap', ['b' => $b])</small>
            @foreach($b['alasan'] ?? [] as $a)<small class="bad">⚠ {{ $a }}</small>@endforeach
        </span>
        <span class="row" style="flex-wrap:wrap;justify-content:flex-end">
            @if($s)<a class="btn btn-sm" href="{{ route('pkl.siswa', $s) }}">Riwayat PKL</a><a class="btn btn-sm" href="{{ route('catatan', ['kasus', 'siswa' => $s->id]) }}">Catatan BK</a>@endif
            @if(! empty($b['tautan']))<a class="btn btn-sm" href="{{ $b['tautan'] }}" target="_blank" rel="noopener">Buka di PKL ↗</a>@endif
        </span>
    </div>
@empty
    <div class="empty">{{ $galat ? 'Data PKL tidak dapat dibaca.' : 'Tidak ada siswa PKL yang perlu perhatian. 👍' }}</div>
@endforelse
</div>
@endsection
