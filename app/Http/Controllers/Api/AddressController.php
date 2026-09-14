<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = Address::where('user_id', Auth::id())
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $addresses->map(function ($address) {
                return $this->formatAddress($address);
            }),
        ]);
    }

    public function store(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'house_number' => 'nullable|string|max:50',
            'street_address' => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
            'barangay_name' => 'nullable|string|max:255',
            'municipality' => 'required|string|max:255',
            'municipality_name' => 'nullable|string|max:255',
            'province' => 'required|string|max:255',
            'province_name' => 'nullable|string|max:255',
            'region' => 'required|string|max:255',
            'region_name' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_default' => 'boolean',
            'label' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->is_default) {
            Address::where('user_id', Auth::id())->update(['is_default' => false]);
        }

        $address = Address::create([
            'user_id' => Auth::id(),
            'recipient_name' => $request->recipient_name,
            'phone' => $request->phone,
            'house_number' => $request->house_number,
            'street_address' => $request->street_address,
            'barangay' => $request->barangay,
            'barangay_name' => $request->barangay_name,
            'municipality' => $request->municipality,
            'municipality_name' => $request->municipality_name,
            'province' => $request->province,
            'province_name' => $request->province_name,
            'region' => $request->region,
            'region_name' => $request->region_name,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'is_default' => $request->is_default ?? false,
            'label' => $request->label,
        ]);

        return response()->json([
            'message' => 'Address saved.',
            'data' => $this->formatAddress($address),
        ], 201);
    }

    public function show(Address $address)
    {
        if ($address->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $this->formatAddress($address),
        ]);
    }

    public function update(Request $request, Address $address)
    {
        if ($address->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'recipient_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'house_number' => 'nullable|string|max:50',
            'street_address' => 'nullable|string|max:255',
            'barangay' => 'nullable|string|max:255',
            'barangay_name' => 'nullable|string|max:255',
            'municipality' => 'nullable|string|max:255',
            'municipality_name' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'province_name' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'region_name' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_default' => 'boolean',
            'label' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->is_default) {
            Address::where('user_id', Auth::id())->update(['is_default' => false]);
        }

        $address->update($request->only([
            'recipient_name', 'phone', 'house_number', 'street_address',
            'barangay', 'barangay_name', 'municipality', 'municipality_name',
            'province', 'province_name', 'region', 'region_name',
            'latitude', 'longitude', 'is_default', 'label',
        ]));

        return response()->json([
            'message' => 'Address updated.',
            'data' => $this->formatAddress($address->fresh()),
        ]);
    }

    public function destroy(Address $address)
    {
        if ($address->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $address->delete();

        return response()->json([
            'message' => 'Address deleted.',
        ]);
    }

    public function setDefault(Address $address)
    {
        if ($address->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        Address::where('user_id', Auth::id())->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return response()->json([
            'message' => 'Default address updated.',
            'data' => $this->formatAddress($address->fresh()),
        ]);
    }

    private function formatAddress(Address $address): array
    {
        return [
            'id' => $address->id,
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'house_number' => $address->house_number,
            'street_address' => $address->street_address,
            'barangay' => $address->barangay,
            'barangay_name' => $address->barangay_name,
            'municipality' => $address->municipality,
            'municipality_name' => $address->municipality_name,
            'province' => $address->province,
            'province_name' => $address->province_name,
            'region' => $address->region,
            'region_name' => $address->region_name,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
            'is_default' => $address->is_default,
            'label' => $address->label,
            'full_address' => $address->full_address,
            'created_at' => $address->created_at?->toISOString(),
        ];
    }
}