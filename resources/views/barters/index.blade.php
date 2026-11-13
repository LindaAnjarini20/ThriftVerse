@extends('layouts.app')
@section('title', 'Barter Hub')
@section('content')
<div class="mb-5"><div class="mono text-[11px] font-bold uppercase text-clay">Barter parity</div><h1 class="text-2xl font-extrabold">Barter Hub</h1><p class="text-sm text-ink/60">Barter hanya sah bila kedua pihak menyetujui ringkasan akhir. Barang kedua pihak dikunci selama barter aktif.</p></div>
<h2 class="mb-2 font-extrabold">Barter saya</h2>
<div class="card mb-8 divide-y divide-sand">
@forelse($barters as $b)<a href="{{ route('barters.show', $b) }}" class="flex flex-wrap items-center justify-between gap-3 p-4 hover:bg-cream">
  <div><div class="font-bold">{{ $b->offerer->name }} ⇄ {{ $b->owner->name }}</div><div class="text-xs text-ink/60">{{ $b->items->pluck('title')->join(', ') }} → {{ $b->target->title }}</div></div>
  <div class="flex items-center gap-2 text-xs">@if($b->cash_top_up > 0)<span class="rounded bg-sand px-2 py-1 font-bold">+ @rp($b->cash_top_up)</span>@endif
    <span class="mono rounded px-2 py-1 font-bold uppercase {{ $b->status==='pending' ? 'bg-clay/10 text-clay' : ($b->status==='accepted'||$b->status==='completed' ? 'bg-brand-light text-brand' : 'bg-sand') }}">{{ $b->status }}</span></div></a>
@empty<div class="p-8 text-center text-sm text-ink/60">Belum ada barter.</div>@endforelse</div>
<h2 class="mb-2 font-extrabold">Listing terbuka barter</h2>
<div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">@foreach($open as $l)<div class="relative">@include('partials.listing-card', ['l' => $l, 'wished' => []])</div>@endforeach</div>
@endsection
