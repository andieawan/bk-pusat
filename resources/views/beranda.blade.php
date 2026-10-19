@extends('layouts.app', ['title' => 'Ringkasan BK'])
@section('content')
@php($me = auth()->user())
<div class="head"><div><h1>Bimbingan Konseling</h1><small>Tahun ajaran {{ $tahun }} · @if(\App\Services\BkService::isCounselor($me))akses penuh Guru BK @elseif(\App\Services\BkService::seesAll($me))lihat semua kelas (baca saja) @else kelas wali Anda @endif</small></div></div>

<div class="quick bk-quick">
    @foreach(\App\Support\BkModules::all() as $j => $mod)
        <a class="quick-item" href="{{ route('catatan', $j) }}"><span class="bk-ic" aria-hidden="true">{{ $mod['icon'] }}</span>
            <span><b>{{ $mod['label'] }}</b><small>{{ $stat[$j]['total'] }} catatan @if($stat[$j]['proses'])· <span class="warn">{{ $stat[$j]['proses'] }} diproses</span>@endif</small></span></a>
    @endforeach
</div>

<div class="grid g2" style="align-items:start">
    <div class="card card-tight">
        <div style="padding:14px 16px;border-bottom:1px solid var(--line2)"><h2>Masih Diproses</h2><small>Kasus/pelanggaran yang belum selesai ditangani</small></div>
        @forelse($proses as $r)
            <a class="stu" href="{{ route('catatan', [$r->jenis, 'siswa' => $r->siswa_id]) }}" style="color:inherit">
                <span class="nm"><b>{{ $r->siswa->nama ?? '—' }}</b><small>{{ $r->kelas->nama ?? '-' }} · {{ \App\Support\BkModules::get($r->jenis)['label'] }} · {{ \App\Support\Dates::human($r->tanggal) }}</small></span>
                <span class="badge b-warn">Proses</span></a>
        @empty
            <div class="empty">Tidak ada yang sedang diproses. 👍</div>
        @endforelse
    </div>
    <div>
        <div class="card"><h2>Pelanggaran per Tingkat</h2>
            @php($maxL = max(1, (int) $perTingkat->max()))
            @foreach(['Ringan' => 'f-ok', 'Sedang' => 'f-warn', 'Berat' => 'f-bad'] as $lv => $cls)
                <div class="bar"><span class="d">{{ $lv }}</span><span class="t"><i class="{{ $cls }}" style="width:{{ round(($perTingkat[$lv] ?? 0) / $maxL * 100) }}%"></i></span><span class="p mono">{{ $perTingkat[$lv] ?? 0 }}</span></div>
            @endforeach
        </div>
        <div class="card card-tight">
            <div style="padding:14px 16px;border-bottom:1px solid var(--line2)"><h2>Catatan Terbaru</h2></div>
            @forelse($terbaru as $r)
                <a class="stu" href="{{ route('catatan', [$r->jenis, 'siswa' => $r->siswa_id]) }}" style="color:inherit">
                    <span class="nm"><b>{{ $r->siswa->nama ?? '—' }}</b><small>{{ \App\Support\BkModules::get($r->jenis)['icon'] }} {{ \App\Support\BkModules::get($r->jenis)['label'] }} · {{ $r->kelas->nama ?? '-' }} · {{ \App\Support\Dates::human($r->tanggal) }}</small>
                    <small>{{ \App\Services\BkService::isMasked($me, $r) ? '🔒 Kasus rahasia — ditangani BK' : \Illuminate\Support\Str::limit($r->judul, 70) }}</small></span></a>
            @empty
                <div class="empty">Belum ada catatan BK.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
