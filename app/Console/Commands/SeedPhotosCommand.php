<?php
namespace App\Console\Commands;

use App\Support\SeedPhotos;
use Illuminate\Console\Command;

class SeedPhotosCommand extends Command
{
    protected $signature = 'thriftverse:seed-photos';
    protected $description = 'Pasang foto sampel ke listing demo yang belum punya foto (tanpa menghapus data)';

    public function handle(): int
    {
        $this->info(SeedPhotos::apply().' listing diberi foto sampel.');
        return self::SUCCESS;
    }
}
