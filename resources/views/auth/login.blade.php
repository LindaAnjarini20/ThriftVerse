<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Masuk · ThriftVerse</title>@include('partials.assets')</head>
<body class="flex min-h-screen items-center justify-center bg-cream p-4 font-sans text-ink antialiased">
<div x-data="{tab:'{{ $tab === 'register' ? 'register' : 'login' }}'}" class="grid w-full max-w-5xl overflow-hidden rounded-3xl bg-white shadow-xl md:grid-cols-[1fr_1.1fr]">
  <div class="hidden flex-col justify-between bg-[#F0E6DF] p-10 md:flex">
    <div>
      <span class="mono inline-flex items-center gap-1 rounded-full bg-white px-3 py-1.5 text-[10px] font-bold uppercase text-brand">🛡 Safe Escrow & Authenticated Preloved</span>
      <h1 class="mt-6 text-4xl font-extrabold tracking-tight">ThriftVerse</h1>
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
    <div class="mb-6 grid grid-cols-2 rounded-xl bg-sand p-1 text-sm font-bold">
      <button type="button" @click="tab='login'" :class="tab==='login' ? 'bg-white shadow' : 'text-ink/60'" class="rounded-lg py-2.5">Masuk (Login)</button>
      <button type="button" @click="tab='register'" :class="tab==='register' ? 'bg-white shadow' : 'text-ink/60'" class="rounded-lg py-2.5">Daftar Akun Baru</button>
    </div>
    <div class="mb-5 text-sm font-bold">
      @if(config('google.client_id'))<a href="{{ route('auth.google') }}" class="btn btn-soft w-full !py-3">Lanjutkan dengan Google</a>@else<button type="button" disabled title="Login Google belum dikonfigurasi" class="btn btn-soft w-full cursor-not-allowed !py-3 opacity-60">Lanjutkan dengan Google · belum dikonfigurasi</button>@endif
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
