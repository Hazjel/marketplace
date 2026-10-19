<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index()
    {
        $addresses = Address::where('user_id', Auth::id())
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $addresses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string',
            'recipient_name' => 'required|string',
            'phone' => 'required|string',
            'address' => 'required|string',
            'city' => 'required|string',
            'city_id' => 'required|string',
            'postal_code' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_primary' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            if ($validated['is_primary'] ?? false) {
                Address::where('user_id', Auth::id())->update(['is_primary' => false]);
            }

            $address = Address::create([
                ...$validated,
                'user_id' => Auth::id(),
                'is_primary' => $validated['is_primary'] ?? false,
            ]);

            // If this is the first address, make it primary automatically
            if (Address::where('user_id', Auth::id())->count() === 1) {
                $address->update(['is_primary' => true]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'data' => $address,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $address = Address::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'label' => 'required|string',
            'recipient_name' => 'required|string',
            'phone' => 'required|string',
            'address' => 'required|string',
            'city' => 'required|string',
            'city_id' => 'required|string',
            'postal_code' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_primary' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            if ($validated['is_primary'] ?? false) {
                Address::where('user_id', Auth::id())->where('id', '!=', $id)->update(['is_primary' => false]);
            }

            $address->update($validated);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'data' => $address,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id)
    {
        $address = Address::where('user_id', Auth::id())->findOrFail($id);
        $address->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Address deleted successfully',
        ]);
    }

    public function show(string $id)
    {
        $address = Address::where('user_id', Auth::id())->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $address,
        ]);
    }
}
