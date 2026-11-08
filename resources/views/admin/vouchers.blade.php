@extends('layouts.admin')
@section('title', 'Voucher')
@section('content')
<h1 class="mb-4 text-2xl font-extrabold">Voucher</h1>
<form method="POST" action="{{ route('admin.vouchers.store') }}" class="card mb-6 grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-4" x-data="{t:'{{ old('type','percent') }}'}">@csrf
  <div><label class="text-xs font-bold">Kode</label><input name="code" value="{{ old('code') }}" required class="field mt-1 uppercase" placeholder="HEMAT10"></div>
  <div><label class="text-xs font-bold">Tipe</label><select name="type" x-model="t" class="field mt-1"><option value="percent">Persen (%)</option><option value="fixed">Nominal (Rp)</option></select></div>
  <div><label class="text-xs font-bold" x-text="t==='percent' ? 'Nilai (%)' : 'Nilai (Rp)'"></label><input type="number" name="value" value="{{ old('value') }}" required min="1" class="field mt-1"></div>
  <div><label class="text-xs font-bold">Minimal belanja (Rp)</label><input type="number" name="min_spend" value="{{ old('min_spend', 0) }}" min="0" class="field mt-1"></div>
  <div x-show="t==='percent'"><label class="text-xs font-bold">Maks. potongan (Rp)</label><input type="number" name="max_discount" value="{{ old('max_discount') }}" min="1" class="field mt-1" placeholder="Opsional"></div>
  <div><label class="text-xs font-bold">Kuota pemakaian</label><input type="number" name="quota" value="{{ old('quota') }}" min="1" class="field mt-1" placeholder="Tanpa batas"></div>
  <div><label class="text-xs font-bold">Berlaku sampai</label><input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="field mt-1"></div>
  <div><label class="text-xs font-bold">Keterangan</label><input name="description" value="{{ old('description') }}" class="field mt-1"></div>
  <div class="sm:col-span-2 lg:col-span-4"><button class="btn btn-brand">Buat voucher</button> <span class="ml-2 text-xs text-ink/60">Satu voucher hanya bisa dipakai sekali per pengguna.</span></div>
</form>
<div class="card overflow-x-auto"><table class="w-full text-sm"><thead class="bg-sand text-left"><tr class="mono text-[10px] uppercase"><th class="p-3">Kode</th><th>Potongan</th><th>Min. belanja</th><th>Terpakai</th><th>Berlaku s.d.</th><th>Status</th><th></th></tr></thead><tbody class="divide-y divide-sand">
@forelse($vouchers as $v)<tr><td class="p-3"><b class="mono">{{ $v->code }}</b><br><span class="text-xs text-ink/60">{{ $v->description }}</span></td><td>{{ $v->label() }}</td><td>@rp($v->min_spend)</td>
  <td>{{ $v->redemptions_count }}{{ $v->quota ? ' / '.$v->quota : '' }}</td><td>{{ $v->ends_at ? $v->ends_at->format('d M Y H:i') : '—' }}</td>
  <td><span class="mono rounded px-2 py-1 text-[10px] font-bold uppercase {{ $v->is_active ? 'bg-brand-light text-brand-dark' : 'bg-red-100 text-red-800' }}">{{ $v->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
  <td class="pr-3 text-right"><form method="POST" action="{{ route('admin.vouchers.toggle', $v) }}">@csrf<button class="btn {{ $v->is_active ? 'btn-danger' : 'btn-brand' }}">{{ $v->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form></td></tr>
@empty<tr><td colspan="7" class="p-6 text-center text-ink/60">Belum ada voucher.</td></tr>@endforelse</tbody></table></div>
@endsection
