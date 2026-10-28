@extends('layouts.app', ['title' => 'Presensi BK'])
@section('content')
<div class="head"><div><h1>Presensi BK</h1><small>Absensi harian per kelas, tautan ketua kelas, dan rekap ketidakhadiran — semuanya tersimpan di aplikasi Absensi.</small></div></div>
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
<div class="card card-tight" id="absen-kelas">
    <div style="padding:14px 16px;border-bottom:1px solid var(--line2)">
        <h2>Absensi Harian — {{ $k->nama }}</h2>
        <small>Isi semua siswa sekaligus, lalu simpan sekali. Tersimpan langsung di aplikasi Absensi; status yang sudah diisi wali kelas/guru ikut tampil di sini.</small>
        <form method="get" action="{{ route('presensi') }}" class="row" style="margin-top:10px;gap:8px;flex-wrap:wrap">
            <input type="hidden" name="kelas" value="{{ $k->id }}"><input type="hidden" name="dari" value="{{ $dari }}"><input type="hidden" name="sampai" value="{{ $sampai }}">
            <label for="tgl-absen" class="mut" style="margin:0;align-self:center">Tanggal</label>
            <input id="tgl-absen" type="date" name="tanggal" value="{{ $tanggal }}" max="{{ $hari }}" style="width:auto" data-auto>
            @if($tanggal !== $hari)<a class="btn btn-sm" href="{{ route('presensi', ['kelas' => $k->id, 'dari' => $dari, 'sampai' => $sampai]) }}#absen-kelas">Hari ini</a>@endif
            <noscript><button class="btn btn-sm">Tampilkan</button></noscript>
        </form>
    </div>
    @if($harian === null)
        <div class="empty">Absensi tidak bisa dibaca sekarang, jadi lembar absensi belum bisa diisi.</div>
    @elseif($siswa->isEmpty())
        <div class="empty">Tidak ada siswa aktif di kelas ini.</div>
    @else
        @php($terisi = $harian->filter(fn ($h) => ! empty($h['status']))->count())
        @if($terisi)<div class="alert alert-info" style="margin:12px 16px 0"><span class="alert-ic" aria-hidden="true">i</span><span class="alert-txt"><b>{{ $terisi }} dari {{ $siswa->count() }} siswa sudah diisi</b> untuk tanggal ini. Ubah yang perlu saja — siswa yang tidak diubah tidak ditimpa.</span></div>@endif
        <form method="post" action="{{ route('presensi.kelas') }}" data-unsaved>
            @csrf <input type="hidden" name="kelas" value="{{ $k->id }}"><input type="hidden" name="tanggal" value="{{ $tanggal }}">
            <div class="list-tools">
                <button type="button" class="btn btn-sm" data-setall="H">✓ Tandai semua Hadir</button>
                <span class="legend" aria-hidden="true"><i class="lg H">H</i>Hadir <i class="lg I">I</i>Izin <i class="lg S">S</i>Sakit <i class="lg A">A</i>Alpa</span>
            </div>
            @foreach($siswa as $i => $s)
                @php($cur = $harian[$s->pusat_uuid] ?? null)
                @php($lama = $cur['status'] ?? '')
                @php($st = old("status.{$s->id}", $lama ?: 'H'))
                @php($note = old("catatan.{$s->id}", $cur['catatan'] ?? ''))
                <div class="stu stu-att" data-row>
                    <span class="no mono">{{ $i + 1 }}</span>
                    <div class="nm"><b>{{ $s->nama }}</b><small>{{ $s->nis }}@if($lama === '') · belum diisi @endif</small></div>
                    <div class="hisa" role="radiogroup" aria-label="Status kehadiran {{ $s->nama }}">
                        @foreach(['H' => 'Hadir', 'I' => 'Izin', 'S' => 'Sakit', 'A' => 'Alpa'] as $kd => $lbl)
                            <label title="{{ $lbl }}"><input type="radio" name="status[{{ $s->id }}]" value="{{ $kd }}" data-status="{{ $kd }}" @checked($st === $kd) aria-label="{{ $lbl }}"><span class="{{ $kd }}" aria-hidden="true">{{ $kd }}</span></label>
                        @endforeach
                    </div>
                    <button type="button" @class(['note-toggle', 'has-note' => $note !== '']) data-note-toggle aria-expanded="{{ $note !== '' ? 'true' : 'false' }}" aria-controls="note-{{ $s->id }}" title="Catatan">✎<span class="sr">Catatan untuk {{ $s->nama }}</span></button>
                    <input id="note-{{ $s->id }}" @class(['note', 'note-open' => $note !== '']) type="text" name="catatan[{{ $s->id }}]" value="{{ $note }}" placeholder="Catatan, mis. dispensasi lomba / pendampingan BK" maxlength="200" aria-label="Catatan {{ $s->nama }}">
                    <input type="hidden" name="lama[{{ $s->id }}]" value="{{ $lama }}"><input type="hidden" name="lama_catatan[{{ $s->id }}]" value="{{ $cur['catatan'] ?? '' }}">
                </div>
            @endforeach
            <div class="sticky-save">
                <div id="recount" class="recount" aria-live="polite"><span class="rc H">H <b data-c="H">0</b></span><span class="rc I">I <b data-c="I">0</b></span><span class="rc S">S <b data-c="S">0</b></span><span class="rc A">A <b data-c="A">0</b></span><small class="hide-sm">dari {{ $siswa->count() }} siswa</small></div>
                <button class="btn btn-pri" data-busy="Menyimpan…">Simpan Absensi Kelas</button>
            </div>
        </form>
    @endif
</div>

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
