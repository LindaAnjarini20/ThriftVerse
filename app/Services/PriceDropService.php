<?php
namespace App\Services;

use App\Models\{Listing, PriceDropHistory, Wishlist};

/** Auto price drop: harga turun berkala sampai batas harga terendah yang ditetapkan penjual. */
class PriceDropService
{
    /** @return int jumlah listing yang harganya diturunkan */
    public static function run(): int
    {
        $count = 0;
        Listing::where('status', 'active')->where('stock', '>', 0)->where('auto_drop', true)->whereNotNull('floor_price')
            ->each(function (Listing $l) use (&$count) {
                $base = $l->last_drop_at ?? $l->published_at ?? $l->created_at;
                if ($base->copy()->addDays(max(1, (int) $l->drop_every_days))->isFuture()) return;

                $old = (float) $l->current_price;
                $new = max((float) $l->floor_price, round($old * (1 - $l->drop_percent / 100), -3));
                if ($new >= $old) return; // sudah mencapai harga terendah

                $update = ['current_price' => $new, 'last_drop_at' => now()];
                if ($l->secret_min_price !== null && $l->secret_min_price > $new) $update['secret_min_price'] = $new;
                $l->update($update);

                PriceDropHistory::create(['listing_id' => $l->id, 'from_price' => $old, 'to_price' => $new, 'source' => 'auto', 'changed_at' => now()]);
                Wishlist::where('listing_id', $l->id)->with('user')->get()->each(fn ($w) => $w->user?->tell('Harga turun', "{$l->title} turun menjadi Rp".number_format($new, 0, ',', '.'), route('listings.show', $l)));
                $l->seller->tell('Harga turun otomatis', "{$l->title} diturunkan menjadi Rp".number_format($new, 0, ',', '.').'.', route('listings.show', $l));
                $count++;
            });
        return $count;
    }
}
