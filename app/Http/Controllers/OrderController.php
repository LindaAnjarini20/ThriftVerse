<?php
namespace App\Http\Controllers;

use App\Models\{CodSchedule, Listing, MeetingPoint, Report, Review, Transaction};
use App\Services\{MidtransService, OrderService, VoucherService};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Pesanan, pembayaran, pengiriman, rekber (SRS 4.5). */
class OrderController extends Controller
{
    private const TABS = [
        'belum_bayar' => ['Belum Bayar', 'pending'], 'dikemas' => ['Dikemas / Menunggu Pertemuan', 'packed'], 'dikirim' => ['Dikirim', 'shipped'],
        'selesai' => ['Selesai', 'completed'], 'dibatalkan' => ['Dibatalkan', 'cancelled'], 'pengembalian' => ['Pengembalian', 'dispute'],
    ];

    public function index(Request $request)
    {
        $role = $request->get('peran', 'beli') === 'jual' ? 'seller_id' : 'buyer_id';
        $tab = $request->get('tab', 'semua');
        $all = Transaction::with(['listing', 'buyer', 'seller'])->where($role, auth()->id())->latest('id')->get();
        $counts = $all->groupBy(fn ($t) => $t->tab())->map->count();
        $orders = $tab === 'semua' ? $all : $all->filter(fn ($t) => $t->tab() === $tab);
        return view('orders.index', ['orders' => $orders, 'tabs' => self::TABS, 'tab' => $tab, 'counts' => $counts, 'peran' => $role === 'seller_id' ? 'jual' : 'beli', 'total' => $all->count()]);
    }

    public function buyNow(Listing $listing)
    {
        abort_unless($listing->isAvailable() && $listing->harga_tetap && $listing->seller_id !== auth()->id(), 403, 'Listing tidak bisa dibeli langsung.');
        $tx = OrderService::createPurchase($listing, auth()->user(), $listing->current_price);
        return redirect()->route('orders.checkout', $tx);
    }

    public function checkout(Transaction $transaction)
    {
        $me = auth()->user();
        abort_unless($transaction->buyer_id === $me->id && $transaction->type === 'purchase', 403);
        abort_unless($transaction->status === 'pending' && $transaction->escrow_status === 'none', 422, 'Checkout sudah dikonfirmasi.');
        $listing = $transaction->listing;
        $codOk = OrderService::codAvailable($listing, $me);
        $points = MeetingPoint::where('is_active', true)->orderByDesc('recommended')->get();
        return view('orders.checkout', ['tx' => $transaction, 'listing' => $listing, 'codOk' => $codOk, 'codKm' => OrderService::codDistance($listing, $me), 'points' => $points,
            'preset' => $transaction->bid?->delivery_method, 'cfg' => config('thriftverse')]);
    }

    public function confirmCheckout(Request $request, Transaction $transaction)
    {
        $me = auth()->user();
        abort_unless($transaction->buyer_id === $me->id && $transaction->status === 'pending' && $transaction->type === 'purchase', 403);
        $data = $request->validate([
            'delivery_method' => 'required|in:ekspedisi,cod', 'payment_method' => 'required|in:app,cash',
            'meeting_point_id' => 'required_if:delivery_method,cod|nullable|exists:meeting_points,id',
            'scheduled_at' => 'required_if:delivery_method,cod|nullable|date|after:+1 hour',
            'voucher_code' => 'nullable|string|max:40',
        ]);
        $cod = $data['delivery_method'] === 'cod';
        if ($cod && ! OrderService::codAvailable($transaction->listing, $me)) return back()->withErrors(['delivery_method' => 'COD tidak tersedia untuk area Anda.'])->withInput();
        if (! $cod && $data['payment_method'] === 'cash') return back()->withErrors(['payment_method' => 'Bayar tunai hanya untuk COD/ketemuan.'])->withInput();

        $voucher = null; $discount = 0.0;
        if ($request->filled('voucher_code')) {
            $voucher = VoucherService::find($request->voucher_code);
            $err = $voucher ? VoucherService::check($voucher, $me, (float) $transaction->agreed_price) : 'Kode voucher tidak ditemukan.';
            if ($err) return back()->withErrors(['voucher_code' => $err])->withInput();
            $discount = VoucherService::discount($voucher, (float) $transaction->agreed_price);
        }

        $q = OrderService::quote($transaction, $data['delivery_method']);
        $cash = $cod && $data['payment_method'] === 'cash';
        $fee = $cash ? (float) config('thriftverse.cod_cash_fee', 0) : $q['fee'];
        $transaction->update([
            'delivery_method' => $data['delivery_method'], 'payment_method' => $data['payment_method'],
            'shipping_cost' => $q['shipping'], 'service_fee' => $fee, 'discount' => $discount, 'total' => $transaction->agreed_price + $q['shipping'] + $fee - $discount,
            'voucher_id' => $voucher?->id, 'voucher_group' => $voucher ? $transaction->transaction_number : null,
            'escrow_status' => $data['payment_method'] === 'app' ? 'pending' : 'none',
            'hold_until' => now()->addHours($cod ? config('thriftverse.hold_meet_hours') : config('thriftverse.hold_pay_hours')),
        ]);
        if ($voucher) VoucherService::redeem($voucher, $me, $transaction->transaction_number);
        if ($cod) {
            CodSchedule::create(['transaction_id' => $transaction->id, 'meeting_point_id' => $data['meeting_point_id'], 'proposed_by' => $me->id, 'scheduled_at' => $data['scheduled_at']]);
            $transaction->seller->tell('Jadwal COD diusulkan', "{$me->name} mengusulkan ketemuan untuk {$transaction->listing->title}", route('cod.show', $transaction->schedule));
            return redirect()->route('cod.show', $transaction->schedule)->with('success', 'Jadwal diusulkan. Menunggu konfirmasi penjual.');
        }
        return redirect()->route('orders.show', $transaction)->with('success', 'Pesanan dibuat. Selesaikan pembayaran dalam '.config('thriftverse.hold_pay_hours').' jam.');
    }

    public function show(Transaction $transaction)
    {
        $me = auth()->user();
        abort_unless($transaction->involves($me) || $me->isAdmin(), 403);
        $transaction->load(['listing', 'buyer', 'seller', 'schedule.point', 'barter.items', 'reviews', 'reports']);
        return view('orders.show', ['tx' => $transaction, 'me' => $me, 'isBuyer' => $transaction->buyer_id === $me->id, 'isSeller' => $transaction->seller_id === $me->id]);
    }

    public function pay(Transaction $transaction)
    {
        abort_unless($transaction->payer_id === auth()->id() && in_array($transaction->status, ['pending', 'packed'], true) && $transaction->escrow_status === 'pending', 403);
        OrderService::fund($transaction);
        return back()->with('success', 'Pembayaran (simulasi) berhasil. Dana ditahan rekber hingga pesanan selesai.');
    }

    public function midtransToken(Transaction $transaction): JsonResponse
    {
        abort_unless($transaction->payer_id === auth()->id()
            && $transaction->status === 'pending'
            && $transaction->escrow_status === 'pending'
            && $transaction->payment_method === 'app', 403);

        return response()->json(['token' => MidtransService::snapToken($transaction)]);
    }

    public function midtransNotification(Request $request): JsonResponse
    {
        $notification = $request->all();
        abort_unless(MidtransService::verifyNotification($notification), 403, 'Signature Midtrans tidak valid.');

        $transaction = Transaction::where('transaction_number', $notification['order_id'] ?? '')->first();
        if (! $transaction) {
            return response()->json(['message' => 'Transaksi tidak ditemukan.'], 404);
        }

        abort_unless((int) round((float) ($notification['gross_amount'] ?? 0)) === (int) round($transaction->total), 422, 'Nominal transaksi tidak sesuai.');

        $shouldCancel = false;
        DB::transaction(function () use ($transaction, $notification, &$shouldCancel) {
            $transaction->refresh();
            $status = (string) ($notification['transaction_status'] ?? '');
            $transaction->update([
                'midtrans_status' => $status,
                'midtrans_transaction_id' => $notification['transaction_id'] ?? null,
            ]);

            if (in_array($status, ['capture', 'settlement'], true)
                && ($notification['fraud_status'] ?? 'accept') === 'accept'
                && $transaction->escrow_status === 'pending') {
                $transaction->midtrans_paid_at = now();
                $transaction->save();
                OrderService::fund($transaction);
            }
            $shouldCancel = in_array($status, ['deny', 'cancel', 'expire'], true)
                && $transaction->escrow_status === 'pending'
                && $transaction->status === 'pending';
        });
        if ($shouldCancel) {
            OrderService::cancel($transaction, 'Pembayaran Midtrans tidak selesai.');
        }

        return response()->json(['message' => 'OK']);
    }

    public function ship(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->seller_id === auth()->id() && $transaction->status === 'packed' && $transaction->delivery_method === 'ekspedisi', 403);
        $data = $request->validate(['courier' => 'required|string|max:40', 'tracking_no' => 'required|string|max:60']);
        $transaction->update($data + ['status' => 'shipped', 'shipped_at' => now(), 'auto_complete_at' => now()->addDays(config('thriftverse.auto_complete_days'))]);
        $transaction->buyer->tell('Pesanan dikirim', "Resi {$data['tracking_no']} ({$data['courier']})", route('orders.show', $transaction));
        return back()->with('success', 'Resi tersimpan. Status: Dikirim.');
    }

    public function receive(Transaction $transaction)
    {
        abort_unless($transaction->buyer_id === auth()->id() && $transaction->status === 'shipped', 403);
        OrderService::complete($transaction);
        return back()->with('success', 'Pesanan selesai. Dana diteruskan ke penjual.');
    }

    public function cancel(Request $request, Transaction $transaction)
    {
        $me = auth()->user();
        abort_unless($transaction->involves($me) && in_array($transaction->status, ['pending', 'packed'], true), 403);
        $isSeller = $transaction->seller_id === $me->id;
        abort_if(! $isSeller && $transaction->status === 'packed', 403, 'Pesanan yang sudah dibayar hanya dapat dibatalkan lewat penjual atau sengketa.');
        OrderService::cancel($transaction, $isSeller ? 'Dibatalkan penjual.' : 'Dibatalkan pembeli.', ($isSeller && $transaction->status === 'packed') ? $me : null);
        return redirect()->route('orders.index')->with('success', 'Pesanan dibatalkan.');
    }

    public function dispute(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->buyer_id === auth()->id() && in_array($transaction->status, ['packed', 'shipped'], true), 403);
        $data = $request->validate(['reason' => 'required|string|min:10|max:1000', 'evidence' => 'required|array|min:1|max:5', 'evidence.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120']);
        $paths = collect($data['evidence'])->map(fn ($f) => $f->store('evidence', 'public'))->all();
        Report::create(['type' => 'dispute', 'reporter_id' => auth()->id(), 'transaction_id' => $transaction->id, 'listing_id' => $transaction->listing_id, 'target_user_id' => $transaction->seller_id, 'reason' => $data['reason'], 'evidence' => $paths]);
        $transaction->update(['status' => 'dispute']);
        $transaction->seller->tell('Komplain pembeli', "Pesanan {$transaction->transaction_number} dikomplain. Admin akan menengahi.", route('orders.show', $transaction));
        return back()->with('success', 'Komplain terkirim. Dana tetap ditahan sampai admin memutuskan.');
    }

    public function review(Request $request, Transaction $transaction)
    {
        $me = auth()->user();
        abort_unless($transaction->involves($me) && $transaction->status === 'completed', 403);
        $data = $request->validate(['rating' => 'required|integer|between:1,5', 'grade_accurate' => 'nullable|boolean', 'comment' => 'nullable|string|max:500']);
        Review::firstOrCreate(['transaction_id' => $transaction->id, 'reviewer_id' => $me->id], [
            'reviewee_id' => $transaction->counterpart($me)->id, 'rating' => $data['rating'], 'grade_accurate' => $request->boolean('grade_accurate', true), 'comment' => $data['comment'] ?? null,
        ]);
        return back()->with('success', 'Terima kasih atas ulasannya.');
    }
}
