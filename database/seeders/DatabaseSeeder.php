<?php
namespace Database\Seeders;

use App\Models\{Bid, Listing, MeetingPoint, PriceDropHistory, User};
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $areas = config('thriftverse.areas');
        $mk = function (string $name, string $username, string $email, string $area, array $extra = []) use ($areas) {
            return User::create(array_merge([
                'name' => $name, 'username' => $username, 'email' => $email, 'password' => 'password123', 'role' => 'user',
                'no_hp' => '08'.random_int(1000000000, 1999999999), 'area' => $area, 'area_lat' => $areas[$area][0], 'area_lng' => $areas[$area][1],
                'email_verified_at' => now(), 'is_active' => true,
            ], $extra));
        };

        $admin = $mk('Admin ThriftVerse', 'admin', 'admin@thriftverse.app', 'Jakarta Selatan', ['role' => 'admin']);
        $rayhan = $mk('Rayhan F.', 'rayhan', 'rayhan@thriftverse.app', 'Jakarta Selatan', ['seller_mode' => true, 'payout_account' => 'BCA 1234567890 a.n. Rayhan F.']);
        $archive = $mk('ArchiveVtg.Jkt', 'archivevtg', 'archive@thriftverse.app', 'Jakarta Selatan', ['seller_mode' => true, 'payout_account' => 'BCA 2222333344']);
        $grunge = $mk('Grunge Vault', 'grunge_vault', 'grunge@thriftverse.app', 'Jakarta Pusat', ['seller_mode' => true, 'payout_account' => 'GoPay 08123456789']);
        $dimas = $mk('Dimas Novan', 'dimas_novan', 'dimas@thriftverse.app', 'Jakarta Selatan');
        $reza = $mk('Reza Archive', 'reza_archive', 'reza@thriftverse.app', 'Jakarta Selatan');
        $kolektor = $mk('Kolektor Low', 'kolektor_low', 'kolektor@thriftverse.app', 'Jakarta Pusat');
        $bdg = $mk('Penjual Bandung', 'bdg_thrift', 'bandung@thriftverse.app', 'Bandung', ['seller_mode' => true, 'payout_account' => 'BRI 9988776655']);

        $pts = [
            ['M Bloc Space - Area Depan Komunal Cafe', 'Panglima Polim, Jakarta Selatan • 250m dari Stasiun MRT Blok M BCA', 'Jakarta', -6.2427, 106.7978, ['CCTV 24 Jam', 'Ruang Terbuka Publik', 'Free Wi-Fi Hub'], true],
            ['Stasiun MRT Blok M Plaza - Pintu Keluar B', 'Kebayoran Baru, Jakarta Selatan • Dekat Customer Service Station', 'Jakarta', -6.2441, 106.8003, ['Pos Keamanan MRT', 'Akses Transportasi Langsung'], false],
            ['Senayan Park (SPARK) - Lobby Timur', 'Jl. Gerbang Pemuda No.3, Gelora, Tanah Abang • Area Pintu Putar', 'Jakarta', -6.2186, 106.8029, ['Parkir Luas', 'Security Patroli'], false],
            ['Tebet Eco Park Hub (Plaza Barat)', 'Tebet, Jakarta Selatan • Area publik ramai', 'Jakarta', -6.2359, 106.8573, ['Ruang Terbuka Publik', 'CCTV 24 Jam'], false],
            ['Senopati Hub Point (Safe Spot)', 'Senopati, Jakarta Selatan • Kafe mitra', 'Jakarta', -6.2328, 106.8097, ['Kafe Mitra', 'Staf Keamanan'], false],
            ['KRL Stasiun Pondok Cina', 'Depok • Dekat kampus UI', 'Depok', -6.3693, 106.8329, ['Pos Keamanan KRL', 'Ramai Mahasiswa'], false],
            ['Hub Kampus ITB - Gerbang Depan', 'Jl. Ganesha, Bandung • Area kampus', 'Bandung', -6.8915, 107.6107, ['Satpam Kampus', 'Ramai Siang Hari'], true],
        ];
        $mp = [];
        foreach ($pts as [$n, $a, $c, $lat, $lng, $f, $rec]) {
            $mp[] = MeetingPoint::create(['name' => $n, 'address' => $a, 'city' => $c, 'latitude' => $lat, 'longitude' => $lng, 'features' => $f, 'recommended' => $rec]);
        }

        // [seller, judul, merek, kategori, ukuran, warna, orig, harga, [checklist], opsi, titik COD (index), verified, deskripsi]
        $items = [
            [$archive, "Vintage Carhartt Detroit J97 Tan Corduroy", 'Carhartt', 'Outerwear', 'L (Boxy)', 'Tan', 950000, 780000, [], ['tetap', 'tawar', 'cod'], 0, true, 'Jaket kerja vintage kerah corduroy, kondisi mulus, lining blanket utuh.'],
            [$rayhan, "90s Nike Heavyweight Center Swoosh Hoodie", 'Nike', 'Outerwear', 'XL Boxy Fit', 'Heather Grey', 480000, 350000, ['fading'], ['tetap', 'tawar', 'barter', 'cod'], 3, true, 'Hoodie 90s heavyweight, fading alami, bahan masih tebal. Terbuka barter 1-to-1 Stussy/Carhartt.'],
            [$grunge, "Levi's 501 Big E Redline Selvedge", "Levi's", 'Denim', 'W32 L34', 'Indigo', 2200000, 1250000, [], ['tawar', 'cod'], 4, true, 'Made in USA, redline selvedge, ronde bidding aktif.'],
            [$archive, "Stussy World Tour Tee 2000s", 'Stussy', 'Kaos Band', 'M', 'Hitam', 280000, 220000, ['fading', 'bulu'], ['tetap', 'cod'], 0, true, 'Grafik crack halus & faded khas 2000s, tag Mexico.'],
            [$rayhan, "Dr. Martens 1461 Vintage England", 'Dr. Martens', 'Sepatu', 'EU 42', 'Oxblood', 2100000, 950000, ['bulu', 'noda_samar'], ['tawar', 'barter', 'cod'], 2, true, 'MIE original, sol masih tebal. Terbuka tukar tambah (+cash).'],
            [$grunge, "Polo Ralph Lauren Crisp Lining Jacket", 'Polo Ralph Lauren', 'Outerwear', 'L', 'Krem', 600000, 540000, [], ['tetap', 'tawar', 'cod'], 5, true, 'Lining kotak-kotak, bordir Polo Pony navy.'],
            [$grunge, "Nirvana 1993 'In Utero' Tour Tee", 'Vintage Band', 'Kaos Band', 'L', 'Hitam', 6500000, 4750000, ['fading'], ['tawar', 'barter'], null, false, 'Single stitch, fading 10%, tanpa pinhole. Menunggu kurasi.'],
            [$archive, "Stüssy Mohair Cardigan '8-Ball'", 'Stussy', 'Outerwear', 'M', 'Hitam', 3800000, 2800000, ['jahitan', 'bau'], ['tawar'], null, false, 'Brand new with tags, kantong ori. Menunggu kurasi.'],
            [$bdg, "Hoodie Thrasher Vintage Flame Logo", 'Thrasher', 'Outerwear', 'L', 'Hitam', 750000, 380000, ['fading', 'noda_jelas'], ['tetap', 'tawar', 'cod'], 6, true, 'Noda sisa di lengan kiri (lihat foto minus), lainnya aman.'],
        ];
        $listings = [];
        foreach ($items as [$seller, $title, $brand, $cat, $size, $color, $orig, $price, $checklist, $opts, $pt, $ver, $desc]) {
            $p = $pt !== null ? $mp[$pt] : null;
            $l = Listing::create([
                'seller_id' => $seller->id, 'title' => $title, 'slug' => \Illuminate\Support\Str::slug($title).'-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(5)),
                'category' => $cat, 'brand' => $brand, 'size' => $size, 'color' => $color, 'description' => $desc,
                'condition_grade' => Listing::gradeFromChecklist($checklist), 'condition_checklist' => $checklist,
                'verified_at' => $ver ? now() : null, 'verified_by' => $ver ? $admin->id : null,
                'original_price' => $orig, 'current_price' => $price, 'secret_min_price' => in_array('tawar', $opts) ? round($price * 0.85, -3) : null,
                'images' => [], 'minus_photos' => [], 'harga_tetap' => in_array('tetap', $opts), 'bisa_tawar' => in_array('tawar', $opts),
                'terbuka_barter' => in_array('barter', $opts), 'barter_preference' => in_array('barter', $opts) ? 'Streetwear Stussy/Carhartt, size M-XL, minimal Grade B' : null,
                'bisa_cod' => in_array('cod', $opts) && $p, 'cod_radius_km' => 10, 'cod_location' => $p?->name, 'cod_latitude' => $p?->latitude, 'cod_longitude' => $p?->longitude,
                'status' => 'active', 'published_at' => now()->subDays(random_int(0, 10)),
            ]);
            // Riwayat harga: harga awal lalu turun sampai harga sekarang
            $start = (int) round($price * 1.18, -4);
            PriceDropHistory::create(['listing_id' => $l->id, 'from_price' => $start, 'to_price' => $start, 'source' => 'manual', 'changed_at' => now()->subDays(12)]);
            PriceDropHistory::create(['listing_id' => $l->id, 'from_price' => $start, 'to_price' => round($price * 1.08, -3), 'source' => 'manual', 'changed_at' => now()->subDays(7)]);
            PriceDropHistory::create(['listing_id' => $l->id, 'from_price' => round($price * 1.08, -3), 'to_price' => $price, 'source' => 'manual', 'changed_at' => now()->subDays(2)]);
            $listings[$title] = $l;
        }

        // Antrean tawaran masuk untuk hoodie Nike milik Rayhan (mengikuti mockup)
        $nike = $listings["90s Nike Heavyweight Center Swoosh Hoodie"];
        foreach ([[$dimas, 295000, 'cod', 'Siap transfer escrow detik ini juga bro, langsung kirim sore ini ya!'], [$reza, 290000, 'cod', null], [$kolektor, 250000, 'ekspedisi', null]] as [$b, $amt, $m, $msg]) {
            Bid::create(['listing_id' => $nike->id, 'buyer_id' => $b->id, 'proposer_id' => $b->id, 'amount' => $amt, 'delivery_method' => $m, 'message' => $msg, 'round' => 1, 'expires_at' => now()->addHours(20)]);
        }

        \App\Support\SeedPhotos::apply();

        // Data demo fitur baru: voucher, auto price drop, dan chat
        \App\Models\Voucher::create(['code' => 'THRIFT10', 'description' => 'Diskon 10% (maks. Rp50.000)', 'type' => 'percent', 'value' => 10, 'min_spend' => 100000, 'max_discount' => 50000, 'is_active' => true]);
        \App\Models\Voucher::create(['code' => 'HEMAT25K', 'description' => 'Potongan Rp25.000 min. belanja Rp200.000', 'type' => 'fixed', 'value' => 25000, 'min_spend' => 200000, 'is_active' => true]);
        $listings["Polo Ralph Lauren Crisp Lining Jacket"]->update(['auto_drop' => true, 'drop_percent' => 5, 'drop_every_days' => 7, 'floor_price' => 450000, 'last_drop_at' => now()]);
        $conv = \App\Models\Conversation::create(['listing_id' => $nike->id, 'buyer_id' => $dimas->id, 'seller_id' => $rayhan->id, 'last_message_at' => now()]);
        \App\Models\Message::create(['conversation_id' => $conv->id, 'sender_id' => $dimas->id, 'body' => 'Halo kak, hoodie Nike-nya masih ada? Bisa COD di M Bloc?']);

        $this->command?->info('Seeder selesai. Login: admin@thriftverse.app | rayhan@thriftverse.app | dimas@thriftverse.app (password: password123)');
    }
}
