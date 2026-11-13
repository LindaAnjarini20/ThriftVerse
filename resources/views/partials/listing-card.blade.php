@php $disc = $l->original_price > 0 ? round((1 - $l->current_price / $l->original_price) * 100) : null; $isWished = in_array($l->id, $wished ?? []); @endphp
<article class="card flex flex-col overflow-hidden">
  <a href="{{ route('listings.show', $l) }}" class="relative block aspect-square overflow-hidden bg-sand sm:aspect-[4/3]">
    @include('partials.cover', ['listing' => $l])
    <div class="absolute left-1.5 top-1.5 flex max-w-[calc(100%-2.5rem)] flex-wrap gap-1 sm:left-2 sm:top-2 sm:max-w-none">@include('partials.grade', ['grade' => $l->condition_grade, 'verified' => (bool) $l->verified_at])</div>
    @if($l->terbuka_barter)<span class="mono absolute bottom-1.5 left-1.5 rounded bg-black/60 px-1.5 py-0.5 text-[9px] text-white sm:bottom-2 sm:left-2 sm:px-2 sm:text-[10px]">Terbuka Barter</span>@endif
  </a>
  @auth<form method="POST" action="{{ route('wishlist.toggle', $l) }}" class="absolute right-1.5 top-1.5 sm:right-2 sm:top-2">@csrf<button class="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-sm shadow sm:h-8 sm:w-8 sm:text-base">{{ $isWished ? '❤️' : '🤍' }}</button></form>@endauth
  <div class="flex flex-1 flex-col p-2 sm:p-3">
    <div class="mono flex justify-between gap-1 text-[9px] uppercase text-ink/50 sm:text-[10px]"><span class="truncate">{{ $l->size }} · {{ $l->brand }}</span>@if($disc && $disc > 0)<span class="shrink-0 text-red-600">-{{ $disc }}%</span>@endif</div>
    <a href="{{ route('listings.show', $l) }}" class="mt-1 line-clamp-2 text-[13px] font-bold leading-snug sm:line-clamp-1 sm:text-base">{{ $l->title }}</a>
    <div class="mt-1 flex flex-wrap items-baseline gap-x-2"><span class="text-base font-extrabold text-brand sm:text-xl">@rp($l->current_price)</span>@if($l->original_price > 0)<span class="text-[10px] text-ink/40 line-through sm:text-xs">@rp($l->original_price)</span>@endif</div>
    @if($l->bisa_cod && $l->cod_location)<div class="mt-2 hidden truncate rounded-lg bg-sand px-2 py-1.5 text-[11px] sm:block">COD: {{ $l->cod_location }}</div>@endif
    <a href="{{ route('listings.show', $l) }}" class="btn btn-brand mt-2 w-full !py-2 sm:hidden">Lihat detail</a>
    <div class="mt-3 hidden grid-cols-2 gap-2 text-center text-[11px] font-bold sm:grid">
      @if($l->bisa_tawar)<a href="{{ route('listings.show', $l) }}#tawar" class="btn btn-indigo !py-2">Tawar Harga</a>@endif
      @if($l->terbuka_barter)<a href="{{ auth()->check() ? route('barters.create', $l) : route('login') }}" class="btn btn-clay !py-2">Ajukan Barter</a>@endif
      @if($l->harga_tetap)<form method="POST" action="{{ route('orders.buy', $l) }}">@csrf<button class="btn btn-brand w-full !py-2">Beli Langsung</button></form>@endif
      <a href="{{ route('listings.show', $l) }}" class="btn btn-soft !py-2">Detail & Histori</a>
    </div>
  </div>
</article>
