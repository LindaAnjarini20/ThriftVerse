@extends('layouts.app')
@section('title', $listing->title)
@section('content')
@php
  [$lo,$hi] = $listing->fairRange();
  $points = $listing->priceDrops->pluck('to_price')->all();
  $svg = '';
  if (count($points) > 1) {
    $mn = min($points); $mx = max($points); $rng = max($mx-$mn,1); $n = count($points); $pp = [];
    foreach ($points as $i => $v) { $pp[] = round(10 + $i*(280/($n-1)),1).','.round(70 - (($v-$mn)/$rng)*55,1); }
    $svg = implode(' ', $pp);
  }
  $disc = $listing->original_price > 0 ? round((1 - $listing->current_price/$listing->original_price)*100) : null;
@endphp
<div class="grid gap-6 lg:grid-cols-[1.1fr_1fr]">
  <div class="space-y-4">
    <div class="card overflow-hidden" x-data="{ i: 0, imgs: {{ Js::from(collect($listing->images ?? [])->map(fn ($p) => $listing->url($p))->values()) }} }">
      <div class="aspect-square bg-sand">
        @if(count($listing->images ?? []))<img src="{{ $listing->coverUrl() }}" :src="imgs[i]" alt="{{ $listing->title }}" class="h-full w-full object-cover">
        @else @include('partials.cover', ['listing' => $listing]) @endif
      </div>
      @if(count($listing->images ?? []) > 1)<div class="grid grid-cols-4 gap-2 p-3">@foreach($listing->images as $i => $img)<button type="button" @click="i = {{ $i }}" :class="i === {{ $i }} ? 'ring-2 ring-brand' : 'opacity-70 hover:opacity-100'" class="overflow-hidden rounded-lg"><img src="{{ $listing->url($img) }}" class="aspect-square w-full object-cover" alt="foto {{ $i+1 }}"></button>@endforeach</div>@endif</div>
    @if(count($listing->minus_photos ?? []))<div class="card p-4"><div class="mono mb-2 text-[11px] font-bold uppercase text-clay">Foto detail minus</div>
      <div class="grid grid-cols-3 gap-2">@foreach($listing->minus_photos as $m)<img src="{{ $listing->url($m) }}" class="aspect-square rounded-lg object-cover" alt="minus">@endforeach</div></div>@endif
    <div class="card p-5"><div class="mono text-[11px] font-bold uppercase text-ink/50">Riwayat harga</div>
      @if($svg)<svg viewBox="0 0 300 80" class="mt-2 w-full"><polyline points="{{ $svg }}" fill="none" stroke="#006B4A" stroke-width="2.5" stroke-linejoin="round"/>
        @foreach(explode(' ', $svg) as $pt)@php [$cx,$cy] = explode(',', $pt); @endphp<circle cx="{{ $cx }}" cy="{{ $cy }}" r="3" fill="#006B4A"/>@endforeach</svg>
      <div class="mt-1 flex justify-between text-[11px] text-ink/60"><span>{{ $listing->priceDrops->first()->changed_at->format('d M') }} · @rp($points[0])</span><span>{{ $listing->priceDrops->last()->changed_at->format('d M') }} · @rp(end($points))</span></div>
      @else<p class="mt-2 text-sm text-ink/60">Belum ada perubahan harga.</p>@endif</div>
  </div>

  <div class="space-y-4">
    <div class="card p-6">
      <div class="flex flex-wrap items-center gap-2">@include('partials.grade', ['grade' => $listing->condition_grade, 'verified' => (bool) $listing->verified_at])
        <span class="text-xs text-ink/60">{{ \App\Models\Listing::gradeLabel($listing->condition_grade) }}</span>@if($listing->status !== 'active')<span class="mono rounded bg-sand px-2 py-0.5 text-[10px] uppercase">{{ $listing->status }}</span>@endif</div>
      <h1 class="mt-2 text-2xl font-extrabold tracking-tight">{{ $listing->title }}</h1>
      <div class="mono mt-1 text-[11px] uppercase text-ink/50">{{ $listing->brand }} · {{ $listing->category }} · {{ $listing->size }} @if($listing->color)· {{ $listing->color }}@endif @if($listing->material)· {{ $listing->material }}@endif</div>
      <div class="mt-4 flex items-baseline gap-3"><span class="text-3xl font-extrabold text-brand">@rp($listing->current_price)</span>@if($listing->original_price > 0)<span class="text-ink/40 line-through">@rp($listing->original_price)</span>@endif @if($disc)<span class="rounded bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700">-{{ $disc }}%</span>@endif</div>
      <div class="mt-3 rounded-xl bg-sand p-3 text-sm"><div class="mono text-[10px] font-bold uppercase text-ink/50">Estimasi harga layak</div><b>@rp($lo) – @rp($hi)</b>
        <span class="ml-1 text-xs text-ink/60">berdasar grade, merek, dan harga baru</span></div>
      @if($listing->auto_drop)<div class="mt-2 text-xs font-semibold text-brand">📉 Harga turun otomatis tiap {{ $listing->drop_every_days }} hari — bisa jadi makin murah.</div>@endif
      <p class="mt-4 whitespace-pre-line text-sm text-ink/80">{{ $listing->description }}</p>
      @if($listing->condition_checklist)<div class="mt-3 flex flex-wrap gap-1.5">@foreach($listing->condition_checklist as $k)<span class="rounded-full bg-sand px-2.5 py-1 text-[11px] font-semibold">{{ \App\Models\Listing::CHECKLIST[$k][0] ?? $k }}</span>@endforeach</div>@else<div class="mt-3 text-xs font-semibold text-brand">Tanpa minus tercatat</div>@endif
      <div class="mt-4 flex items-center gap-3 border-t border-sand pt-4 text-sm"><div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand text-white font-bold">{{ strtoupper(substr($listing->seller->name,0,1)) }}</div>
        <div><b>{{ $listing->seller->name }}</b> <span class="mono text-[11px] text-ink/50">{{ $listing->seller->handle() }}</span><div class="text-xs text-ink/60">@if($listing->seller->ratingAverage())★ {{ $listing->seller->ratingAverage() }} · @endif Skor kepercayaan {{ $listing->seller->trust_score }}</div></div></div>
    </div>

    @if(! $isOwner && $listing->isAvailable())
    <div class="card space-y-3 p-6" id="tawar">
      @guest<a href="{{ route('login') }}" class="btn btn-brand w-full">Masuk untuk membeli / menawar</a>@endguest
      @auth
      @if($listing->harga_tetap)<form method="POST" action="{{ route('orders.buy', $listing) }}">@csrf<button class="btn btn-brand w-full !py-3.5 !text-base">Beli Langsung · @rp($listing->current_price)</button></form>
        <form method="POST" action="{{ route('cart.add', $listing) }}">@csrf<button class="btn btn-soft w-full">{{ $inCart ? '✓ Sudah di keranjang' : '🛒 Tambah ke Keranjang' }}</button></form>@endif

      @if($listing->bisa_tawar)
        @if($thread && $thread->isOpen())
          <div class="rounded-xl bg-indigoX/10 p-3 text-sm"><b>Tawaran aktif Anda:</b> @rp($thread->amount) · putaran {{ $thread->round }}/{{ config('thriftverse.bid_max_rounds') }}<br><a class="font-bold text-indigoX" href="{{ route('negotiations.index', ['tab' => 'saya']) }}">Lihat negosiasi →</a></div>
        @else
        <form method="POST" action="{{ route('bids.store', $listing) }}" x-data="{amt: {{ (int) round(($lo+$hi)/2) }}, lo: {{ (int) $lo }}, hi: {{ (int) $hi }}, min: {{ (int) $listing->minBid() }}}" class="space-y-3">@csrf
          <div class="mono text-[11px] font-bold uppercase text-indigoX">Ajukan Harga ala inDrive</div>
          <input name="amount" x-model.number="amt" type="number" class="field !text-lg font-extrabold">
          <div class="h-2 overflow-hidden rounded-full bg-sand"><div class="h-full transition-all" :style="`width:${Math.min(100,Math.max(4,amt/{{ (int) $listing->current_price }}*100))}%`" :class="amt<lo ? 'bg-red-500' : (amt>hi ? 'bg-indigoX' : 'bg-brand')"></div></div>
          <div class="text-sm font-bold" :class="amt<lo ? 'text-red-600' : (amt>hi ? 'text-indigoX' : 'text-brand')" x-text="amt<min ? 'Terlalu rendah (di bawah batas minimum)' : (amt<lo ? 'Terlalu Rendah' : (amt>hi ? 'Tinggi' : 'Wajar'))"></div>
          <div class="grid grid-cols-2 gap-2 text-xs font-bold">
            <label class="rounded-xl bg-sand p-2.5"><input type="radio" name="delivery_method" value="ekspedisi" checked class="mr-1 text-brand">Ekspedisi</label>
            <label class="rounded-xl bg-sand p-2.5 {{ $codOk ? '' : 'opacity-50' }}"><input type="radio" name="delivery_method" value="cod" @disabled(! $codOk) class="mr-1 text-brand">COD / Ketemuan @if($codKm !== null)<span class="font-normal">· {{ number_format($codKm,1) }} km</span>@endif</label></div>
          <input name="message" placeholder="Pesan singkat (opsional)" class="field">
          <button class="btn btn-indigo w-full !py-3">Kirim Tawaran</button>
          <p class="text-[11px] text-ink/60">Berlaku {{ config('thriftverse.bid_expiry_hours') }} jam · maksimal {{ config('thriftverse.bid_max_rounds') }} putaran · minimal @rp($listing->minBid())</p>
        </form>
        @endif
      @endif

      @if($listing->terbuka_barter)<a href="{{ route('barters.create', $listing) }}" class="btn btn-clay w-full">Ajukan Barter / Tukar Tambah</a>@if($listing->barter_preference)<p class="text-xs text-ink/60">Dicari: {{ $listing->barter_preference }}</p>@endif @endif
      @if($listing->bisa_cod)<div class="rounded-xl bg-brand-light p-3 text-xs text-brand-dark"><b>COD – Gratis Ongkir</b> · {{ $listing->cod_location }} · radius {{ $listing->cod_radius_km }} km @if(! $codOk && auth()->user()->area)<br>Area Anda belum masuk radius COD.@elseif(! auth()->user()->area)<br>Atur area di profil untuk cek ketersediaan COD.@endif</div>@endif
      <form method="POST" action="{{ route('chat.start', $listing) }}">@csrf<button class="btn btn-soft w-full">💬 Chat Penjual</button></form>
      <form method="POST" action="{{ route('wishlist.toggle', $listing) }}">@csrf<button class="btn btn-soft w-full">{{ $wished ? '❤️ Hapus dari wishlist' : '🤍 Simpan ke wishlist' }}</button></form>
      @endauth
    </div>
    @elseif($isOwner)
      <div class="card p-4 text-sm"><a class="btn btn-soft" href="{{ route('listings.edit', $listing) }}">Ubah listing</a></div>
    @else<div class="card p-4 text-sm font-semibold text-ink/60">Barang ini sedang ditahan / tidak tersedia.</div>@endif

    @auth @if(! $isOwner)<details class="card p-4 text-sm"><summary class="cursor-pointer font-bold">Laporkan listing</summary>
      <form method="POST" action="{{ route('listings.report', $listing) }}" class="mt-3 space-y-2">@csrf<textarea name="reason" required minlength="5" class="field" rows="2" placeholder="Alasan laporan"></textarea><button class="btn btn-soft">Kirim laporan</button></form></details>@endif @endauth
  </div>
</div>
@endsection
