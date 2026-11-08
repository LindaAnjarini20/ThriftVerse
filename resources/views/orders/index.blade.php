@extends('layouts.app')
@section('title', 'Pesanan')
@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h1 class="text-2xl font-extrabold">{{ $peran==='jual' ? 'Pesanan Masuk' : 'Pesanan Saya' }}</h1>
  <div class="flex rounded-xl bg-sand p-1 text-sm font-bold"><a href="?peran=beli" class="rounded-lg px-4 py-2 {{ $peran==='beli' ? 'bg-white shadow' : 'text-ink/60' }}">Sebagai Pembeli</a><a href="?peran=jual" class="rounded-lg px-4 py-2 {{ $peran==='jual' ? 'bg-white shadow' : 'text-ink/60' }}">Sebagai Penjual</a></div></div>
<div class="mb-4 flex gap-2 overflow-x-auto text-xs font-bold">
  <a href="?peran={{ $peran }}" class="whitespace-nowrap rounded-full px-4 py-2 {{ $tab==='semua' ? 'bg-dark text-white' : 'bg-white' }}">Semua ({{ $total }})</a>
  @foreach($tabs as $k => [$lab])<a href="?peran={{ $peran }}&tab={{ $k }}" class="whitespace-nowrap rounded-full px-4 py-2 {{ $tab===$k ? 'bg-dark text-white' : 'bg-white' }}">{{ $lab }} ({{ $counts[$k] ?? 0 }})</a>@endforeach</div>
@php $payGroups = $peran === 'beli' ? $orders->filter(fn ($o) => $o->cart_group && $o->status === 'pending' && $o->escrow_status === 'pending')->groupBy('cart_group') : collect(); @endphp
@foreach($payGroups as $grp => $list)<form method="POST" action="{{ route('cart.payall', $grp) }}" class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">@csrf
  <span><b>{{ $list->count() }} pesanan dari keranjang</b> menunggu pembayaran (total @rp($list->sum('total'))).</span><button class="btn btn-brand">Bayar semua (simulasi)</button></form>@endforeach
<div class="space-y-3">
@forelse($orders as $o)
<a href="{{ route('orders.show', $o) }}" class="card flex flex-wrap items-center gap-4 p-4 hover:ring-2 hover:ring-brand/30">
  <div class="h-16 w-16 overflow-hidden rounded-lg bg-sand">@include('partials.cover', ['listing' => $o->listing])</div>
  <div class="min-w-0 flex-1"><div class="mono text-[10px] uppercase text-ink/50">{{ $o->transaction_number }} · {{ $o->type==='barter' ? 'Barter' : 'Pembelian' }} · {{ $o->delivery_method==='cod' ? 'COD' : ($o->delivery_method ? 'Ekspedisi' : '—') }}</div>
    <div class="font-bold">{{ $o->listing->title }}</div><div class="text-xs text-ink/60">{{ $peran==='jual' ? 'Pembeli: '.$o->buyer->name : 'Penjual: '.$o->seller->name }}</div></div>
  <div class="text-right"><div class="font-extrabold text-brand">@rp($o->total ?: $o->agreed_price)</div><span class="mono mt-1 inline-block rounded bg-sand px-2 py-1 text-[10px] font-bold uppercase">{{ $o->statusLabel() }}</span></div></a>
@empty<div class="card p-10 text-center text-ink/60">Belum ada pesanan di tab ini.</div>@endforelse</div>
@endsection
