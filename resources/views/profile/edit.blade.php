@extends('layouts.app')
@section('title', 'Profil')
@section('content')
@php $u = auth()->user(); @endphp
<div class="mx-auto max-w-3xl space-y-5"><h1 class="text-2xl font-extrabold">Profil & Pengaturan</h1>
<form method="POST" action="{{ route('profile.update') }}" class="card space-y-4 p-6">@csrf @method('PATCH')
  <div class="grid gap-4 sm:grid-cols-2">
    <div><label class="text-sm font-bold">Nama</label><input name="name" value="{{ old('name', $u->name) }}" required class="field mt-1"></div>
    <div><label class="text-sm font-bold">Username</label><input name="username" value="{{ old('username', $u->username) }}" class="field mt-1"></div>
    <div><label class="text-sm font-bold">No. HP</label><input name="no_hp" value="{{ old('no_hp', $u->no_hp) }}" class="field mt-1"></div>
    <div><label class="text-sm font-bold">Area (untuk cek radius COD)</label><select name="area" class="field mt-1"><option value="">— pilih —</option>@foreach($areas as $a)<option @selected(old('area', $u->area)===$a)>{{ $a }}</option>@endforeach</select></div>
  </div>
  <div><label class="text-sm font-bold">Alamat (tidak ditampilkan publik)</label><input name="address" value="{{ old('address', $u->address) }}" class="field mt-1"></div>
  <div class="rounded-xl bg-sand p-4"><label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="seller_mode" value="1" @checked(old('seller_mode', $u->seller_mode)) class="rounded text-brand"> Aktifkan Mode Penjual</label>
    <div class="mt-3"><label class="text-sm font-bold">Rekening / dompet pencairan</label><input name="payout_account" value="{{ old('payout_account', $u->payout_account) }}" class="field mt-1" placeholder="BCA 1234567890 a.n. Nama"></div></div>
  <button class="btn btn-brand">Simpan profil</button></form>
<form method="POST" action="{{ route('password.update') }}" class="card space-y-3 p-6">@csrf @method('PUT')<h2 class="font-extrabold">Ganti kata sandi</h2>
  <input type="password" name="current_password" placeholder="Sandi saat ini" class="field"><input type="password" name="password" placeholder="Sandi baru (min. 8)" class="field"><input type="password" name="password_confirmation" placeholder="Ulangi sandi baru" class="field"><button class="btn btn-soft">Ganti sandi</button></form></div>
@endsection
