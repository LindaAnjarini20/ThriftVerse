<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Review extends Model
{
    protected $fillable = ['transaction_id', 'reviewer_id', 'reviewee_id', 'rating', 'grade_accurate', 'comment'];
    protected $casts = ['grade_accurate' => 'boolean'];
    public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }
}
