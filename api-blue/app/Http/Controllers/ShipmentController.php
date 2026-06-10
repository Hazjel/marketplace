<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShipmentController extends Controller
{
    private string $baseUrl = 'https://api-sandbox.collaborator.komerce.id/tariff/api/v1';

    public function destination(Request $request)
    {
        $request->validate(['keyword' => 'required|string|max:100']);

        try {
            $response = Http::withHeaders(['x-api-key' => config('services.komerce.api_key')])
                ->get($this->baseUrl . '/destination/search', ['keyword' => $request->keyword]);

            return response()->json($response->json(), $response->status());
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
            $response = Http::withHeaders(['x-api-key' => config('services.komerce.api_key')])
                ->get($this->baseUrl . '/calculate', $request->only([
                    'shipper_destination_id',
                    'receiver_destination_id',
                    'item_value',
                    'weight',
                ]));

            return response()->json($response->json(), $response->status());
        } catch (\Exception $e) {
            Log::error('Shipment calculate failed', ['error' => $e->getMessage()]);
            return ResponseHelper::jsonResponse(false, 'Gagal menghitung ongkir.', null, 500);
        }
    }
}
