@php $disc = $l->original_price > 0 ? round((1 - $l->current_price / $l->original_price) * 100) : null; $isWished = in_array($l->id, $wished ?? []); @endphp
<article class="card flex flex-col overflow-hidden">
  <a href="{{ route('listings.show', $l) }}" class="relative block aspect-[4/3] overflow-hidden bg-sand">
    @include('partials.cover', ['listing' => $l])
    <div class="absolute left-2 top-2 flex flex-wrap gap-1">@include('partials.grade', ['grade' => $l->condition_grade, 'verified' => (bool) $l->verified_at])</div>
    @if($l->terbuka_barter)<span class="mono absolute bottom-2 left-2 rounded bg-black/60 px-2 py-0.5 text-[10px] text-white">Terbuka Barter</span>@endif
  </a>
  @auth<form method="POST" action="{{ route('wishlist.toggle', $l) }}" class="absolute right-2 top-2">@csrf<button class="flex h-8 w-8 items-center justify-center rounded-full bg-white/90 shadow">{{ $isWished ? '❤️' : '🤍' }}</button></form>@endauth
  <div class="flex flex-1 flex-col p-3">
    <div class="mono flex justify-between text-[10px] uppercase text-ink/50"><span>{{ $l->size }} · {{ $l->brand }}</span>@if($disc && $disc > 0)<span class="text-red-600">-{{ $disc }}%</span>@endif</div>
    <a href="{{ route('listings.show', $l) }}" class="mt-1 line-clamp-1 font-bold">{{ $l->title }}</a>
    <div class="mt-1 flex items-baseline gap-2"><span class="text-xl font-extrabold text-brand">@rp($l->current_price)</span>@if($l->original_price > 0)<span class="text-xs text-ink/40 line-through">@rp($l->original_price)</span>@endif</div>
    @if($l->bisa_cod && $l->cod_location)<div class="mt-2 truncate rounded-lg bg-sand px-2 py-1.5 text-[11px]">COD: {{ $l->cod_location }}</div>@endif
    <div class="mt-3 grid grid-cols-2 gap-2 text-center text-[11px] font-bold">
      @if($l->bisa_tawar)<a href="{{ route('listings.show', $l) }}#tawar" class="btn btn-indigo !py-2">Tawar Harga</a>@endif
      @if($l->terbuka_barter)<a href="{{ auth()->check() ? route('barters.create', $l) : route('login') }}" class="btn btn-clay !py-2">Ajukan Barter</a>@endif
      @if($l->harga_tetap)<form method="POST" action="{{ route('orders.buy', $l) }}">@csrf<button class="btn btn-brand w-full !py-2">Beli Langsung</button></form>@endif
      <a href="{{ route('listings.show', $l) }}" class="btn btn-soft !py-2">Detail & Histori</a>
    </div>
  </div>
</article>
