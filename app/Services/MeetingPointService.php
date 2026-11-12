<?php
namespace App\Services;

use App\Models\{Listing, MeetingPoint, User};
use Illuminate\Validation\ValidationException;

/** Titik temu kustom: lokasi yang dipilih pengguna sendiri (lokasi saat ini / klik di peta). */
class MeetingPointService
{
    public static function inIndonesia(float $lat, float $lng): bool
    {
        return $lat >= -11.5 && $lat <= 6.5 && $lng >= 94.5 && $lng <= 141.5;
    }

    /** Titik pilihan harus berada dalam radius COD yang ditetapkan penjual pada listing. */
    public static function assertWithinCod(Listing $l, float $lat, float $lng): void
    {
        if (! $l->cod_latitude || ! $l->cod_longitude) return;
        $km = OrderService::distanceKm((float) $l->cod_latitude, (float) $l->cod_longitude, $lat, $lng);
        $max = (float) ($l->cod_radius_km ?: 10);
        if ($km > $max) {
            throw ValidationException::withMessages(['custom_lat' => sprintf('Titik pilihan berjarak %.1f km dari titik COD penjual, melebihi radius %d km. Pilih titik yang lebih dekat.', $km, $max)]);
        }
    }

    /**
     * Membuat (atau memakai ulang) titik temu kustom. Titik kustom dibuat nonaktif (is_active = false)
     * supaya tidak muncul di daftar Safe Meeting Point umum, tetapi tetap bisa dipakai jadwal COD.
     */
    public static function custom(User $u, float $lat, float $lng, ?string $name, ?string $address): MeetingPoint
    {
        $name = mb_substr(trim((string) $name) ?: 'Titik temu '.$u->name, 0, 100);
        $address = mb_substr(trim((string) $address) ?: sprintf('Koordinat %.5f, %.5f', $lat, $lng), 0, 200);
        return MeetingPoint::firstOrCreate(
            ['name' => $name, 'address' => $address, 'latitude' => round($lat, 7), 'longitude' => round($lng, 7), 'is_active' => false],
            ['city' => mb_substr((string) ($u->area ?: 'Indonesia'), 0, 60), 'features' => ['Titik kustom (bukan Safe Meeting Point terverifikasi)'], 'recommended' => false]
        );
    }
}
