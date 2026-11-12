<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Http};
use Illuminate\Support\Str;

/** Login dengan Google (OAuth 2.0 / OpenID Connect, tanpa paket tambahan). */
class GoogleAuthController extends Controller
{
    private function redirectUri(): string
    {
        return config('google.redirect') ?: route('auth.google.callback');
    }

    private function fail(string $message)
    {
        return redirect()->route('login')->withErrors(['login' => $message]);
    }

    public function redirect(Request $request)
    {
        $c = config('google');
        if (empty($c['client_id']) || empty($c['client_secret'])) {
            return $this->fail('Login Google belum dikonfigurasi.');
        }
        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);
        $query = http_build_query([
            'client_id' => $c['client_id'],
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);
        return redirect('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request)
    {
        $expected = $request->session()->pull('google_oauth_state');
        if ($request->filled('error')) return $this->fail('Login Google dibatalkan.');
        if (! $expected || ! hash_equals((string) $expected, (string) $request->query('state'))) {
            return $this->fail('Sesi login Google tidak valid. Silakan coba lagi.');
        }
        $code = (string) $request->query('code');
        if ($code === '') return $this->fail('Google tidak mengirim kode login. Silakan coba lagi.');

        $c = config('google');
        try {
            $token = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'code' => $code, 'client_id' => $c['client_id'], 'client_secret' => $c['client_secret'],
                'redirect_uri' => $this->redirectUri(), 'grant_type' => 'authorization_code',
            ]);
            if (! $token->successful() || ! $token->json('access_token')) return $this->fail('Google menolak permintaan masuk. Silakan coba lagi.');
            $info = Http::withToken($token->json('access_token'))->timeout(10)->get('https://openidconnect.googleapis.com/v1/userinfo');
        } catch (\Throwable $e) {
            report($e);
            return $this->fail('Tidak bisa terhubung ke Google. Silakan coba lagi.');
        }
        if (! $info->successful()) return $this->fail('Gagal membaca data akun Google. Silakan coba lagi.');

        $g = $info->json();
        $sub = (string) ($g['sub'] ?? '');
        $email = strtolower(trim((string) ($g['email'] ?? '')));
        if ($sub === '' || $email === '' || ! filter_var($g['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return $this->fail('Email akun Google belum terverifikasi.');
        }

        $isNew = false;
        $user = User::where('google_id', $sub)->first();
        if (! $user) {
            $existing = User::where('email', $email)->first();
            if ($existing) {
                // Hanya tautkan ke akun yang emailnya sudah terverifikasi (mencegah pembajakan akun yang didaftarkan lebih dulu oleh orang lain).
                if (! $existing->email_verified_at) {
                    return $this->fail('Email ini sudah terdaftar dengan kata sandi tetapi belum diverifikasi. Masuk dengan email dan kata sandi Anda.');
                }
                $existing->forceFill(['google_id' => $sub])->save();
                $user = $existing;
            } else {
                $user = new User();
                $user->forceFill([
                    'name' => Str::limit(trim((string) ($g['name'] ?? '')) ?: strtok($email, '@'), 100, ''),
                    'username' => $this->makeUsername($email), 'email' => $email,
                    'password' => Str::random(40), 'role' => 'user', 'google_id' => $sub, 'email_verified_at' => now(), 'is_active' => true,
                ])->save();
                $isNew = true;
            }
        }
        // Hanya akun yang secara eksplisit dinonaktifkan admin yang ditolak (nilai kosong pada akun yang baru dibuat dianggap aktif).
        if ($user->is_active === false) return $this->fail('Akun ditangguhkan.');

        Auth::login($user, true);
        $request->session()->regenerate();
        if ($isNew) {
            return redirect()->route('profile.edit')->with('success', 'Akun dibuat lewat Google. Lengkapi nomor HP dan area agar bisa bertransaksi.');
        }
        return redirect()->intended(route('dashboard'));
    }

    private function makeUsername(string $email): string
    {
        $base = substr(preg_replace('/[^a-z0-9_]/', '', strtolower((string) strtok($email, '@'))), 0, 20) ?: 'user';
        $name = $base;
        while (User::where('username', $name)->exists()) $name = $base.random_int(100, 99999);
        return $name;
    }
}
