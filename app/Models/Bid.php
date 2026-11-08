<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Bid extends Model
{
    protected $fillable = ['listing_id', 'buyer_id', 'proposer_id', 'parent_id', 'amount', 'delivery_method', 'message',
        'round', 'status', 'expires_at', 'responded_at', 'deal_price'];
    protected $casts = ['amount' => 'float', 'deal_price' => 'float', 'expires_at' => 'datetime', 'responded_at' => 'datetime'];
    public function listing() { return $this->belongsTo(Listing::class); }
    public function buyer() { return $this->belongsTo(User::class, 'buyer_id'); }
    public function proposer() { return $this->belongsTo(User::class, 'proposer_id'); }
    public function parent() { return $this->belongsTo(Bid::class, 'parent_id'); }
    public function replies() { return $this->hasMany(Bid::class, 'parent_id'); }
    public function isOpen(): bool { return $this->status === 'pending' && $this->expires_at && $this->expires_at->isFuture(); }
}
