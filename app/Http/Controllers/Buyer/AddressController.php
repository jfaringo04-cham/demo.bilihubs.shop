<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    public function index()
    {
        $addresses = Auth::user()->addresses()->latest()->get();
        return view('buyer.addresses.index', compact('addresses'));
    }

    public function create()
    {
        $user = Auth::user();
        $currentAddress = [
            'house_number' => $user->house_number,
            'street_address' => $user->street_address,
            'barangay' => $user->barangay_name ?? $user->barangay,
            'municipality' => $user->municipality_name ?? $user->municipality,
            'province' => $user->province_name ?? $user->province,
            'region' => $user->region_name ?? $user->region,
        ];
        return view('buyer.addresses.create', compact('currentAddress'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'label' => 'nullable|string|max:50',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($request->is_default) {
            Auth::user()->addresses()->update(['is_default' => false]);
        }

        Auth::user()->addresses()->create($request->all());

        return redirect()->route('buyer.addresses.index')->with('success', 'Address added successfully.');
    }

    public function useCurrentAddress(Request $request)
    {
        $user = Auth::user();

        if (!$user->barangay || !$user->province) {
            return back()->with('error', 'No current address on file. Please update your profile first.');
        }

        $addressLine1 = trim(implode(', ', array_filter([
            $user->house_number,
            $user->street_address,
            ($user->barangay_name ?? $user->barangay),
        ])));

        if ($request->is_default) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = $user->addresses()->create([
            'label' => $request->input('label', 'Home'),
            'address_line1' => $addressLine1 ?: 'Address from profile',
            'address_line2' => $user->street_address,
            'city' => $user->municipality_name ?? $user->municipality ?? '',
            'province' => $user->province_name ?? $user->province ?? '',
            'country' => 'Philippines',
            'phone' => $user->phone,
            'is_default' => $request->boolean('is_default', true),
        ]);

        return redirect()->route('buyer.addresses.index')
            ->with('success', 'Current address added to your address book.');
    }

    public function edit(Address $address)
    {
        if ($address->user_id !== Auth::id()) {
            abort(403);
        }
        return view('buyer.addresses.edit', compact('address'));
    }

    public function update(Request $request, Address $address)
    {
        if ($address->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'label' => 'nullable|string|max:50',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($request->is_default) {
            Auth::user()->addresses()->update(['is_default' => false]);
        }

        $address->update($request->all());

        return redirect()->route('buyer.addresses.index')->with('success', 'Address updated successfully.');
    }

    public function destroy(Address $address)
    {
        if ($address->user_id !== Auth::id()) {
            abort(403);
        }
        $address->delete();
        return back()->with('success', 'Address deleted successfully.');
    }
}
