<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = ['user_id', 'listing_id'];
    public function listing() { return $this->belongsTo(Listing::class)->withTrashed(); }
    public function user() { return $this->belongsTo(User::class); }
}
