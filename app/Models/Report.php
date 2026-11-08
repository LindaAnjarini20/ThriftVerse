<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Report extends Model
{
    protected $fillable = ['type', 'reporter_id', 'transaction_id', 'listing_id', 'target_user_id', 'reason', 'evidence', 'status',
        'resolution', 'admin_note', 'resolved_by', 'resolved_at'];
    protected $casts = ['evidence' => 'array', 'resolved_at' => 'datetime'];
    public function reporter() { return $this->belongsTo(User::class, 'reporter_id'); }
    public function transaction() { return $this->belongsTo(Transaction::class); }
    public function listing() { return $this->belongsTo(Listing::class)->withTrashed(); }
    public function target() { return $this->belongsTo(User::class, 'target_user_id'); }
}
