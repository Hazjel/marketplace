<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Midtrans Iris (payout). SDK PHP Midtrans tidak punya klien Iris, jadi
 * dipanggil langsung. Kode exception mengikuti RefundCancelledTransactionJob:
 * 0 (jaringan) atau >= 500 boleh dicoba lagi, 4xx permanen.
 */
class IrisPayoutGateway
{
    public function isConfigured(): bool
    {
        return (string) config('midtrans.irisKey') !== '';
    }

    /** @return list<array{code: string, name: string}> */
    public function banks(): array
    {
        return Cache::remember('iris:beneficiary_banks', now()->addDay(), function () {
            $banks = $this->request('GET', '/api/v1/beneficiary_banks')['beneficiary_banks'] ?? null;

            // Lempar, bukan cache: daftar kosong/rusak akan tersimpan sehari.
            if (! is_array($banks)) {
                throw new RuntimeException('Iris beneficiary_banks tidak valid', 502);
            }

            return array_map(function ($bank) {
                if (! is_string($bank['code'] ?? null) || ! is_string($bank['name'] ?? null)) {
                    throw new RuntimeException('Iris beneficiary_banks tidak valid', 502);
                }

                return ['code' => $bank['code'], 'name' => $bank['name']];
            }, array_values($banks));
        });
    }

    /** Nama pemilik rekening, atau null bila Iris menyatakan rekeningnya tidak valid. */
    public function validateAccount(string $bankCode, string $accountNumber): ?string
    {
        try {
            $body = $this->request('GET', '/api/v1/account_validation', [
                'bank' => $bankCode,
                'account' => $accountNumber,
            ]);
        } catch (RuntimeException $e) {
            // Kunci salah / rate limit bukan berarti rekeningnya salah.
            $code = $e->getCode();
            if ($code >= 400 && $code < 500 && ! in_array($code, [401, 403, 429], true)) {
                return null;
            }

            throw $e;
        }

        return $body['account_name'] ?? null;
    }

    /**
     * @param  string  $idempotencyKey  id baris Payout: percobaan ulang setelah
     *                                  timeout/5xx tidak membuat transfer kedua
     * @return string reference_no dari Iris
     */
    public function createPayout(string $name, string $account, string $bankCode, string $amount, string $notes, string $idempotencyKey): string
    {
        $body = $this->request('POST', '/api/v1/payouts', ['payouts' => [[
            'beneficiary_name' => $name,
            'beneficiary_account' => $account,
            'beneficiary_bank' => $bankCode,
            'amount' => $amount,
            // Iris menolak notes yang panjang atau bersimbol.
            'notes' => trim(mb_substr(preg_replace('/[^A-Za-z0-9]+/', ' ', $notes), 0, 100)),
        ]]], ['X-Idempotency-Key' => $idempotencyKey]);

        $referenceNo = $body['payouts'][0]['reference_no'] ?? null;
        if (! is_string($referenceNo) || $referenceNo === '') {
            throw new RuntimeException('Iris tidak mengembalikan reference_no');
        }

        return $referenceNo;
    }

    /** @return array<string, mixed> termasuk status (queued|approved|processed|completed|failed|rejected) */
    public function payoutDetail(string $referenceNo): array
    {
        return $this->request('GET', '/api/v1/payouts/'.rawurlencode($referenceNo));
    }

    public function balance(): string
    {
        $balance = $this->request('GET', '/api/v1/balance')['balance'] ?? null;

        if (! is_numeric($balance)) {
            throw new RuntimeException('Iris balance tidak valid', 502);
        }

        return (string) $balance;
    }

    /** @return array<string, mixed> */
    private function request(string $method, string $path, array $data = [], array $headers = []): array
    {
        $http = Http::withBasicAuth((string) config('midtrans.irisKey'), '')
            ->withHeaders($headers)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(30);
        $url = rtrim((string) config('midtrans.irisBaseUrl'), '/').$path;

        try {
            $response = $method === 'POST' ? $http->post($url, $data) : $http->get($url, $data);
        } catch (ConnectionException) {
            // Pesan aslinya memuat URL beserta query (nomor rekening): tidak dibawa.
            throw new RuntimeException('Iris tidak dapat dihubungi', 0);
        }

        if ($response->failed()) {
            $error = $response->json('error_message');

            throw new RuntimeException(
                'Iris HTTP '.$response->status().(is_string($error) ? ': '.mb_substr($error, 0, 200) : ''),
                $response->status()
            );
        }

        return (array) $response->json();
    }
}
