<?php
namespace App\Http\Controllers;

use App\Models\{CartItem, CodSchedule, Listing, MeetingPoint, Transaction};
use App\Services\{OrderService, VoucherService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Keranjang multi-item: satu pesanan per barang, dikelompokkan per penjual (ongkir & biaya layanan sekali per penjual). */
class CartController extends Controller
{
    private function items()
    {
        return CartItem::with('listing.seller')->where('user_id', auth()->id())->latest('id')->get();
    }

    private function usable($items)
    {
        return $items->filter(fn ($i) => $i->listing && $i->listing->isAvailable() && $i->listing->harga_tetap && $i->listing->seller_id !== auth()->id())->values();
    }

    public function index()
    {
        $items = $this->items();
        $valid = $this->usable($items);
        return view('cart.index', ['items' => $items, 'valid' => $valid, 'subtotal' => (float) $valid->sum(fn ($i) => $i->listing->current_price)]);
    }

    public function add(Listing $listing)
    {
        abort_unless($listing->isAvailable() && $listing->harga_tetap && $listing->seller_id !== auth()->id(), 403, 'Barang ini tidak bisa dimasukkan ke keranjang.');
        CartItem::firstOrCreate(['user_id' => auth()->id(), 'listing_id' => $listing->id]);
        return back()->with('success', 'Ditambahkan ke keranjang.');
    }

    public function remove(CartItem $item)
    {
        abort_unless($item->user_id === auth()->id(), 403);
        $item->delete();
        return back()->with('success', 'Dihapus dari keranjang.');
    }

    public function checkout()
    {
        $me = auth()->user();
        $valid = $this->usable($this->items());
        if ($valid->isEmpty()) return redirect()->route('cart.index')->with('error', 'Keranjang kosong atau barangnya sudah tidak tersedia.');

        $groups = $valid->groupBy(fn ($i) => $i->listing->seller_id)->map(fn ($g) => [
            'seller' => $g->first()->listing->seller, 'items' => $g,
            'subtotal' => (float) $g->sum(fn ($i) => $i->listing->current_price),
            'codOk' => $g->every(fn ($i) => OrderService::codAvailable($i->listing, $me)),
        ]);
        $points = MeetingPoint::where('is_active', true)->orderByDesc('recommended')->get();
        return view('cart.checkout', ['groups' => $groups, 'points' => $points, 'subtotal' => (float) $valid->sum(fn ($i) => $i->listing->current_price), 'cfg' => config('thriftverse')]);
    }

    public function place(Request $request)
    {
        $me = auth()->user();
        $valid = $this->usable($this->items());
        if ($valid->isEmpty()) return redirect()->route('cart.index')->with('error', 'Keranjang kosong atau barangnya sudah tidak tersedia.');
        $groups = $valid->groupBy(fn ($i) => $i->listing->seller_id);

        $rules = ['voucher_code' => 'nullable|string|max:40'];
        foreach ($groups->keys() as $sid) {
            $rules["delivery.$sid"] = 'required|in:ekspedisi,cod';
            $rules["meeting_point_id.$sid"] = "required_if:delivery.$sid,cod|nullable|exists:meeting_points,id";
            $rules["scheduled_at.$sid"] = "required_if:delivery.$sid,cod|nullable|date|after:+1 hour";
        }
        $data = $request->validate($rules);

        foreach ($groups as $sid => $items) {
            if ($data['delivery'][$sid] === 'cod' && ! $items->every(fn ($i) => OrderService::codAvailable($i->listing, $me))) {
                return back()->withErrors(['delivery' => 'COD tidak tersedia untuk salah satu barang (di luar radius atau tidak dibuka penjual).'])->withInput();
            }
        }

        $subtotal = (float) $valid->sum(fn ($i) => $i->listing->current_price);
        $voucher = null; $discount = 0.0;
        if ($request->filled('voucher_code')) {
            $voucher = VoucherService::find($request->voucher_code);
            $err = $voucher ? VoucherService::check($voucher, $me, $subtotal) : 'Kode voucher tidak ditemukan.';
            if ($err) return back()->withErrors(['voucher_code' => $err])->withInput();
            $discount = VoucherService::discount($voucher, $subtotal);
        }

        $groupKey = 'CART-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        $count = DB::transaction(function () use ($groups, $valid, $data, $me, $voucher, $discount, $subtotal, $groupKey) {
            $n = $valid->count(); $idx = 0; $remaining = $discount; $made = 0;
            foreach ($groups as $sid => $items) {
                $delivery = $data['delivery'][$sid]; $cod = $delivery === 'cod'; $first = true;
                foreach ($items as $it) {
                    $l = $it->listing; $idx++;
                    $tx = OrderService::createPurchase($l, $me, (float) $l->current_price);

                    $share = 0.0;
                    if ($voucher && $subtotal > 0) {
                        $share = $idx === $n ? $remaining : min($remaining, round($discount * $l->current_price / $subtotal));
                        $share = (float) max(0, min($share, $l->current_price));
                        $remaining -= $share;
                    }
                    $ship = ($first && ! $cod) ? (float) config('thriftverse.shipping_flat') : 0.0;
                    $fee = $first ? (float) config('thriftverse.service_fee') : 0.0;
                    $first = false;

                    $tx->update([
                        'delivery_method' => $delivery, 'payment_method' => 'app', 'shipping_cost' => $ship, 'service_fee' => $fee,
                        'discount' => $share, 'total' => $tx->agreed_price + $ship + $fee - $share, 'escrow_status' => 'pending',
                        'hold_until' => now()->addHours($cod ? config('thriftverse.hold_meet_hours') : config('thriftverse.hold_pay_hours')),
                        'cart_group' => $groupKey, 'voucher_id' => $voucher?->id, 'voucher_group' => $voucher ? $groupKey : null,
                    ]);
                    if ($cod) {
                        $s = CodSchedule::create(['transaction_id' => $tx->id, 'meeting_point_id' => $data['meeting_point_id'][$sid], 'proposed_by' => $me->id, 'scheduled_at' => $data['scheduled_at'][$sid]]);
                        $tx->seller->tell('Jadwal COD diusulkan', "{$me->name} mengusulkan ketemuan untuk {$l->title}", route('cod.show', $s));
                    } else {
                        $tx->seller->tell('Pesanan baru', "{$me->name} memesan {$l->title}. Menunggu pembayaran.", route('orders.show', $tx));
                    }
                    $made++;
                }
            }
            if ($voucher) VoucherService::redeem($voucher, $me, $groupKey);
            CartItem::where('user_id', $me->id)->whereIn('listing_id', $valid->pluck('listing_id')->all())->delete();
            return $made;
        });

        return redirect()->route('orders.index')->with('success', "$count pesanan dibuat dari keranjang. Selesaikan pembayaran untuk tiap pesanan, atau pakai tombol \"Bayar semua\" di halaman pesanan.");
    }

    /** Bayar semua pesanan dalam satu grup keranjang (simulasi sandbox). */
    public function payAll(string $group)
    {
        $txs = Transaction::where('cart_group', $group)->where('payer_id', auth()->id())->where('escrow_status', 'pending')->whereIn('status', ['pending', 'packed'])->get();
        abort_if($txs->isEmpty(), 404);
        foreach ($txs as $tx) OrderService::fund($tx);
        return redirect()->route('orders.index')->with('success', $txs->count().' pesanan dibayar (simulasi). Dana ditahan rekber.');
    }
}
