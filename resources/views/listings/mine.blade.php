@extends('layouts.app')
@section('title', 'Listing Saya')
@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3"><div><div class="mono text-[11px] font-bold uppercase text-brand">Mode Penjual · Dealmaker Suite</div><h1 class="text-2xl font-extrabold">Listing Saya</h1></div>
  <div class="flex gap-2"><a href="{{ route('negotiations.index', ['tab' => 'masuk']) }}" class="btn btn-indigo">Antrean Tawaran</a><a href="{{ route('orders.index', ['peran' => 'jual']) }}" class="btn btn-soft">Pesanan Masuk</a><a href="{{ route('listings.create') }}" class="btn btn-brand">+ Jual barang</a></div></div>
<div class="card divide-y divide-sand">
@forelse($listings as $l)
  <div class="flex flex-wrap items-center gap-4 p-4"><div class="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-sand">@include('partials.cover', ['listing' => $l])</div>
    <div class="min-w-0 flex-1"><a href="{{ route('listings.show', $l) }}" class="font-bold">{{ $l->title }}</a>
      <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">@include('partials.grade', ['grade' => $l->condition_grade, 'verified' => (bool) $l->verified_at])<span class="mono rounded bg-sand px-2 py-0.5 uppercase">{{ $l->status }}</span>
        @if($l->bisa_tawar)<span class="text-indigoX font-bold">Nego</span>@endif @if($l->terbuka_barter)<span class="text-clay font-bold">Barter</span>@endif @if($l->bisa_cod)<span class="text-brand font-bold">COD</span>@endif<span class="text-ink/50">{{ $l->bids_count }} tawaran</span></div></div>
    <div class="text-right font-extrabold text-brand">@rp($l->current_price)</div>
    <div class="flex gap-2"><a href="{{ route('listings.edit', $l) }}" class="btn btn-soft">Ubah</a>
      <form method="POST" action="{{ route('listings.destroy', $l) }}" onsubmit="return confirm('Hapus listing ini?')">@csrf @method('DELETE')<button class="btn btn-danger">Hapus</button></form></div></div>
@empty<div class="p-10 text-center text-ink/60">Belum ada listing. <a class="font-bold text-brand" href="{{ route('listings.create') }}">Pasang listing pertama</a>.</div>@endforelse</div>
<div class="mt-4">{{ $listings->links() }}</div>
@endsection
