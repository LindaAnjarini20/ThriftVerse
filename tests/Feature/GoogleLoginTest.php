<?php
namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['google.client_id' => 'cid', 'google.client_secret' => 'sec', 'google.redirect' => 'http://localhost/auth/google/callback']);
    }

    private function fakeGoogle(array $info): void
    {
        // Http::fake menumpuk jawaban tiruan (yang pertama menang), jadi reset dulu agar tiap pemanggilan berlaku.
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok'], 200),
            'openidconnect.googleapis.com/*' => Http::response($info, 200),
        ]);
    }

    /** Memulai alur dan mengembalikan nilai state yang dikirim ke Google. */
    private function startFlow(): string
    {
        $res = $this->get('/auth/google');
        $res->assertRedirect();
        $loc = $res->headers->get('Location');
        $this->assertStringContainsString('accounts.google.com', $loc);
        parse_str((string) parse_url($loc, PHP_URL_QUERY), $q);
        $this->assertEquals('cid', $q['client_id']);
        $this->assertEquals('http://localhost/auth/google/callback', $q['redirect_uri']);
        return $q['state'];
    }

    public function test_new_google_user_is_created_and_logged_in(): void
    {
        $this->fakeGoogle(['sub' => 'g-100', 'email' => 'Budi.Baru@example.com', 'email_verified' => true, 'name' => 'Budi Baru']);
        $state = $this->startFlow();
        $this->get('/auth/google/callback?code=abc&state='.$state)->assertRedirect(route('profile.edit'));
        $this->assertAuthenticated();
        $u = User::where('google_id', 'g-100')->firstOrFail();
        $this->assertEquals('budi.baru@example.com', $u->email);
        $this->assertNotNull($u->email_verified_at);
        $this->assertEquals('user', $u->role);
        $this->assertEquals('budibaru', $u->username);

        // login kedua dengan akun Google yang sama -> langsung ke dashboard, tanpa akun ganda
        $this->post('/logout');
        $state = $this->startFlow();
        $this->get('/auth/google/callback?code=abc&state='.$state)->assertRedirect(route('dashboard'));
        $this->assertEquals(1, User::where('google_id', 'g-100')->count());
    }

    public function test_existing_verified_account_is_linked(): void
    {
        $rayhan = User::where('email', 'rayhan@thriftverse.app')->firstOrFail();
        $this->fakeGoogle(['sub' => 'g-200', 'email' => 'rayhan@thriftverse.app', 'email_verified' => true, 'name' => 'Rayhan']);
        $state = $this->startFlow();
        $this->get('/auth/google/callback?code=abc&state='.$state)->assertRedirect();
        $this->assertAuthenticatedAs($rayhan);
        $this->assertEquals('g-200', $rayhan->fresh()->google_id);
    }

    public function test_unverified_local_account_is_not_taken_over(): void
    {
        User::create(['name' => 'Korban', 'email' => 'korban@example.com', 'password' => 'rahasia123', 'role' => 'user']);
        $this->fakeGoogle(['sub' => 'g-300', 'email' => 'korban@example.com', 'email_verified' => true, 'name' => 'Korban']);
        $state = $this->startFlow();
        $this->get('/auth/google/callback?code=abc&state='.$state)->assertRedirect(route('login'))->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->assertNull(User::where('email', 'korban@example.com')->first()->google_id);
    }

    public function test_invalid_state_unverified_email_and_suspended_are_rejected(): void
    {
        $this->fakeGoogle(['sub' => 'g-400', 'email' => 'x@example.com', 'email_verified' => true, 'name' => 'X']);
        $this->startFlow();
        $this->get('/auth/google/callback?code=abc&state=salah')->assertRedirect(route('login'))->assertSessionHasErrors('login');
        $this->get('/auth/google/callback?code=abc&state=salah')->assertSessionHasErrors('login'); // state sekali pakai
        $this->assertGuest();

        $this->fakeGoogle(['sub' => 'g-401', 'email' => 'y@example.com', 'email_verified' => false, 'name' => 'Y']);
        $state = $this->startFlow();
        $this->get('/auth/google/callback?code=abc&state='.$state)->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->assertNull(User::where('email', 'y@example.com')->first());

        $dimas = User::where('email', 'dimas@thriftverse.app')->firstOrFail();
        \Illuminate\Support\Facades\DB::table('users')->where('id', $dimas->id)->update(['is_active' => false, 'google_id' => 'g-402']);
        $this->fakeGoogle(['sub' => 'g-402', 'email' => 'dimas@thriftverse.app', 'email_verified' => true, 'name' => 'Dimas']);
        $state = $this->startFlow();
        $this->get('/auth/google/callback?code=abc&state='.$state)->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_not_configured_shows_error_and_disabled_button(): void
    {
        config(['google.client_id' => null, 'google.client_secret' => null]);
        $this->get('/auth/google')->assertRedirect(route('login'))->assertSessionHasErrors('login');
        $this->get('/login')->assertOk()->assertSee('belum dikonfigurasi');
    }
}
