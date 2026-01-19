<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;
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
            'data' => $addresses
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'label' => 'required|string',
            'recipient_name' => 'required|string',
            'phone' => 'required|string',
            'address' => 'required|string',
            'city' => 'required|string',
            'city_id' => 'required|string',
            'postal_code' => 'required|string',
            'is_primary' => 'boolean'
        ]);

        DB::beginTransaction();
        try {
            if ($request->is_primary) {
                Address::where('user_id', Auth::id())->update(['is_primary' => false]);
            }

            $address = Address::create([
                'user_id' => Auth::id(),
                'label' => $request->label,
                'recipient_name' => $request->recipient_name,
                'phone' => $request->phone,
                'address' => $request->address,
                'city' => $request->city,
                'city_id' => $request->city_id,
                'postal_code' => $request->postal_code,
                'is_primary' => $request->is_primary ?? false,
            ]);

            // If this is the first address, make it primary automatically
            if (Address::where('user_id', Auth::id())->count() === 1) {
                $address->update(['is_primary' => true]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'data' => $address
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $address = Address::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'label' => 'required|string',
            'recipient_name' => 'required|string',
            'phone' => 'required|string',
            'address' => 'required|string',
            'city' => 'required|string',
            'city_id' => 'required|string',
            'postal_code' => 'required|string',
            'is_primary' => 'boolean'
        ]);

        DB::beginTransaction();
        try {
            if ($request->is_primary) {
                Address::where('user_id', Auth::id())->where('id', '!=', $id)->update(['is_primary' => false]);
            }

            $address->update($request->all());

            DB::commit();

            return response()->json([
                'status' => 'success',
                'data' => $address
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
            'message' => 'Address deleted successfully'
        ]);
    }

    public function show(string $id)
    {
        $address = Address::where('user_id', Auth::id())->findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => $address
        ]);
    }
}
