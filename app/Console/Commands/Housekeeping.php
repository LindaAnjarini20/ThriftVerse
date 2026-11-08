<?php
namespace App\Console\Commands;

use App\Models\{Barter, Bid, Listing, Transaction};
use App\Services\{OrderService, PriceDropService};
use Illuminate\Console\Command;

class Housekeeping extends Command
{
    protected $signature = 'thriftverse:housekeeping';
    protected $description = 'Kedaluwarsakan tawaran/barter, batalkan pesanan lewat batas waktu, dan auto-selesaikan pesanan (FR-046, FR-075, FR-044)';

    public function handle(): int
    {
        $bids = Bid::where('status', 'pending')->where('expires_at', '<=', now())->update(['status' => 'expired']);

        $barters = 0;
        Barter::where('status', 'pending')->where('expires_at', '<=', now())->each(function (Barter $b) use (&$barters) {
            $b->update(['status' => 'expired']);
            Listing::whereIn('id', $b->items()->pluck('listings.id')->push($b->target_listing_id))->where('status', 'reserved')->update(['status' => 'active']);
            $barters++;
        });

        $unpaid = 0; $unshipped = 0; $auto = 0;
        Transaction::where('status', 'pending')->whereNotNull('hold_until')->where('hold_until', '<=', now())->each(function ($tx) use (&$unpaid) {
            OrderService::cancel($tx, 'Batas waktu pembayaran / penjadwalan terlewati.'); $unpaid++;
        });
        Transaction::where('status', 'packed')->where('delivery_method', 'ekspedisi')->whereNull('tracking_no')->where('ship_by', '<=', now())->each(function ($tx) use (&$unshipped) {
            OrderService::cancel($tx, 'Penjual tidak mengirim dalam batas waktu. Dana dikembalikan.', $tx->seller); $unshipped++;
        });
        Transaction::where('status', 'shipped')->where('auto_complete_at', '<=', now())->each(function ($tx) use (&$auto) {
            OrderService::complete($tx); $auto++;
        });

        $drops = PriceDropService::run();

        $this->info("Tawaran kedaluwarsa: $bids | Barter: $barters | Belum bayar: $unpaid | Tidak dikirim: $unshipped | Auto-selesai: $auto | Auto price drop: $drops");
        return self::SUCCESS;
    }
}
