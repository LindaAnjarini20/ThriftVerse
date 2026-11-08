<?php
namespace App\Http\Controllers;

use App\Models\{Barter, Listing};
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Barter & tukar tambah (SRS 4.9). Metode serah terima: ketemuan (kirim belum didukung). */
class BarterController extends Controller
{
    public function index()
    {
        $me = auth()->id();
        $barters = Barter::with(['target', 'offerer', 'owner', 'items'])->where(fn ($q) => $q->where('offerer_id', $me)->orWhere('owner_id', $me))->latest('id')->get();
        $open = Listing::active()->where('terbuka_barter', true)->where('seller_id', '!=', $me)->latest('published_at')->limit(12)->get();
        return view('barters.index', compact('barters', 'open'));
    }

    public function create(Listing $listing)
    {
        $this->guardTarget($listing);
        $mine = Listing::active()->where('seller_id', auth()->id())->get();
        return view('barters.create', compact('listing', 'mine'));
    }

    public function store(Request $request, Listing $listing)
    {
        $this->guardTarget($listing);
        $data = $request->validate([
            'items' => 'required|array|min:1|max:3', 'items.*' => 'integer|distinct',
            'cash_top_up' => 'nullable|numeric|min:0', 'top_up_payer' => 'nullable|in:offerer,owner', 'message' => 'nullable|string|max:500',
        ]);
        $offers = Listing::active()->where('seller_id', auth()->id())->whereIn('id', $data['items'])->get();
        if ($offers->count() !== count($data['items'])) return back()->withErrors(['items' => 'Pilih barang aktif milik Anda (maks. 3).'])->withInput();
        $topUp = (float) ($data['cash_top_up'] ?? 0);

        $barter = DB::transaction(function () use ($listing, $offers, $data, $topUp) {
            $b = Barter::create([
                'target_listing_id' => $listing->id, 'offerer_id' => auth()->id(), 'owner_id' => $listing->seller_id, 'awaiting_user_id' => $listing->seller_id,
                'cash_top_up' => $topUp, 'top_up_payer' => $topUp > 0 ? ($data['top_up_payer'] ?? 'offerer') : null, 'method' => 'ketemuan',
                'message' => $data['message'] ?? null, 'round' => 1, 'offerer_approved' => true, 'owner_approved' => false,
                'expires_at' => now()->addHours(config('thriftverse.barter_expiry_hours')),
            ]);
            $b->items()->attach($offers->mapWithKeys(fn ($o) => [$o->id => ['estimated_value' => $o->current_price]])->all());
            $b->log(auth()->user()->name.' mengajukan barter'.($topUp ? ' + Rp'.number_format($topUp, 0, ',', '.') : ''));
            $b->save();
            // FR-086: barang kedua pihak dikunci selama barter aktif
            Listing::whereIn('id', $offers->pluck('id')->push($listing->id))->update(['status' => 'reserved']);
            return $b;
        });
        $listing->seller->tell('Ajakan barter', auth()->user()->name." ingin menukar dengan {$listing->title}", route('barters.show', $barter));
        return redirect()->route('barters.show', $barter)->with('success', 'Pengajuan barter terkirim. Barang Anda dikunci selama barter aktif.');
    }

    public function show(Barter $barter)
    {
        $this->party($barter);
        $barter->load(['target', 'offerer', 'owner', 'items', 'transaction']);
        $ind = Barter::indicator($barter->offeredValue(), $barter->target->current_price);
        return view('barters.show', compact('barter', 'ind'));
    }

    public function respond(Request $request, Barter $barter)
    {
        $me = $this->party($barter);
        $barter->load(['items', 'target']);
        abort_unless($barter->status === 'pending', 422, 'Barter sudah tidak aktif.');
        $data = $request->validate(['action' => 'required|in:approve,reject,counter,cancel', 'cash_top_up' => 'nullable|numeric|min:0', 'top_up_payer' => 'nullable|in:offerer,owner', 'message' => 'nullable|string|max:300']);
        $other = $me->id === $barter->offerer_id ? $barter->owner : $barter->offerer;
        $unlock = fn () => Listing::whereIn('id', $barter->items->pluck('id')->push($barter->target_listing_id))->where('status', 'reserved')->update(['status' => 'active']);

        if ($data['action'] === 'cancel' || $data['action'] === 'reject') {
            abort_if($data['action'] === 'cancel' && $me->id !== $barter->offerer_id, 403);
            abort_if($data['action'] === 'reject' && $barter->awaiting_user_id !== $me->id, 403, 'Menunggu respons pihak lain.');
            $barter->status = $data['action'] === 'cancel' ? 'cancelled' : 'rejected';
            $barter->log("{$me->name} ".($data['action'] === 'cancel' ? 'membatalkan' : 'menolak').' barter'); $barter->save(); $unlock();
            $other->tell('Barter '.$barter->status, "Barter untuk {$barter->target->title} {$barter->status}.", route('barters.show', $barter));
            return back()->with('success', 'Barter '.$barter->status.'. Barang dibuka kembali.');
        }
        abort_unless($barter->awaiting_user_id === $me->id, 403, 'Menunggu respons pihak lain.');

        if ($data['action'] === 'counter') {
            if ($barter->round >= config('thriftverse.barter_max_rounds')) return back()->withErrors(['cash_top_up' => 'Maksimal putaran tercapai.']);
            $topUp = (float) ($data['cash_top_up'] ?? 0);
            $barter->fill(['cash_top_up' => $topUp, 'top_up_payer' => $topUp > 0 ? ($data['top_up_payer'] ?? 'offerer') : null, 'round' => $barter->round + 1,
                'awaiting_user_id' => $other->id, 'offerer_approved' => $me->id === $barter->offerer_id, 'owner_approved' => $me->id === $barter->owner_id,
                'expires_at' => now()->addHours(config('thriftverse.barter_expiry_hours'))]);
            $barter->log("{$me->name} mengajukan balik: uang tambahan Rp".number_format($topUp, 0, ',', '.')); $barter->save();
            $other->tell('Barter diajukan balik', "Ringkasan baru untuk {$barter->target->title}", route('barters.show', $barter));
            return back()->with('success', 'Pengajuan balik terkirim; persetujuan sebelumnya direset (FR-085).');
        }

        // approve — pihak yang mengajukan ringkasan sudah otomatis setuju; ini persetujuan pihak lawan
        $barter->fill($me->id === $barter->offerer_id ? ['offerer_approved' => true] : ['owner_approved' => true]);
        if (! ($barter->offerer_approved && $barter->owner_approved)) { $barter->save(); return back(); }
        $barter->fill(['status' => 'accepted', 'awaiting_user_id' => null, 'expires_at' => null]);
        $barter->log('Kedua pihak menyetujui ringkasan akhir'); $barter->save();
        $tx = OrderService::createBarterTransaction($barter);
        $other->tell('Barter disepakati', 'Atur jadwal ketemuan untuk serah terima.', route('orders.show', $tx));
        return redirect()->route('orders.show', $tx)->with('success', 'Barter disepakati kedua pihak. Atur jadwal ketemuan.');
    }

    private function guardTarget(Listing $l): void
    {
        abort_unless($l->isAvailable() && $l->terbuka_barter && $l->seller_id !== auth()->id(), 403, 'Listing ini tidak terbuka untuk barter.');
    }

    private function party(Barter $b)
    {
        $u = auth()->user();
        abort_unless(in_array($u->id, [$b->offerer_id, $b->owner_id], true), 403);
        return $u;
    }
}
