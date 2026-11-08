<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit() { return view('profile.edit', ['areas' => array_keys(config('thriftverse.areas'))]); }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'username' => ['nullable', 'alpha_dash', 'max:30', Rule::unique('users', 'username')->ignore($user->id)],
            'no_hp' => 'nullable|string|max:20',
            'area' => ['nullable', Rule::in(array_keys(config('thriftverse.areas')))],
            'address' => 'nullable|string|max:255',
            'payout_account' => 'nullable|string|max:100',
        ]);
        if (! empty($data['area'])) [$data['area_lat'], $data['area_lng']] = config('thriftverse.areas')[$data['area']];
        $data['seller_mode'] = $request->boolean('seller_mode');
        if ($data['seller_mode'] && empty($data['payout_account'] ?? $user->payout_account)) {
            return back()->withErrors(['payout_account' => 'Isi rekening/dompet pencairan untuk mengaktifkan Mode Penjual.'])->withInput();
        }
        $user->update($data);
        return back()->with('success', 'Profil diperbarui.');
    }

    public function password(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|current_password', 'password' => 'required|min:8|confirmed']);
        $request->user()->update(['password' => $data['password']]);
        return back()->with('success', 'Kata sandi diganti.');
    }
}
