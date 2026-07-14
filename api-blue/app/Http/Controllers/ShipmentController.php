<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShipmentController extends Controller
{
    private string $baseUrl = 'https://rajaongkir.komerce.id/api/v1';

    public function destination(Request $request)
    {
        $request->validate(['keyword' => 'required|string|max:100']);

        try {
            $response = Http::withHeaders(['key' => config('services.komerce.api_key')])
                ->get($this->baseUrl.'/destination/domestic-destination', [
                    'search' => $request->keyword,
                    'limit' => 50,
                    'offset' => 0,
                ]);

            $status = $response->status();
            if ($status === 401 || $status === 403) {
                return ResponseHelper::jsonResponse(false, 'Layanan pengiriman tidak tersedia saat ini.', null, 503);
            }
            if ($status === 404) {
                return response()->json(['meta' => ['code' => 200, 'status' => 'success'], 'data' => []]);
            }

            return response()->json($response->json(), $status);
        } catch (\Exception $e) {
            Log::error('Shipment destination search failed', ['error' => $e->getMessage()]);

            return ResponseHelper::jsonResponse(false, 'Gagal mencari destinasi pengiriman.', null, 500);
        }
    }

    public function calculate(Request $request)
    {
        $request->validate([
            'shipper_destination_id' => 'required|integer',
            'receiver_destination_id' => 'required|integer',
            'item_value' => 'required|numeric|min:0',
            'weight' => 'required|numeric|min:1',
        ]);

        try {
            $response = Http::withHeaders(['key' => config('services.komerce.api_key')])
                ->get($this->baseUrl.'/calculate', $request->only([
                    'shipper_destination_id',
                    'receiver_destination_id',
                    'item_value',
                    'weight',
                ]));

            $status = $response->status();
            if ($status === 401 || $status === 403) {
                return ResponseHelper::jsonResponse(false, 'Layanan pengiriman tidak tersedia saat ini.', null, 503);
            }

            return response()->json($response->json(), $status);
        } catch (\Exception $e) {
            Log::error('Shipment calculate failed', ['error' => $e->getMessage()]);

            return ResponseHelper::jsonResponse(false, 'Gagal menghitung ongkir.', null, 500);
        }
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
