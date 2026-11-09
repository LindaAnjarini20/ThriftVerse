<?php
namespace Tests\Feature;

use App\Models\{CartItem, Conversation, Listing, Message, Transaction, User, Voucher, VoucherRedemption};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Peta interaktif, auto price drop, voucher, chat, dan keranjang multi-item. */
class NewFeaturesTest extends TestCase
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

    public function test_midtrans_snap_token_is_reused_after_payment_popup_is_closed(): void
    {
        config(['services.midtrans.server_key' => 'test-server-key']);
        Http::fake(['*' => Http::response(['token' => 'snap-token'], 201)]);

        $buyer = $this->u('dimas_novan');
        $item = $this->l('Carhartt');
        $this->actingAs($buyer)->post("/listings/{$item->id}/beli");
        $tx = Transaction::latest('id')->firstOrFail();
        $this->post("/pesanan/{$tx->id}/checkout", [
            'delivery_method' => 'ekspedisi',
            'payment_method' => 'app',
        ])->assertRedirect("/pesanan/{$tx->id}");

        $this->postJson("/pesanan/{$tx->id}/midtrans-token")->assertOk()->assertJson(['token' => 'snap-token']);
        $this->postJson("/pesanan/{$tx->id}/midtrans-token")->assertOk()->assertJson(['token' => 'snap-token']);

        $this->assertSame('snap-token', $tx->fresh()->midtrans_snap_token);
        Http::assertSentCount(1);
    }

    public function test_map_is_rendered_on_checkout_cod_and_listing_form(): void
    {
        $item = $this->l('Carhartt');
        $this->actingAs($this->u('dimas_novan'))->post("/listings/{$item->id}/beli");
        $tx = Transaction::latest('id')->first();
        $this->get("/pesanan/{$tx->id}/checkout")->assertOk()->assertSee('leaflet', false)->assertSee('Safe Meeting Point Hub');

        $this->actingAs($this->u('archivevtg'))->get('/jual/baru')->assertOk()->assertSee('leaflet', false);
    }

    public function test_voucher_discount_reuse_and_release_on_cancel(): void
    {
        $buyer = $this->u('dimas_novan');
        $item = $this->l('Polo Ralph');
        $this->actingAs($buyer)->post("/listings/{$item->id}/beli");
        $tx = Transaction::latest('id')->first();

        $this->getJson('/voucher/cek?code=thrift10&amount=540000')->assertOk()->assertJson(['ok' => true, 'discount' => 50000]);
        $this->getJson('/voucher/cek?code=NGAWUR&amount=540000')->assertJson(['ok' => false]);
        $this->getJson('/voucher/cek?code=HEMAT25K&amount=100000')->assertJson(['ok' => false]); // di bawah minimal belanja

        $this->post("/pesanan/{$tx->id}/checkout", ['delivery_method' => 'ekspedisi', 'payment_method' => 'app', 'voucher_code' => 'thrift10'])->assertRedirect();
        $tx->refresh();
        $this->assertEquals(50000, $tx->discount);
        $this->assertEquals(540000 + 24000 + 1000 - 50000, $tx->total);
        $this->assertEquals(1, VoucherRedemption::count());

        // pemakaian kedua oleh pengguna yang sama ditolak
        $item2 = $this->l('Levi'); $item2->update(['harga_tetap' => true]);
        $this->post("/listings/{$item2->id}/beli");
        $tx2 = Transaction::latest('id')->first();
        $this->post("/pesanan/{$tx2->id}/checkout", ['delivery_method' => 'ekspedisi', 'payment_method' => 'app', 'voucher_code' => 'THRIFT10'])->assertSessionHasErrors('voucher_code');

        // dibatalkan -> voucher bisa dipakai lagi
        $this->post("/pesanan/{$tx->id}/batal")->assertRedirect();
        $this->assertEquals(0, VoucherRedemption::count());
        $this->post("/pesanan/{$tx2->id}/checkout", ['delivery_method' => 'ekspedisi', 'payment_method' => 'app', 'voucher_code' => 'THRIFT10'])->assertSessionHasNoErrors();
        $this->assertEquals(1, VoucherRedemption::count());
    }

    public function test_voucher_quota_and_admin_management(): void
    {
        $this->actingAs($this->u('dimas_novan'))->get('/admin/voucher')->assertForbidden();
        $this->actingAs($this->u('admin'))->get('/admin/voucher')->assertOk()->assertSee('THRIFT10');
        $this->post('/admin/voucher', ['code' => 'kuota1', 'type' => 'fixed', 'value' => 10000, 'quota' => 1])->assertRedirect();
        $v = Voucher::where('code', 'KUOTA1')->firstOrFail();
        $this->post("/admin/voucher/{$v->id}/toggle")->assertRedirect();
        $this->assertFalse($v->fresh()->is_active);
        $this->post("/admin/voucher/{$v->id}/toggle");

        VoucherRedemption::create(['voucher_id' => $v->id, 'user_id' => $this->u('reza_archive')->id, 'group_key' => 'X']);
        $this->actingAs($this->u('dimas_novan'))->getJson('/voucher/cek?code=KUOTA1&amount=300000')->assertJson(['ok' => false]);
    }

    public function test_auto_price_drop_runs_via_housekeeping(): void
    {
        $l = $this->l('Polo Ralph');
        $l->update(['auto_drop' => true, 'drop_percent' => 10, 'drop_every_days' => 7, 'floor_price' => 500000, 'last_drop_at' => now()->subDays(8), 'current_price' => 540000]);
        $this->artisan('thriftverse:housekeeping')->assertSuccessful();
        $this->assertEquals(500000, $l->fresh()->current_price);   // 486.000 dibatasi harga terendah
        $this->assertDatabaseHas('price_drop_histories', ['listing_id' => $l->id, 'to_price' => 500000, 'source' => 'auto']);

        $this->artisan('thriftverse:housekeeping')->assertSuccessful(); // belum jatuh tempo lagi
        $this->assertEquals(1, \App\Models\PriceDropHistory::where('listing_id', $l->id)->where('source', 'auto')->count());
    }

    public function test_listing_form_requires_floor_price_when_auto_drop(): void
    {
        $this->actingAs($this->u('rayhan'));
        $img = fn ($n) => UploadedFile::fake()->image($n.'.jpg', 600, 600);
        $base = ['title' => 'Test Drop', 'category' => 'Outerwear', 'current_price' => 300000, 'original_price' => 600000, 'harga_tetap' => 1, 'auto_drop' => 1,
            'drop_percent' => 10, 'drop_every_days' => 7];
        $files = fn () => ['photo_front' => $img('a'), 'photo_back' => $img('b'), 'photo_label' => $img('c'), 'photo_detail' => $img('d')];

        $this->post('/jual', $base + $files())->assertSessionHasErrors('floor_price');
        $this->post('/jual', $base + ['floor_price' => 200000] + $files())->assertRedirect();
        $this->assertTrue((bool) Listing::where('title', 'Test Drop')->first()->auto_drop);
    }

    public function test_chat_start_send_mask_phone_and_privacy(): void
    {
        $item = $this->l('Carhartt'); $buyer = $this->u('dimas_novan'); $seller = $this->u('archivevtg');
        $this->actingAs($seller)->post("/listings/{$item->id}/chat")->assertForbidden();

        $this->actingAs($buyer)->post("/listings/{$item->id}/chat")->assertRedirect();
        $c = Conversation::where('listing_id', $item->id)->firstOrFail();
        $this->get("/chat/{$c->id}")->assertOk();
        $this->postJson("/chat/{$c->id}", ['body' => 'Hubungi 081234567890 atau wa.me/628123 ya'])->assertOk();
        $m = Message::where('conversation_id', $c->id)->latest('id')->first();
        $this->assertStringContainsString('[nomor disembunyikan]', $m->body);
        $this->assertStringNotContainsString('081234567890', $m->body);

        $this->actingAs($seller)->get('/chat')->assertOk()->assertSee('Dimas Novan');
        $this->getJson("/chat/{$c->id}/pesan?after=0")->assertOk()->assertJsonCount(1);
        $this->assertNotNull($m->fresh()->read_at);

        $this->actingAs($this->u('reza_archive'))->get("/chat/{$c->id}")->assertForbidden();
        $this->actingAs($this->u('reza_archive'))->postJson("/chat/{$c->id}", ['body' => 'halo'])->assertForbidden();
    }

    public function test_cart_multi_item_checkout_with_voucher_and_pay_all(): void
    {
        $buyer = $this->u('dimas_novan');
        $this->actingAs($buyer);
        $carhartt = $this->l('Carhartt'); $stussy = $this->l('Stussy World'); $polo = $this->l('Polo Ralph');
        foreach ([$carhartt, $stussy, $polo] as $i) $this->post("/keranjang/{$i->id}")->assertRedirect();
        $this->assertEquals(3, CartItem::where('user_id', $buyer->id)->count());
        $this->post("/keranjang/{$this->l('Levi')->id}")->assertForbidden(); // tanpa harga tetap
        $this->get('/keranjang')->assertOk()->assertSee('Carhartt');
        $this->get('/keranjang/checkout')->assertOk();

        $a = $this->u('archivevtg')->id; $g = $this->u('grunge_vault')->id;
        $this->post('/keranjang/checkout', ['delivery' => [$a => 'ekspedisi', $g => 'ekspedisi'], 'voucher_code' => 'HEMAT25K'])->assertRedirect('/pesanan');

        $txs = Transaction::where('buyer_id', $buyer->id)->whereNotNull('cart_group')->get();
        $this->assertCount(3, $txs);
        $this->assertEquals(0, CartItem::where('user_id', $buyer->id)->count());
        $this->assertEquals(48000, $txs->sum('shipping_cost'));     // ongkir sekali per penjual
        $this->assertEquals(2000, $txs->sum('service_fee'));        // biaya layanan sekali per penjual
        $this->assertEquals(25000, $txs->sum('discount'));
        $this->assertEquals(780000 + 220000 + 540000 + 48000 + 2000 - 25000, $txs->sum('total'));
        $this->assertEquals(1, VoucherRedemption::count());

        $group = $txs->first()->cart_group;
        $this->post("/keranjang/bayar/{$group}")->assertRedirect('/pesanan');
        $this->assertEquals(3, Transaction::where('cart_group', $group)->where('status', 'packed')->where('escrow_status', 'funded')->count());
    }

    public function test_cart_checkout_rejects_cod_outside_radius(): void
    {
        $buyer = $this->u('dimas_novan');
        $thrasher = $this->l('Thrasher'); // penjual Bandung, pembeli Jakarta
        $this->actingAs($buyer)->post("/keranjang/{$thrasher->id}")->assertRedirect();
        $this->post('/keranjang/checkout', ['delivery' => [$thrasher->seller_id => 'cod'], 'meeting_point_id' => [$thrasher->seller_id => 1], 'scheduled_at' => [$thrasher->seller_id => now()->addDays(2)->format('Y-m-d H:i')]])
            ->assertSessionHasErrors('delivery');
        $this->assertEquals(0, Transaction::where('buyer_id', $buyer->id)->count());
    }
}
