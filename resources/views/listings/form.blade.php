@extends('layouts.app')
@section('title', $listing ? 'Ubah Listing' : 'Pasang Listing')
@section('content')
@php
  $edit = (bool) $listing; $cl = $listing?->condition_checklist ?? [];
  $meta = collect(\App\Models\Listing::CHECKLIST)->map(fn ($v) => ['p' => $v[1], 'h' => $v[2]]);
  $ov = fn($k,$d=null) => old($k, $listing?->$k ?? $d);
@endphp
<div class="card mx-auto max-w-4xl p-6 sm:p-8" x-data="{
    meta: {{ Js::from($meta) }}, picked: {{ Js::from(old('checklist', $cl)) }},
    get grade(){ let p=0,h=false; this.picked.forEach(k=>{p+=this.meta[k].p; h=h||this.meta[k].h}); return p===0?'A':((h||p>=4)?'C':'B') },
    tetap: {{ $ov('harga_tetap', true) ? 'true':'false' }}, tawar: {{ $ov('bisa_tawar', false) ? 'true':'false' }}, barter: {{ $ov('terbuka_barter', false) ? 'true':'false' }}, cod: {{ $ov('bisa_cod', false) ? 'true':'false' }}, drop: {{ $ov('auto_drop', false) ? 'true':'false' }} }">
  <div class="flex flex-wrap items-start justify-between gap-3"><div><h1 class="text-2xl font-extrabold">{{ $edit ? 'Ubah Listing' : 'Pemasangan Listing & Grading Baku' }}</h1>
    <p class="text-sm text-ink/60">Setiap listing melewati verifikasi inspektur kurasi ThriftVerse untuk mengamankan pelepasan dana escrow.</p></div>
    <span class="mono rounded-lg bg-sand px-3 py-2 text-[10px] font-bold uppercase">Protokol verifikasi v1</span></div>

  <form method="POST" enctype="multipart/form-data" action="{{ $edit ? route('listings.update', $listing) : route('listings.store') }}" class="mt-6 space-y-8">
    @csrf @if($edit)@method('PUT')@endif
    <section class="space-y-4"><h2 class="mono text-[11px] font-bold uppercase text-ink/50">01. Metadata pakaian</h2>
      <div><label class="text-sm font-bold">Nama barang lengkap</label><input name="title" value="{{ $ov('title') }}" required class="field mt-1"></div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="text-sm font-bold">Kategori</label><select name="category" class="field mt-1">@foreach(['Outerwear','Denim & Celana Panjang','Kaos Band','Kemeja','Sepatu','Aksesoris','Lainnya'] as $c)<option @selected($ov('category')===$c)>{{ $c }}</option>@endforeach</select></div>
        <div><label class="text-sm font-bold">Merek</label><input name="brand" value="{{ $ov('brand') }}" class="field mt-1"></div>
        <div><label class="text-sm font-bold">Ukuran / tag</label><input name="size" value="{{ $ov('size') }}" class="field mt-1" placeholder="L / W32 L34 / EU 42"></div>
        <div><label class="text-sm font-bold">Warna</label><input name="color" value="{{ $ov('color') }}" class="field mt-1"></div>
        <div><label class="text-sm font-bold">Bahan</label><input name="material" value="{{ $ov('material') }}" class="field mt-1"></div>
        <div><label class="text-sm font-bold">Harga baru (referensi)</label><input type="number" name="original_price" value="{{ $ov('original_price', 0) }}" @disabled($edit) class="field mt-1"></div>
        <div><label class="text-sm font-bold">Harga jual (Rp)</label><input type="number" name="current_price" value="{{ $ov('current_price') }}" required class="field mt-1"></div>
        <div x-show="tawar"><label class="text-sm font-bold">Harga minimum rahasia (auto-terima)</label><input type="number" name="secret_min_price" value="{{ $ov('secret_min_price') }}" class="field mt-1" placeholder="Opsional"></div>
      </div>
      <div><label class="text-sm font-bold">Deskripsi</label><textarea name="description" rows="3" class="field mt-1">{{ $ov('description') }}</textarea></div>
    </section>

    @unless($edit)
    <section class="space-y-4"><div class="flex items-center justify-between"><h2 class="mono text-[11px] font-bold uppercase text-ink/50">02. Penilaian grade baku</h2><span class="mono text-[10px] font-bold text-brand">Grade dihitung otomatis dari checklist</span></div>
      <div class="grid gap-3 sm:grid-cols-3">
        @foreach(['A' => ['Mulus · Like New','Tidak ada noda, warna pekat, tanpa ubahan.','brand'], 'B' => ['Minus ringan · Estetik','Fading, bulu halus, minus kecil yang tidak mengganggu.','indigoX'], 'C' => ['Minus nyata · Rework','Noda sisa, lubang kecil, jahitan lepas jelas.','clay']] as $g => [$t,$d,$col])
        <div class="rounded-xl border-2 p-4 transition" :class="grade==='{{ $g }}' ? 'border-{{ $col }} bg-white shadow' : 'border-transparent bg-sand opacity-70'">
          <div class="text-xl font-extrabold text-{{ $col }}">Grade {{ $g }}</div><div class="mono text-[10px] font-bold uppercase text-{{ $col }}">{{ $t }}</div><p class="mt-1 text-xs text-ink/70">{{ $d }}</p></div>@endforeach</div>
      <div class="rounded-xl bg-sand p-4"><div class="mb-2 text-sm font-bold">Checklist minus wajib terdata (pilih jika ada):</div>
        <div class="grid gap-2 sm:grid-cols-2">@foreach(\App\Models\Listing::CHECKLIST as $k => $v)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="checklist[]" value="{{ $k }}" x-model="picked" class="rounded text-brand"> {{ $v[0] }}</label>@endforeach</div></div>
    </section>
    <section class="space-y-3"><h2 class="mono text-[11px] font-bold uppercase text-ink/50">03. Foto kurasi wajib</h2>
      <div class="grid gap-3 sm:grid-cols-2">
        @foreach(['photo_front' => '1. Tampak depan', 'photo_back' => '2. Tampak belakang', 'photo_label' => '3. Label / wash tag', 'photo_detail' => '4. Detail bahan'] as $n => $lab)
        <div><label class="text-sm font-bold">{{ $lab }}</label><input type="file" name="{{ $n }}" accept="image/*" required class="field mt-1"></div>@endforeach</div>
      <div x-show="grade!=='A'" x-cloak class="rounded-xl border-2 border-dashed border-clay/50 bg-clay/5 p-4"><div class="mono mb-1 text-[11px] font-bold uppercase text-clay">Wajib detail minus (Grade B/C)</div>
        <input type="file" name="minus_photos[]" accept="image/*" multiple class="field">
        <div x-show="grade==='C'" class="mt-3"><label class="text-sm font-bold">Catatan minus</label><textarea name="minus_note" rows="2" class="field mt-1">{{ old('minus_note') }}</textarea></div></div>
      <p class="text-xs text-ink/60">Format JPG/PNG/WebP, maks. 5 MB per foto.</p></section>
    @endunless

    <section class="space-y-3"><h2 class="mono text-[11px] font-bold uppercase text-ink/50">04. Protokol & opsi transaksi fleksibel</h2>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <label class="rounded-xl bg-sand p-3 text-sm"><input type="checkbox" name="harga_tetap" value="1" x-model="tetap" class="mr-1 rounded text-brand"><b>Harga Tetap</b><br><span class="text-xs text-ink/60">Beli langsung tanpa nego.</span></label>
        <label class="rounded-xl bg-sand p-3 text-sm"><input type="checkbox" name="bisa_tawar" value="1" x-model="tawar" class="mr-1 rounded text-brand"><b>Bisa Nego inDrive</b><br><span class="text-xs text-ink/60">Pembeli mengajukan harga.</span></label>
        <label class="rounded-xl bg-sand p-3 text-sm"><input type="checkbox" name="terbuka_barter" value="1" x-model="barter" class="mr-1 rounded text-brand"><b>Terbuka Barter</b><br><span class="text-xs text-ink/60">Barter / tukar tambah.</span></label>
        <label class="rounded-xl bg-sand p-3 text-sm"><input type="checkbox" name="bisa_cod" value="1" x-model="cod" class="mr-1 rounded text-brand"><b>Safe Meetup COD</b><br><span class="text-xs text-ink/60">Ongkir Rp0, titik temu aman.</span></label></div>
      <div x-show="barter" x-cloak><label class="text-sm font-bold">Preferensi barang yang diinginkan</label><input name="barter_preference" value="{{ $ov('barter_preference') }}" class="field mt-1" placeholder="Kategori, ukuran, grade minimal"></div>
      <div x-show="cod" x-cloak class="grid gap-3 sm:grid-cols-2"><div><label class="text-sm font-bold">Titik temu COD</label><select name="meeting_point_id" class="field mt-1"><option value="">— pilih —</option>@foreach($points as $p)<option value="{{ $p->id }}" @selected(old('meeting_point_id', $listing?->cod_location ? $points->firstWhere('name', $listing->cod_location)?->id : null)==$p->id)>{{ $p->name }}</option>@endforeach</select></div>
        <div><label class="text-sm font-bold">Radius COD</label><select name="cod_radius_km" class="field mt-1">@foreach([5,10,15,25] as $r)<option value="{{ $r }}" @selected(($ov('cod_radius_km',10))==$r)>{{ $r }} km</option>@endforeach</select></div></div>
      <div x-show="cod" x-cloak>@include('partials.meeting-map', ['points' => $points, 'sync' => true, 'height' => 260, 'selected' => old('meeting_point_id', $listing?->cod_location ? $points->firstWhere('name', $listing->cod_location)?->id : null)])</div>
      <div class="rounded-xl bg-sand p-4"><label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="auto_drop" value="1" x-model="drop" class="rounded text-brand"> Turunkan harga otomatis (auto price drop)</label>
        <div x-show="drop" x-cloak class="mt-3 grid gap-3 sm:grid-cols-3">
          <div><label class="text-xs font-bold">Turun setiap</label><select name="drop_every_days" class="field mt-1">@foreach([3,7,14] as $d)<option value="{{ $d }}" @selected((int) $ov('drop_every_days', 7) === $d)>{{ $d }} hari</option>@endforeach</select></div>
          <div><label class="text-xs font-bold">Besar penurunan</label><select name="drop_percent" class="field mt-1">@foreach([5,10,15,20] as $d)<option value="{{ $d }}" @selected((int) $ov('drop_percent', 5) === $d)>{{ $d }}%</option>@endforeach</select></div>
          <div><label class="text-xs font-bold">Harga terendah (Rp)</label><input type="number" name="floor_price" value="{{ $ov('floor_price') }}" class="field mt-1" placeholder="Batas bawah"></div></div>
        <p class="mt-2 text-xs text-ink/60">Harga turun otomatis sesuai jadwal sampai batas terendah. Pembeli tidak melihat harga terendah; wishlist diberi tahu tiap harga turun.</p></div>
      @if($edit)<div><label class="text-sm font-bold">Status</label><select name="status" class="field mt-1">@foreach(['active'=>'Aktif','draft'=>'Draf','archived'=>'Nonaktif'] as $k=>$v)<option value="{{ $k }}" @selected($listing->status===$k)>{{ $v }}</option>@endforeach</select></div>@endif
    </section>
    <div class="flex items-center justify-between gap-4 border-t border-sand pt-5"><p class="text-xs text-ink/60">Grade akan diverifikasi kurator; listing terverifikasi mendapat lencana.</p>
      <button class="btn btn-brand !px-6 !py-3.5 !text-base">{{ $edit ? 'Simpan Perubahan' : 'Publikasikan Listing' }}</button></div>
  </form>
</div>
@endsection
