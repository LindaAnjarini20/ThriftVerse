<?php
namespace App\Services;

use App\Models\{User, Voucher, VoucherRedemption};

/** Validasi, hitung potongan, dan pencatatan pemakaian voucher. Potongan hanya berlaku atas harga barang. */
class VoucherService
{
    public static function find(?string $code): ?Voucher
    {
        $code = strtoupper(trim((string) $code));
        return $code === '' ? null : Voucher::where('code', $code)->first();
    }

    /** Mengembalikan pesan error, atau null bila voucher valid. */
    public static function check(Voucher $v, User $u, float $subtotal): ?string
    {
        if (! $v->is_active) return 'Voucher tidak aktif.';
        if ($v->starts_at && $v->starts_at->isFuture()) return 'Voucher belum berlaku.';
        if ($v->ends_at && $v->ends_at->isPast()) return 'Voucher sudah kedaluwarsa.';
        if ($subtotal < $v->min_spend) return 'Minimal belanja Rp'.number_format($v->min_spend, 0, ',', '.').' untuk voucher ini.';
        if ($v->quota !== null && VoucherRedemption::where('voucher_id', $v->id)->count() >= $v->quota) return 'Kuota voucher sudah habis.';
        if (VoucherRedemption::where('voucher_id', $v->id)->where('user_id', $u->id)->exists()) return 'Voucher ini sudah pernah Anda pakai.';
        return null;
    }

    public static function discount(Voucher $v, float $subtotal): float
    {
        $d = $v->type === 'percent' ? $subtotal * $v->value / 100 : $v->value;
        if ($v->type === 'percent' && $v->max_discount) $d = min($d, $v->max_discount);
        return (float) max(0, min(round($d), $subtotal));
    }

    public static function redeem(Voucher $v, User $u, string $groupKey): void
    {
        VoucherRedemption::create(['voucher_id' => $v->id, 'user_id' => $u->id, 'group_key' => $groupKey]);
    }
}
