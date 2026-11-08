<?php
namespace App\Models;

use App\Notifications\GenericNotice;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'role', 'no_hp', 'area', 'area_lat', 'area_lng',
        'address', 'seller_mode', 'payout_account', 'trust_score', 'is_active',
    ];
    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'seller_mode' => 'boolean',
        ];

    public function isAdmin(): bool { return $this->role === 'admin'; }

    public function listings() { return $this->hasMany(Listing::class, 'seller_id'); }

    public function handle(): string { return '@'.($this->username ?: strtok($this->email, '@')); }

    public function maskedPhone(): string
    {
        $p = (string) $this->no_hp;
        return strlen($p) > 6 ? substr($p, 0, 4).str_repeat('*', strlen($p) - 7).substr($p, -3) : '****';
    }

    public function ratingAverage(): ?float
    {
        $avg = Review::where('reviewee_id', $this->id)->avg('rating');
        return $avg ? round($avg, 1) : null;
    }

    public function tell(string $title, string $message, ?string $url = null): void
    {
        $this->notify(new GenericNotice($title, $message, $url));
    }
}
