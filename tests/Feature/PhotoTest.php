<?php
namespace Tests\Feature;

use App\Models\Listing;
use App\Support\SeedPhotos;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_listings_have_photos_and_urls_are_domain_relative(): void
    {
        $this->seed(DatabaseSeeder::class);
        $l = Listing::where('title', 'like', '%Carhartt%')->firstOrFail();
        $this->assertCount(3, $l->images);
        $this->assertEquals('/images/seed/carhartt-j97-1.jpg', $l->coverUrl());
        foreach (SeedPhotos::MAP as $slug) {
            foreach ([1, 2, 3] as $n) $this->assertFileExists(public_path("images/seed/$slug-$n.jpg"));
        }
        $this->assertEquals('/storage/listings/abc.jpg', Listing::publicUrl('listings/abc.jpg'));
        $this->assertEquals('https://x.test/a.jpg', Listing::publicUrl('https://x.test/a.jpg'));
        $this->assertNull(Listing::publicUrl(null));
        $this->get('/')->assertOk()->assertSee('/images/seed/', false);
    }

    public function test_seed_photos_command_keeps_real_uploads(): void
    {
        $this->seed(DatabaseSeeder::class);
        $a = Listing::where('title', 'like', '%Carhartt%')->firstOrFail();
        $b = Listing::where('title', 'like', '%Nike Heavyweight%')->firstOrFail();
        $a->update(['images' => ['listings/foto-asli.jpg']]);
        $b->update(['images' => []]);
        $this->artisan('thriftverse:seed-photos')->assertSuccessful();
        $this->assertEquals(['listings/foto-asli.jpg'], $a->fresh()->images);
        $this->assertEquals('seed/nike-hoodie-1.jpg', $b->fresh()->images[0]);
    }
}
