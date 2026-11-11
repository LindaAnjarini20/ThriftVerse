<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ThriftVerse') · ThriftVerse</title>
    @include('partials.assets')
    @stack('head')
</head>
<body class="min-h-screen bg-cream font-sans text-ink antialiased">
@php $nav = fn($r) => request()->routeIs($r) ? 'bg-brand text-white' : 'text-ink/80 hover:bg-sand'; @endphp
<header class="relative z-40 sm:sticky sm:top-0">
    <div class="bg-dark text-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 sm:flex-nowrap sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand text-lg font-extrabold">T</span>
                <span class="hidden text-lg font-extrabold tracking-tight sm:block">THRIFTVERSE</span>
            </a>
            <form action="{{ route('home') }}" method="GET" class="order-last flex w-full items-center overflow-hidden rounded-xl bg-white text-ink ring-2 ring-brand/60 sm:order-none sm:mx-2 sm:w-auto sm:flex-1">
                <input name="q" value="{{ request('q') }}" placeholder="Cari jaket thrift, sepatu, atau barter barang…" class="w-full border-0 bg-transparent px-4 py-2.5 text-sm focus:outline-none">
                <button class="m-1 rounded-lg bg-brand px-4 py-1.5 text-sm font-bold text-white">Cari</button>
            </form>
            <div class="ml-auto flex items-center gap-1 sm:ml-0 sm:gap-3">
            @auth
                <div class="hidden items-center rounded-full bg-white/10 p-1 text-[11px] font-bold md:flex">
                    <a href="{{ route('home') }}" class="rounded-full px-3 py-1.5 {{ ! request()->routeIs('listings.mine','listings.create','listings.edit') ? 'bg-brand' : 'text-white/70' }}">MODE PEMBELI</a>
                    <a href="{{ route('listings.mine') }}" class="rounded-full px-3 py-1.5 {{ request()->routeIs('listings.mine','listings.create','listings.edit') ? 'bg-brand' : 'text-white/70' }}">MODE PENJUAL</a>
                </div>
                <a href="{{ route('notifications.index') }}" class="relative rounded-lg p-2 hover:bg-white/10" title="Notifikasi">🔔
                    @if($unread)<span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold">{{ $unread }}</span>@endif</a>
                <a href="{{ route('chat.index') }}" class="relative rounded-lg p-2 hover:bg-white/10" title="Chat">💬
                    @if($chatUnread)<span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold">{{ $chatUnread }}</span>@endif</a>
                <a href="{{ route('cart.index') }}" class="relative rounded-lg p-2 hover:bg-white/10" title="Keranjang">🛒
                    @if($cartCount)<span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-bold">{{ $cartCount }}</span>@endif</a>
                <a href="{{ route('orders.index') }}" class="hidden rounded-lg p-2 hover:bg-white/10 sm:block" title="Pesanan Saya">🛍️</a>
                <div x-data="{o:false}" class="relative">
                    <button @click="o=!o" class="flex h-9 w-9 items-center justify-center rounded-full bg-brand text-sm font-bold">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</button>
                    <div x-show="o" x-cloak @click.outside="o=false" class="absolute right-0 mt-2 w-56 rounded-xl bg-white p-2 text-sm text-ink shadow-xl">
                        <div class="px-3 py-2"><div class="font-bold">{{ auth()->user()->name }}</div><div class="mono text-[11px] text-ink/60">Skor kepercayaan {{ auth()->user()->trust_score }}</div></div>
                        <a class="block rounded-lg px-3 py-2 hover:bg-sand" href="{{ route('profile.edit') }}">Profil & Pengaturan</a>
                        <a class="block rounded-lg px-3 py-2 hover:bg-sand" href="{{ route('listings.create') }}">Jual barang</a>
                        @if(auth()->user()->isAdmin())<a class="block rounded-lg px-3 py-2 hover:bg-sand" href="{{ route('admin.dashboard') }}">Portal Admin</a>@endif
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-red-600 hover:bg-sand">Keluar</button></form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-sm font-bold hover:bg-white/10">Masuk</a>
                <a href="{{ route('login', ['tab' => 'register']) }}" class="rounded-lg bg-brand px-3 py-2 text-sm font-bold">Daftar</a>
            @endauth
            </div>
        </div>
    </div>
    <div class="border-b border-sand bg-white/90 backdrop-blur">
        <nav class="mx-auto flex max-w-7xl items-center gap-1 overflow-x-auto px-4 py-2 text-sm font-bold sm:px-6">
            <a href="{{ route('home') }}" class="whitespace-nowrap rounded-lg px-3 py-1.5 {{ $nav('home') }}">Jelajah Katalog</a>
            <a href="{{ route('negotiations.index') }}" class="whitespace-nowrap rounded-lg px-3 py-1.5 {{ $nav('negotiations.*') }}">Negosiasi & Tawar @if($awaitingBids)<span class="ml-1 rounded-full bg-red-500 px-1.5 text-[10px] text-white">{{ $awaitingBids }}</span>@endif</a>
            <a href="{{ route('barters.index') }}" class="whitespace-nowrap rounded-lg px-3 py-1.5 {{ $nav('barters.*') }}">Barter Hub</a>
            <a href="{{ route('cod.index') }}" class="whitespace-nowrap rounded-lg px-3 py-1.5 {{ $nav('cod.*') }}">Jadwal COD</a>
            <a href="{{ route('orders.index') }}" class="whitespace-nowrap rounded-lg px-3 py-1.5 {{ $nav('orders.*') }}">Pesanan Saya</a>
            @auth<a href="{{ route('chat.index') }}" class="whitespace-nowrap rounded-lg px-3 py-1.5 {{ $nav('chat.*') }}">Chat @if($chatUnread)<span class="ml-1 rounded-full bg-red-500 px-1.5 text-[10px] text-white">{{ $chatUnread }}</span>@endif</a>
            <a href="{{ route('cart.index') }}" class="whitespace-nowrap rounded-lg px-3 py-1.5 {{ $nav('cart.*') }}">Keranjang @if($cartCount)<span class="ml-1 rounded-full bg-amber-500 px-1.5 text-[10px] text-white">{{ $cartCount }}</span>@endif</a>@endauth
            <span class="mono ml-auto hidden items-center gap-2 whitespace-nowrap text-[11px] font-bold text-brand md:flex"><span class="h-2 w-2 rounded-full bg-brand"></span>Safe Trade MRT/KRL Aktif</span>
        </nav>
    </div>
</header>

<main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
    @include('partials.flash')
    @yield('content')
</main>

<footer class="mt-12 border-t border-sand bg-white">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 text-sm sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
        <div><div class="flex items-center gap-2 text-lg font-extrabold"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand text-white">T</span>THRIFTVERSE</div>
            <p class="mt-3 text-ink/70">Marketplace preloved dengan grading kondisi terstandar, tawar harga ala inDrive, barter, dan COD gratis ongkir.</p></div>
        <div><div class="mono mb-2 text-[11px] font-bold uppercase text-ink/50">Fitur Transaksi</div><ul class="space-y-1 text-ink/80"><li>Tawar Harga Fair-Pricing</li><li>Barter & Tukar Tambah</li><li>COD Titik Temu Aman</li><li>Rekber (Escrow)</li></ul></div>
        <div><div class="mono mb-2 text-[11px] font-bold uppercase text-ink/50">Bantuan</div><ul class="space-y-1 text-ink/80"><li>Standar Grading Kondisi (A/B/C)</li><li>Panduan Titik Temu Publik</li><li>Pusat Resolusi Masalah</li></ul></div>
        <div><div class="mono mb-2 text-[11px] font-bold uppercase text-ink/50">Keamanan Komunitas</div><p class="text-ink/70">Kode serah terima 6 digit/QR: berikan kode kepada penjual hanya setelah barang diperiksa dan sesuai.</p></div>
    </div>
    <div class="border-t border-sand py-4 text-center text-xs text-ink/50">© {{ date('Y') }} ThriftVerse — proyek Startup Digital, Sistem Informasi.</div>
</footer>
@stack('scripts')
</body>
</html>
