<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransService
{
    public static function snapToken(Transaction $transaction): string
    {
        $serverKey = config('services.midtrans.server_key');
        if (! $serverKey) {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum dikonfigurasi.');
        }

        $items = [[
            'id' => (string) $transaction->listing_id,
            'price' => (int) round($transaction->agreed_price),
            'quantity' => 1,
            'name' => mb_substr($transaction->listing->title, 0, 50),
        ]];
        if ($transaction->shipping_cost > 0) {
            $items[] = ['id' => 'shipping', 'price' => (int) round($transaction->shipping_cost), 'quantity' => 1, 'name' => 'Ongkos kirim'];
        }
        if ($transaction->service_fee > 0) {
            $items[] = ['id' => 'service-fee', 'price' => (int) round($transaction->service_fee), 'quantity' => 1, 'name' => 'Biaya layanan'];
        }
        if ($transaction->discount > 0) {
            $items[] = ['id' => 'discount', 'price' => -((int) round($transaction->discount)), 'quantity' => 1, 'name' => 'Diskon voucher'];
        }

        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->post(self::baseUrl().'/snap/v1/transactions', [
                'transaction_details' => [
                    'order_id' => $transaction->transaction_number,
                    'gross_amount' => (int) round($transaction->total),
                ],
                'item_details' => $items,
                'customer_details' => [
                    'first_name' => $transaction->buyer->name,
                    'email' => $transaction->buyer->email,
                ],
            ]);

        $response->throw();
        $token = $response->json('token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Midtrans tidak mengembalikan Snap token.');
        }

        return $token;
    }

    public static function verifyNotification(array $notification): bool
    {
        $serverKey = config('services.midtrans.server_key');
        $signature = hash('sha512', ($notification['order_id'] ?? '')
            .($notification['status_code'] ?? '')
            .($notification['gross_amount'] ?? '')
            .$serverKey);

        return hash_equals($signature, (string) ($notification['signature_key'] ?? ''));
    }

    private static function baseUrl(): string
    {
        return config('services.midtrans.is_production')
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
    }
}
