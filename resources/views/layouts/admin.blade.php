<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title','Admin') · ThriftVerse Admin</title>@include('partials.assets')</head>
<body class="bg-cream font-sans text-ink antialiased">
@php $a = fn($r) => request()->routeIs($r) ? 'bg-brand text-white' : 'text-white/75 hover:bg-white/10'; @endphp
<div class="flex min-h-screen">
  <aside class="hidden w-60 shrink-0 bg-dark p-4 text-white md:block">
    <a href="{{ route('admin.dashboard') }}" class="mb-1 flex items-center gap-2"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand font-extrabold">T</span>
      <div><div class="font-extrabold leading-tight">ThriftVerse</div><div class="mono text-[9px] text-white/60">SUPER ADMIN & KURATOR</div></div></a>
    <nav class="mt-6 space-y-1 text-sm font-bold">
      <a href="{{ route('admin.dashboard') }}" class="block rounded-lg px-3 py-2.5 {{ $a('admin.dashboard') }}">✔ Verifikasi & Kurasi</a>
      <a href="{{ route('admin.disputes') }}" class="block rounded-lg px-3 py-2.5 {{ $a('admin.disputes') }}">⚖ Escrow & Sengketa</a>
      <a href="{{ route('admin.vouchers') }}" class="block rounded-lg px-3 py-2.5 {{ $a('admin.vouchers*') }}">🎟 Voucher</a>
      <a href="{{ route('admin.users') }}" class="block rounded-lg px-3 py-2.5 {{ $a('admin.users') }}">👥 Manajemen Pengguna</a>
      <a href="{{ route('home') }}" class="block rounded-lg px-3 py-2.5 text-white/75 hover:bg-white/10">🛍 Lihat Marketplace</a>
    </nav>
    <form method="POST" action="{{ route('logout') }}" class="mt-10">@csrf<button class="w-full rounded-lg bg-white/10 px-3 py-2 text-left text-xs font-bold">Keluar ({{ auth()->user()->name }})</button></form>
  </aside>
  <main class="min-w-0 flex-1 p-4 sm:p-6">
    <div class="mb-4 flex items-center gap-2 md:hidden text-sm font-bold">
      <a class="btn btn-soft" href="{{ route('admin.dashboard') }}">Kurasi</a><a class="btn btn-soft" href="{{ route('admin.disputes') }}">Sengketa</a><a class="btn btn-soft" href="{{ route('admin.vouchers') }}">Voucher</a><a class="btn btn-soft" href="{{ route('admin.users') }}">Pengguna</a></div>
    @include('partials.flash')
    @yield('content')
  </main>
</div></body></html>
