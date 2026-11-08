<?php
namespace App\Http\Controllers;

use App\Models\{Bid, Listing};
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Tawar harga ala inDrive (SRS 4.8). */
class NegotiationController extends Controller
{
    public function index(Request $request)
    {
        $me = auth()->id();
        $base = fn () => Bid::with(['listing', 'buyer', 'proposer'])->whereDoesntHave('replies')->latest('id');
        $incoming = $base()->whereHas('listing', fn ($q) => $q->where('seller_id', $me))->get();
        $mine = $base()->where('buyer_id', $me)->get();
        $awaiting = $incoming->merge($mine)->filter(fn ($b) => $b->isOpen() && $b->proposer_id !== $me)->count();
        return view('negotiations.index', compact('incoming', 'mine', 'awaiting') + ['tab' => $request->get('tab', $incoming->isNotEmpty() && ! $mine->isNotEmpty() ? 'masuk' : 'saya')]);
    }

    public function store(Request $request, Listing $listing)
    {
        $me = auth()->user();
        abort_unless($listing->isAvailable() && $listing->bisa_tawar && $listing->seller_id !== $me->id, 403, 'Listing ini tidak menerima tawaran.');
        $data = $request->validate([
            'amount' => 'required|numeric|min:1', 'message' => 'nullable|string|max:500', 'delivery_method' => 'required|in:ekspedisi,cod',
        ]);
        $amount = (float) $data['amount'];
        if ($amount > $listing->current_price) return back()->withErrors(['amount' => 'Tawaran tidak boleh melebihi harga listing.'])->withInput();
        if ($amount < $listing->minBid()) return back()->withErrors(['amount' => 'Tawaran terlalu rendah. Minimal Rp'.number_format($listing->minBid(), 0, ',', '.').' ('.(config('thriftverse.bid_min_pct') * 100).'% harga listing).'])->withInput();
        if ($data['delivery_method'] === 'cod' && ! OrderService::codAvailable($listing, $me)) return back()->withErrors(['delivery_method' => 'COD tidak tersedia di area Anda. Lengkapi area di profil atau pilih ekspedisi.'])->withInput();
        $open = Bid::where('listing_id', $listing->id)->where('buyer_id', $me->id)->where('status', 'pending')->where('expires_at', '>', now())->exists();
        if ($open) return back()->withErrors(['amount' => 'Masih ada tawaran aktif untuk listing ini.'])->withInput();
        $active = Bid::where('buyer_id', $me->id)->where('status', 'pending')->where('expires_at', '>', now())->count();
        if ($active >= config('thriftverse.bid_max_active')) return back()->withErrors(['amount' => 'Batas tawaran aktif tercapai.'])->withInput();

        $bid = Bid::create([
            'listing_id' => $listing->id, 'buyer_id' => $me->id, 'proposer_id' => $me->id, 'amount' => $amount,
            'delivery_method' => $data['delivery_method'], 'message' => $data['message'] ?? null, 'round' => 1,
            'expires_at' => now()->addHours(config('thriftverse.bid_expiry_hours')),
        ]);

        // FR-076 auto-terima bila >= harga minimum rahasia
        if ($listing->secret_min_price && $amount >= $listing->secret_min_price) {
            return $this->accept($bid->fresh(['listing']), 'Tawaran otomatis diterima.');
        }
        $listing->seller->tell('Tawaran baru', "{$me->name} menawar Rp".number_format($amount, 0, ',', '.')." untuk {$listing->title}", route('negotiations.index', ['tab' => 'masuk']));
        return redirect()->route('negotiations.index', ['tab' => 'saya'])->with('success', 'Tawaran terkirim. Berlaku '.config('thriftverse.bid_expiry_hours').' jam.');
    }

    public function respond(Request $request, Bid $bid)
    {
        $bid->load('listing');
        $me = auth()->user();
        $isSeller = $bid->listing->seller_id === $me->id;
        abort_unless($isSeller || $bid->buyer_id === $me->id, 403);
        abort_if($bid->proposer_id === $me->id, 403, 'Menunggu respons pihak lain.');
        if ($bid->status === 'pending' && $bid->expires_at->isPast()) $bid->update(['status' => 'expired']);
        abort_unless($bid->status === 'pending', 422, 'Putaran ini sudah tidak aktif.');
        $data = $request->validate(['action' => 'required|in:accept,reject,counter', 'amount' => 'required_if:action,counter|nullable|numeric|min:1', 'message' => 'nullable|string|max:500']);

        if ($data['action'] === 'reject') {
            $bid->update(['status' => 'rejected', 'responded_at' => now()]);
            $other = $isSeller ? $bid->buyer : $bid->listing->seller;
            $other->tell('Tawaran ditolak', "Tawaran untuk {$bid->listing->title} ditolak.", route('negotiations.index'));
            return back()->with('success', 'Tawaran ditolak.');
        }
        if ($data['action'] === 'counter') {
            if ($bid->round >= config('thriftverse.bid_max_rounds')) return back()->withErrors(['amount' => 'Maksimal '.config('thriftverse.bid_max_rounds').' putaran tercapai.']);
            $amt = (float) $data['amount'];
            if ($amt > $bid->listing->current_price || $amt < $bid->listing->minBid()) return back()->withErrors(['amount' => 'Nominal di luar batas wajar (maks harga listing, min '.(config('thriftverse.bid_min_pct') * 100).'%).']);
            DB::transaction(function () use ($bid, $amt, $data, $me) {
                $bid->update(['status' => 'countered', 'responded_at' => now()]);
                Bid::create(['listing_id' => $bid->listing_id, 'buyer_id' => $bid->buyer_id, 'proposer_id' => $me->id, 'parent_id' => $bid->id,
                    'amount' => $amt, 'delivery_method' => $bid->delivery_method, 'message' => $data['message'] ?? null, 'round' => $bid->round + 1,
                    'expires_at' => now()->addHours(config('thriftverse.bid_expiry_hours'))]);
            });
            $other = $isSeller ? $bid->buyer : $bid->listing->seller;
            $other->tell('Tawar balik', "Penawaran baru Rp".number_format($amt, 0, ',', '.')." untuk {$bid->listing->title}", route('negotiations.index'));
            return back()->with('success', 'Tawar balik terkirim.');
        }
        return $this->accept($bid, 'Deal! Harga Sepakat dikunci.');
    }

    private function accept(Bid $bid, string $msg)
    {
        $listing = $bid->listing;
        if (! $listing->isAvailable()) return back()->withErrors(['amount' => 'Barang sudah tidak tersedia.']);
        $tx = DB::transaction(function () use ($bid, $listing) {
            $bid->update(['status' => 'accepted', 'deal_price' => $bid->amount, 'responded_at' => now()]);
            // FR-074: tawaran lain otomatis ditutup
            Bid::where('listing_id', $listing->id)->where('id', '!=', $bid->id)->where('status', 'pending')->with('buyer')->get()->each(function ($o) use ($listing) {
                $o->update(['status' => 'closed']);
                $o->buyer->tell('Tawaran ditutup', "{$listing->title} sudah terjual ke penawar lain.", route('negotiations.index'));
            });
            return OrderService::createPurchase($listing, $bid->buyer, $bid->amount, $bid);
        });
        $bid->buyer->tell('Tawaran diterima', "Harga sepakat Rp".number_format($bid->amount, 0, ',', '.')." untuk {$listing->title}. Selesaikan checkout sebelum ".$tx->hold_until->format('d M H:i'), route('orders.checkout', $tx));
        $listing->seller->tell('Deal terkunci', "{$listing->title} ditahan untuk {$bid->buyer->name}.", route('orders.show', $tx));
        return redirect()->route('orders.show', $tx)->with('success', $msg);
    }
}
