<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Listing, Report, Transaction, User};
use App\Services\OrderService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /** Verifikasi & Kurasi (FR-017) + statistik (FR-063). */
    public function dashboard()
    {
        $stats = [
            'listings' => Listing::active()->count(),
            'pending' => Listing::whereNull('verified_at')->whereIn('status', ['active', 'reserved'])->count(),
            'users' => User::count(),
            'escrow' => (float) Transaction::where('escrow_status', 'funded')->sum('total'),
            'escrow_tx' => Transaction::where('escrow_status', 'funded')->count(),
            'disputes' => Report::where('status', 'open')->whereIn('type', ['dispute'])->count(),
            'tx_done' => Transaction::where('status', 'completed')->count(),
            'revenue' => (float) Transaction::where('status', 'completed')->get()->sum(fn ($t) => $t->service_fee + $t->agreed_price * config('thriftverse.commission_pct') / 100),
        ];
        $queue = Listing::with('seller')->whereNull('verified_at')->whereIn('status', ['active', 'reserved'])->latest()->limit(20)->get();
        return view('admin.dashboard', compact('stats', 'queue'));
    }

    public function verify(Request $request, Listing $listing)
    {
        $data = $request->validate(['action' => 'required|in:approve,downgrade,reject']);
        match ($data['action']) {
            'approve' => $listing->update(['verified_at' => now(), 'verified_by' => auth()->id()]),
            'downgrade' => $listing->update(['condition_grade' => $listing->condition_grade === 'A' ? 'B' : 'C', 'verified_at' => now(), 'verified_by' => auth()->id()]),
            'reject' => $listing->update(['status' => 'archived']),
        };
        $msg = ['approve' => 'Grade diverifikasi — lencana terverifikasi diberikan.', 'downgrade' => "Grade diturunkan menjadi {$listing->condition_grade} setelah inspeksi.", 'reject' => 'Listing ditolak oleh kurator.'][$data['action']];
        $listing->seller->tell('Hasil verifikasi grade', "{$listing->title}: $msg", route('listings.show', $listing));
        return back()->with('success', $msg);
    }

    /** Escrow & Sengketa (FR-065). */
    public function disputes()
    {
        $reports = Report::with(['reporter', 'target', 'transaction.listing', 'listing'])->orderByRaw("status = 'open' desc")->latest('id')->paginate(15);
        $escrow = Transaction::with('listing')->where('escrow_status', 'funded')->latest()->limit(10)->get();
        return view('admin.disputes', compact('reports', 'escrow'));
    }

    public function resolve(Request $request, Report $report)
    {
        abort_unless($report->status === 'open', 422);
        $data = $request->validate(['resolution' => 'required|in:refund,release,dismissed', 'admin_note' => 'nullable|string|max:500']);
        $tx = $report->transaction;
        if ($tx && $tx->isOpen()) {
            if ($data['resolution'] === 'refund') OrderService::cancel($tx, 'Sengketa: dana dikembalikan ke pembeli.', $tx->seller);
            if ($data['resolution'] === 'release') OrderService::complete($tx);
            if ($data['resolution'] === 'dismissed' && $tx->status === 'dispute') $tx->update(['status' => $tx->shipped_at ? 'shipped' : 'packed']);
        }
        $report->update($data + ['status' => 'resolved', 'resolved_by' => auth()->id(), 'resolved_at' => now()]);
        $report->reporter->tell('Laporan diputuskan', 'Keputusan admin: '.['refund' => 'refund ke pembeli', 'release' => 'dana dicairkan ke penjual', 'dismissed' => 'laporan ditutup'][$data['resolution']], $tx ? route('orders.show', $tx) : null);
        return back()->with('success', 'Laporan diputuskan.');
    }

    /** Manajemen pengguna (FR-060). */
    public function users(Request $request)
    {
        $users = User::when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$request->q}%")->orWhere('email', 'like', "%{$request->q}%")))
            ->latest()->paginate(15)->withQueryString();
        return view('admin.users', compact('users'));
    }

    public function toggleUser(User $user)
    {
        abort_if($user->id === auth()->id() || $user->isAdmin(), 403, 'Tidak dapat menangguhkan akun admin.');
        $user->update(['is_active' => ! $user->is_active]);
        return back()->with('success', $user->is_active ? 'Akun diaktifkan.' : 'Akun ditangguhkan.');
    }
}
