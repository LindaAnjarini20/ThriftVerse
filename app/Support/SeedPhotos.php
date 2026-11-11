<?php
namespace App\Support;

use App\Models\Listing;

/** Foto sampel (ilustrasi) untuk listing demo. File ada di public/images/seed dan ikut ter-deploy. */
class SeedPhotos
{
    /** potongan judul listing => nama dasar file */
    public const MAP = [
        'Carhartt' => 'carhartt-j97',
        'Nike Heavyweight' => 'nike-hoodie',
        "Big E Redline" => 'levis-501',
        'Stussy World Tour' => 'stussy-tee',
        'Dr. Martens' => 'dr-martens-1461',
        'Polo Ralph' => 'polo-jacket',
        'Nirvana' => 'nirvana-tee',
        'Mohair' => 'stussy-cardigan',
        'Thrasher' => 'thrasher-hoodie',
    ];

    /** Pasang foto sampel ke listing yang belum punya foto (atau yang fotonya masih sampel). Foto unggahan penjual tidak disentuh. */
    public static function apply(): int
    {
        $n = 0;
        foreach (self::MAP as $needle => $slug) {
            foreach (Listing::where('title', 'like', "%$needle%")->get() as $l) {
                $imgs = $l->images ?? [];
                $isSeedOrEmpty = count($imgs) === 0 || collect($imgs)->every(fn ($i) => str_starts_with((string) $i, 'seed/'));
                if (! $isSeedOrEmpty) continue;
                $photos = ["seed/$slug-1.jpg", "seed/$slug-2.jpg", "seed/$slug-3.jpg"];
                $data = ['images' => $photos];
                if ($l->condition_grade === 'C' && count($l->minus_photos ?? []) === 0) $data['minus_photos'] = ["seed/$slug-3.jpg"];
                $l->update($data);
                $n++;
            }
        }
        return $n;
    }
}
