@extends('layouts.admin')
@section('title', 'Verifikasi & Kurasi')
@section('content')
<div class="mb-5 rounded-2xl bg-sand p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-2xl font-extrabold">Super Admin & Lead Curator Portal</h1>
  <span class="mono mt-2 inline-block rounded bg-indigoX px-2 py-1 text-[10px] font-bold uppercase text-white">Admin · full escrow & role authority</span></div>
  <span class="mono rounded-lg bg-white px-3 py-2 text-[11px]">Transaksi selesai: <b>{{ $stats['tx_done'] }}</b> · Pendapatan platform: <b>@rp($stats['revenue'])</b></span></div></div>
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <div class="card p-5"><div class="mono text-[10px] uppercase text-ink/50">Listing aktif & pipeline</div><div class="text-3xl font-extrabold">{{ number_format($stats['listings']) }}</div><div class="mt-2 rounded bg-amber-100 px-2 py-1 text-xs font-bold text-amber-800">{{ $stats['pending'] }} menunggu kurasi grade</div></div>
  <div class="card p-5"><div class="mono text-[10px] uppercase text-ink/50">Pengguna terdaftar</div><div class="text-3xl font-extrabold">{{ number_format($stats['users']) }}</div></div>
  <div class="card p-5"><div class="mono text-[10px] uppercase text-ink/50">Saldo escrow aman</div><div class="text-3xl font-extrabold text-brand">@rp($stats['escrow'])</div><div class="mt-2 text-xs text-ink/60">{{ $stats['escrow_tx'] }} transaksi mengambang</div></div>
  <div class="card p-5"><div class="mono text-[10px] uppercase text-ink/50">Sengketa aktif</div><div class="text-3xl font-extrabold text-red-600">{{ $stats['disputes'] }} Kasus</div><a href="{{ route('admin.disputes') }}" class="mt-2 inline-block text-xs font-bold text-indigoX">Buka pusat sengketa →</a></div>
</div>
<div class="card p-5"><h2 class="text-xl font-extrabold">Inspeksi & Kurasi Grade Autentisitas</h2><p class="text-sm text-ink/60">Verifikasi foto 4 titik: depan, belakang, label, dan detail bahan/minus.</p>
  <div class="mt-4 divide-y divide-sand">
  @forelse($queue as $l)
    <div class="flex flex-wrap items-center gap-4 py-4"><div class="h-16 w-16 overflow-hidden rounded-lg bg-sand">@include('partials.cover', ['listing' => $l])</div>
      <div class="min-w-0 flex-1"><a href="{{ route('listings.show', $l) }}" target="_blank" class="font-bold">{{ $l->title }}</a><div class="text-xs text-ink/60">Penjual {{ $l->seller->handle() }} · skor {{ $l->seller->trust_score }}</div>
        <div class="mt-1 flex flex-wrap items-center gap-1.5">@include('partials.grade', ['grade' => $l->condition_grade])@foreach(($l->condition_checklist ?? []) as $k)<span class="rounded bg-sand px-1.5 py-0.5 text-[10px]">{{ \App\Models\Listing::CHECKLIST[$k][0] ?? $k }}</span>@endforeach</div></div>
      <div class="text-right font-extrabold">@rp($l->current_price)<div class="text-[10px] font-semibold text-ink/50">{{ $l->bisa_tawar ? 'Bisa Tawar ' : '' }}{{ $l->terbuka_barter ? '/ Barter' : '' }}</div></div>
      <form method="POST" action="{{ route('admin.verify', $l) }}" class="flex gap-2 text-xs">@csrf
        <button name="action" value="approve" class="btn btn-brand">Setujui Lencana Auth</button>
        @if($l->condition_grade !== 'C')<button name="action" value="downgrade" class="btn btn-soft">Turunkan Grade</button>@endif
        <button name="action" value="reject" class="btn btn-danger" onclick="return confirm('Tolak listing?')">⛔</button></form></div>
  @empty<p class="py-6 text-center text-ink/60">Semua listing sudah dikurasi 🎉</p>@endforelse</div></div>
@endsection
