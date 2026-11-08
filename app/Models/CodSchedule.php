<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
class CodSchedule extends Model
{
    protected $fillable = ['transaction_id', 'meeting_point_id', 'proposed_by', 'scheduled_at', 'status', 'code_enc', 'code_attempts',
        'buyer_arrived_at', 'buyer_arrival_verified', 'seller_arrived_at', 'seller_arrival_verified', 'handover_at'];
    protected $casts = ['scheduled_at' => 'datetime', 'buyer_arrived_at' => 'datetime', 'seller_arrived_at' => 'datetime',
            'handover_at' => 'datetime', 'buyer_arrival_verified' => 'boolean', 'seller_arrival_verified' => 'boolean'];
    public function transaction() { return $this->belongsTo(Transaction::class); }
    public function point() { return $this->belongsTo(MeetingPoint::class, 'meeting_point_id'); }

    public function makeCode(): string
    {
        $code = (string) random_int(100000, 999999);
        $this->code_enc = Crypt::encryptString($code);
        return $code;
    }
    public function code(): ?string { return $this->code_enc ? Crypt::decryptString($this->code_enc) : null; }
}
