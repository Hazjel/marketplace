<?php

namespace App\Services;

use App\Interfaces\ShippingGatewayInterface;
use App\Support\IndonesianCities;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KomerceShippingGateway implements ShippingGatewayInterface
{
    private string $baseUrl = 'https://rajaongkir.komerce.id/api/v1';

    private const COURIERS = 'jne:sicepat:jnt:anteraja:pos:tiki';

    public function searchDestinations(string $keyword): array
    {
        $results = $this->fetchDestinations($keyword);

        // Komerce hanya match kata utuh — keyword pendek sering kosong.
        // Ekspansi: cocokkan sebagai prefix nama kota, gabungkan hasilnya
        // supaya "ban" mengeluarkan Bandung + Banjarmasin + Banyuwangi dst.
        if (count($results) < 10) {
            foreach (IndonesianCities::matchPrefix($keyword, 5) as $city) {
                if (mb_strtolower($city) === mb_strtolower($keyword)) {
                    continue; // sudah dicari langsung
                }
                // Maks 12 baris per kota supaya hasil beragam, tidak
                // didominasi kota pertama yang cocok
                $results = array_merge($results, array_slice($this->fetchDestinations($city), 0, 12));
            }

            // Dedup berdasarkan id, batasi 50
            $seen = [];
            $results = array_values(array_filter($results, function ($row) use (&$seen) {
                $id = $row['id'] ?? null;
                if ($id === null || isset($seen[$id])) {
                    return false;
                }
                $seen[$id] = true;

                return true;
            }));
            $results = array_slice($results, 0, 50);
        }

        return $results;
    }

    public function calculateCosts(int $originId, int $destinationId, int $weightGrams, ?string $destinationCityName = null): array
    {
        $cacheKey = sprintf('komerce_cost:%s:%s:%d', $originId, $destinationId, $weightGrams);

        return Cache::remember($cacheKey, now()->addHour(), function () use ($originId, $destinationId, $weightGrams, $destinationCityName) {
            $couriers = $this->fetchCourierCosts($originId, $destinationId, $weightGrams);

            // Fallback: Komerce kadang menolak ID level kecamatan/kelurahan
            // hasil pencarian mereka sendiri ("Origin or Destination not
            // found") walau ID itu valid di endpoint pencarian destinasi --
            // inkonsistensi data di sisi mereka. Coba lagi pakai ID kota
            // (level lebih umum) hasil pencarian ulang nama kotanya.
            if (empty($couriers) && $destinationCityName) {
                $fallbackId = $this->findCityLevelDestinationId($destinationCityName);

                if ($fallbackId !== null && $fallbackId !== $destinationId) {
                    Log::info('Komerce calculate fallback ke city-level destination', [
                        'original_destination' => $destinationId,
                        'fallback_destination' => $fallbackId,
                    ]);

                    $couriers = $this->fetchCourierCosts($originId, $fallbackId, $weightGrams);
                }
            }

            return $couriers;
        });
    }

    /**
     * Query destinasi ke Komerce dengan cache 24 jam (data wilayah statis).
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchDestinations(string $search): array
    {
        $cacheKey = 'komerce_dest:'.md5(mb_strtolower($search));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $response = Http::timeout(10)
            ->withHeaders(['key' => config('services.komerce.api_key')])
            ->get($this->baseUrl.'/destination/domestic-destination', [
                'search' => $search,
                'limit' => 50,
                'offset' => 0,
            ]);

        $status = $response->status();
        if ($status === 401 || $status === 403) {
            throw new \RuntimeException('Komerce unauthorized');
        }
        if ($status === 404) {
            Cache::put($cacheKey, [], now()->addHours(24));

            return [];
        }
        // Error sementara upstream (429/5xx): jangan di-cache — kalau tidak,
        // keyword yang dicoba saat gangguan akan kosong 24 jam ke depan
        if (! $response->successful()) {
            Log::warning('Komerce destination non-200', ['status' => $status, 'search' => $search]);

            return [];
        }

        $results = $response->json('data') ?? [];
        Cache::put($cacheKey, $results, now()->addHours(24));

        return $results;
    }

    /**
     * Panggil endpoint calculate Komerce, return list kurir (map ke format lama
     * calculate_reguler) atau array kosong kalau gagal/tidak ditemukan.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchCourierCosts(int $originId, int $destinationId, int $grams): array
    {
        $response = Http::timeout(15)
            ->asForm()
            ->withHeaders(['key' => config('services.komerce.api_key')])
            ->post($this->baseUrl.'/calculate/domestic-cost', [
                'origin' => $originId,
                'destination' => $destinationId,
                'weight' => $grams,
                'courier' => self::COURIERS,
            ]);

        $status = $response->status();
        if ($status === 401 || $status === 403) {
            throw new \RuntimeException('Komerce unauthorized');
        }
        if (! $response->successful()) {
            Log::warning('Komerce calculate non-200', [
                'status' => $status,
                'body' => $response->body(),
                'origin' => $originId,
                'destination' => $destinationId,
            ]);

            return [];
        }

        return collect($response->json('data') ?? [])
            ->map(fn ($row) => [
                'shipping_name' => $row['name'] ?? $row['code'] ?? '-',
                'service_name' => $row['service'] ?? '-',
                'shipping_cost' => $row['cost'] ?? 0,
                'shipping_cost_net' => $row['cost'] ?? 0,
                'etd' => $row['etd'] ?? null,
            ])
            ->sortBy('shipping_cost_net')
            ->values()
            ->all();
    }

    /**
     * Cari ID destinasi level kota (bukan kecamatan/kelurahan) dari nama kota,
     * dipakai sebagai fallback saat ID yang lebih spesifik ditolak Komerce.
     * Level kota lebih mungkin punya cakupan ongkir kurir yang lengkap.
     */
    private function findCityLevelDestinationId(string $cityName): ?int
    {
        $results = $this->fetchDestinations($cityName);

        $cityLevel = collect($results)->first(
            fn ($row) => mb_strtolower($row['city_name'] ?? '') === mb_strtolower($cityName)
                && ($row['subdistrict_name'] ?? '') === '-'
        );

        if ($cityLevel) {
            return (int) $cityLevel['id'];
        }

        // Tidak ada baris murni level kota -- ambil hasil pertama yang
        // city_name-nya cocok, lebih baik daripada tidak fallback sama sekali.
        $anyMatch = collect($results)->first(
            fn ($row) => mb_strtolower($row['city_name'] ?? '') === mb_strtolower($cityName)
        );

        return $anyMatch ? (int) $anyMatch['id'] : null;
    }
}
