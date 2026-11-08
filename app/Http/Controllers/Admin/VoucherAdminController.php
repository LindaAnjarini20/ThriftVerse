<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherAdminController extends Controller
{
    public function index()
    {
        $vouchers = Voucher::withCount('redemptions')->latest('id')->get();
        return view('admin.vouchers', compact('vouchers'));
    }

    public function store(Request $request)
    {
        $request->merge(['code' => strtoupper(trim((string) $request->code))]);
        $data = $request->validate([
            'code' => 'required|string|max:40|regex:/^[A-Z0-9_-]+$/|unique:vouchers,code',
            'description' => 'nullable|string|max:150',
            'type' => 'required|in:percent,fixed',
            'value' => ['required', 'numeric', 'min:1', $request->type === 'percent' ? 'max:100' : 'max:10000000'],
            'min_spend' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:1',
            'quota' => 'nullable|integer|min:1',
            'ends_at' => 'nullable|date|after:now',
        ]);
        $data['min_spend'] = $data['min_spend'] ?? 0;
        if ($data['type'] !== 'percent') $data['max_discount'] = null;
        Voucher::create($data + ['is_active' => true]);
        return back()->with('success', "Voucher {$data['code']} dibuat.");
    }

    public function toggle(Voucher $voucher)
    {
        $voucher->update(['is_active' => ! $voucher->is_active]);
        return back()->with('success', $voucher->is_active ? 'Voucher diaktifkan.' : 'Voucher dinonaktifkan.');
    }
}
