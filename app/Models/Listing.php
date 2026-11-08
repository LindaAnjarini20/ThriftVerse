<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Listing extends Model
{
    use HasFactory, SoftDeletes;

    /** Checklist kondisi terpandu (FR-011): kunci => [label, poin minus, berat] */
    public const CHECKLIST = [
        'fading'        => ['Fading alami / pudar tipis', 1, false],
        'bulu'          => ['Bulu halus / pilling', 1, false],
        'noda_samar'    => ['Noda samar', 1, false],
        'ritsleting'    => ['Ritsleting kaku', 1, false],
        'label_hilang'  => ['Label / tag hilang', 1, false],
        'jahitan'       => ['Jahitan ujung lepas', 2, false],
        'bau'           => ['Bau apek', 2, false],
        'noda_jelas'    => ['Noda sisa jelas', 3, true],
        'lubang'        => ['Lubang kecil / sobek', 3, true],
    ];

    protected $fillable = [
        'seller_id', 'title', 'slug', 'category', 'brand', 'size', 'color', 'material', 'description',
        'condition_grade', 'condition_checklist', 'verified_at', 'verified_by', 'original_price',
        'current_price', 'secret_min_price', 'stock', 'images', 'minus_photos', 'harga_tetap', 'bisa_tawar',
        'terbuka_barter', 'barter_preference', 'bisa_cod', 'cod_radius_km', 'cod_location', 'cod_latitude',
        'cod_longitude', 'status', 'published_at', 'auto_drop', 'drop_percent', 'drop_every_days', 'floor_price', 'last_drop_at',
    ];

    protected $casts = [
            'condition_checklist' => 'array', 'images' => 'array', 'minus_photos' => 'array',
            'harga_tetap' => 'boolean', 'bisa_tawar' => 'boolean', 'terbuka_barter' => 'boolean', 'bisa_cod' => 'boolean',
            'verified_at' => 'datetime', 'published_at' => 'datetime', 'auto_drop' => 'boolean', 'last_drop_at' => 'datetime', 'floor_price' => 'float',
            'original_price' => 'float', 'current_price' => 'float', 'secret_min_price' => 'float',
        ];

    /** Grade otomatis dari checklist (FR-012). */
    public static function gradeFromChecklist(array $keys): string
    {
        $points = 0; $heavy = false;
        foreach ($keys as $k) {
            if (! isset(self::CHECKLIST[$k])) continue;
            $points += self::CHECKLIST[$k][1];
            $heavy = $heavy || self::CHECKLIST[$k][2];
        }
        if ($points === 0) return 'A';
        return ($heavy || $points >= 4) ? 'C' : 'B';
    }

    public static function gradeLabel(string $g): string
    {
        return ['A' => 'Like New', 'B' => 'Layak Pakai (minus ringan)', 'C' => 'Layak Pakai (minus jelas)'][$g] ?? $g;
    }

    /** Rentang harga wajar [min, max] (FR-032). */
    public function fairRange(): array
    {
        [$lo, $hi] = config('thriftverse.fair_ratio')[$this->condition_grade] ?? [0.4, 0.6];
        $base = $this->original_price > 0 ? $this->original_price : $this->current_price / (($lo + $hi) / 2);
        return [round($base * $lo, -2), round($base * $hi, -2)];
    }

    /** Indikator posisi harga: Terlalu Rendah / Wajar / Tinggi (FR-071). */
    public function priceIndicator(float $amount): string
    {
        [$lo, $hi] = $this->fairRange();
        return $amount < $lo ? 'Terlalu Rendah' : ($amount > $hi ? 'Tinggi' : 'Wajar');
    }

    public function minBid(): float
    {
        return ceil($this->current_price * config('thriftverse.bid_min_pct'));
    }

    public function isAvailable(): bool { return $this->status === 'active' && $this->stock > 0; }

    public function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function coverUrl(): ?string { return $this->url(($this->images ?? [])[0] ?? null); }

    public function scopeActive($q) { return $q->where('status', 'active'); }

    public function seller() { return $this->belongsTo(User::class, 'seller_id'); }
    public function priceDrops() { return $this->hasMany(PriceDropHistory::class)->orderBy('changed_at'); }
    public function bids() { return $this->hasMany(Bid::class); }
    public function wishedBy() { return $this->hasMany(Wishlist::class); }
}
