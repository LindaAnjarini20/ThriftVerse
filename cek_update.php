<?php
// Cek apakah semua file update fitur sudah terpasang dengan benar.
// Jalankan dari folder proyek:  php cek_update.php
$expected = [
    'routes/web.php' => 8586,
    'README.md' => 5751,
    'app/Console/Commands/Housekeeping.php' => 2040,
    'app/Http/Controllers/Admin/VoucherAdminController.php' => 1566,
    'app/Http/Controllers/CartController.php' => 7605,
    'app/Http/Controllers/ChatController.php' => 3378,
    'app/Http/Controllers/ListingController.php' => 9099,
    'app/Http/Controllers/OrderController.php' => 10123,
    'app/Http/Controllers/VoucherController.php' => 980,
    'app/Models/CartItem.php' => 314,
    'app/Models/Conversation.php' => 824,
    'app/Models/Listing.php' => 4191,
    'app/Models/Message.php' => 812,
    'app/Models/Transaction.php' => 3028,
    'app/Models/Voucher.php' => 855,
    'app/Models/VoucherRedemption.php' => 177,
    'app/Providers/AppServiceProvider.php' => 1325,
    'app/Services/OrderService.php' => 6950,
    'app/Services/PriceDropService.php' => 1759,
    'app/Services/VoucherService.php' => 1758,
    'database/migrations/2026_11_09_000001_add_cart_chat_voucher_autodrop.php' => 3848,
    'database/seeders/DatabaseSeeder.php' => 9657,
    'resources/views/admin/vouchers.blade.php' => 3230,
    'resources/views/cart/checkout.blade.php' => 5637,
    'resources/views/cart/index.blade.php' => 2393,
    'resources/views/chat/index.blade.php' => 1535,
    'resources/views/chat/show.blade.php' => 3774,
    'resources/views/cod/show.blade.php' => 9491,
    'resources/views/layouts/admin.blade.php' => 2331,
    'resources/views/layouts/app.blade.php' => 8104,
    'resources/views/listings/form.blade.php' => 10518,
    'resources/views/listings/show.blade.php' => 9583,
    'resources/views/orders/checkout.blade.php' => 8959,
    'resources/views/orders/index.blade.php' => 2736,
    'resources/views/orders/show.blade.php' => 8400,
    'resources/views/partials/meeting-map.blade.php' => 3690,
    'tests/Feature/NewFeaturesTest.php' => 9526,
];
$bad = 0;
foreach ($expected as $file => $size) {
    $path = __DIR__.'/'.$file;
    if (! file_exists($path)) { echo "HILANG  $file\n"; $bad++; }
    elseif (filesize($path) !== $size) { echo "BEDA    $file (ada ".filesize($path)." byte, seharusnya $size)\n"; $bad++; }
}
echo $bad ? "\n$bad file bermasalah. Ekstrak ulang zip update dengan 7-Zip (Replace all), atau salin file di atas secara manual.\n" : "Semua ".count($expected)." file update sudah benar.\n";
