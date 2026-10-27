@php($r = $b['rekap'] ?? [])
<span class="lg H" title="Hadir">H {{ $r['H'] ?? 0 }}</span> <span class="lg I" title="Izin">I {{ $r['I'] ?? 0 }}</span> <span class="lg S" title="Sakit">S {{ $r['S'] ?? 0 }}</span> <span class="lg A" title="Alpa">A {{ $r['A'] ?? 0 }}</span>
@if(($r['kosong'] ?? 0) > 0)<span class="badge b-warn" title="Hari kerja tanpa catatan absen">{{ $r['kosong'] }} tanpa keterangan</span>@endif
@if(($r['T'] ?? 0) > 0)<span class="badge" title="Terlambat">{{ $r['T'] }}× terlambat</span>@endif
<small class="mut">· {{ ($r['persen'] ?? null) === null ? '-' : $r['persen'].'%' }} hadir dari {{ $r['hari'] ?? 0 }} hari kerja</small>
