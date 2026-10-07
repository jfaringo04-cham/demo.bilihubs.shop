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
        'label' => 'nullable|string|max:50',
        'address_line1' => 'required|string|max:255',
        'address_line2' => 'nullable|string|max:255',
        'city' => 'required|string|max:255',
        'province' => 'required|string|max:255',
        'postal_code' => 'nullable|string|max:20',
        'country' => 'nullable|string|max:255',
        'phone' => 'required|string|max:20',
        'latitude' => 'nullable|numeric|between:-90,90',
        'longitude' => 'nullable|numeric|between:-180,180',
        'is_default' => 'boolean',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'errors' => $validator->errors(),
        ], 422);
    }

    if ($request->boolean('is_default')) {
        Address::where('user_id', Auth::id())
            ->update(['is_default' => false]);
    }

    $address = Address::create([
        'user_id' => Auth::id(),
        'label' => $request->label,
        'address_line1' => $request->address_line1,
        'address_line2' => $request->address_line2,
        'city' => $request->city,
        'province' => $request->province,
        'postal_code' => $request->postal_code,
        'country' => $request->country ?? 'Philippines',
        'phone' => $request->phone,
        'latitude' => $request->latitude,
        'longitude' => $request->longitude,
        'is_default' => $request->boolean('is_default'),
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
        return response()->json([
            'message' => 'Unauthorized',
        ], 403);
    }

    $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
        'label' => 'nullable|string|max:50',
        'address_line1' => 'nullable|string|max:255',
        'address_line2' => 'nullable|string|max:255',
        'city' => 'nullable|string|max:255',
        'province' => 'nullable|string|max:255',
        'postal_code' => 'nullable|string|max:20',
        'country' => 'nullable|string|max:255',
        'phone' => 'nullable|string|max:20',
        'latitude' => 'nullable|numeric|between:-90,90',
        'longitude' => 'nullable|numeric|between:-180,180',
        'is_default' => 'boolean',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'errors' => $validator->errors(),
        ], 422);
    }

    if ($request->boolean('is_default')) {
        Address::where('user_id', Auth::id())
            ->where('id', '!=', $address->id)
            ->update(['is_default' => false]);
    }

    $address->update($request->only([
        'label',
        'address_line1',
        'address_line2',
        'city',
        'province',
        'postal_code',
        'country',
        'phone',
        'latitude',
        'longitude',
        'is_default',
    ]));

    $address->refresh();

    return response()->json([
        'message' => 'Address updated.',
        'data' => $this->formatAddress($address),
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
        'label' => $address->label,
        'address_line1' => $address->address_line1,
        'address_line2' => $address->address_line2,
        'city' => $address->city,
        'province' => $address->province,
        'postal_code' => $address->postal_code,
        'country' => $address->country,
        'phone' => $address->phone,
        'latitude' => $address->latitude,
        'longitude' => $address->longitude,
        'is_default' => (bool) $address->is_default,
        'full_address' => collect([
            $address->address_line1,
            $address->address_line2,
            $address->city,
            $address->province,
            $address->postal_code,
            $address->country,
        ])->filter()->implode(', '),
        'created_at' => $address->created_at?->toISOString(),
    ];
}
}