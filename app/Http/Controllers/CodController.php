<?php
namespace App\Http\Controllers;

use App\Models\{CodSchedule, MeetingPoint, Report};
use App\Services\OrderService;
use Illuminate\Http\Request;

/** COD / ketemuan gratis ongkir (SRS 4.10). */
class CodController extends Controller
{
    public function index()
    {
        $me = auth()->id();
        $schedules = CodSchedule::with(['transaction.listing', 'transaction.buyer', 'transaction.seller', 'point'])
            ->whereHas('transaction', fn ($q) => $q->where(fn ($w) => $w->where('buyer_id', $me)->orWhere('seller_id', $me)))->orderBy('scheduled_at')->get();
        return view('cod.index', compact('schedules'));
    }

    public function show(CodSchedule $schedule)
    {
        $tx = $this->tx($schedule);
        $me = auth()->user();
        $isBuyer = $tx->buyer_id === $me->id;
        return view('cod.show', [
            's' => $schedule->load('point'), 'tx' => $tx->load(['listing', 'buyer', 'seller']), 'isBuyer' => $isBuyer, 'me' => $me,
            'code' => $isBuyer && $schedule->status === 'confirmed' ? $schedule->code() : null, // kode hanya terlihat pembeli (FR-094)
            'points' => MeetingPoint::where('is_active', true)->orderByDesc('recommended')->get(),
            'noShowReady' => $schedule->status === 'confirmed' && $schedule->scheduled_at->copy()->addMinutes(config('thriftverse.cod_no_show_minutes'))->isPast(),
        ]);
    }

    public function propose(Request $request, CodSchedule $schedule)
    {
        $tx = $this->tx($schedule); $this->open($schedule, $tx);
        $data = $request->validate(['meeting_point_id' => 'required|exists:meeting_points,id', 'scheduled_at' => 'required|date|after:+1 hour']);
        $schedule->update($data + ['proposed_by' => auth()->id(), 'status' => 'proposed', 'code_enc' => null, 'code_attempts' => 0]);
        if ($tx->status === 'packed' && $tx->delivery_method === 'cod') $tx->update(['status' => 'pending']);
        $tx->counterpart(auth()->user())->tell('Jadwal COD diubah', 'Usulan titik temu/waktu baru menunggu konfirmasi Anda.', route('cod.show', $schedule));
        return back()->with('success', 'Usulan jadwal terkirim.');
    }

    public function confirm(CodSchedule $schedule)
    {
        $tx = $this->tx($schedule); $this->open($schedule, $tx);
        abort_unless($schedule->status === 'proposed' && $schedule->proposed_by !== auth()->id(), 403, 'Konfirmasi dilakukan pihak lawan.');
        $schedule->makeCode(); $schedule->update(['status' => 'confirmed']);
        $tx->update(['status' => 'packed', 'hold_until' => null]);
        $tx->counterpart(auth()->user())->tell('Jadwal COD dikonfirmasi', 'Pertemuan disepakati. Kode serah terima tersedia di akun pembeli.', route('cod.show', $schedule));
        return back()->with('success', 'Jadwal dikonfirmasi. Kode serah terima dibuat untuk pembeli.');
    }

    /** "Saya Sudah Tiba" — check-in; lokasi opsional dan hanya diverifikasi bila dibagikan (FR-096/097). */
    public function arrive(Request $request, CodSchedule $schedule)
    {
        $tx = $this->tx($schedule); $this->open($schedule, $tx);
        abort_unless($schedule->status === 'confirmed', 422, 'Jadwal belum dikonfirmasi.');
        $request->validate(['latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180']);
        $verified = false;
        if ($request->filled(['latitude', 'longitude']) && $schedule->point) {
            $km = OrderService::distanceKm((float) $request->latitude, (float) $request->longitude, $schedule->point->latitude, $schedule->point->longitude);
            $verified = $km * 1000 <= config('thriftverse.cod_arrival_radius_m');
        }
        $p = auth()->id() === $tx->buyer_id ? 'buyer' : 'seller';
        $schedule->update(["{$p}_arrived_at" => now(), "{$p}_arrival_verified" => $verified]);
        $tx->counterpart(auth()->user())->tell('Pihak lawan sudah tiba', auth()->user()->name.' sudah di titik temu.', route('cod.show', $schedule));
        return back()->with('success', $verified ? 'Check-in tercatat (lokasi terverifikasi).' : 'Check-in tercatat (lokasi tidak diverifikasi).');
    }

    /** Penjual memasukkan kode dari pembeli → pesanan Selesai (FR-094). */
    public function handover(Request $request, CodSchedule $schedule)
    {
        $tx = $this->tx($schedule); $this->open($schedule, $tx);
        abort_unless($tx->seller_id === auth()->id() && $schedule->status === 'confirmed', 403);
        $data = $request->validate(['code' => 'required|digits:6']);
        if ($tx->payment_method === 'app' && $tx->escrow_status !== 'funded') return back()->withErrors(['code' => 'Pembeli belum membayar via aplikasi (rekber).']);
        if ($schedule->code_attempts >= config('thriftverse.cod_code_max_attempts')) return back()->withErrors(['code' => 'Terlalu banyak percobaan. Ajukan Laporan Masalah.']);
        if (! hash_equals((string) $schedule->code(), $data['code'])) {
            $schedule->increment('code_attempts');
            return back()->withErrors(['code' => 'Kode tidak cocok.']);
        }
        $schedule->update(['handover_at' => now()]);
        OrderService::complete($tx);
        return redirect()->route('orders.show', $tx)->with('success', 'Serah terima berhasil. Pesanan selesai.');
    }

    /** Tolak di tempat (FR-095). */
    public function reject(Request $request, CodSchedule $schedule)
    {
        $tx = $this->tx($schedule); $this->open($schedule, $tx);
        abort_unless($tx->buyer_id === auth()->id() && $schedule->status === 'confirmed', 403);
        $data = $request->validate(['reason' => 'required|string|min:5|max:500', 'evidence' => 'required|array|min:1|max:4', 'evidence.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120']);
        $paths = collect($data['evidence'])->map(fn ($f) => $f->store('evidence', 'public'))->all();
        Report::create(['type' => 'cod_reject', 'reporter_id' => auth()->id(), 'transaction_id' => $tx->id, 'listing_id' => $tx->listing_id, 'target_user_id' => $tx->seller_id, 'reason' => $data['reason'], 'evidence' => $paths, 'status' => 'resolved', 'resolution' => 'refund']);
        OrderService::cancel($tx, 'Ditolak di tempat: '.$data['reason'], $tx->seller);
        return redirect()->route('orders.show', $tx)->with('success', 'Barang ditolak. Pesanan dibatalkan tanpa biaya, rekber dikembalikan.');
    }

    /** Gagal bertemu: pihak lawan tidak hadir > 30 menit (FR-096). */
    public function noShow(CodSchedule $schedule)
    {
        $tx = $this->tx($schedule); $this->open($schedule, $tx);
        $me = auth()->user();
        $mine = $me->id === $tx->buyer_id ? 'buyer_arrived_at' : 'seller_arrived_at';
        $theirs = $me->id === $tx->buyer_id ? 'seller_arrived_at' : 'buyer_arrived_at';
        $late = $schedule->scheduled_at->copy()->addMinutes(config('thriftverse.cod_no_show_minutes'))->isPast();
        abort_unless($schedule->status === 'confirmed' && $late && $schedule->$mine && ! $schedule->$theirs, 422, 'Syarat belum terpenuhi: Anda harus sudah check-in dan pihak lawan belum hadir > '.config('thriftverse.cod_no_show_minutes').' menit.');
        $absent = $tx->counterpart($me);
        Report::create(['type' => 'no_show', 'reporter_id' => $me->id, 'transaction_id' => $tx->id, 'target_user_id' => $absent->id, 'reason' => 'Pihak lawan tidak hadir.', 'status' => 'resolved', 'resolution' => 'refund']);
        OrderService::cancel($tx, 'Gagal bertemu: '.$absent->name.' tidak hadir.', $absent);
        return redirect()->route('orders.show', $tx)->with('success', 'Gagal Bertemu dilaporkan; pelanggaran dicatat.');
    }

    public function problem(Request $request, CodSchedule $schedule)
    {
        $tx = $this->tx($schedule);
        $data = $request->validate(['reason' => 'required|string|min:10|max:1000']);
        Report::create(['type' => 'dispute', 'reporter_id' => auth()->id(), 'transaction_id' => $tx->id, 'listing_id' => $tx->listing_id, 'target_user_id' => $tx->counterpart(auth()->user())->id, 'reason' => $data['reason']]);
        $tx->update(['status' => 'dispute']);
        return back()->with('success', 'Laporan masalah terkirim ke admin.');
    }

    private function tx(CodSchedule $s)
    {
        $tx = $s->transaction;
        abort_unless($tx->involves(auth()->user()), 403);
        return $tx;
    }

    private function open(CodSchedule $s, $tx): void
    {
        abort_unless(in_array($s->status, ['proposed', 'confirmed'], true) && $tx->isOpen(), 422, 'Jadwal ini sudah tidak aktif.');
    }
}
