<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PriceDropHistory extends Model
{
    protected $fillable = ['listing_id', 'from_price', 'to_price', 'source', 'changed_at'];
    protected $casts = ['changed_at' => 'datetime', 'from_price' => 'float', 'to_price' => 'float'];
}
