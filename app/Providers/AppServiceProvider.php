<?php
namespace App\Providers;

use App\Models\{Bid, CartItem, Message};
use Illuminate\Support\Facades\Blade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Di balik proxy HTTPS (Railway) aplikasi menerima permintaan sebagai http. Paksa https agar alamat formulir,
        // pengalihan, dan aset tidak berawalan http:// (Chrome memperingatkan "form is not secure").
        if ($this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
            Request::setTrustedProxies(['*'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
        }

        // Titik temu kustom: middleware ini mengisi meeting_point_id dari koordinat yang dipilih di peta.
        $this->app['router']->pushMiddlewareToGroup('web', \App\Http\Middleware\ResolveCustomMeetingPoint::class);

        // Rute tambahan (login Google) di routes/extra.php, tanpa mengubah routes/web.php.
        if (! $this->app->routesAreCached()) {
            $this->app->booted(fn () => Route::middleware('web')->group(base_path('routes/extra.php')));
        }

        Blade::directive('rp', fn ($e) => "<?php echo 'Rp'.number_format((float)($e), 0, ',', '.'); ?>");

        View::composer(['layouts.app', 'layouts.admin'], function ($view) {
            $u = auth()->user();
            $view->with('unread', $u ? $u->unreadNotifications()->count() : 0);
            $view->with('cartCount', $u ? CartItem::where('user_id', $u->id)->count() : 0);
            $view->with('chatUnread', $u ? Message::whereNull('read_at')->where('sender_id', '!=', $u->id)
                ->whereHas('conversation', fn ($c) => $c->where(fn ($w) => $w->where('buyer_id', $u->id)->orWhere('seller_id', $u->id)))->count() : 0);
            $view->with('awaitingBids', $u ? Bid::whereDoesntHave('replies')->where('status', 'pending')->where('expires_at', '>', now())
                ->where('proposer_id', '!=', $u->id)
                ->where(fn ($q) => $q->where('buyer_id', $u->id)->orWhereHas('listing', fn ($l) => $l->where('seller_id', $u->id)))->count() : 0);
        });
    }
}
