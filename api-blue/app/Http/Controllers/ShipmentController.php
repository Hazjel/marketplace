<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Support\IndonesianCities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShipmentController extends Controller
{
    private string $baseUrl = 'https://rajaongkir.komerce.id/api/v1';

    public function destination(Request $request)
    {
        $request->validate(
            ['keyword' => 'required|string|min:2|max:100'],
            ['keyword.min' => 'Kata kunci pencarian minimal 2 huruf.']
        );

        $keyword = trim($request->keyword);

        try {
            $results = $this->searchDestinations($keyword);

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
                    $results = array_merge($results, array_slice($this->searchDestinations($city), 0, 12));
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

            return response()->json(['meta' => ['code' => 200, 'status' => 'success'], 'data' => $results]);
        } catch (\RuntimeException $e) {
            return ResponseHelper::jsonResponse(false, 'Layanan pengiriman tidak tersedia saat ini.', null, 503);
        } catch (\Exception $e) {
            Log::error('Shipment destination search failed', ['error' => $e->getMessage()]);

            return ResponseHelper::jsonResponse(false, 'Gagal mencari destinasi pengiriman.', null, 500);
        }
    }

    /**
     * Query destinasi ke Komerce dengan cache 24 jam (data wilayah statis).
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchDestinations(string $search): array
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

    private const COURIERS = 'jne:sicepat:jnt:anteraja:pos:tiki';

    public function calculate(Request $request)
    {
        $request->validate([
            'shipper_destination_id' => 'required|integer',
            'receiver_destination_id' => 'required|integer',
            'item_value' => 'required|numeric|min:0',
            'weight' => 'required|numeric|min:0.01',
            // Nama kota alamat penerima — dipakai sebagai fallback pencarian
            // kalau receiver_destination_id (level kecamatan) ditolak Komerce.
            'receiver_city_name' => 'nullable|string|max:100',
        ]);

        // Berat produk tersimpan dalam kg; Komerce butuh gram
        $grams = max(100, (int) round($request->weight * 1000));

        $cacheKey = sprintf(
            'komerce_cost:%s:%s:%d',
            $request->shipper_destination_id,
            $request->receiver_destination_id,
            $grams
        );

        try {
            $couriers = Cache::remember($cacheKey, now()->addHour(), function () use ($request, $grams) {
                $couriers = $this->fetchCourierCosts(
                    $request->shipper_destination_id,
                    $request->receiver_destination_id,
                    $grams
                );

                // Fallback: Komerce kadang menolak ID level kecamatan/kelurahan
                // hasil pencarian mereka sendiri ("Origin or Destination not
                // found") walau ID itu valid di endpoint pencarian destinasi --
                // inkonsistensi data di sisi mereka. Coba lagi pakai ID kota
                // (level lebih umum) hasil pencarian ulang nama kotanya.
                if (empty($couriers) && $request->filled('receiver_city_name')) {
                    $fallbackId = $this->findCityLevelDestinationId($request->receiver_city_name);

                    if ($fallbackId !== null && $fallbackId !== (int) $request->receiver_destination_id) {
                        Log::info('Komerce calculate fallback ke city-level destination', [
                            'original_destination' => $request->receiver_destination_id,
                            'fallback_destination' => $fallbackId,
                        ]);

                        $couriers = $this->fetchCourierCosts(
                            $request->shipper_destination_id,
                            $fallbackId,
                            $grams
                        );
                    }
                }

                return $couriers;
            });

            return response()->json([
                'meta' => ['code' => 200, 'status' => 'success'],
                'data' => ['calculate_reguler' => $couriers],
            ]);
        } catch (\RuntimeException $e) {
            return ResponseHelper::jsonResponse(false, 'Layanan pengiriman tidak tersedia saat ini.', null, 503);
        } catch (\Exception $e) {
            Log::error('Shipment calculate failed', ['error' => $e->getMessage()]);

            return ResponseHelper::jsonResponse(false, 'Gagal menghitung ongkir.', null, 500);
        }
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
            ->post($this->baseUrl.'/calculate/district/domestic-cost', [
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
        $results = $this->searchDestinations($cityName);

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

    public function geocode(Request $request)
    {
        $request->validate(['city' => 'required|string|max:100']);

        $city = trim($request->city);
        $cacheKey = 'geocode:'.md5(mb_strtolower($city));

        try {
            $result = Cache::remember($cacheKey, now()->addDays(7), function () use ($city) {
                $response = Http::timeout(10)
                    ->withHeaders(['User-Agent' => 'Marketplace/1.0 (contact@marketplace.id)'])
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q' => $city.', Indonesia',
                        'format' => 'json',
                        'limit' => 1,
                    ]);

                $data = $response->json();

                if (empty($data)) {
                    return ['lat' => null, 'lon' => null];
                }

                return ['lat' => $data[0]['lat'], 'lon' => $data[0]['lon']];
            });

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Geocoding failed', ['error' => $e->getMessage()]);

            return response()->json(['lat' => null, 'lon' => null]);
        }
    }

    public function reverseGeocode(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lon' => 'required|numeric|between:-180,180',
        ]);

        // Bulatkan 4 desimal (~11m) supaya cache hit lebih sering
        $lat = round((float) $request->lat, 4);
        $lon = round((float) $request->lon, 4);
        $cacheKey = "reverse_geocode:{$lat},{$lon}";

        try {
            $result = Cache::remember($cacheKey, now()->addDays(7), function () use ($lat, $lon) {
                $response = Http::timeout(10)
                    ->withHeaders(['User-Agent' => 'Marketplace/1.0 (contact@marketplace.id)'])
                    ->get('https://nominatim.openstreetmap.org/reverse', [
                        'lat' => $lat,
                        'lon' => $lon,
                        'format' => 'json',
                        'zoom' => 18,
                        'addressdetails' => 1,
                    ]);

                $data = $response->json();

                if (empty($data) || isset($data['error'])) {
                    return null;
                }

                $addr = $data['address'] ?? [];

                return [
                    'display_name' => $data['display_name'] ?? null,
                    'road' => $addr['road'] ?? null,
                    'suburb' => $addr['suburb'] ?? $addr['village'] ?? null,
                    'city' => $addr['city'] ?? $addr['town'] ?? $addr['county'] ?? null,
                    'state' => $addr['state'] ?? null,
                    'postal_code' => $addr['postcode'] ?? null,
                ];
            });

            if ($result === null) {
                return ResponseHelper::jsonResponse(false, 'Alamat tidak ditemukan untuk lokasi ini.', null, 404);
            }

            return ResponseHelper::jsonResponse(true, 'Alamat ditemukan.', $result, 200);
        } catch (\Exception $e) {
            Log::error('Reverse geocoding failed', ['error' => $e->getMessage()]);

            return ResponseHelper::jsonResponse(false, 'Gagal mengambil alamat dari lokasi.', null, 500);
        }
    }
}
