@extends('layouts.app', ['title' => 'Masuk', 'bare' => true])
@section('content')
<div class="card login">
    <img class="logo-login" src="{{ asset('icons/icon-192.png') }}" alt="" width="56" height="56">
    <h1>Bimbingan Konseling</h1>
    <p class="mut">{{ config('bk.school_name') }}<br>Masuk memakai akun Pusat Sekolah.</p>
    @if(\App\Services\PusatKlien::aktif())<a class="btn btn-pri" style="width:100%;text-align:center;display:block" href="{{ route('sso.login') }}">Masuk lewat Pusat</a>@endif
    @if(config('bk.login_lokal'))
    <details style="margin-top:14px" @if($errors->any()) open @endif><summary class="mut" style="cursor:pointer">Admin lokal (darurat)</summary>
    <form method="post" action="{{ route('login') }}" autocomplete="off" style="margin-top:10px">
        @csrf
        <div class="field"><label for="u">Username</label><input id="u" type="text" name="username" value="{{ old('username') }}" autocapitalize="none" autocomplete="username" required></div>
        <div class="field"><label for="pw">Password</label><input id="pw" type="password" name="password" autocomplete="current-password" required></div>
        <button class="btn" style="width:100%" data-busy="Memeriksa…">Masuk</button>
    </form></details>
    @endif
</div>
@endsection
