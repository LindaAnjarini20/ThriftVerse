<?php
namespace App\Services;

use App\Models\{Barter, Bid, Listing, Transaction, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Logika pesanan, rekber (escrow), dan siklus status transaksi (SRS 4.5, 4.9, 4.10). */
class OrderService
{
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1); $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $r * asin(min(1, sqrt($a)));
    }

    /** COD tersedia bila jarak area pembeli ke titik COD penjual <= radius COD (FR-091). */
    public static function codDistance(Listing $l, User $u): ?float
    {
        if (! $l->bisa_cod || ! $l->cod_latitude || ! $u->area_lat) return null;
        return self::distanceKm((float) $u->area_lat, (float) $u->area_lng, (float) $l->cod_latitude, (float) $l->cod_longitude);
    }

    public static function codAvailable(Listing $l, User $u): bool
    {
        $d = self::codDistance($l, $u);
        return $d !== null && $d <= min($l->cod_radius_km ?: 10, config('thriftverse.cod_default_radius') * 3);
    }

    public static function number(): string
    {
        return 'TRX-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
    }

    /** Buat pesanan pembelian (dari "Beli Langsung" atau tawaran yang diterima). Barang ditahan. */
    public static function createPurchase(Listing $listing, User $buyer, float $price, ?Bid $bid = null): Transaction
    {
        return DB::transaction(function () use ($listing, $buyer, $price, $bid) {
            $l = Listing::lockForUpdate()->findOrFail($listing->id);
            abort_unless($l->isAvailable(), 422, 'Barang sudah tidak tersedia.');
            $cod = $bid && $bid->delivery_method === 'cod';
            $hours = $cod ? config('thriftverse.hold_meet_hours') : config('thriftverse.hold_pay_hours');
            $tx = Transaction::create([
                'transaction_number' => self::number(), 'listing_id' => $l->id, 'seller_id' => $l->seller_id,
                'buyer_id' => $buyer->id, 'payer_id' => $buyer->id, 'bid_id' => $bid?->id, 'type' => 'purchase',
                'agreed_price' => $price, 'total' => $price, 'status' => 'pending', 'hold_until' => now()->addHours($hours),
            ]);
            $l->update(['status' => 'reserved']);
            return $tx;
        });
    }

    /** Pesanan barter setelah kedua pihak setuju (FR-085, FR-088). */
    public static function createBarterTransaction(Barter $b): Transaction
    {
        $payer = $b->top_up_payer === 'owner' ? $b->owner_id : $b->offerer_id;
        return Transaction::create([
            'transaction_number' => self::number(), 'listing_id' => $b->target_listing_id, 'seller_id' => $b->owner_id,
            'buyer_id' => $b->offerer_id, 'payer_id' => $payer, 'barter_id' => $b->id, 'type' => 'barter',
            'delivery_method' => 'cod', 'payment_method' => $b->cash_top_up > 0 ? 'app' : null,
            'agreed_price' => $b->cash_top_up, 'total' => $b->cash_top_up, 'status' => 'pending',
            'escrow_status' => $b->cash_top_up > 0 ? 'pending' : 'none',
            'hold_until' => now()->addHours(config('thriftverse.hold_meet_hours')),
        ]);
    }

    /** Rincian biaya (FR-041): subtotal, ongkir (COD Rp0), biaya layanan. */
    public static function quote(Transaction $tx, string $delivery): array
    {
        $ship = $delivery === 'cod' ? 0 : (float) config('thriftverse.shipping_flat');
        $fee = (float) config('thriftverse.service_fee');
        return ['shipping' => $ship, 'fee' => $fee, 'total' => $tx->agreed_price + $ship + $fee];
    }

    /** Pembayaran disimulasikan (sandbox) → dana masuk rekber. Ganti dengan payment gateway nyata. */
    public static function fund(Transaction $tx): void
    {
        $tx->escrow_status = 'funded';
        $tx->paid_at = now();
        if ($tx->delivery_method === 'ekspedisi') {
            $tx->status = 'packed';
            $tx->ship_by = now()->addHours(config('thriftverse.ship_within_hours'));
            $tx->hold_until = null;
        }
        $tx->save();
        $tx->seller->tell('Pembayaran diterima', "Pesanan {$tx->transaction_number} sudah dibayar. Dana ditahan rekber.", route('orders.show', $tx));
    }

    public static function cancel(Transaction $tx, string $reason, ?User $penalize = null): void
    {
        DB::transaction(function () use ($tx, $reason, $penalize) {
            $tx->update([
                'status' => 'cancelled', 'cancel_reason' => $reason,
                'escrow_status' => $tx->escrow_status === 'funded' ? 'refunded' : ($tx->escrow_status === 'pending' ? 'none' : $tx->escrow_status),
            ]);
            $tx->schedule?->update(['status' => 'cancelled']);
            self::reopen($tx);
            // Voucher dikembalikan bila seluruh pesanan dalam grup yang memakainya dibatalkan
            if ($tx->voucher_group && ! Transaction::where('voucher_group', $tx->voucher_group)->where('status', '!=', 'cancelled')->exists()) {
                \App\Models\VoucherRedemption::where('group_key', $tx->voucher_group)->delete();
            }
            if ($penalize) $penalize->decrement('trust_score', config('thriftverse.trust_penalty'));
        });
        foreach ([$tx->buyer, $tx->seller] as $u) $u->tell('Pesanan dibatalkan', "{$tx->transaction_number}: $reason", route('orders.show', $tx));
    }

    private static function reopen(Transaction $tx): void
    {
        $ids = [$tx->listing_id];
        if ($tx->barter) {
            $ids = array_merge($ids, $tx->barter->items()->pluck('listings.id')->all());
            $tx->barter->update(['status' => 'cancelled']);
        }
        Listing::whereIn('id', $ids)->where('status', 'reserved')->update(['status' => 'active']);
    }

    public static function complete(Transaction $tx): void
    {
        DB::transaction(function () use ($tx) {
            $tx->update([
                'status' => 'completed', 'completed_at' => now(),
                'escrow_status' => $tx->escrow_status === 'funded' ? 'released' : $tx->escrow_status,
            ]);
            $tx->schedule?->update(['status' => 'completed']);
            if ($tx->barter) {
                Listing::whereIn('id', $tx->barter->items()->pluck('listings.id')->push($tx->listing_id))->update(['status' => 'sold', 'stock' => 0]);
                $tx->barter->update(['status' => 'completed']);
            } else {
                $l = $tx->listing;
                $left = max(0, $l->stock - 1);
                $l->update(['stock' => $left, 'status' => $left > 0 ? 'active' : 'sold']);
            }
        });
        foreach ([$tx->buyer, $tx->seller] as $u) $u->tell('Pesanan selesai', "{$tx->transaction_number} selesai. Jangan lupa beri ulasan.", route('orders.show', $tx));
    }
}
