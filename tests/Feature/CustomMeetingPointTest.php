<?php
namespace Tests\Feature;

use App\Models\{CodSchedule, Listing, MeetingPoint, Transaction, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Titik temu kustom (lokasi saya / klik peta), ubah foto sebagian, dan pesan error yang jelas. */
class CustomMeetingPointTest extends TestCase
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

    private function payload(array $extra = []): array
    {
        $img = fn ($n) => UploadedFile::fake()->image($n.'.jpg', 600, 600);
        return array_merge(['title' => 'Jaket Uji Titik', 'category' => 'Outerwear', 'current_price' => 300000, 'original_price' => 600000, 'harga_tetap' => 1,
            'photo_front' => $img('a'), 'photo_back' => $img('b'), 'photo_label' => $img('c'), 'photo_detail' => $img('d')], $extra);
    }

    public function test_seller_sets_cod_point_from_current_location(): void
    {
        $this->actingAs($this->u('rayhan'))->get('/jual/baru')->assertOk()->assertSee('Gunakan lokasi saya');
        $this->post('/jual', $this->payload(['bisa_cod' => 1, 'cod_radius_km' => 10, 'custom_lat' => '-6.2300000', 'custom_lng' => '106.8200000',
            'custom_name' => 'Depan Indomaret Tebet', 'custom_address' => 'Jl. Tebet Raya']))->assertRedirect();
        $l = Listing::where('title', 'Jaket Uji Titik')->firstOrFail();
        $this->assertTrue($l->bisa_cod);
        $this->assertEquals('Depan Indomaret Tebet', $l->cod_location);
        $this->assertEqualsWithDelta(-6.23, (float) $l->cod_latitude, 0.0001);
        $this->assertFalse(MeetingPoint::where('name', 'Depan Indomaret Tebet')->firstOrFail()->is_active);

        // titik kustom tidak ikut daftar Safe Meeting Point umum
        $this->get('/jual/baru')->assertOk()->assertDontSee('Depan Indomaret Tebet');
        // saat edit, titik kustom tersimpan dimuat kembali di peta
        $this->get("/jual/{$l->id}/edit")->assertOk()->assertSee('Depan Indomaret Tebet');
    }

    public function test_cod_without_any_point_shows_friendly_error(): void
    {
        $this->actingAs($this->u('rayhan'))->post('/jual', $this->payload(['bisa_cod' => 1]))->assertSessionHasErrors('meeting_point_id');
        $this->assertNull(Listing::where('title', 'Jaket Uji Titik')->first());
    }

    public function test_custom_point_rejected_outside_indonesia(): void
    {
        $this->actingAs($this->u('rayhan'))->post('/jual', $this->payload(['bisa_cod' => 1, 'custom_lat' => '50.1', 'custom_lng' => '8.6']))->assertSessionHasErrors('custom_lat');
        $this->assertNull(Listing::where('title', 'Jaket Uji Titik')->first());
    }

    public function test_buyer_picks_custom_point_within_radius_and_can_repropose(): void
    {
        $item = $this->l('Stussy World Tour'); $buyer = $this->u('dimas_novan');
        $this->actingAs($buyer)->post("/listings/{$item->id}/beli")->assertRedirect();
        $tx = Transaction::latest('id')->first();
        $lat = (float) $item->cod_latitude + 0.003; $lng = (float) $item->cod_longitude + 0.003;
        $when = now()->addDay()->format('Y-m-d\TH:i');

        $this->post("/pesanan/{$tx->id}/checkout", ['delivery_method' => 'cod', 'payment_method' => 'cash', 'scheduled_at' => $when,
            'custom_lat' => $lat, 'custom_lng' => $lng, 'custom_name' => 'Lobi Apartemen Kalibata', 'custom_address' => 'Kalibata City'])->assertRedirect();
        $s = CodSchedule::where('transaction_id', $tx->id)->firstOrFail();
        $this->assertEquals('Lobi Apartemen Kalibata', $s->point->name);
        $this->assertFalse($s->point->is_active);

        $this->post("/jadwal-cod/{$s->id}/usul", ['scheduled_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'custom_lat' => $lat + 0.001, 'custom_lng' => $lng, 'custom_name' => 'Parkiran Mall'])->assertSessionHasNoErrors();
        $this->assertEquals('Parkiran Mall', $s->fresh()->point->name);
    }

    public function test_buyer_custom_point_outside_seller_radius_is_rejected(): void
    {
        $item = $this->l('Stussy World Tour'); $buyer = $this->u('dimas_novan');
        $this->actingAs($buyer)->post("/listings/{$item->id}/beli")->assertRedirect();
        $tx = Transaction::latest('id')->first();
        $this->post("/pesanan/{$tx->id}/checkout", ['delivery_method' => 'cod', 'payment_method' => 'cash', 'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'custom_lat' => (float) $item->cod_latitude + 0.5, 'custom_lng' => (float) $item->cod_longitude])->assertSessionHasErrors('custom_lat');
        $this->assertEquals(0, CodSchedule::where('transaction_id', $tx->id)->count());
    }

    public function test_edit_replaces_only_chosen_photo_and_deletes_old_file(): void
    {
        $this->actingAs($this->u('rayhan'));
        $this->post('/jual', $this->payload())->assertRedirect();
        $l = Listing::where('title', 'Jaket Uji Titik')->firstOrFail();
        $old = $l->images;
        $this->assertCount(4, $old);
        Storage::disk('public')->assertExists($old[1]);
        $this->get("/jual/{$l->id}/edit")->assertOk()->assertSee('ganti sebagian');

        $this->put("/jual/{$l->id}", ['title' => $l->title, 'category' => $l->category, 'current_price' => 300000, 'status' => 'active', 'harga_tetap' => 1,
            'photo_back' => UploadedFile::fake()->image('baru.jpg', 600, 600)])->assertRedirect(route('listings.mine'));
        $new = $l->fresh()->images;
        $this->assertCount(4, $new);
        $this->assertEquals($old[0], $new[0]);
        $this->assertEquals($old[2], $new[2]);
        $this->assertNotEquals($old[1], $new[1]);
        Storage::disk('public')->assertMissing($old[1]);
        Storage::disk('public')->assertExists($new[1]);
    }

    public function test_grade_c_cannot_remove_its_last_minus_photo(): void
    {
        $t = $this->l('Thrasher');
        $this->assertNotEmpty($t->minus_photos);
        $this->actingAs($this->u('bdg_thrift'))->put("/jual/{$t->id}", ['title' => $t->title, 'category' => $t->category, 'current_price' => 300000, 'status' => 'active',
            'harga_tetap' => 1, 'remove_minus' => $t->minus_photos])->assertSessionHasErrors('minus_photos');
        $this->assertNotEmpty($t->fresh()->minus_photos);
    }

    public function test_reserved_listing_shows_message_instead_of_422_page(): void
    {
        $n = $this->l('Nike Heavyweight');
        $n->update(['status' => 'reserved']);
        $this->actingAs($this->u('rayhan'))->put("/jual/{$n->id}", ['title' => $n->title, 'category' => $n->category, 'current_price' => 300000, 'status' => 'active', 'harga_tetap' => 1])
            ->assertSessionHasErrors('status');
    }
}
