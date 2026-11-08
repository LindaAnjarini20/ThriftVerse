<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Barter extends Model
{
    protected $fillable = ['target_listing_id', 'offerer_id', 'owner_id', 'awaiting_user_id', 'cash_top_up', 'top_up_payer',
        'method', 'message', 'round', 'offerer_approved', 'owner_approved', 'status', 'expires_at', 'history'];
    protected $casts = ['cash_top_up' => 'float', 'offerer_approved' => 'boolean', 'owner_approved' => 'boolean',
            'expires_at' => 'datetime', 'history' => 'array'];
    public function target() { return $this->belongsTo(Listing::class, 'target_listing_id'); }
    public function offerer() { return $this->belongsTo(User::class, 'offerer_id'); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function items() { return $this->belongsToMany(Listing::class, 'barter_items')->withPivot('estimated_value'); }
    public function transaction() { return $this->hasOne(Transaction::class); }

    public function offeredValue(): float { return (float) $this->items->sum('pivot.estimated_value'); }

    /** Selisih nilai (%) terhadap barang target + indikator (FR-082). */
    public static function indicator(float $offered, float $target): array
    {
        $base = max($target, 1);
        $pct = abs($target - $offered) / $base * 100;
        $label = $pct <= config('thriftverse.barter_balanced_pct') ? 'Seimbang'
            : ($pct <= config('thriftverse.barter_topup_pct') ? 'Perlu Tambahan' : 'Timpang');
        return ['pct' => round($pct, 1), 'label' => $label, 'diff' => $target - $offered];
    }

    public function log(string $text): void
    {
        $h = $this->history ?? [];
        $h[] = ['at' => now()->format('d M H:i'), 'text' => $text];
        $this->history = $h;
    }

    public function isActive(): bool { return in_array($this->status, ['pending', 'accepted'], true); }
}
