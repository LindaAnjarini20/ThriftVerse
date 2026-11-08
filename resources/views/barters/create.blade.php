@extends('layouts.app')
@section('title', 'Ajukan Barter')
@section('content')
<div class="mx-auto max-w-3xl" x-data="{ vals: {{ Js::from($mine->pluck('current_price', 'id')) }}, picked: [], top: 0,
   get offered(){ return this.picked.reduce((s,id)=>s+Number(this.vals[id]||0),0) }, target: {{ (int) $listing->current_price }},
   get pct(){ return Math.abs(this.target-this.offered)/Math.max(this.target,1)*100 },
   get label(){ return this.pct<={{ config('thriftverse.barter_balanced_pct') }} ? 'Seimbang' : (this.pct<={{ config('thriftverse.barter_topup_pct') }} ? 'Perlu Tambahan' : 'Timpang') },
   get diff(){ return this.target-this.offered } }">
  <h1 class="text-2xl font-extrabold">Ajukan Barter / Tukar Tambah</h1>
  <div class="card mt-4 flex items-center gap-4 p-4"><div class="h-20 w-20 overflow-hidden rounded-lg bg-sand">@include('partials.cover', ['listing' => $listing])</div>
    <div><div class="mono text-[10px] font-bold uppercase text-clay">Barang target</div><div class="font-extrabold">{{ $listing->title }}</div><div class="text-brand font-bold">@rp($listing->current_price)</div>@if($listing->barter_preference)<div class="text-xs text-ink/60">Dicari: {{ $listing->barter_preference }}</div>@endif</div></div>
  <form method="POST" action="{{ route('barters.store', $listing) }}" class="card mt-4 space-y-5 p-6">@csrf
    <div><div class="mb-2 text-sm font-bold">Pilih 1–3 barang Anda untuk ditukar</div>
      @forelse($mine as $m)<label class="mb-2 flex items-center gap-3 rounded-xl bg-sand p-3 text-sm"><input type="checkbox" name="items[]" value="{{ $m->id }}" x-model="picked" :disabled="picked.length>=3 && !picked.includes('{{ $m->id }}')" class="rounded text-brand"><span class="flex-1 font-bold">{{ $m->title }}</span><span>@rp($m->current_price)</span></label>
      @empty<p class="rounded-xl bg-sand p-4 text-sm">Anda belum punya listing aktif. <a class="font-bold text-brand" href="{{ route('listings.create') }}">Pasang listing dulu</a>.</p>@endforelse</div>
    <div class="rounded-xl p-4" :class="label==='Seimbang' ? 'bg-brand-light' : (label==='Perlu Tambahan' ? 'bg-amber-100' : 'bg-red-100')">
      <div class="mono text-[10px] font-bold uppercase">Kalkulator paritas</div>
      <div class="mt-1 text-sm">Nilai tawaran Anda <b x-text="'Rp'+offered.toLocaleString('id-ID')"></b> vs target <b x-text="'Rp'+target.toLocaleString('id-ID')"></b></div>
      <div class="mt-1 text-lg font-extrabold"><span x-text="label"></span> <span class="text-sm font-semibold" x-text="'(selisih '+pct.toFixed(1)+'%)'"></span></div>
      <div class="text-xs text-ink/70">Indikator hanya peringatan, tidak memblokir. Uang tambahan disarankan: <b x-text="'Rp'+Math.max(0,diff).toLocaleString('id-ID')"></b></div></div>
    <div class="grid gap-3 sm:grid-cols-2"><div><label class="text-sm font-bold">Uang tambahan (Rp)</label><input type="number" min="0" name="cash_top_up" x-model.number="top" class="field mt-1"></div>
      <div><label class="text-sm font-bold">Pihak pembayar</label><select name="top_up_payer" class="field mt-1"><option value="offerer">Saya (pengaju)</option><option value="owner">Pemilik barang target</option></select></div></div>
    <div><label class="text-sm font-bold">Catatan</label><textarea name="message" rows="2" class="field mt-1"></textarea></div>
    <p class="text-xs text-ink/60">Serah terima lewat ketemuan di titik temu aman (ongkir Rp0). Uang tambahan dibayar lewat rekber.</p>
    <button class="btn btn-clay w-full !py-3.5 !text-base" :disabled="picked.length===0">Kirim Pengajuan Barter</button>
  </form>
</div>
@endsection
