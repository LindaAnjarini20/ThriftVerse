<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        return view('auth.login', ['tab' => $request->query('tab', 'login')]);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['login' => 'required|string', 'password' => 'required|string']);
        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $login = $field === 'username' ? ltrim($data['login'], '@') : $data['login'];

        if (Auth::attempt([$field => $login, 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }
        return back()->withErrors(['login' => 'Email/username atau kata sandi tidak sesuai, atau akun ditangguhkan.'])->onlyInput('login');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'username' => 'nullable|alpha_dash|max:30|unique:users,username',
            'email' => 'required|email|max:150|unique:users,email',
            'no_hp' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);
        $user = User::create([
            'name' => $data['name'], 'username' => $data['username'] ?? null, 'email' => $data['email'],
            'no_hp' => $data['no_hp'] ?? null, 'password' => $data['password'], 'role' => 'user',
        ]);
        event(new Registered($user));
        Auth::login($user);
        return redirect()->route('profile.edit')->with('success', 'Akun dibuat. Lengkapi area dan mode penjual agar bisa bertransaksi.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
