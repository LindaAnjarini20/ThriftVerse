<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class VoucherRedemption extends Model
{
    protected $fillable = ['voucher_id', 'user_id', 'group_key'];
}
