@extends('layouts.app')
@section('title', 'Checkout')
@section('content')
@php $disc = $listing->current_price > $tx->agreed_price ? $listing->current_price - $tx->agreed_price : 0; @endphp
<div x-data="{ m: '{{ $preset === 'cod' && $codOk ? 'cod' : ($preset === 'ekspedisi' ? 'ekspedisi' : ($codOk ? 'cod' : 'ekspedisi')) }}', pay: 'app', price: {{ (int) $tx->agreed_price }}, ship: {{ (int) $cfg['shipping_flat'] }}, fee: {{ (int) $cfg['service_fee'] }}, codfee: {{ (int) ($cfg['cod_cash_fee'] ?? 0) }}, disc: 0, code: '{{ old('voucher_code') }}', vmsg: '', vok: false,
  async check(){ if(!this.code.trim()){ this.disc=0; this.vmsg=''; this.vok=false; return }
    try{ const r = await fetch('{{ route('vouchers.check') }}?code='+encodeURIComponent(this.code)+'&amount='+this.price, {headers:{'Accept':'application/json'}}); const j = await r.json(); this.disc = j.ok ? j.discount : 0; this.vmsg = j.message; this.vok = j.ok }
    catch(e){ this.vmsg='Gagal memeriksa voucher.'; this.vok=false } },
  get shipping(){ return this.m==='cod' ? 0 : this.ship }, get service(){ return (this.m==='cod' && this.pay==='cash') ? this.codfee : this.fee },
  get total(){ return this.price + this.shipping + this.service - this.disc }, rp(n){ return 'Rp'+n.toLocaleString('id-ID') } }">
<form method="POST" action="{{ route('orders.checkout.confirm', $tx) }}" class="grid gap-6 lg:grid-cols-[1fr_380px]">@csrf
  <input type="hidden" name="delivery_method" :value="m"><input type="hidden" name="payment_method" :value="m==='ekspedisi' ? 'app' : pay">
  <div class="space-y-5">
    <a href="{{ route('orders.show', $tx) }}" class="text-sm font-bold">← Kembali ke pesanan</a>
    <div class="card p-5"><div class="flex flex-wrap items-center gap-4"><div class="h-20 w-20 overflow-hidden rounded-xl bg-sand">@include('partials.cover', ['listing' => $listing])</div>
      <div class="min-w-0 flex-1"><div class="mono text-[10px] font-bold uppercase text-indigoX">{{ $tx->bid ? 'Hasil negosiasi sepakat' : 'Pembelian langsung' }}</div><div class="text-lg font-extrabold">{{ $listing->title }}</div><div class="mono text-[11px] text-ink/60">{{ $listing->size }} · Grade {{ $listing->condition_grade }} · {{ $listing->seller->name }}</div></div>
      <div class="text-right"><div class="text-2xl font-extrabold text-brand">@rp($tx->agreed_price)</div>@if($disc)<div class="text-xs text-ink/40 line-through">@rp($listing->current_price)</div><div class="mono rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">Hemat @rp($disc)</div>@endif</div></div>
      <div class="mt-3 text-xs text-ink/60">Barang ditahan hingga {{ $tx->hold_until->format('d M Y H:i') }} WIB.</div></div>

    <div class="card p-5"><h2 class="text-lg font-extrabold">Pilih Metode Pengiriman & Serah Terima</h2>
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <label class="cursor-pointer rounded-xl border-2 p-4" :class="m==='ekspedisi' ? 'border-brand bg-brand-light/40' : 'border-sand'"><input type="radio" class="sr-only" x-on:click="m='ekspedisi'">
          <div class="font-bold">🚚 Ekspedisi Reguler</div><p class="mt-1 text-xs text-ink/60">Kurir dengan nomor resi. Dana ditahan rekber sampai Anda konfirmasi terima.</p><div class="mt-3 flex justify-between text-xs"><span class="mono text-ink/50">Estimasi 2-3 hari</span><b>@rp($cfg['shipping_flat'])</b></div></label>
        <label class="rounded-xl border-2 p-4 {{ $codOk ? 'cursor-pointer' : 'opacity-50' }}" :class="m==='cod' ? 'border-brand bg-brand-light/40' : 'border-sand'"><input type="radio" class="sr-only" x-on:click="{{ $codOk ? "m='cod'" : '' }}">
          <div class="flex items-center justify-between font-bold"><span>🤝 COD / Ketemuan</span>@if($codOk)<span class="mono rounded-full bg-brand px-2 py-0.5 text-[9px] text-white">TERPILIH</span>@endif</div>
          <p class="mt-1 text-xs text-ink/60">{{ $codOk ? 'Cek kondisi fisik barang langsung sebelum memberi kode serah terima.' : 'Tidak tersedia: penjual tidak membuka COD atau area Anda di luar radius.' }}</p>
          <div class="mt-3 flex justify-between text-xs"><span class="mono text-ink/50">@if($codKm!==null){{ number_format($codKm,1) }} km dari titik COD @endif</span><b class="text-brand">Gratis Rp0</b></div></label></div>
      @error('delivery_method')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror</div>

    <div class="card p-5" x-show="m==='cod'" x-cloak><h2 class="text-lg font-extrabold">Safe Meeting Point Hub</h2><p class="text-sm text-ink/60">Pilih titik temu publik terverifikasi. Penjual dapat mengonfirmasi atau mengusulkan ulang.</p>
      @include('partials.meeting-map', ['points' => $points, 'sync' => true, 'selected' => old('meeting_point_id', $points->firstWhere('name', $listing->cod_location)?->id),
        'circle' => $listing->cod_latitude ? ['lat' => (float) $listing->cod_latitude, 'lng' => (float) $listing->cod_longitude, 'km' => (int) $listing->cod_radius_km] : null, 'height' => 300])
      <div class="mt-3 space-y-2">@foreach($points as $p)<label class="flex cursor-pointer items-start gap-3 rounded-xl bg-sand p-3 has-[:checked]:ring-2 has-[:checked]:ring-brand"><input type="radio" name="meeting_point_id" value="{{ $p->id }}" @checked(old('meeting_point_id', $listing->cod_location === $p->name ? $p->id : null) == $p->id) class="mt-1 text-brand">
        <span class="text-sm"><b>{{ $p->name }}</b> @if($p->recommended)<span class="ml-1 rounded bg-amber-200 px-1.5 py-0.5 text-[10px] font-bold">Rekomendasi Aman</span>@endif<br><span class="text-xs text-ink/60">{{ $p->address }}</span><br><span class="mono text-[10px] text-brand">{{ collect($p->features)->join(' · ') }}</span></span></label>@endforeach</div>
      <div class="mt-4 grid gap-3 sm:grid-cols-2"><div><label class="text-sm font-bold">Tanggal & waktu</label><input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" class="field mt-1"></div>
        <div><label class="text-sm font-bold">Cara bayar COD</label><div class="mt-1 grid grid-cols-2 gap-2 text-xs font-bold"><label class="rounded-xl bg-sand p-3"><input type="radio" value="app" x-model="pay" class="mr-1 text-brand">Via aplikasi (rekber)</label><label class="rounded-xl bg-sand p-3"><input type="radio" value="cash" x-model="pay" class="mr-1 text-brand">Tunai / QRIS di tempat</label></div></div></div>
      <p class="mt-2 text-xs text-ink/60">Toleransi keterlambatan {{ $cfg['cod_no_show_minutes'] }} menit dari jam janji temu. Tunai di tempat berada di luar perlindungan rekber.</p></div>
  </div>

  <aside class="h-fit space-y-4 lg:sticky lg:top-32">
    <div class="rounded-2xl bg-indigoX p-5 text-white"><div class="mono text-[10px] font-bold uppercase text-white/70">Kunci serah terima aman</div>
      <p class="mt-2 text-sm">Kode 6 digit + QR akan dibuat setelah <b>penjual mengonfirmasi jadwal</b>, dan hanya tampil di akun pembeli. Berikan kode setelah barang diperiksa.</p></div>
    <div class="card p-5"><h3 class="font-extrabold">Voucher</h3>
      <div class="mt-2 flex gap-2"><input name="voucher_code" x-model="code" @keydown.enter.prevent="check()" placeholder="Kode voucher" class="field uppercase"><button type="button" class="btn btn-soft" @click="check()">Pakai</button></div>
      <p class="mt-2 text-xs font-bold" :class="vok ? 'text-brand' : 'text-red-600'" x-text="vmsg"></p>
      @error('voucher_code')<p class="mt-1 text-xs font-bold text-red-600">{{ $message }}</p>@enderror</div>
    <div class="card p-5"><h3 class="font-extrabold">Rincian Pembayaran</h3>
      <dl class="mt-3 space-y-2 text-sm"><div class="flex justify-between"><dt>Harga barang</dt><dd x-text="rp(price)"></dd></div>
        <div class="flex justify-between"><dt :class="m==='cod' ? 'text-brand font-bold' : ''">Ongkos kirim</dt><dd x-text="shipping===0 ? 'Rp0 (Gratis)' : rp(shipping)" :class="m==='cod' ? 'text-brand font-bold' : ''"></dd></div>
        <div class="flex justify-between"><dt>Biaya layanan / rekber</dt><dd x-text="rp(service)"></dd></div>
        <div class="flex justify-between text-brand" x-show="disc>0" x-cloak><dt>Voucher</dt><dd x-text="'-'+rp(disc)"></dd></div></dl>
      <div class="mt-3 rounded-xl bg-sand p-3"><div class="flex items-center justify-between"><span class="font-bold">Total Tagihan</span><span class="text-2xl font-extrabold text-brand" x-text="rp(total)"></span></div>
        <p class="mt-1 text-[11px] text-ink/60">Pembayaran via rekber (simulasi sandbox): VA / e-wallet / QRIS akan terhubung ke payment gateway.</p></div>
      <button class="btn btn-brand mt-4 w-full !py-3.5 !text-sm" x-text="m==='cod' ? 'Konfirmasi Jadwal & Kunci COD' : 'Buat Pesanan & Lanjut Bayar'"></button>
      </div>
  </aside>
</form>
<form method="POST" action="{{ route('orders.cancel', $tx) }}" class="mt-4 text-center">@csrf<button class="text-sm font-bold text-red-600" onclick="return confirm('Batalkan pesanan ini?')">Batalkan pesanan</button></form>
</div>
@endsection
