<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MeetingPoint extends Model
{
    protected $fillable = ['name', 'address', 'city', 'latitude', 'longitude', 'features', 'recommended', 'is_active'];
    protected $casts = ['features' => 'array', 'recommended' => 'boolean', 'is_active' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
}
