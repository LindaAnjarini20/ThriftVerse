@extends('layouts.app')
@section('title', 'Barter')
@section('content')
@php $me = auth()->id(); $mineTurn = $barter->status==='pending' && $barter->awaiting_user_id === $me;
     $col = ['Seimbang' => 'bg-brand-light text-brand-dark', 'Perlu Tambahan' => 'bg-amber-100 text-amber-800', 'Timpang' => 'bg-red-100 text-red-800'][$ind['label']]; @endphp
<div class="mx-auto max-w-4xl space-y-4">
  <div class="flex items-center justify-between"><h1 class="text-2xl font-extrabold">Ringkasan Barter #{{ $barter->id }}</h1><span class="mono rounded-lg bg-sand px-3 py-1.5 text-xs font-bold uppercase">{{ $barter->status }} · putaran {{ $barter->round }}/{{ config('thriftverse.barter_max_rounds') }}</span></div>
  <div class="grid gap-4 md:grid-cols-[1fr_auto_1fr] md:items-center">
    <div class="card p-4"><div class="mono mb-2 text-[10px] font-bold uppercase text-ink/50">{{ $barter->offerer->name }} menawarkan</div>
      @foreach($barter->items as $i)<div class="mb-2 flex items-center gap-3"><div class="h-12 w-12 overflow-hidden rounded-lg bg-sand">@include('partials.cover', ['listing' => $i])</div><div class="text-sm"><div class="font-bold">{{ $i->title }}</div><div class="text-brand">@rp($i->pivot->estimated_value)</div></div></div>@endforeach
      <div class="mt-2 border-t border-sand pt-2 text-sm font-extrabold">Total @rp($barter->offeredValue())</div></div>
    <div class="text-center text-3xl text-clay">⇄</div>
    <div class="card p-4"><div class="mono mb-2 text-[10px] font-bold uppercase text-ink/50">{{ $barter->owner->name }} menawarkan</div>
      <div class="flex items-center gap-3"><div class="h-12 w-12 overflow-hidden rounded-lg bg-sand">@include('partials.cover', ['listing' => $barter->target])</div><div class="text-sm"><a href="{{ route('listings.show', $barter->target) }}" class="font-bold">{{ $barter->target->title }}</a><div class="text-brand">@rp($barter->target->current_price)</div></div></div></div>
  </div>
  <div class="card p-5"><div class="flex flex-wrap items-center gap-3"><span class="mono rounded-md px-2.5 py-1 text-xs font-bold uppercase {{ $col }}">{{ $ind['label'] }} · selisih {{ $ind['pct'] }}%</span>
    <span class="text-sm">Uang tambahan: <b>@rp($barter->cash_top_up)</b> @if($barter->cash_top_up>0)dibayar oleh <b>{{ $barter->top_up_payer==='owner' ? $barter->owner->name : $barter->offerer->name }}</b>@endif · Metode: ketemuan</span></div>
    <div class="mt-3 flex gap-4 text-xs font-bold"><span>{{ $barter->offerer_approved ? '✅' : '⏳' }} {{ $barter->offerer->name }}</span><span>{{ $barter->owner_approved ? '✅' : '⏳' }} {{ $barter->owner->name }}</span></div>
    @if($barter->message)<p class="mt-3 rounded-lg bg-sand p-3 text-sm italic">"{{ $barter->message }}"</p>@endif</div>

  @if($mineTurn)
  <form method="POST" action="{{ route('barters.respond', $barter) }}" class="card space-y-3 p-5" x-data="{c:false}">@csrf
    <div class="text-sm font-bold">Respons Anda</div>
    <div class="grid grid-cols-3 gap-2"><button name="action" value="approve" class="btn btn-brand">Setuju</button><button type="button" @click="c=!c" class="btn btn-indigo">Ajukan Balik</button><button name="action" value="reject" class="btn btn-soft">Tolak</button></div>
    <div x-show="c" x-cloak class="grid gap-2 sm:grid-cols-3"><input type="number" min="0" name="cash_top_up" value="{{ (int) $barter->cash_top_up }}" class="field"><select name="top_up_payer" class="field"><option value="offerer">Pengaju membayar</option><option value="owner">Pemilik membayar</option></select><button name="action" value="counter" class="btn btn-indigo">Kirim balik</button></div>
  </form>
  @elseif($barter->status==='pending')
  <div class="card flex items-center justify-between p-4 text-sm"><span class="font-semibold text-ink/60">Menunggu respons pihak lain · kedaluwarsa {{ $barter->expires_at?->diffForHumans() }}</span>
    @if($barter->offerer_id === $me)<form method="POST" action="{{ route('barters.respond', $barter) }}">@csrf<button name="action" value="cancel" class="btn btn-danger">Batalkan</button></form>@endif</div>
  @endif
  @if($barter->transaction)<a href="{{ route('orders.show', $barter->transaction) }}" class="btn btn-brand w-full">Lanjut ke pesanan & jadwal ketemuan →</a>@endif
  <div class="card p-5"><div class="mono mb-2 text-[10px] font-bold uppercase text-ink/50">Riwayat negosiasi</div><ul class="space-y-1 text-sm">@foreach(array_reverse($barter->history ?? []) as $h)<li><span class="mono text-xs text-ink/50">{{ $h['at'] }}</span> — {{ $h['text'] }}</li>@endforeach</ul></div>
</div>
@endsection
