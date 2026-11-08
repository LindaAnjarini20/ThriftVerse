@extends('layouts.app')
@section('title', 'Jadwal COD')
@section('content')
@php $other = $tx->counterpart($me); $active = in_array($s->status, ['proposed','confirmed']) && $tx->isOpen(); $iAmProposer = $s->proposed_by === $me->id;
  $myArr = $isBuyer ? $s->buyer_arrived_at : $s->seller_arrived_at; $theirArr = $isBuyer ? $s->seller_arrived_at : $s->buyer_arrived_at; @endphp
<div class="grid gap-6 lg:grid-cols-[1fr_380px]">
  <div class="space-y-5">
    <a href="{{ route('orders.show', $tx) }}" class="text-sm font-bold">← Kembali ke pesanan {{ $tx->transaction_number }}</a>
    <div class="card p-5"><div class="flex items-center gap-4"><div class="h-16 w-16 overflow-hidden rounded-xl bg-sand">@include('partials.cover', ['listing' => $tx->listing])</div>
      <div class="flex-1"><div class="font-extrabold">{{ $tx->listing->title }}</div><div class="text-xs text-ink/60">{{ $isBuyer ? 'Penjual' : 'Pembeli' }}: {{ $other->name }} · 📞 {{ $s->status === 'confirmed' || $s->status === 'completed' ? ($other->no_hp ?: '—') : $other->maskedPhone() }} @if($s->status==='proposed')<span class="text-ink/40">(disamarkan sampai jadwal disepakati)</span>@endif</div></div>
      <span class="mono rounded px-2 py-1 text-[10px] font-bold uppercase bg-sand">{{ $s->status }}</span></div></div>

    <div class="card p-5"><h2 class="text-lg font-extrabold">Titik temu & waktu</h2>
      @if($s->point)<div class="mt-2 rounded-xl bg-sand p-3 text-sm"><b>{{ $s->point->name }}</b><br><span class="text-xs text-ink/60">{{ $s->point->address }}</span><br><span class="mono text-[10px] text-brand">{{ collect($s->point->features)->join(' · ') }}</span>
        <div class="mt-2 text-sm font-bold">🗓 {{ $s->scheduled_at->translatedFormat('l, d M Y') }} · {{ $s->scheduled_at->format('H:i') }} WIB</div>
        <a class="text-xs font-bold text-indigoX" target="_blank" href="https://www.google.com/maps?q={{ $s->point->latitude }},{{ $s->point->longitude }}">Buka peta ↗</a>
        <div class="mt-3">@include('partials.meeting-map', ['points' => collect([$s->point]), 'selected' => $s->point->id, 'height' => 220])</div></div>@endif
      @if($active)
        @if($s->status==='proposed' && ! $iAmProposer)<form method="POST" action="{{ route('cod.confirm', $s) }}" class="mt-3">@csrf<button class="btn btn-brand w-full">Konfirmasi jadwal ini</button></form>
        @elseif($s->status==='proposed')<p class="mt-3 rounded-lg bg-amber-50 p-2.5 text-sm text-amber-900">Menunggu {{ $other->name }} mengonfirmasi usulan Anda.</p>@endif
        <details class="mt-3 rounded-xl bg-sand p-3 text-sm"><summary class="cursor-pointer font-bold">Usulkan titik temu / waktu lain</summary>
          <form method="POST" action="{{ route('cod.propose', $s) }}" class="mt-3 grid gap-2 sm:grid-cols-2">@csrf
            <select name="meeting_point_id" class="field">@foreach($points as $p)<option value="{{ $p->id }}" @selected($s->meeting_point_id===$p->id)>{{ $p->name }}</option>@endforeach</select>
            <input type="datetime-local" name="scheduled_at" required class="field"><button class="btn btn-indigo sm:col-span-2">Kirim usulan</button></form></details>
      @endif</div>

    @if($active && $s->status==='confirmed')
    <div class="card p-5"><h2 class="flex items-center justify-between text-lg font-extrabold"><span>Aksi lapangan saat di titik temu</span><span class="mono text-[10px] text-ink/50">FR-090 s.d. FR-098</span></h2>
      <p class="text-sm text-ink/60">Gunakan kontrol ini saat Anda berada di lokasi temu. Semua aksi tersimpan sebagai rekaman sengketa.</p>
      <div class="mt-3 grid gap-3 sm:grid-cols-3" x-data="{ geo(){ const f=this.$refs.arrive; if(!navigator.geolocation){f.submit();return} navigator.geolocation.getCurrentPosition(p=>{f.latitude.value=p.coords.latitude;f.longitude.value=p.coords.longitude;f.submit()},()=>f.submit(),{timeout:6000}) } }">
        <form x-ref="arrive" method="POST" action="{{ route('cod.arrive', $s) }}">@csrf<input type="hidden" name="latitude"><input type="hidden" name="longitude">
          <button type="button" @click="geo()" class="btn btn-brand h-full w-full flex-col !py-4" @disabled($myArr)>📍 Saya Sudah Tiba<span class="text-[10px] font-semibold opacity-80">{{ $myArr ? 'Tercatat '.$myArr->format('H:i') : 'Check-in GPS (opsional)' }}</span></button></form>
        @if($isBuyer)<details class="rounded-xl bg-sand p-3 text-xs sm:col-span-1"><summary class="cursor-pointer text-center text-sm font-bold">✖ Tolak di Tempat</summary>
          <form method="POST" action="{{ route('cod.reject', $s) }}" enctype="multipart/form-data" class="mt-2 space-y-2">@csrf<textarea name="reason" required rows="2" class="field" placeholder="Alasan (tidak sesuai grade/foto)"></textarea><input type="file" name="evidence[]" accept="image/*" multiple required class="field"><button class="btn btn-danger w-full">Tolak & batalkan</button></form></details>@endif
        <details class="rounded-xl bg-sand p-3 text-xs"><summary class="cursor-pointer text-center text-sm font-bold">⚠ Laporkan Masalah</summary>
          <form method="POST" action="{{ route('cod.problem', $s) }}" class="mt-2 space-y-2">@csrf<textarea name="reason" required minlength="10" rows="2" class="field" placeholder="Jelaskan masalah"></textarea><button class="btn btn-soft w-full">Kirim ke admin</button></form></details>
      </div>
      <div class="mt-3 flex gap-4 text-xs font-bold"><span>{{ $s->buyer_arrived_at ? '✅' : '⏳' }} Pembeli @if($s->buyer_arrival_verified)<span class="text-brand">(GPS ✓)</span>@endif</span><span>{{ $s->seller_arrived_at ? '✅' : '⏳' }} Penjual @if($s->seller_arrival_verified)<span class="text-brand">(GPS ✓)</span>@endif</span></div>
      @if($noShowReady && $myArr && ! $theirArr)<form method="POST" action="{{ route('cod.noshow', $s) }}" class="mt-3">@csrf<button class="btn btn-danger w-full" onclick="return confirm('Laporkan Gagal Bertemu?')">Laporkan Gagal Bertemu (pihak lawan tidak hadir &gt; {{ config('thriftverse.cod_no_show_minutes') }} menit)</button></form>@endif
    </div>@endif
  </div>

  <aside class="space-y-4 lg:sticky lg:top-32 lg:h-fit">
    @if($isBuyer && $code)
    <div class="rounded-2xl bg-indigoX p-5 text-white"><div class="flex items-center justify-between"><span class="mono text-[10px] font-bold uppercase">Kunci serah terima aman</span><span class="mono rounded-full bg-white/20 px-2 py-0.5 text-[9px]">Terenkripsi</span></div>
      <div class="mono mt-3 rounded-xl bg-white py-3 text-center text-3xl font-extrabold tracking-[.3em] text-ink">{{ substr($code,0,3) }}-{{ substr($code,3) }}</div>
      <div class="mt-3 flex justify-center rounded-xl bg-white p-3"><div id="qr"></div></div>
      <p class="mt-3 rounded-lg bg-white/15 p-3 text-xs"><b>PERINGATAN:</b> jangan berikan kode ini sebelum Anda memeriksa fisik barang dan puas dengan kondisinya!</p></div>
    @push('scripts')<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>new QRCode(document.getElementById('qr'),{text:'THRIFTVERSE:{{ $tx->transaction_number }}:{{ $code }}',width:150,height:150})</script>@endpush
    @elseif(! $isBuyer && $s->status==='confirmed' && $active)
    <div class="card p-5"><h3 class="font-extrabold">Masukkan kode serah terima</h3><p class="text-sm text-ink/60">Minta pembeli menunjukkan kode 6 digit setelah ia memeriksa barang.</p>
      @if($tx->payment_method==='app' && $tx->escrow_status!=='funded')<p class="mt-2 rounded-lg bg-amber-50 p-2 text-xs text-amber-900">Pembeli belum membayar via aplikasi. Serah terima ditahan sampai dana masuk rekber.</p>@endif
      <form method="POST" action="{{ route('cod.handover', $s) }}" class="mt-3 space-y-2">@csrf<input name="code" inputmode="numeric" maxlength="6" placeholder="••••••" class="field mono text-center text-2xl font-extrabold tracking-[.3em]"><button class="btn btn-brand w-full">Selesaikan Serah Terima</button></form></div>
    @endif
    <div class="card p-5 text-sm"><h3 class="font-extrabold">Rincian Pembayaran</h3><dl class="mt-3 space-y-2"><div class="flex justify-between"><dt>Harga</dt><dd>@rp($tx->agreed_price)</dd></div><div class="flex justify-between"><dt>Ongkir titik temu</dt><dd class="font-bold text-brand">Rp0 (Gratis)</dd></div><div class="flex justify-between"><dt>Biaya layanan</dt><dd>@rp($tx->service_fee)</dd></div>@if($tx->discount > 0)<div class="flex justify-between text-brand"><dt>Voucher</dt><dd>-@rp($tx->discount)</dd></div>@endif<div class="flex justify-between border-t border-sand pt-2 text-lg font-extrabold"><dt>Total</dt><dd class="text-brand">@rp($tx->total)</dd></div></dl>
      <p class="mt-2 text-xs text-ink/60">{{ $tx->payment_method==='cash' ? 'Bayar tunai/QRIS pribadi penjual saat bertemu.' : 'Dana ditahan rekber: '.['none'=>'belum dibayar','pending'=>'belum dibayar','funded'=>'sudah masuk rekber','released'=>'dilepas ke penjual','refunded'=>'dikembalikan'][$tx->escrow_status] }}</p>
      @if($tx->payment_method==='app' && $tx->escrow_status==='pending' && $tx->payer_id===$me->id)<form method="POST" action="{{ route('orders.pay', $tx) }}" class="mt-3">@csrf<button class="btn btn-brand w-full">Bayar via aplikasi (simulasi)</button></form>@endif</div>
    <div class="rounded-xl bg-amber-50 p-4 text-xs text-amber-900"><b>Tips aman:</b> bertemu di tempat umum dan siang hari, jangan bagikan kode sebelum barang diperiksa, laporkan pihak yang mencurigakan.</div>
  </aside>
</div>
@endsection
