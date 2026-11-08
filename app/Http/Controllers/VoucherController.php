<?php
namespace App\Http\Controllers;

use App\Services\VoucherService;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    /** Pratinjau potongan di checkout. Validasi final tetap dilakukan saat pesanan dibuat. */
    public function check(Request $request)
    {
        $request->validate(['code' => 'required|string|max:40', 'amount' => 'required|numeric|min:0']);
        $v = VoucherService::find($request->code);
        if (! $v) return response()->json(['ok' => false, 'message' => 'Kode voucher tidak ditemukan.', 'discount' => 0]);
        $err = VoucherService::check($v, auth()->user(), (float) $request->amount);
        if ($err) return response()->json(['ok' => false, 'message' => $err, 'discount' => 0]);
        $d = VoucherService::discount($v, (float) $request->amount);
        return response()->json(['ok' => true, 'message' => "Voucher {$v->code} terpasang: hemat Rp".number_format($d, 0, ',', '.'), 'discount' => $d]);
    }
}
