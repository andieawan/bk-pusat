@extends('surat.layout', ['title' => $title.' — '.$siswa->nama])
@php
    $student = $siswa;
    $d = fn (string $k, int $n = 30) => trim((string) ($v[$k] ?? '')) !== '' ? e($v[$k]) : str_repeat('.', $n);
    $jk = $siswa->jk === 'L' ? 'Laki-laki' : 'Perempuan';
    $kelas = $siswa->kelas->nama ?? '';
    $kepsek = $cfg['kepsek'];
    $kota = $v['tempat'] ?? $cfg['kota'];
@endphp
@section('toolbar')
    @foreach($fields as $f)
        <label @if(! empty($f['wide'])) style="grid-column:1/-1" @endif>{{ $f['label'] }}<input type="text" name="{{ $f['k'] }}" value="{{ $f['v'] }}"></label>
    @endforeach
@endsection
@section('letter')
@include('surat.t.'.$jenis)
@endsection
