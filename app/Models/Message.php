<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'sender_id', 'body', 'read_at'];
    protected $casts = ['read_at' => 'datetime'];

    public function conversation() { return $this->belongsTo(Conversation::class); }
    public function sender() { return $this->belongsTo(User::class, 'sender_id'); }

    /** Samarkan nomor telepon / tautan chat luar agar transaksi tetap di platform. */
    public static function sanitize(string $text): string
    {
        $text = preg_replace('/(?:\+?62|0)\s*8[\d\s().-]{7,14}\d/u', '[nomor disembunyikan]', $text);
        $text = preg_replace('~(?:https?://)?(?:wa\.me|api\.whatsapp\.com|t\.me)/\S*~iu', '[tautan disembunyikan]', $text);
        return trim($text);
    }
}
