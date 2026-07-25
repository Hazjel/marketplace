<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Interfaces\GeocodingGatewayInterface;
use App\Interfaces\ShippingGatewayInterface;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function __construct(
        private ShippingGatewayInterface $shippingGateway,
        private GeocodingGatewayInterface $geocodingGateway
    ) {}

    public function destination(Request $request)
    {
        $request->validate(
            ['keyword' => 'required|string|min:2|max:100'],
            ['keyword.min' => 'Kata kunci pencarian minimal 2 huruf.']
        );

        try {
            $results = $this->shippingGateway->searchDestinations(trim($request->keyword));

            return response()->json(['meta' => ['code' => 200, 'status' => 'success'], 'data' => $results]);
        } catch (\RuntimeException) {
            return ResponseHelper::jsonResponse(false, 'Layanan pengiriman tidak tersedia saat ini.', null, 503);
        } catch (\Exception) {
            return ResponseHelper::jsonResponse(false, 'Gagal mencari destinasi pengiriman.', null, 500);
        }
    }

    public function calculate(Request $request)
    {
        $request->validate([
            'shipper_destination_id' => 'required|integer',
            'receiver_destination_id' => 'required|integer',
            'item_value' => 'required|numeric|min:0',
            'weight' => 'required|numeric|min:0.01',
            // Nama kota alamat penerima — dipakai gateway sebagai fallback
            // pencarian kalau receiver_destination_id (level kecamatan) ditolak.
            'receiver_city_name' => 'nullable|string|max:100',
        ]);

        // Berat produk tersimpan dalam kg; gateway butuh gram
        $grams = max(100, (int) round($request->weight * 1000));

        try {
            $couriers = $this->shippingGateway->calculateCosts(
                (int) $request->shipper_destination_id,
                (int) $request->receiver_destination_id,
                $grams,
                $request->receiver_city_name
            );

            return response()->json([
                'meta' => ['code' => 200, 'status' => 'success'],
                'data' => ['calculate_reguler' => $couriers],
            ]);
        } catch (\RuntimeException) {
            return ResponseHelper::jsonResponse(false, 'Layanan pengiriman tidak tersedia saat ini.', null, 503);
        } catch (\Exception) {
            return ResponseHelper::jsonResponse(false, 'Gagal menghitung ongkir.', null, 500);
        }
    }

    public function geocode(Request $request)
    {
        $request->validate(['city' => 'required|string|max:100']);

        $result = $this->geocodingGateway->geocode(trim($request->city));

        return response()->json($result);
    }

    public function reverseGeocode(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lon' => 'required|numeric|between:-180,180',
        ]);

        $result = $this->geocodingGateway->reverseGeocode((float) $request->lat, (float) $request->lon);

        if ($result === null) {
            return ResponseHelper::jsonResponse(false, 'Alamat tidak ditemukan untuk lokasi ini.', null, 404);
        }

        return ResponseHelper::jsonResponse(true, 'Alamat ditemukan.', $result, 200);
    }
}
