@extends('layouts.app')
@section('title', 'Negosiasi & Tawar')
@section('content')
@php $me = auth()->id(); @endphp
<div class="mb-5 flex flex-wrap items-end justify-between gap-3"><div><div class="mono text-[11px] font-bold uppercase text-indigoX">● Bidding arena live</div><h1 class="text-2xl font-extrabold">Negosiasi & Tawar</h1></div>
  <div class="flex rounded-xl bg-sand p-1 text-sm font-bold"><a href="?tab=masuk" class="rounded-lg px-4 py-2 {{ $tab==='masuk' ? 'bg-white shadow' : 'text-ink/60' }}">Tawaran Masuk ({{ $incoming->count() }})</a><a href="?tab=saya" class="rounded-lg px-4 py-2 {{ $tab==='saya' ? 'bg-white shadow' : 'text-ink/60' }}">Tawaran Saya ({{ $mine->count() }})</a></div></div>
@php $list = $tab === 'masuk' ? $incoming : $mine; @endphp
<div class="grid gap-4 lg:grid-cols-2">
@forelse($list as $b)
  @php $l = $b->listing; $ind = $l->priceIndicator($b->amount); $open = $b->isOpen(); $mineTurn = $open && $b->proposer_id !== $me;
       $col = ['Wajar' => 'text-brand bg-brand-light', 'Tinggi' => 'text-indigoX bg-indigoX/10', 'Terlalu Rendah' => 'text-red-700 bg-red-100'][$ind]; @endphp
  <div class="card p-5">
    <div class="flex gap-3"><div class="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-sand">@include('partials.cover', ['listing' => $l])</div>
      <div class="min-w-0 flex-1"><div class="mono text-[10px] font-bold uppercase text-brand">Ronde {{ $b->round }}/{{ config('thriftverse.bid_max_rounds') }} · {{ $b->delivery_method === 'cod' ? 'COD' : 'Ekspedisi' }}</div>
        <a href="{{ route('listings.show', $l) }}" class="line-clamp-1 font-extrabold">{{ $l->title }}</a><div class="text-xs text-ink/60">Harga dasar @rp($l->current_price) · {{ $tab==='masuk' ? '@'.($b->buyer->username ?? $b->buyer->name) : 'Penjual '.$l->seller->name }}</div></div>
      @if($open)<div class="mono rounded-lg bg-sand px-2 py-1 text-[11px]" x-data="{t:{{ $b->expires_at->timestamp }}, n:Math.floor(Date.now()/1000)}" x-init="setInterval(()=>n=Math.floor(Date.now()/1000),1000)" x-text="(()=>{let s=Math.max(0,t-n);return String(Math.floor(s/3600)).padStart(2,'0')+':'+String(Math.floor(s%3600/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0')})()"></div>@endif</div>
    <div class="mt-3 flex items-end justify-between"><div><div class="text-3xl font-extrabold">@rp($b->amount)</div><div class="text-xs text-ink/50">diajukan oleh {{ $b->proposer_id === $me ? 'Anda' : $b->proposer->name }}</div></div><span class="mono rounded-md px-2 py-1 text-[10px] font-bold uppercase {{ $col }}">{{ $ind }}</span></div>
    @if($b->message)<div class="mt-2 rounded-lg bg-sand p-2.5 text-sm italic">"{{ $b->message }}"</div>@endif
    @if($mineTurn)
      <form method="POST" action="{{ route('bids.respond', $b) }}" class="mt-4 space-y-2" x-data="{c:false}">@csrf
        <div class="grid grid-cols-3 gap-2"><button name="action" value="accept" class="btn btn-brand">🔒 Kunci & Deal</button><button type="button" @click="c=!c" class="btn btn-indigo">⇄ Counter</button><button name="action" value="reject" class="btn btn-soft" onclick="return confirm('Tolak tawaran ini?')">✕ Tolak</button></div>
        <div x-show="c" x-cloak class="flex gap-2"><input type="number" name="amount" value="{{ (int) $b->amount + 10000 }}" class="field"><button name="action" value="counter" class="btn btn-indigo">Kirim</button></div>
      </form>
    @elseif($open)<div class="mt-4 rounded-lg bg-sand p-2.5 text-center text-xs font-bold text-ink/60">Menunggu respons pihak lain…</div>
    @else<div class="mono mt-4 text-[11px] font-bold uppercase text-ink/50">Status: {{ $b->status }}
      @if($b->status==='accepted' && ($tx = \App\Models\Transaction::where('bid_id',$b->id)->first()))· <a class="text-brand" href="{{ route('orders.show', $tx) }}">Lihat pesanan →</a>@endif</div>@endif
  </div>
@empty<div class="card col-span-full p-10 text-center text-ink/60">Belum ada tawaran.</div>@endforelse
</div>
@endsection
