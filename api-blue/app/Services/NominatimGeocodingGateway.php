<?php

namespace App\Services;

use App\Interfaces\GeocodingGatewayInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NominatimGeocodingGateway implements GeocodingGatewayInterface
{
    public function geocode(string $city): array
    {
        $cacheKey = 'geocode:'.md5(mb_strtolower($city));

        try {
            return Cache::remember($cacheKey, now()->addDays(7), function () use ($city) {
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
        } catch (\Exception $e) {
            Log::error('Geocoding failed', ['error' => $e->getMessage()]);

            return ['lat' => null, 'lon' => null];
        }
    }

    public function reverseGeocode(float $lat, float $lon): ?array
    {
        // Bulatkan 4 desimal (~11m) supaya cache hit lebih sering
        $lat = round($lat, 4);
        $lon = round($lon, 4);
        $cacheKey = "reverse_geocode:{$lat},{$lon}";

        try {
            return Cache::remember($cacheKey, now()->addDays(7), function () use ($lat, $lon) {
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
        } catch (\Exception $e) {
            Log::error('Reverse geocoding failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
