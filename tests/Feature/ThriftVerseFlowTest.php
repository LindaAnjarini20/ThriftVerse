<?php
namespace Tests\Feature;

use App\Models\{Barter, Bid, CodSchedule, Listing, Report, Transaction, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ThriftVerseFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
    }

    private function u(string $name): User { return User::where('username', $name)->firstOrFail(); }
    private function l(string $like): Listing { return Listing::where('title', 'like', "%$like%")->firstOrFail(); }

    public function test_public_pages_and_auth(): void
    {
        $this->get('/')->assertOk()->assertSee('Katalog Pilihan');
        $this->get('/?mode[]=tawar&grade[]=A&sort=harga_asc&area=Jakarta Selatan&radius=10&q=nike')->assertOk();
        $this->get('/listings/'.$this->l('Carhartt')->slug)->assertOk()->assertSee('Estimasi harga layak');
        $this->get('/login')->assertOk();
        $this->post('/login', ['login' => 'rayhan@thriftverse.app', 'password' => 'password123'])->assertRedirect('/dashboard');
        $this->post('/logout');
        $this->post('/login', ['login' => '@rayhan', 'password' => 'password123'])->assertRedirect('/dashboard');
        $this->get('/negosiasi')->assertOk()->assertSee('Tawaran Masuk');
        foreach (['/barter', '/pesanan', '/jadwal-cod', '/jual', '/jual/baru', '/profile', '/notifications'] as $p) $this->get($p)->assertOk();
        $this->get('/admin')->assertForbidden();
    }

    public function test_register_creates_user(): void
    {
        $this->post('/register', ['name' => 'Baru', 'email' => 'baru@x.com', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertRedirect('/profile');
        $this->assertDatabaseHas('users', ['email' => 'baru@x.com', 'role' => 'user']);
    }

    public function test_listing_creation_grades_from_checklist_and_requires_photos(): void
    {
        $this->actingAs($this->u('rayhan'));
        $img = fn ($n) => UploadedFile::fake()->image($n.'.jpg', 600, 600);
        $base = ['title' => 'Test Jaket', 'category' => 'Outerwear', 'brand' => 'X', 'current_price' => 200000, 'original_price' => 500000, 'harga_tetap' => 1,
            'photo_front' => $img('a'), 'photo_back' => $img('b'), 'photo_label' => $img('c'), 'photo_detail' => $img('d')];

        $this->post('/jual', $base)->assertRedirect();
        $this->assertEquals('A', Listing::where('title', 'Test Jaket')->first()->condition_grade);

        // grade C (lubang) wajib foto minus + catatan
        $bad = array_merge($base, ['checklist' => ['lubang'], 'title' => 'Test C']);
        $this->post('/jual', $bad)->assertSessionHasErrors(['minus_photos']);
        $ok = array_merge($bad, ['minus_photos' => [$img('m')], 'minus_note' => 'lubang di lengan',
            'photo_front' => $img('a'), 'photo_back' => $img('b'), 'photo_label' => $img('c'), 'photo_detail' => $img('d')]);
        $this->post('/jual', $ok)->assertRedirect();
        $this->assertEquals('C', Listing::where('title', 'Test C')->first()->condition_grade);

        // minimal satu opsi transaksi
        $none = array_merge($base, ['title' => 'Tanpa opsi', 'photo_front' => $img('a'), 'photo_back' => $img('b'), 'photo_label' => $img('c'), 'photo_detail' => $img('d')]);
        unset($none['harga_tetap']);
        $this->post('/jual', $none)->assertStatus(422);
    }

    public function test_bid_validation_accept_closes_others_and_creates_hold(): void
    {
        $nike = $this->l('Nike Heavyweight');
        $rayhan = $this->u('rayhan'); $dimas = $this->u('dimas_novan'); $reza = $this->u('reza_archive');
        $this->assertEquals(3, Bid::where('listing_id', $nike->id)->count());

        // tawaran terlalu rendah (< 30%) ditolak; ada tawaran aktif sebelumnya (seed) -> pakai user baru
        $kolektor = $this->u('kolektor_low');
        $this->actingAs($this->u('bdg_thrift'))->post("/listings/{$nike->id}/bids", ['amount' => 1000, 'delivery_method' => 'ekspedisi'])->assertSessionHasErrors('amount');
        // COD di luar radius (Bandung -> Jakarta) ditolak
        $this->post("/listings/{$nike->id}/bids", ['amount' => 200000, 'delivery_method' => 'cod'])->assertSessionHasErrors('delivery_method');

        // penjual terima tawaran Dimas
        $bid = Bid::where('buyer_id', $dimas->id)->first();
        $this->actingAs($rayhan)->post("/bids/{$bid->id}/respond", ['action' => 'accept'])->assertRedirect();
        $this->assertEquals('accepted', $bid->fresh()->status);
        $this->assertEquals('closed', Bid::where('buyer_id', $reza->id)->first()->status);
        $this->assertEquals('reserved', $nike->fresh()->status);
        $tx = Transaction::where('bid_id', $bid->id)->firstOrFail();
        $this->assertEquals(295000, $tx->agreed_price);
        $this->assertNotNull($tx->hold_until);

        // hold lewat -> housekeeping batalkan & buka kembali barang
        $tx->update(['hold_until' => now()->subMinute()]);
        $this->artisan('thriftverse:housekeeping')->assertSuccessful();
        $this->assertEquals('cancelled', $tx->fresh()->status);
        $this->assertEquals('active', $nike->fresh()->status);
    }

    public function test_counter_offer_rounds_limit(): void
    {
        $nike = $this->l('Nike Heavyweight'); $dimas = $this->u('dimas_novan'); $rayhan = $this->u('rayhan');
        $bid = Bid::where('buyer_id', $dimas->id)->first();
        $bid->update(['round' => 5]);
        $this->actingAs($rayhan)->post("/bids/{$bid->id}/respond", ['action' => 'counter', 'amount' => 330000])->assertSessionHasErrors('amount');
        $bid->update(['round' => 1]);
        $this->post("/bids/{$bid->id}/respond", ['action' => 'counter', 'amount' => 330000])->assertSessionHasNoErrors();
        $this->assertEquals('countered', $bid->fresh()->status);
        $reply = Bid::where('parent_id', $bid->id)->first();
        $this->assertEquals(2, $reply->round);
        // pembeli tidak boleh merespons tawarannya sendiri, tapi boleh menerima counter
        $this->actingAs($dimas)->post("/bids/{$reply->id}/respond", ['action' => 'accept'])->assertRedirect();
        $this->assertEquals(330000, Transaction::where('bid_id', $reply->id)->first()->agreed_price);
    }

    public function test_cod_full_flow_cash_with_code_handover_and_review(): void
    {
        $item = $this->l('Stussy World Tour'); $buyer = $this->u('dimas_novan'); $seller = $this->u('archivevtg');
        $this->actingAs($buyer)->post("/listings/{$item->id}/beli")->assertRedirect();
        $tx = Transaction::latest('id')->first();
        $this->get("/pesanan/{$tx->id}/checkout")->assertOk()->assertSee('Safe Meeting Point Hub');
        $when = now()->addDay()->format('Y-m-d\TH:i');
        $this->post("/pesanan/{$tx->id}/checkout", ['delivery_method' => 'cod', 'payment_method' => 'cash', 'meeting_point_id' => \App\Models\MeetingPoint::first()->id, 'scheduled_at' => $when])->assertRedirect();
        $tx->refresh(); $s = $tx->schedule;
        $this->assertEquals(0, $tx->shipping_cost);
        $this->assertEquals('proposed', $s->status);

        // pembeli tidak bisa konfirmasi usulannya sendiri; penjual bisa -> kode dibuat
        $this->post("/jadwal-cod/{$s->id}/konfirmasi")->assertForbidden();
        $this->actingAs($seller)->post("/jadwal-cod/{$s->id}/konfirmasi")->assertRedirect();
        $s->refresh(); $this->assertEquals('confirmed', $s->status); $this->assertNotNull($s->code());
        $this->assertEquals('packed', $tx->fresh()->status);

        // kode hanya terlihat pembeli
        $code = $s->code();
        $this->get("/jadwal-cod/{$s->id}")->assertOk()->assertDontSee($code);
        $this->actingAs($buyer)->get("/jadwal-cod/{$s->id}")->assertOk()->assertSee(substr($code, 0, 3).'-'.substr($code, 3));

        // check-in terverifikasi bila dekat titik temu
        $pt = $s->point;
        $this->post("/jadwal-cod/{$s->id}/tiba", ['latitude' => $pt->latitude, 'longitude' => $pt->longitude])->assertRedirect();
        $this->assertTrue($s->fresh()->buyer_arrival_verified);

        // kode salah ditolak, kode benar menyelesaikan
        $this->actingAs($seller)->post("/jadwal-cod/{$s->id}/serah-terima", ['code' => $code === '123456' ? '654321' : '123456'])->assertSessionHasErrors('code');
        $this->post("/jadwal-cod/{$s->id}/serah-terima", ['code' => $code])->assertRedirect();
        $this->assertEquals('completed', $tx->fresh()->status);
        $this->assertEquals('sold', $item->fresh()->status);

        $this->actingAs($buyer)->post("/pesanan/{$tx->id}/ulasan", ['rating' => 5, 'grade_accurate' => 1])->assertRedirect();
        $this->assertEquals(5, $seller->fresh()->ratingAverage());
    }

    public function test_cod_reject_and_no_show_refund_and_penalty(): void
    {
        $buyer = $this->u('dimas_novan'); $seller = $this->u('archivevtg'); $item = $this->l('Stussy World Tour');
        $mk = function () use ($buyer, $item) {
            $this->actingAs($buyer)->post("/listings/{$item->id}/beli");
            $tx = Transaction::latest('id')->first();
            $this->post("/pesanan/{$tx->id}/checkout", ['delivery_method' => 'cod', 'payment_method' => 'app', 'meeting_point_id' => \App\Models\MeetingPoint::first()->id, 'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i')]);
            $this->actingAs($this->u('archivevtg'))->post('/jadwal-cod/'.Transaction::latest('id')->first()->schedule->id.'/konfirmasi');
            return Transaction::latest('id')->first();
        };
        $tx = $mk();
        // bayar via aplikasi -> rekber
        $this->actingAs($buyer)->post("/pesanan/{$tx->id}/bayar")->assertRedirect();
        $this->assertEquals('funded', $tx->fresh()->escrow_status);
        // tolak di tempat butuh foto bukti
        $s = $tx->schedule;
        $this->post("/jadwal-cod/{$s->id}/tolak", ['reason' => 'Tidak sesuai foto'])->assertSessionHasErrors('evidence');
        $this->post("/jadwal-cod/{$s->id}/tolak", ['reason' => 'Ada noda besar', 'evidence' => [UploadedFile::fake()->image('b.jpg')]])->assertRedirect();
        $this->assertEquals('cancelled', $tx->fresh()->status);
        $this->assertEquals('refunded', $tx->fresh()->escrow_status);
        $this->assertEquals('active', $item->fresh()->status);
        $this->assertEquals(95, $seller->fresh()->trust_score);

        // gagal bertemu: harus lewat 30 menit + sudah check-in
        $tx2 = $mk(); $s2 = $tx2->schedule;
        $this->actingAs($buyer)->post("/jadwal-cod/{$s2->id}/gagal-bertemu")->assertStatus(422);
        $s2->update(['scheduled_at' => now()->subMinutes(45), 'buyer_arrived_at' => now()->subMinutes(40)]);
        $this->post("/jadwal-cod/{$s2->id}/gagal-bertemu")->assertRedirect();
        $this->assertEquals('cancelled', $tx2->fresh()->status);
        $this->assertEquals(90, $seller->fresh()->trust_score);
    }

    public function test_courier_flow_pay_ship_receive_and_unshipped_auto_cancel(): void
    {
        $item = $this->l('Polo Ralph'); $buyer = $this->u('dimas_novan'); $seller = $this->u('grunge_vault');
        $this->actingAs($buyer)->post("/listings/{$item->id}/beli");
        $tx = Transaction::latest('id')->first();
        $this->post("/pesanan/{$tx->id}/checkout", ['delivery_method' => 'ekspedisi', 'payment_method' => 'app'])->assertRedirect();
        $this->assertEquals(540000 + 24000 + 1000, $tx->fresh()->total);
        $this->post("/pesanan/{$tx->id}/bayar")->assertRedirect();
        $this->assertEquals('packed', $tx->fresh()->status);
        $this->actingAs($seller)->post("/pesanan/{$tx->id}/kirim", ['courier' => 'JNE', 'tracking_no' => 'JNE123'])->assertRedirect();
        $this->assertEquals('shipped', $tx->fresh()->status);
        $this->actingAs($buyer)->post("/pesanan/{$tx->id}/terima")->assertRedirect();
        $this->assertEquals('completed', $tx->fresh()->status);
        $this->assertEquals('released', $tx->fresh()->escrow_status);

        // penjual tidak kirim dalam batas waktu -> batal otomatis + refund + penalti
        $item2 = $this->l('Levi'); $item2->update(['harga_tetap' => true, 'seller_id' => $seller->id]); $this->actingAs($buyer)->post("/listings/{$item2->id}/beli");
        $t2 = Transaction::latest('id')->first();
        $this->post("/pesanan/{$t2->id}/checkout", ['delivery_method' => 'ekspedisi', 'payment_method' => 'app']);
        $this->post("/pesanan/{$t2->id}/bayar");
        $t2->update(['ship_by' => now()->subHour()]);
        $this->artisan('thriftverse:housekeeping')->assertSuccessful();
        $this->assertEquals('cancelled', $t2->fresh()->status);
        $this->assertEquals('refunded', $t2->fresh()->escrow_status);
        $this->assertEquals(95, $seller->fresh()->trust_score);
    }

    public function test_dispute_admin_resolution(): void
    {
        $item = $this->l('Polo Ralph'); $buyer = $this->u('dimas_novan'); $admin = $this->u('admin');
        $this->actingAs($buyer)->post("/listings/{$item->id}/beli");
        $tx = Transaction::latest('id')->first();
        $this->post("/pesanan/{$tx->id}/checkout", ['delivery_method' => 'ekspedisi', 'payment_method' => 'app']);
        $this->post("/pesanan/{$tx->id}/bayar");
        $this->actingAs($this->u('grunge_vault'))->post("/pesanan/{$tx->id}/kirim", ['courier' => 'JNE', 'tracking_no' => 'X1']);
        $this->actingAs($buyer)->post("/pesanan/{$tx->id}/komplain", ['reason' => 'Tidak sesuai grade A', 'evidence' => [UploadedFile::fake()->image('e.jpg')]])->assertRedirect();
        $this->assertEquals('dispute', $tx->fresh()->status);
        $rep = Report::first();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Verifikasi');
        $this->get('/admin/sengketa')->assertOk()->assertSee('Lepas Dana');
        $this->post("/admin/laporan/{$rep->id}/putuskan", ['resolution' => 'refund'])->assertRedirect();
        $this->assertEquals('refunded', $tx->fresh()->escrow_status);
        $this->assertEquals('active', $item->fresh()->status);
        $this->get('/admin/pengguna')->assertOk();
    }

    public function test_admin_grade_verification(): void
    {
        $l = $this->l('Nirvana'); $this->assertNull($l->verified_at);
        $this->actingAs($this->u('admin'))->post("/admin/listings/{$l->id}/verify", ['action' => 'downgrade'])->assertRedirect();
        $this->assertEquals('B', $l->condition_grade); $this->assertEquals('C', $l->fresh()->condition_grade); $this->assertNotNull($l->fresh()->verified_at);
    }

    public function test_barter_flow_locking_counter_dual_approval_and_handover(): void
    {
        $target = $this->l('Dr. Martens'); // milik rayhan, terbuka barter
        $offer = $this->l('Thrasher'); $offerer = $this->u('bdg_thrift'); $rayhan = $this->u('rayhan');
        $offer->update(['terbuka_barter' => false]);
        $this->actingAs($offerer)->get("/listings/{$target->id}/barter")->assertOk();
        $this->post("/listings/{$target->id}/barter", ['items' => [$offer->id], 'cash_top_up' => 500000, 'top_up_payer' => 'offerer'])->assertRedirect();
        $b = Barter::first();
        $this->assertEquals('reserved', $target->fresh()->status); $this->assertEquals('reserved', $offer->fresh()->status);
        $this->get("/barters/{$b->id}")->assertOk();
        $this->get("/barter")->assertOk();

        // pemilik mengajukan balik; persetujuan direset
        $this->actingAs($rayhan)->post("/barters/{$b->id}/respond", ['action' => 'counter', 'cash_top_up' => 400000, 'top_up_payer' => 'offerer'])->assertRedirect();
        $b->refresh(); $this->assertEquals(2, $b->round); $this->assertTrue($b->owner_approved); $this->assertFalse($b->offerer_approved);
        // pengaju tidak boleh counter sepihak setelah menunggu
        $this->post("/barters/{$b->id}/respond", ['action' => 'approve'])->assertForbidden();

        $this->actingAs($offerer)->post("/barters/{$b->id}/respond", ['action' => 'approve'])->assertRedirect();
        $b->refresh(); $this->assertEquals('accepted', $b->status);
        $tx = $b->transaction; $this->assertEquals('barter', $tx->type); $this->assertEquals(400000, $tx->agreed_price);

        // jadwal ketemuan & bayar uang tambahan lewat rekber
        $this->get("/pesanan/{$tx->id}")->assertOk();
        $sc = CodSchedule::create(['transaction_id' => $tx->id, 'meeting_point_id' => \App\Models\MeetingPoint::first()->id, 'proposed_by' => $offerer->id, 'scheduled_at' => now()->addDay()]);
        $this->post("/pesanan/{$tx->id}/bayar")->assertRedirect();
        $this->actingAs($rayhan)->post("/jadwal-cod/{$sc->id}/konfirmasi")->assertRedirect();
        $code = $sc->fresh()->code();
        $this->post("/jadwal-cod/{$sc->id}/serah-terima", ['code' => $code])->assertRedirect();
        $this->assertEquals('completed', $tx->fresh()->status);
        $this->assertEquals('sold', $target->fresh()->status); $this->assertEquals('sold', $offer->fresh()->status);
        $this->assertEquals('completed', $b->fresh()->status);
    }

    public function test_barter_reject_unlocks_and_expiry(): void
    {
        $target = $this->l('Dr. Martens'); $offer = $this->l('Thrasher'); $offerer = $this->u('bdg_thrift');
        $this->actingAs($offerer)->post("/listings/{$target->id}/barter", ['items' => [$offer->id]]);
        $b = Barter::first();
        $this->actingAs($this->u('rayhan'))->post("/barters/{$b->id}/respond", ['action' => 'reject'])->assertRedirect();
        $this->assertEquals('active', $target->fresh()->status); $this->assertEquals('active', $offer->fresh()->status);

        $this->actingAs($offerer)->post("/listings/{$target->id}/barter", ['items' => [$offer->id]]);
        Barter::latest('id')->first()->update(['expires_at' => now()->subMinute()]);
        $this->artisan('thriftverse:housekeeping');
        $this->assertEquals('active', $target->fresh()->status);
    }

    public function test_wishlist_and_price_drop_history(): void
    {
        $l = $this->l('Carhartt'); $buyer = $this->u('dimas_novan');
        $this->actingAs($buyer)->post("/listings/{$l->id}/wishlist");
        $this->assertDatabaseHas('wishlists', ['user_id' => $buyer->id, 'listing_id' => $l->id]);
        $n = $l->priceDrops()->count();
        $this->actingAs($l->seller)->put("/jual/{$l->id}", ['title' => $l->title, 'category' => $l->category, 'current_price' => 700000, 'status' => 'active', 'harga_tetap' => 1, 'bisa_tawar' => 1])->assertRedirect();
        $this->assertEquals($n + 1, $l->priceDrops()->count());
        $this->assertEquals(1, $buyer->notifications()->count());
    }
}
