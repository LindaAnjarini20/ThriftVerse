@extends('layouts.app')
@section('title', 'Katalog')
@section('content')
@php $f = fn($k,$v) => in_array($v, (array) request($k)); @endphp
<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
  <div><div class="mono text-[11px] font-bold uppercase text-brand">Marketplace preloved & vintage terverifikasi</div>
    <h1 class="text-3xl font-extrabold tracking-tight">Katalog Pilihan & Kurasi Fisik</h1></div>
  <div class="card flex gap-6 px-5 py-3 text-sm"><div><div class="mono text-[10px] uppercase text-ink/50">Rerata diskon</div><div class="text-lg font-extrabold">-{{ $stats['discount'] }}%</div></div>
    <div><div class="mono text-[10px] uppercase text-ink/50">Live bidding</div><div class="text-lg font-extrabold text-indigoX">{{ $stats['live_bids'] }} Aktif</div></div>
    <div><div class="mono text-[10px] uppercase text-ink/50">Safe hub</div><div class="text-lg font-extrabold">{{ $stats['hubs'] }} Titik</div></div></div>
</div>
<div class="mb-5 flex flex-wrap gap-2 text-xs font-bold">
  <a href="{{ route('home') }}" class="rounded-full bg-dark px-4 py-2 text-white">Semua</a>
  <a href="{{ route('home', ['mode' => ['tawar']]) }}" class="rounded-full bg-white px-4 py-2 shadow-sm">Bisa Nego inDrive</a>
  <a href="{{ route('home', ['mode' => ['barter']]) }}" class="rounded-full bg-white px-4 py-2 shadow-sm">Terbuka Barter</a>
  <a href="{{ route('home', ['grade' => ['A']]) }}" class="rounded-full bg-white px-4 py-2 shadow-sm">Grade A Mulus</a>
  <a href="{{ route('home', ['max_price' => 500000]) }}" class="rounded-full bg-white px-4 py-2 shadow-sm">Under Rp500k</a>
  <a href="{{ route('home', ['sort' => 'turun']) }}" class="rounded-full bg-white px-4 py-2 shadow-sm">Drop Hari Ini</a>
</div>
<div class="grid gap-6 lg:grid-cols-[260px_1fr]" x-data="{ f: false }">
  <div>
  <button type="button" @click="f = !f" class="btn btn-soft mb-3 w-full lg:hidden"><span x-text="f ? 'Tutup filter' : 'Filter & urutkan ({{ $listings->total() }} barang)'"></span></button>
  <form method="GET" class="card hidden h-fit space-y-5 p-5 text-sm lg:block" :class="f ? '!block' : ''">
    <input type="hidden" name="q" value="{{ request('q') }}">
    <div class="flex items-center justify-between"><h2 class="text-lg font-extrabold">Filter Pintar</h2><a href="{{ route('home') }}" class="mono text-[10px] font-bold text-indigoX">RESET SEMUA</a></div>
    <div><div class="mono mb-2 text-[11px] font-bold uppercase text-ink/50">Mode Transaksi</div>
      @foreach(['tawar' => 'Nego inDrive (Ronde Aktif)', 'barter' => 'Terbuka Barter', 'cod' => 'COD Bebas Ongkir'] as $k => $lab)
        <label class="flex items-center gap-2 py-1"><input type="checkbox" name="mode[]" value="{{ $k }}" @checked($f('mode',$k)) class="rounded text-brand"> {{ $lab }}</label>@endforeach</div>
    <div><div class="mono mb-2 text-[11px] font-bold uppercase text-ink/50">Grade Kondisi Baku</div>
      @foreach(['A' => 'Mulus / Like New', 'B' => 'Minus Ringan / Estetik', 'C' => 'Minus Nyata / Siap Rework'] as $k => $lab)
        <label class="mb-1 flex items-center justify-between rounded-lg bg-sand px-3 py-2"><span><b>Grade {{ $k }}</b><br><span class="text-xs text-ink/60">{{ $lab }}</span></span><input type="checkbox" name="grade[]" value="{{ $k }}" @checked($f('grade',$k)) class="rounded text-brand"></label>@endforeach</div>
    <div><div class="mono mb-2 text-[11px] font-bold uppercase text-ink/50">Rentang Harga</div>
      <div class="grid grid-cols-2 gap-2"><input name="min_price" value="{{ request('min_price') }}" placeholder="Min (Rp)" class="field"><input name="max_price" value="{{ request('max_price') }}" placeholder="Maks (Rp)" class="field"></div></div>
    <div><div class="mono mb-2 text-[11px] font-bold uppercase text-ink/50">Kategori</div>
      <select name="category" class="field"><option value="">Semua</option>@foreach($categories as $c)<option @selected(request('category')===$c)>{{ $c }}</option>@endforeach</select></div>
    <div><div class="mono mb-2 text-[11px] font-bold uppercase text-ink/50">Ukuran</div>
      <div class="flex flex-wrap gap-1">@foreach(['S','M','L','XL','XXL'] as $s)<label class="cursor-pointer"><input type="radio" name="size" value="{{ $s }}" @checked(request('size')===$s) class="peer sr-only"><span class="block rounded-lg bg-sand px-3 py-1.5 text-xs font-bold peer-checked:bg-dark peer-checked:text-white">{{ $s }}</span></label>@endforeach</div></div>
    <div><div class="mono mb-2 text-[11px] font-bold uppercase text-ink/50">Area titik temu aman</div>
      <select name="area" class="field"><option value="">Semua area</option>@foreach(array_keys($areas) as $a)<option @selected(request('area')===$a)>{{ $a }}</option>@endforeach</select>
      <div class="mt-2 flex gap-2">@foreach([5,10,15,25] as $r)<label class="flex-1"><input type="radio" name="radius" value="{{ $r }}" @checked((int) request('radius', 10)===$r) class="peer sr-only"><span class="block cursor-pointer rounded-lg bg-sand py-1.5 text-center text-xs font-bold peer-checked:bg-brand peer-checked:text-white">{{ $r }} km</span></label>@endforeach</div></div>
    <button class="btn btn-brand w-full !py-3">TERAPKAN FILTER ({{ $listings->total() }})</button>
  </form>
  </div>

  <section>
    <form method="GET" class="card mb-4 flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
      @foreach(request()->except(['sort','page']) as $k => $v) @foreach((array) $v as $vv)<input type="hidden" name="{{ $k }}{{ is_array($v) ? '[]' : '' }}" value="{{ $vv }}">@endforeach @endforeach
      <b>{{ number_format($listings->total()) }} <span class="font-semibold text-ink/60">koleksi terverifikasi</span></b>
      <label class="flex items-center gap-2"><span class="mono text-[10px] uppercase text-ink/50">Urutkan</span>
        <select name="sort" onchange="this.form.submit()" class="rounded-lg bg-sand px-3 py-1.5 text-sm font-bold">
          @foreach(['populer' => 'Paling Populer & Trending', 'terbaru' => 'Terbaru', 'harga_asc' => 'Harga Terendah', 'harga_desc' => 'Harga Tertinggi', 'grade' => 'Grade Terbaik', 'turun' => 'Turun Harga Terbaru'] as $k => $lab)<option value="{{ $k }}" @selected(request('sort','populer')===$k)>{{ $lab }}</option>@endforeach</select></label>
    </form>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      @forelse($listings as $l)<div class="relative">@include('partials.listing-card', ['l' => $l, 'wished' => $wished])</div>
      @empty<div class="card col-span-full p-10 text-center text-ink/60">Belum ada barang yang cocok dengan filter.</div>@endforelse
    </div>
    <div class="mt-6">{{ $listings->links() }}</div>
  </section>
</div>
@endsection
