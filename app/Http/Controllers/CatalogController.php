<?php
namespace App\Http\Controllers;

use App\Models\{Bid, Listing, MeetingPoint};
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $areas = config('thriftverse.areas');
        $q = Listing::query()->active()->where('stock', '>', 0)->with('seller')
            ->withCount(['bids', 'wishedBy'])->withMax('priceDrops', 'changed_at')
            ->when($request->filled('q'), function ($qq) use ($request) {
                $t = '%'.$request->q.'%';
                $qq->where(fn ($w) => $w->where('title', 'like', $t)->orWhere('brand', 'like', $t)->orWhere('category', 'like', $t));
            })
            ->when($request->filled('category'), fn ($qq) => $qq->where('category', $request->category))
            ->when($request->filled('size'), fn ($qq) => $qq->where('size', 'like', $request->size.'%'))
            ->when($request->filled('grade'), fn ($qq) => $qq->whereIn('condition_grade', (array) $request->grade))
            ->when($request->filled('min_price'), fn ($qq) => $qq->where('current_price', '>=', (float) $request->min_price))
            ->when($request->filled('max_price'), fn ($qq) => $qq->where('current_price', '<=', (float) $request->max_price))
            ->when(in_array('tawar', (array) $request->mode), fn ($qq) => $qq->where('bisa_tawar', true))
            ->when(in_array('barter', (array) $request->mode), fn ($qq) => $qq->where('terbuka_barter', true))
            ->when(in_array('cod', (array) $request->mode), fn ($qq) => $qq->where('bisa_cod', true));

        // Filter lokasi/radius (FR-021): kotak pembatas di sekitar area (DB-agnostik, perkiraan)
        if ($request->filled('area') && isset($areas[$request->area]) && in_array((int) $request->radius, [5, 10, 15, 25], true)) {
            [$lat, $lng] = $areas[$request->area]; $r = (int) $request->radius;
            $dLat = $r / 111; $dLng = $r / (111 * max(cos(deg2rad($lat)), 0.2));
            $q->whereBetween('cod_latitude', [$lat - $dLat, $lat + $dLat])->whereBetween('cod_longitude', [$lng - $dLng, $lng + $dLng]);
        }

        match ($request->get('sort', 'populer')) {
            'terbaru' => $q->latest('published_at'),
            'harga_asc' => $q->orderBy('current_price'),
            'harga_desc' => $q->orderByDesc('current_price'),
            'grade' => $q->orderBy('condition_grade')->latest('published_at'),
            'turun' => $q->orderByDesc('price_drops_max_changed_at')->latest('published_at'),
            'jarak' => $q->latest('published_at'),
            default => $q->orderByRaw('(bids_count + wished_by_count) desc')->latest('published_at'),
        };

        $listings = $q->paginate(12)->withQueryString();
        $wished = auth()->check() ? \App\Models\Wishlist::where('user_id', auth()->id())->pluck('listing_id')->all() : [];

        $stats = [
            'discount' => round((float) Listing::active()->where('original_price', '>', 0)->selectRaw('avg(1 - current_price / original_price) as d')->value('d') * 100, 1),
            'live_bids' => Bid::where('status', 'pending')->where('expires_at', '>', now())->count(),
            'hubs' => MeetingPoint::where('is_active', true)->count(),
        ];
        $categories = Listing::active()->distinct()->orderBy('category')->pluck('category');

        return view('catalog.index', compact('listings', 'wished', 'stats', 'categories', 'areas'));
    }
}
