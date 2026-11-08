@extends('layouts.app')
@section('title', 'Pesanan '.$tx->transaction_number)
@section('content')
@php $l = $tx->listing; $steps = $tx->isCod() ? ['Checkout','Jadwal','Menunggu Pertemuan','Selesai'] : ['Belum Bayar','Dikemas','Dikirim','Selesai'];
  $idx = match($tx->status) { 'pending' => 0, 'packed' => ($tx->isCod() ? 2 : 1), 'shipped' => 2, 'completed' => 3, default => -1 };
  if ($tx->isCod() && $tx->status === 'pending' && $tx->schedule) $idx = 1;
  $myReview = $tx->reviews->firstWhere('reviewer_id', $me->id); @endphp
<div class="mx-auto max-w-4xl space-y-4">
  <div class="flex flex-wrap items-center justify-between gap-3"><div><div class="mono text-[11px] uppercase text-ink/50">{{ $tx->transaction_number }} · {{ $tx->type==='barter' ? 'Barter' : 'Pembelian' }}</div><h1 class="text-2xl font-extrabold">{{ $l->title }}</h1></div>
    <span class="mono rounded-lg px-3 py-2 text-xs font-bold uppercase {{ $tx->status==='completed' ? 'bg-brand-light text-brand-dark' : ($tx->status==='cancelled' ? 'bg-red-100 text-red-800' : 'bg-sand') }}">{{ $tx->statusLabel() }}</span></div>
  @if($idx >= 0)<div class="card grid grid-cols-4 gap-2 p-4 text-center text-[11px] font-bold">@foreach($steps as $i => $s)<div><div class="mx-auto mb-1 flex h-7 w-7 items-center justify-center rounded-full {{ $i <= $idx ? 'bg-brand text-white' : 'bg-sand' }}">{{ $i+1 }}</div>{{ $s }}</div>@endforeach</div>@endif
  @if($tx->status==='cancelled')<div class="rounded-xl bg-red-50 p-4 text-sm text-red-800"><b>Dibatalkan:</b> {{ $tx->cancel_reason }}</div>@endif

  <div class="grid gap-4 md:grid-cols-[1.3fr_1fr]">
    <div class="space-y-4">
      <div class="card flex gap-4 p-5"><div class="h-20 w-20 overflow-hidden rounded-xl bg-sand">@include('partials.cover', ['listing' => $l])</div>
        <div class="text-sm"><a class="font-bold" href="{{ route('listings.show', $l) }}">{{ $l->title }}</a><div class="mt-1">@include('partials.grade', ['grade' => $l->condition_grade, 'verified' => (bool) $l->verified_at])</div>
          <div class="mt-2 text-ink/70">Pembeli: <b>{{ $tx->buyer->name }}</b><br>Penjual: <b>{{ $tx->seller->name }}</b></div></div></div>
      @if($tx->barter)<div class="card p-5 text-sm"><div class="mono mb-1 text-[10px] font-bold uppercase text-clay">Barter</div>{{ $tx->barter->items->pluck('title')->join(', ') }} ⇄ {{ $l->title }} · <a class="font-bold text-clay" href="{{ route('barters.show', $tx->barter) }}">Lihat ringkasan</a></div>@endif

      {{-- AKSI --}}
      <div class="card space-y-3 p-5"><h2 class="font-extrabold">Tindakan</h2>
        @if($tx->status==='pending' && ! $tx->delivery_method && $isBuyer && $tx->type==='purchase')<a href="{{ route('orders.checkout', $tx) }}" class="btn btn-brand w-full">Lanjut Checkout</a>@endif
        @if($tx->status==='pending' && $tx->escrow_status==='pending' && $tx->payer_id===$me->id)
          <form method="POST" action="{{ route('orders.pay', $tx) }}">@csrf<button class="btn btn-brand w-full !py-3">Bayar @rp($tx->total) (simulasi sandbox)</button></form>
          <p class="text-xs text-ink/60">Batas {{ $tx->hold_until?->format('d M H:i') }} WIB. Ganti dengan payment gateway nyata (VA/e-wallet/QRIS) pada rilis berikutnya.</p>@endif
        @if($tx->cart_group && $isBuyer && $tx->status==='pending' && $tx->escrow_status==='pending')<form method="POST" action="{{ route('cart.payall', $tx->cart_group) }}">@csrf<button class="btn btn-soft w-full">Bayar semua pesanan keranjang ini (simulasi)</button></form>@endif
        @if($tx->isCod() && $tx->schedule)<a href="{{ route('cod.show', $tx->schedule) }}" class="btn btn-indigo w-full">Buka Jadwal & Serah Terima COD</a>@endif
        @if($isSeller && $tx->status==='packed' && $tx->delivery_method==='ekspedisi')
          <form method="POST" action="{{ route('orders.ship', $tx) }}" class="grid gap-2 sm:grid-cols-3">@csrf<select name="courier" class="field"><option>JNE</option><option>SiCepat</option><option>J&T</option><option>Pos Indonesia</option></select><input name="tracking_no" required placeholder="Nomor resi" class="field"><button class="btn btn-brand">Input Resi</button></form>
          <p class="text-xs text-ink/60">Wajib dikirim sebelum {{ $tx->ship_by?->format('d M H:i') }} WIB atau pesanan dibatalkan otomatis.</p>@endif
        @if($tx->status==='shipped')<div class="rounded-xl bg-sand p-3 text-sm">Resi <b>{{ $tx->tracking_no }}</b> ({{ $tx->courier }}) · otomatis selesai {{ $tx->auto_complete_at?->format('d M') }}</div>
          @if($isBuyer)<form method="POST" action="{{ route('orders.receive', $tx) }}">@csrf<button class="btn btn-brand w-full">Konfirmasi Barang Diterima</button></form>@endif @endif
        @if($isBuyer && in_array($tx->status, ['packed','shipped']))
          <details class="rounded-xl bg-sand p-3 text-sm"><summary class="cursor-pointer font-bold">Ajukan komplain / pengembalian</summary>
            <form method="POST" action="{{ route('orders.dispute', $tx) }}" enctype="multipart/form-data" class="mt-3 space-y-2">@csrf<textarea name="reason" required minlength="10" rows="3" class="field" placeholder="Jelaskan ketidaksesuaian grade/foto"></textarea><input type="file" name="evidence[]" accept="image/*" multiple required class="field"><button class="btn btn-danger">Kirim komplain</button></form></details>@endif
        @if(in_array($tx->status, ['pending','packed']) && ($isSeller || ($isBuyer && $tx->status==='pending')))
          <form method="POST" action="{{ route('orders.cancel', $tx) }}" onsubmit="return confirm('Batalkan pesanan?')">@csrf<button class="btn btn-danger w-full">Batalkan pesanan</button></form>@endif
        @if($tx->status==='dispute')<div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Sengketa dalam penanganan admin. Dana tetap ditahan.</div>@endif
        @if($tx->status==='completed' && ! $myReview)
          <form method="POST" action="{{ route('orders.review', $tx) }}" class="space-y-2 rounded-xl bg-sand p-3 text-sm">@csrf<div class="font-bold">Beri ulasan untuk {{ $tx->counterpart($me)->name }}</div>
            <select name="rating" class="field">@foreach([5,4,3,2,1] as $r)<option value="{{ $r }}">{{ str_repeat('★', $r) }}</option>@endforeach</select>
            <label class="flex items-center gap-2"><input type="checkbox" name="grade_accurate" value="1" checked class="rounded text-brand"> Grade/foto sesuai kondisi barang</label>
            <textarea name="comment" rows="2" class="field" placeholder="Komentar (opsional)"></textarea><button class="btn btn-brand">Kirim ulasan</button></form>
        @elseif($myReview)<div class="text-sm text-ink/60">Ulasan Anda: {{ str_repeat('★', $myReview->rating) }} — terima kasih.</div>@endif
      </div>
    </div>
    <div class="space-y-4">
      <div class="card p-5 text-sm"><h2 class="font-extrabold">Rincian</h2>
        <dl class="mt-3 space-y-2"><div class="flex justify-between"><dt>{{ $tx->type==='barter' ? 'Uang tambahan' : 'Harga barang' }}</dt><dd>@rp($tx->agreed_price)</dd></div>
          <div class="flex justify-between"><dt>Ongkos kirim</dt><dd>{{ $tx->delivery_method ? ($tx->shipping_cost > 0 ? 'Rp'.number_format($tx->shipping_cost,0,',','.') : 'Rp0 (Gratis)') : '—' }}</dd></div>
          <div class="flex justify-between"><dt>Biaya layanan</dt><dd>@rp($tx->service_fee)</dd></div>
          @if($tx->discount > 0)<div class="flex justify-between text-brand"><dt>Voucher{{ $tx->voucher ? ' ('.$tx->voucher->code.')' : '' }}</dt><dd>-@rp($tx->discount)</dd></div>@endif
          <div class="flex justify-between border-t border-sand pt-2 text-base font-extrabold"><dt>Total</dt><dd class="text-brand">@rp($tx->total)</dd></div></dl>
        <div class="mt-3 rounded-lg bg-sand p-3 text-xs"><b>Rekber:</b> {{ ['none' => $tx->payment_method==='cash' ? 'Bayar tunai di tempat (di luar rekber)' : 'Belum ada dana', 'pending' => 'Menunggu pembayaran', 'funded' => 'Dana ditahan platform', 'released' => 'Dana diteruskan ke penjual', 'refunded' => 'Dana dikembalikan ke pembeli'][$tx->escrow_status] }}</div></div>
      @if($tx->reports->count())<div class="card p-5 text-sm"><h2 class="font-extrabold">Laporan</h2>@foreach($tx->reports as $r)<div class="mt-2 rounded-lg bg-sand p-2.5"><span class="mono text-[10px] uppercase">{{ $r->type }} · {{ $r->status }}</span><br>{{ $r->reason }}</div>@endforeach</div>@endif
    </div>
  </div>
</div>
@endsection
