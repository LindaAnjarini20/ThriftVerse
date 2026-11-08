@extends('layouts.app')
@section('title', 'Checkout Keranjang')
@section('content')
@php
  $init = $groups->map(fn ($g, $sid) => ['m' => 'ekspedisi', 'cod' => $g['codOk'], 'sub' => $g['subtotal'], 'n' => $g['items']->count()])->toArray();
@endphp
<div x-data="{ g: {{ Js::from($init) }}, ship: {{ (int) $cfg['shipping_flat'] }}, fee: {{ (int) $cfg['service_fee'] }}, sub: {{ (int) $subtotal }}, code: '{{ old('voucher_code') }}', disc: 0, vmsg: '', vok: false,
  get shipping(){ let t=0; Object.values(this.g).forEach(x=>{ if(x.m==='ekspedisi') t+=this.ship }); return t },
  get service(){ return Object.keys(this.g).length * this.fee },
  get total(){ return this.sub + this.shipping + this.service - this.disc },
  rp(n){ return 'Rp'+Math.round(n).toLocaleString('id-ID') },
  async check(){ if(!this.code.trim()){ this.disc=0; this.vmsg=''; this.vok=false; return }
    try{ const r = await fetch('{{ route('vouchers.check') }}?code='+encodeURIComponent(this.code)+'&amount='+this.sub, {headers:{'Accept':'application/json'}}); const j = await r.json(); this.disc = j.ok ? j.discount : 0; this.vmsg = j.message; this.vok = j.ok }
    catch(e){ this.vmsg='Gagal memeriksa voucher.'; this.vok=false } } }">
<form method="POST" action="{{ route('cart.place') }}" class="grid gap-6 lg:grid-cols-[1fr_360px]">@csrf
  <div class="space-y-5">
    <a href="{{ route('cart.index') }}" class="text-sm font-bold">← Kembali ke keranjang</a>
    @error('delivery')<p class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p>@enderror

    @foreach($groups as $sid => $g)
    <div class="card p-5">
      <div class="flex items-center justify-between"><h2 class="font-extrabold">Penjual: {{ $g['seller']->name }}</h2><span class="mono text-[11px] text-ink/50">{{ $g['items']->count() }} barang · @rp($g['subtotal'])</span></div>
      <ul class="mt-2 space-y-1 text-sm">@foreach($g['items'] as $it)<li class="flex justify-between"><span>{{ $it->listing->title }}</span><span class="text-ink/60">@rp($it->listing->current_price)</span></li>@endforeach</ul>
      <input type="hidden" name="delivery[{{ $sid }}]" :value="g[{{ $sid }}].m">
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <label class="cursor-pointer rounded-xl border-2 p-3 text-sm" :class="g[{{ $sid }}].m==='ekspedisi' ? 'border-brand bg-brand-light/40' : 'border-sand'"><input type="radio" class="sr-only" @click="g[{{ $sid }}].m='ekspedisi'"><b>🚚 Ekspedisi</b><br><span class="text-xs text-ink/60">Ongkir @rp($cfg['shipping_flat']) sekali untuk penjual ini.</span></label>
        <label class="rounded-xl border-2 p-3 text-sm {{ $g['codOk'] ? 'cursor-pointer' : 'opacity-50' }}" :class="g[{{ $sid }}].m==='cod' ? 'border-brand bg-brand-light/40' : 'border-sand'"><input type="radio" class="sr-only" @click="{{ $g['codOk'] ? "g[$sid].m='cod'" : '' }}"><b>🤝 COD / Ketemuan</b><br><span class="text-xs text-ink/60">{{ $g['codOk'] ? 'Gratis ongkir. Bayar lewat rekber.' : 'Tidak tersedia untuk semua barang penjual ini.' }}</span></label>
      </div>
      @if($g['codOk'])
      <div x-show="g[{{ $sid }}].m==='cod'" x-cloak class="mt-3 space-y-3">
        <div class="space-y-2">@foreach($points as $p)<label class="flex cursor-pointer items-start gap-3 rounded-xl bg-sand p-3 has-[:checked]:ring-2 has-[:checked]:ring-brand"><input type="radio" name="meeting_point_id[{{ $sid }}]" value="{{ $p->id }}" @checked(old("meeting_point_id.$sid") == $p->id) class="mt-1 text-brand"><span class="text-sm"><b>{{ $p->name }}</b>@if($p->recommended)<span class="ml-1 rounded bg-amber-200 px-1.5 py-0.5 text-[10px] font-bold">Rekomendasi Aman</span>@endif<br><span class="text-xs text-ink/60">{{ $p->address }}</span></span></label>@endforeach</div>
        <div><label class="text-sm font-bold">Tanggal & waktu</label><input type="datetime-local" name="scheduled_at[{{ $sid }}]" value="{{ old("scheduled_at.$sid") }}" class="field mt-1"></div>
      </div>
      @endif
    </div>
    @endforeach
  </div>

  <aside class="h-fit space-y-4 lg:sticky lg:top-32">
    <div class="card p-5"><h3 class="font-extrabold">Voucher</h3>
      <div class="mt-2 flex gap-2"><input name="voucher_code" x-model="code" @keydown.enter.prevent="check()" placeholder="Kode voucher" class="field uppercase"><button type="button" class="btn btn-soft" @click="check()">Pakai</button></div>
      <p class="mt-2 text-xs font-bold" :class="vok ? 'text-brand' : 'text-red-600'" x-text="vmsg"></p>
      @error('voucher_code')<p class="mt-1 text-xs font-bold text-red-600">{{ $message }}</p>@enderror</div>
    <div class="card p-5"><h3 class="font-extrabold">Rincian Pembayaran</h3>
      <dl class="mt-3 space-y-2 text-sm"><div class="flex justify-between"><dt>Harga barang</dt><dd x-text="rp(sub)"></dd></div>
        <div class="flex justify-between"><dt>Ongkos kirim</dt><dd x-text="shipping===0 ? 'Rp0 (Gratis)' : rp(shipping)"></dd></div>
        <div class="flex justify-between"><dt>Biaya layanan / rekber</dt><dd x-text="rp(service)"></dd></div>
        <div class="flex justify-between text-brand" x-show="disc>0" x-cloak><dt>Voucher</dt><dd x-text="'-'+rp(disc)"></dd></div></dl>
      <div class="mt-3 rounded-xl bg-sand p-3"><div class="flex items-center justify-between"><span class="font-bold">Total Tagihan</span><span class="text-2xl font-extrabold text-brand" x-text="rp(total)"></span></div>
        <p class="mt-1 text-[11px] text-ink/60">Pembayaran via rekber (simulasi sandbox). Voucher dipotong dari harga barang.</p></div>
      <button class="btn btn-brand mt-4 w-full !py-3.5 !text-sm">Buat Pesanan</button></div>
  </aside>
</form>
</div>
@endsection
