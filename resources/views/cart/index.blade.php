@extends('layouts.app')
@section('title', 'Keranjang')
@section('content')
<div class="mx-auto max-w-4xl">
  <h1 class="mb-4 text-2xl font-extrabold">Keranjang</h1>
  @if($items->isEmpty())
    <div class="card p-10 text-center text-ink/60">Keranjang masih kosong. <a href="{{ route('home') }}" class="font-bold text-brand">Jelajahi katalog</a> dan tekan "Tambah ke Keranjang" pada barang berharga tetap.</div>
  @else
  <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
    <div class="space-y-3">
      @foreach($items as $it)
        @php $l = $it->listing; $ok = $valid->contains(fn ($v) => $v->id === $it->id); @endphp
        <div class="card flex items-center gap-4 p-4 {{ $ok ? '' : 'opacity-60' }}">
          @if($l)<div class="h-16 w-16 overflow-hidden rounded-lg bg-sand">@include('partials.cover', ['listing' => $l])</div>@endif
          <div class="min-w-0 flex-1">
            @if($l)<a href="{{ route('listings.show', $l) }}" class="font-bold">{{ $l->title }}</a>
              <div class="mono text-[11px] text-ink/60">{{ $l->size }} · Grade {{ $l->condition_grade }} · {{ $l->seller->name }}</div>
              @unless($ok)<div class="mt-1 text-xs font-bold text-red-600">Tidak tersedia / tidak bisa dibeli langsung. Tidak ikut checkout.</div>@endunless
            @else<div class="font-bold text-ink/60">Listing sudah dihapus</div>@endif</div>
          <div class="text-right">@if($l)<div class="font-extrabold text-brand">@rp($l->current_price)</div>@endif
            <form method="POST" action="{{ route('cart.remove', $it) }}">@csrf @method('DELETE')<button class="text-xs font-bold text-red-600">Hapus</button></form></div>
        </div>
      @endforeach
    </div>
    <aside class="card h-fit p-5 lg:sticky lg:top-32">
      <h2 class="font-extrabold">Ringkasan</h2>
      <dl class="mt-3 space-y-2 text-sm"><div class="flex justify-between"><dt>{{ $valid->count() }} barang</dt><dd>@rp($subtotal)</dd></div>
        <div class="flex justify-between text-ink/60"><dt>Ongkir & biaya layanan</dt><dd>dihitung di checkout</dd></div></dl>
      @if($valid->isNotEmpty())<a href="{{ route('cart.checkout') }}" class="btn btn-brand mt-4 w-full !py-3">Checkout</a>@endif
      <p class="mt-3 text-xs text-ink/60">Satu pesanan dibuat untuk tiap barang. Ongkir dan biaya layanan hanya dihitung sekali per penjual.</p>
    </aside>
  </div>
  @endif
</div>
@endsection
