<?php
namespace App\Http\Middleware;

use App\Models\{CodSchedule, Transaction};
use App\Services\MeetingPointService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Jika formulir membawa custom_lat/custom_lng (titik yang dipilih di peta), buat titik temu kustom
 * lalu isikan meeting_point_id, sehingga controller yang ada (listing, checkout, jadwal COD) bekerja tanpa diubah.
 */
class ResolveCustomMeetingPoint
{
    public function handle(Request $request, Closure $next)
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true) || ! $request->user()
            || ! $request->filled('custom_lat') || ! $request->filled('custom_lng')) {
            return $next($request);
        }
        // Abaikan bila pengguna sudah beralih ke metode lain (mis. ekspedisi atau COD dimatikan).
        if ($request->has('delivery_method') && $request->input('delivery_method') !== 'cod') return $next($request);
        if ($request->routeIs('listings.store', 'listings.update') && ! $request->boolean('bisa_cod')) return $next($request);

        $lat = $request->input('custom_lat'); $lng = $request->input('custom_lng');
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            throw ValidationException::withMessages(['custom_lat' => 'Koordinat titik temu tidak valid.']);
        }
        $lat = (float) $lat; $lng = (float) $lng;
        if (! MeetingPointService::inIndonesia($lat, $lng)) {
            throw ValidationException::withMessages(['custom_lat' => 'Titik temu harus berada di wilayah Indonesia.']);
        }

        $route = $request->route();
        $listing = null;
        if ($route) {
            $tx = $route->parameter('transaction'); $sch = $route->parameter('schedule');
            if ($tx instanceof Transaction) $listing = $tx->listing;
            elseif ($sch instanceof CodSchedule) $listing = Transaction::find($sch->transaction_id)?->listing;
        }
        if ($listing) MeetingPointService::assertWithinCod($listing, $lat, $lng);

        $mp = MeetingPointService::custom($request->user(), $lat, $lng, $request->input('custom_name'), $request->input('custom_address'));
        $request->merge(['meeting_point_id' => $mp->id]);
        return $next($request);
    }
}
