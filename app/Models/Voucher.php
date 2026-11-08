<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = ['code', 'description', 'type', 'value', 'min_spend', 'max_discount', 'quota', 'starts_at', 'ends_at', 'is_active'];
    protected $casts = ['value' => 'float', 'min_spend' => 'float', 'max_discount' => 'float', 'is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function redemptions() { return $this->hasMany(VoucherRedemption::class); }

    public function label(): string
    {
        $v = $this->type === 'percent' ? rtrim(rtrim(number_format($this->value, 2, '.', ''), '0'), '.').'%' : 'Rp'.number_format($this->value, 0, ',', '.');
        return $v.($this->max_discount && $this->type === 'percent' ? ' (maks. Rp'.number_format($this->max_discount, 0, ',', '.').')' : '');
    }
}
