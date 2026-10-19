@extends('layouts.app', ['title' => $m['label'].' · BK'])
@section('content')
@php
    $me = auth()->user();
    $konselor = \App\Services\BkService::isCounselor($me);
    $pre = $preselect ? \App\Models\Siswa::find($preselect) : null;
@endphp
<div class="head"><div><h1>{{ $m['icon'] }} {{ $m['label'] }}</h1><small>{{ $m['desc'] }}</small></div></div>

@if($kelasTulis->isNotEmpty())
<details class="card" @if($errors->any() || session('error') || ($pre && $kelasTulis->contains('id', $pre->kelas_id))) open @endif>
    <summary style="font-weight:700">+ Catat {{ $m['label'] }}</summary>
    <form method="post" action="{{ route('catatan.simpan', $jenis) }}" enctype="multipart/form-data" style="margin-top:12px" data-unsaved>@csrf
        @php($selClass = (int) old('pilih_kelas', $pre->kelas_id ?? ($kelasTulis->count() === 1 ? $kelasTulis->first()->id : 0)))
        <div class="fields">
            <div><label for="pick-class">Kelas</label>
                <select id="pick-class" name="pilih_kelas" data-pick-class="#pick-student">
                    @if($kelasTulis->count() > 1)<option value="">— pilih kelas —</option>@endif
                    @foreach($kelasTulis as $c)<option value="{{ $c->id }}" @selected($selClass === $c->id)>{{ $c->nama }}</option>@endforeach
                </select></div>
            <div style="grid-column:span 2"><label for="pick-student">Nama siswa</label>
                <select id="pick-student" name="siswa_id" required>
                    <option value="">— pilih siswa —</option>
                    @foreach($kelasTulis as $c)
                        @if(isset($pilihSiswa[$c->id]))
                        <optgroup label="{{ $c->nama }}" data-class="{{ $c->id }}">
                            @foreach($pilihSiswa[$c->id] as $s)<option value="{{ $s->id }}" @selected((int) old('siswa_id', $preselect) === $s->id)>{{ $s->nama }} ({{ $s->nis }})</option>@endforeach
                        </optgroup>
                        @endif
                    @endforeach
                </select></div>
        </div>
        <div style="margin-top:12px">@include('catatan._fields', ['rec' => null, 'px' => 'n-', 'hariIni' => $hariIni])</div>
        <button class="btn btn-pri" data-busy="Menyimpan…" style="margin-top:6px">Simpan</button>
    </form>
</details>
@elseif(! $konselor && ! $m['wali_tulis'])
    <p class="hint">{{ $m['label'] }} dicatat oleh Guru BK. Anda dapat melihat catatan {{ \App\Services\BkService::seesAll($me) ? 'semua kelas' : 'siswa kelas Anda' }}.</p>
@endif

<form method="get" action="{{ route('catatan', $jenis) }}" class="card filter-card">
    <div class="fields">
        <div><label for="q">Cari</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nama / NIS / {{ strtolower($m['judul'][0]) }}"></div>
        @if($kelasFilter->count() > 1)
            <div><label for="fc">Kelas</label><select id="fc" name="kelas" data-auto><option value="">Semua kelas</option>@foreach($kelasFilter as $c)<option value="{{ $c->id }}" @selected($fk === $c->id)>{{ $c->nama }}</option>@endforeach</select></div>
        @endif
        @if($m['status'])
            <div><label for="fs">Status</label><select id="fs" name="status" data-auto><option value="">Semua</option>@foreach(\App\Support\BkModules::STATUS as $o)<option @selected($status === $o)>{{ $o }}</option>@endforeach</select></div>
        @endif
        <div style="align-self:end" class="row"><button class="btn">Cari</button>@if($q !== '' || $fk || $status || $preselect)<a class="btn btn-sm" href="{{ route('catatan', $jenis) }}">Reset</a>@endif</div>
    </div>
    @if($pre)<input type="hidden" name="siswa" value="{{ $pre->id }}"><p class="hint" style="margin:8px 0 0">Menampilkan catatan <b>{{ $pre->nama }}</b> saja.</p>@endif
</form>

<div class="card card-tight" id="daftar-bk" data-swap>
    @forelse($catatan as $r)
        @php($masked = \App\Services\BkService::isMasked($me, $r))
        @php($canEdit = \App\Services\BkService::canEdit($me, $r))
        <article class="bk-item">
            <div class="bk-top">
                <div class="nm"><b>{{ $r->siswa->nama ?? '(siswa dihapus)' }}</b>
                    <small>{{ $r->kelas->nama ?? '-' }} · {{ \App\Support\Dates::human($r->tanggal) }}@if($r->pencatat) · dicatat {{ $r->pencatat->nama }}@endif</small></div>
                <div class="row" style="gap:6px">
                    @if($r->kategori && ! $masked)<span @class(['badge', 'b-ok' => in_array($r->kategori, ['Ringan', 'Sekolah', 'Kecamatan']), 'b-warn' => $r->kategori === 'Sedang', 'b-bad' => $r->kategori === 'Berat'])>{{ $r->kategori }}</span>@endif
                    @if($r->rahasia)<span class="badge">🔒 Rahasia</span>@endif
                    @if($r->status)<span @class(['badge', 'b-warn' => $r->status === 'Proses', 'b-ok' => $r->status === 'Selesai'])>{{ $r->status }}</span>@endif
                </div>
            </div>
            @if($masked)
                <p class="mut" style="margin:6px 0 0">🔒 Kasus rahasia — ditangani Guru BK. Detail tidak ditampilkan.</p>
            @else
                <p style="margin:6px 0 0"><b>{{ $r->judul }}</b>@if($r->x('peringkat')) — {{ $r->x('peringkat') }}@endif</p>
                @php($extras = collect($m['extra'])->except('peringkat')->filter(fn ($d, $k) => $r->x($k) !== ''))
                @if($extras->isNotEmpty())<small class="mut">@foreach($extras as $k => [$label]){{ $label }}: {{ $r->x($k) }}@if(! $loop->last) · @endif @endforeach</small>@endif
                @if($r->uraian)<p class="bk-txt">{{ $r->uraian }}</p>@endif
                @if($r->tindak_lanjut)<p class="bk-txt"><b>{{ $m['tindak'] }}:</b> {{ $r->tindak_lanjut }}</p>@endif
            @endif
            <div class="row" style="margin-top:8px;gap:6px">
                @if(! $masked && $r->berkas_path)<a class="btn btn-sm" target="_blank" href="{{ route('catatan.berkas', [$jenis, $r]) }}">📎 Lihat scan</a>@endif
                @if($r->siswa && ! $masked)@include('surat._menu', ['s' => $r->siswa])@endif
                @if($canEdit && $r->siswa)
                    @php($wa = \App\Support\WhatsApp::link($r->siswa->no_hp_ortu, \App\Support\WhatsApp::recordMessage($r, $me->nama)))
                    @if($wa)<a class="btn btn-sm btn-wa" target="_blank" rel="noopener" href="{{ $wa }}">WhatsApp ortu</a>@else<span class="btn btn-sm" aria-disabled="true" title="Nomor HP orang tua belum diisi di Pusat">No. HP ortu belum ada</span>@endif
                @endif
            </div>
            @if($canEdit)
                <details class="more"><summary>Ubah / hapus</summary>
                    <form method="post" action="{{ route('catatan.ubah', [$jenis, $r]) }}" enctype="multipart/form-data">@csrf @method('PUT')
                        @include('catatan._fields', ['rec' => $r, 'px' => 'e'.$r->id.'-', 'hariIni' => $hariIni])
                        <button class="btn btn-sm btn-pri" data-busy="Menyimpan…">Simpan perubahan</button>
                    </form>
                    <form method="post" action="{{ route('catatan.hapus', [$jenis, $r]) }}" data-confirm="Hapus catatan ini? Tidak dapat dibatalkan." style="margin-top:8px">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Hapus catatan</button></form>
                </details>
            @endif
        </article>
    @empty
        <div class="empty">Belum ada catatan {{ strtolower($m['label']) }}@if($q !== '' || $fk || $status) yang cocok dengan pencarian @endif.</div>
    @endforelse
    @include('partials.pager', ['p' => $catatan])
</div>
@endsection
