<?php

return [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    // contoh: https://domain-anda/auth/google/callback  (harus sama persis dengan yang didaftarkan di Google Cloud Console)
    'redirect' => env('GOOGLE_REDIRECT_URI'),
];
