<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Masuk · ThriftVerse</title>@include('partials.assets')<link rel="icon" type="image/svg+xml" href="/images/logo/favicon.svg"><link rel="icon" type="image/png" sizes="32x32" href="/images/logo/favicon-32.png"><link rel="alternate icon" href="/favicon.ico"><link rel="apple-touch-icon" href="/images/logo/apple-touch-icon.png"><meta name="theme-color" content="#006B4A"></head>
<body class="flex min-h-screen items-center justify-center bg-cream p-4 font-sans text-ink antialiased">
<div x-data="{tab:'{{ $tab === 'register' ? 'register' : 'login' }}'}" class="grid w-full max-w-5xl overflow-hidden rounded-3xl bg-white shadow-xl md:grid-cols-[1fr_1.1fr]">
  <div class="hidden flex-col justify-between bg-[#F0E6DF] p-10 md:flex">
    <div>
      <span class="mono inline-flex items-center gap-1 rounded-full bg-white px-3 py-1.5 text-[10px] font-bold uppercase text-brand">🛡 Safe Escrow & Authenticated Preloved</span>
      <img src="/images/logo/logo-mark.svg" alt="" width="72" height="72" class="mt-6 h-[72px] w-[72px]">
      <h1 class="mt-4 text-4xl font-extrabold tracking-tight">ThriftVerse</h1>
      <p class="mt-2 text-ink/75">Pusat kurasi streetwear bekas, rilisan arsip langka, dan transaksi peer-to-peer terjamin di Indonesia.</p>
      <div class="mt-8 space-y-3 text-sm">
        <div class="rounded-xl bg-white p-4"><b>1 Akun Multi-Peran</b><br><span class="text-ink/70">Beli, tawar harga ala inDrive, hingga barter koleksi tanpa akun ganda.</span></div>
        <div class="rounded-xl bg-white p-4"><b>Rekber Otomatis & Terproteksi</b><br><span class="text-ink/70">Dana ditahan di escrow aman sampai barang diperiksa dan disetujui.</span></div>
        <div class="rounded-xl bg-white p-4"><b>COD Titik Temu Bebas Risiko</b><br><span class="text-ink/70">Titik temu publik (stasiun MRT/KRL, kampus) dengan kode serah terima 6 digit/QR.</span></div>
      </div>
    </div>
    <div class="mono mt-8 flex justify-between text-[10px] font-bold uppercase text-ink/60"><span>🔒 Kata sandi di-hash</span><span>Rekber · COD · Barter</span></div>
  </div>
  <div class="p-6 sm:p-10">
    <div class="mb-5 flex items-center gap-3 md:hidden"><img src="/images/logo/logo-mark.svg" alt="" width="44" height="44" class="h-11 w-11"><div><div class="text-xl font-extrabold leading-tight">ThriftVerse</div><div class="mono text-[10px] uppercase text-ink/60">Preloved & vintage terverifikasi</div></div></div>
    <div class="mb-6 grid grid-cols-2 rounded-xl bg-sand p-1 text-sm font-bold">
      <button type="button" @click="tab='login'" :class="tab==='login' ? 'bg-white shadow' : 'text-ink/60'" class="rounded-lg py-2.5">Masuk (Login)</button>
      <button type="button" @click="tab='register'" :class="tab==='register' ? 'bg-white shadow' : 'text-ink/60'" class="rounded-lg py-2.5">Daftar Akun Baru</button>
    </div>
    <div class="mb-5 text-sm font-bold">
      @if(config('google.client_id'))<a href="{{ route('auth.google') }}" class="btn btn-soft flex w-full items-center justify-center gap-3 !py-3"><svg class="h-5 w-5 shrink-0" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg><span>Lanjutkan dengan Google</span></a>@else<button type="button" disabled title="Login Google belum dikonfigurasi" class="btn btn-soft flex w-full cursor-not-allowed items-center justify-center gap-3 !py-3 opacity-60"><svg class="h-5 w-5 shrink-0" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg><span>Lanjutkan dengan Google · belum dikonfigurasi</span></button>@endif
    </div>
    @if($errors->any())<div class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    @if(session('success'))<div class="mb-4 rounded-xl bg-brand-light px-4 py-3 text-sm font-semibold text-brand-dark">{{ session('success') }}</div>@endif

    <form x-show="tab==='login'" method="POST" action="{{ route('login.store') }}" class="space-y-4">@csrf
      <div><label class="text-sm font-bold">Email atau Username</label><input name="login" value="{{ old('login') }}" required class="field mt-1" placeholder="nama@email.com atau @thriftcollector"></div>
      <div><label class="text-sm font-bold">Kata Sandi</label><input type="password" name="password" required class="field mt-1" placeholder="Minimal 8 karakter"></div>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" class="rounded text-brand" checked> Ingat sesi saya di perangkat ini</label>
      <button class="btn btn-brand w-full !py-3.5 !text-base">Masuk ke ThriftVerse →</button>
      <p class="text-center text-xs text-ink/60">Demo: <b>rayhan@thriftverse.app</b> / <b>admin@thriftverse.app</b> · sandi <b>password123</b></p>
    </form>

    <form x-show="tab==='register'" x-cloak method="POST" action="{{ route('register.store') }}" class="space-y-3">@csrf
      <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="text-sm font-bold">Nama</label><input name="name" value="{{ old('name') }}" required class="field mt-1"></div>
        <div><label class="text-sm font-bold">Username</label><input name="username" value="{{ old('username') }}" class="field mt-1" placeholder="thriftcollector"></div>
      </div>
      <div><label class="text-sm font-bold">Email</label><input type="email" name="email" value="{{ old('email') }}" required class="field mt-1"></div>
      <div><label class="text-sm font-bold">No. HP</label><input name="no_hp" value="{{ old('no_hp') }}" class="field mt-1" placeholder="08xxxxxxxxxx"></div>
      <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="text-sm font-bold">Kata Sandi</label><input type="password" name="password" required class="field mt-1"></div>
        <div><label class="text-sm font-bold">Ulangi Sandi</label><input type="password" name="password_confirmation" required class="field mt-1"></div>
      </div>
      <button class="btn btn-brand w-full !py-3.5 !text-base">Buat Akun →</button>
    </form>
    <div class="mt-6 text-center text-xs text-ink/60"><a href="{{ route('home') }}" class="font-bold text-brand">← Lihat katalog tanpa login</a></div>
  </div>
</div></body></html>
