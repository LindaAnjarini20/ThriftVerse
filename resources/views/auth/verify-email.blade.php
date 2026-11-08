@extends('layouts.app')
@section('content')
<div class="card mx-auto max-w-lg p-8 text-center"><h1 class="text-xl font-extrabold">Verifikasi email Anda</h1>
<p class="mt-2 text-sm text-ink/70">Kami mengirim tautan verifikasi ke {{ auth()->user()->email }}. (Mode lokal: lihat <code>storage/logs/laravel.log</code>.)</p>
<form method="POST" action="{{ route('verification.send') }}" class="mt-4">@csrf<button class="btn btn-brand">Kirim ulang tautan</button></form></div>
@endsection
