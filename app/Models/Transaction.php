<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Transaction extends Model
{
    protected $fillable = ['transaction_number', 'listing_id', 'seller_id', 'buyer_id', 'payer_id', 'bid_id', 'barter_id', 'type',
        'delivery_method', 'payment_method', 'agreed_price', 'shipping_cost', 'service_fee', 'total', 'status', 'escrow_status',
        'hold_until', 'paid_at', 'ship_by', 'courier', 'tracking_no', 'shipped_at', 'auto_complete_at', 'completed_at', 'cancel_reason', 'voucher_id', 'voucher_group', 'discount', 'cart_group'];

    protected $casts = ['agreed_price' => 'float', 'shipping_cost' => 'float', 'service_fee' => 'float', 'total' => 'float', 'discount' => 'float',
            'hold_until' => 'datetime', 'paid_at' => 'datetime', 'ship_by' => 'datetime', 'shipped_at' => 'datetime',
            'auto_complete_at' => 'datetime', 'completed_at' => 'datetime'];

    public function listing() { return $this->belongsTo(Listing::class)->withTrashed(); }
    public function seller() { return $this->belongsTo(User::class, 'seller_id'); }
    public function buyer() { return $this->belongsTo(User::class, 'buyer_id'); }
    public function payer() { return $this->belongsTo(User::class, 'payer_id'); }
    public function bid() { return $this->belongsTo(Bid::class); }
    public function barter() { return $this->belongsTo(Barter::class); }
    public function schedule() { return $this->hasOne(CodSchedule::class); }
    public function reviews() { return $this->hasMany(Review::class); }
    public function reports() { return $this->hasMany(Report::class); }
    public function voucher() { return $this->belongsTo(Voucher::class); }

    public function involves(User $u): bool { return in_array($u->id, [$this->buyer_id, $this->seller_id], true); }
    public function counterpart(User $u): User { return $u->id === $this->buyer_id ? $this->seller : $this->buyer; }
    public function isCod(): bool { return $this->delivery_method === 'cod'; }

    public function statusLabel(): string
    {
        if ($this->status === 'pending') return $this->delivery_method ? ($this->isCod() ? 'Menunggu Jadwal' : 'Belum Bayar') : 'Belum Checkout';
        if ($this->status === 'packed') return $this->isCod() ? 'Menunggu Pertemuan' : 'Dikemas';
        return ['shipped' => 'Dikirim', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', 'dispute' => 'Pengembalian / Sengketa'][$this->status] ?? $this->status;
    }

    /** Tab "Pesanan Saya" (FR-048). */
    public function tab(): string
    {
        return match (true) {
            $this->status === 'pending' => 'belum_bayar',
            $this->status === 'packed' => 'dikemas',
            $this->status === 'shipped' => 'dikirim',
            $this->status === 'completed' => 'selesai',
            $this->status === 'cancelled' => 'dibatalkan',
            default => 'pengembalian',
        };
    }

    public function isOpen(): bool { return in_array($this->status, ['pending', 'packed', 'shipped', 'dispute'], true); }
}
