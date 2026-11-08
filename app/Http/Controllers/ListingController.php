<?php
namespace App\Http\Controllers;

use App\Models\{Bid, CartItem, Listing, MeetingPoint, PriceDropHistory, Report, Wishlist};
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ListingController extends Controller
{
    public function mine()
    {
        $listings = Listing::where('seller_id', auth()->id())->withCount('bids')->latest()->paginate(12);
        return view('listings.mine', compact('listings'));
    }

    public function show(Listing $listing)
    {
        abort_unless($listing->status !== 'archived' || $listing->seller_id === auth()->id(), 404);
        $listing->load(['seller', 'priceDrops']);
        $user = auth()->user();
        $thread = $user ? Bid::where('listing_id', $listing->id)->where('buyer_id', $user->id)->latest('id')->first() : null;
        $codKm = $user ? OrderService::codDistance($listing, $user) : null;
        $codOk = $user ? OrderService::codAvailable($listing, $user) : false;
        $wished = $user ? Wishlist::where('user_id', $user->id)->where('listing_id', $listing->id)->exists() : false;
        $isOwner = $user && $user->id === $listing->seller_id;
        $inCart = $user ? CartItem::where('user_id', $user->id)->where('listing_id', $listing->id)->exists() : false;
        return view('listings.show', compact('listing', 'thread', 'codKm', 'codOk', 'wished', 'isOwner', 'inCart'));
    }

    public function create()
    {
        $this->ensureSellerReady();
        return view('listings.form', ['listing' => null, 'points' => MeetingPoint::where('is_active', true)->get()]);
    }

    public function store(Request $request)
    {
        $this->ensureSellerReady();
        $request->validate([
            'checklist' => 'nullable|array', 'checklist.*' => 'in:'.implode(',', array_keys(Listing::CHECKLIST)),
        ]);
        $checklist = array_values((array) $request->input('checklist', []));
        $grade = Listing::gradeFromChecklist($checklist);

        $data = $request->validate(array_merge($this->commonRules(), [
            'original_price' => 'required|numeric|min:0',
            'photo_front' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'photo_back' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'photo_label' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'photo_detail' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'minus_photos' => $grade === 'A' ? 'nullable|array' : 'required|array|min:1|max:6',
            'minus_photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'minus_note' => $grade === 'C' ? 'required|string|max:500' : 'nullable|string|max:500',
        ]));
        $this->assertOptions($request);

        $images = [];
        foreach (['photo_front', 'photo_back', 'photo_label', 'photo_detail'] as $f) $images[] = $request->file($f)->store('listings', 'public');
        $minus = collect($request->file('minus_photos', []))->map(fn ($p) => $p->store('listings/minus', 'public'))->all();

        $payload = $this->payload($request, $data);
        $payload += [
            'seller_id' => auth()->id(), 'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(6)),
            'condition_grade' => $grade, 'condition_checklist' => $checklist, 'original_price' => $data['original_price'],
            'images' => $images, 'minus_photos' => $minus, 'status' => 'active', 'published_at' => now(), 'stock' => 1,
        ];
        if ($request->filled('minus_note')) $payload['description'] = trim(($payload['description'] ?? '')."\n\nCatatan minus: ".$request->minus_note);

        $listing = Listing::create($payload);
        PriceDropHistory::create(['listing_id' => $listing->id, 'from_price' => $listing->current_price, 'to_price' => $listing->current_price, 'source' => 'manual', 'changed_at' => now()]);

        return redirect()->route('listings.show', $listing)->with('success', "Listing tayang dengan Grade {$grade}. Admin akan memverifikasi grade untuk lencana terverifikasi.");
    }

    public function edit(Listing $listing)
    {
        $this->own($listing);
        return view('listings.form', ['listing' => $listing, 'points' => MeetingPoint::where('is_active', true)->get()]);
    }

    public function update(Request $request, Listing $listing)
    {
        $this->own($listing);
        $data = $request->validate(array_merge($this->commonRules(), ['status' => 'required|in:active,draft,archived']));
        $this->assertOptions($request);
        abort_if($listing->status === 'reserved', 422, 'Listing sedang ditahan untuk transaksi.');

        $old = $listing->current_price;
        $payload = $this->payload($request, $data) + ['status' => $data['status']];
        if ($payload['auto_drop'] && ! $listing->auto_drop) $payload['last_drop_at'] = now();
        $listing->update($payload);

        // FR-030: setiap perubahan harga dicatat; FR-034: notifikasi turun harga ke wishlist
        if ((float) $data['current_price'] !== (float) $old) {
            PriceDropHistory::create(['listing_id' => $listing->id, 'from_price' => $old, 'to_price' => $data['current_price'], 'source' => 'manual', 'changed_at' => now()]);
            if ($data['current_price'] < $old) {
                Wishlist::where('listing_id', $listing->id)->with('user')->get()->each(fn ($w) => $w->user?->tell('Harga turun', "{$listing->title} turun menjadi Rp".number_format($data['current_price'], 0, ',', '.'), route('listings.show', $listing)));
            }
        }
        return redirect()->route('listings.mine')->with('success', 'Listing diperbarui.');
    }

    public function destroy(Listing $listing)
    {
        $this->own($listing);
        abort_if($listing->status === 'reserved', 422, 'Listing sedang ditahan untuk transaksi.');
        $listing->update(['status' => 'archived']);
        $listing->delete();
        return redirect()->route('listings.mine')->with('success', 'Listing dihapus.');
    }

    public function report(Request $request, Listing $listing)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);
        Report::create(['type' => 'listing', 'reporter_id' => auth()->id(), 'listing_id' => $listing->id, 'target_user_id' => $listing->seller_id, 'reason' => $data['reason']]);
        return back()->with('success', 'Laporan terkirim ke moderator.');
    }

    private function commonRules(): array
    {
        return [
            'title' => 'required|string|max:150', 'category' => 'required|string|max:80', 'brand' => 'nullable|string|max:80',
            'size' => 'nullable|string|max:40', 'color' => 'nullable|string|max:40', 'material' => 'nullable|string|max:80',
            'description' => 'nullable|string|max:3000', 'current_price' => 'required|numeric|min:1000',
            'secret_min_price' => 'nullable|numeric|min:1|lte:current_price', 'barter_preference' => 'nullable|string|max:200',
            'meeting_point_id' => 'nullable|exists:meeting_points,id', 'cod_radius_km' => 'nullable|integer|in:5,10,15,25',
            'auto_drop' => 'nullable|boolean', 'drop_percent' => 'nullable|integer|in:5,10,15,20', 'drop_every_days' => 'nullable|integer|in:3,7,14',
            'floor_price' => 'required_if:auto_drop,1|nullable|numeric|min:1000|lte:current_price',
        ];
    }

    private function assertOptions(Request $r): void
    {
        abort_unless($r->boolean('harga_tetap') || $r->boolean('bisa_tawar') || $r->boolean('terbuka_barter') || $r->boolean('bisa_cod'), 422, 'Pilih minimal satu opsi transaksi.');
        abort_if($r->boolean('bisa_cod') && ! $r->filled('meeting_point_id'), 422, 'Pilih titik temu COD.');
    }

    private function payload(Request $r, array $d): array
    {
        $p = collect($d)->only(['title', 'category', 'brand', 'size', 'color', 'material', 'description', 'current_price', 'secret_min_price', 'barter_preference'])->all();
        $p += ['harga_tetap' => $r->boolean('harga_tetap'), 'bisa_tawar' => $r->boolean('bisa_tawar'), 'terbuka_barter' => $r->boolean('terbuka_barter'), 'bisa_cod' => $r->boolean('bisa_cod')];
        $p['secret_min_price'] = $p['bisa_tawar'] ? ($p['secret_min_price'] ?? null) : null;
        $drop = $r->boolean('auto_drop');
        $p += ['auto_drop' => $drop, 'drop_percent' => (int) $r->input('drop_percent', 5), 'drop_every_days' => (int) $r->input('drop_every_days', 7), 'floor_price' => $drop ? $r->input('floor_price') : null];
        if ($p['bisa_cod'] && ($mp = MeetingPoint::find($r->meeting_point_id))) {
            $p += ['cod_location' => $mp->name, 'cod_latitude' => $mp->latitude, 'cod_longitude' => $mp->longitude, 'cod_radius_km' => $r->input('cod_radius_km', 10)];
        }
        return $p;
    }

    private function own(Listing $l): void { abort_unless($l->seller_id === auth()->id(), 403); }

    private function ensureSellerReady(): void
    {
        $u = auth()->user();
        abort_unless($u->seller_mode && $u->payout_account, 403, 'Aktifkan Mode Penjual dan isi rekening pencairan di profil terlebih dahulu.');
    }
}
