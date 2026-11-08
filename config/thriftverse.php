<?php
// Parameter bisnis ThriftVerse (SRS 2.5 / FR-062). Semua dapat diubah di sini atau lewat .env.
return [
    'require_verified'   => env('THRIFTVERSE_REQUIRE_VERIFIED', false),

    // Rentang harga wajar = harga baru x rasio (min, max) per grade (FR-032)
    'fair_ratio' => [
        'A' => [0.55, 0.70],
        'B' => [0.40, 0.55],
        'C' => [0.25, 0.40],
    ],

    // Tawar harga (FR-073, FR-075, FR-078)
    'bid_max_rounds'      => 5,
    'bid_expiry_hours'    => 24,
    'bid_min_pct'         => 0.30,   // tawaran tidak boleh di bawah 30% harga listing
    'bid_max_active'      => 10,
    'hold_pay_hours'      => 24,     // barang ditahan untuk bayar (ekspedisi)
    'hold_meet_hours'     => 48,     // barang ditahan untuk menjadwalkan ketemuan

    // Barter (FR-082, FR-084)
    'barter_balanced_pct' => 15,
    'barter_topup_pct'    => 30,
    'barter_max_rounds'   => 5,
    'barter_expiry_hours' => 48,

    // Pesanan (FR-044, FR-045)
    'service_fee'         => 1000,   // biaya layanan pembeli (proposal 5.2)
    'cod_cash_fee'        => 0,      // FR-098: biaya layanan COD tunai (0 = gratis)
    'commission_pct'      => 5,
    'shipping_flat'       => 24000,  // simulasi API ongkir
    'ship_within_hours'   => 48,
    'auto_complete_days'  => 5,      // asumsi: estimasi tiba 3 hari + 2x24 jam

    // COD (FR-091, FR-096)
    'cod_default_radius'  => 10,
    'cod_no_show_minutes' => 30,
    'cod_arrival_radius_m'=> 150,
    'cod_code_max_attempts'=> 5,
    'trust_penalty'       => 5,

    'areas' => [  // area, bukan alamat tepat (FR-004)
        'Jakarta Selatan' => [-6.2615, 106.8106],
        'Jakarta Pusat'   => [-6.1865, 106.8341],
        'Jakarta Barat'   => [-6.1683, 106.7588],
        'Bandung'         => [-6.9175, 107.6191],
        'Cimahi'          => [-6.8721, 107.5420],
    ],
];
