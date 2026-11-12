<?php

namespace App\Http\Controllers;

use App\Models\ReturnShipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ReturnShipmentController extends Controller
{
    public function logistics(Request $request)
    {
        $logistic = $request->user()->ownedLogistic()->firstOrFail();
        $shipments = ReturnShipment::with(['sellerOrder.order', 'sellerOrder.items', 'sellerOrder.shipment.destinationHub', 'rider'])
            ->where('logistic_id', $logistic->id)->latest('id')->paginate(20);
        $hubIds = $shipments->getCollection()
            ->map(fn ($shipment) => $this->pickupHub($shipment)?->id)
            ->filter()->unique()->values();

        $ridersByHub = $this->availableRiders($logistic->id)
            ->whereIn('hub_id', $hubIds)
            ->orderBy('current_load')->orderBy('name')->get()->groupBy('hub_id');

        $pickupHubs = $shipments->getCollection()->mapWithKeys(
            fn ($shipment) => [$shipment->id => $this->pickupHub($shipment)]
        );

        return view('returns.shipments', compact('logistic', 'shipments', 'ridersByHub', 'pickupHubs'));
    }

    public function rider(Request $request)
    {
        $shipments = ReturnShipment::with(['sellerOrder.order', 'sellerOrder.items'])
            ->where('rider_id', $request->user()->id)
            ->where('logistic_id', $request->user()->logistic_id)
            ->latest('id')->paginate(20);

        return view('returns.shipments', compact('shipments'));
    }

    public function assign(Request $request, ReturnShipment $returnShipment)
    {
        $logistic = $request->user()->ownedLogistic()->firstOrFail();
        abort_unless((int) $returnShipment->logistic_id === (int) $logistic->id, 403);
        $validated = $request->validate(['rider_id' => 'required|integer']);

        DB::transaction(function () use ($returnShipment, $logistic, $validated) {
            $shipment = ReturnShipment::lockForUpdate()->findOrFail($returnShipment->id);
            if ($shipment->rider_id || $shipment->picked_up_at || $shipment->delivered_at) {
                throw ValidationException::withMessages(['rider_id' => 'This return already has a rider.']);
            }

            $hub = $this->pickupHub($shipment);
            if (!$hub) {
                throw ValidationException::withMessages([
                    'rider_id' => 'No active buyer-side hub is linked to this return.',
                ]);
            }

            $rider = $this->availableRiders($logistic->id)
                ->where('hub_id', $hub->id)->lockForUpdate()->find($validated['rider_id']);

            if (!$rider) {
                throw ValidationException::withMessages([
                    'rider_id' => 'Choose an available rider from the buyer-side hub.',
                ]);
            }

            $load = (int) $rider->current_load + 1;
            $rider->update([
                'current_load' => $load,
                'availability_status' => $load >= (int) $rider->max_capacity ? 'busy' : $rider->availability_status,
            ]);
            $shipment->update(['rider_id' => $rider->id]);

            \App\Models\Notification::create([
                'user_id' => $rider->id,
                'title' => 'New Return Pickup Assignment',
                'message' => 'Pick up the return from the buyer. Tracking: ' . $shipment->tracking_number,
                'type' => 'shipment',
                'link' => route('rider.returns.index', [], false),
                'is_read' => false,
            ]);
        });

        return back()->with('success', 'Return pickup assigned.');
    }

    public function pickupQr(Request $request, ReturnShipment $returnShipment)
    {
        abort_unless(
            (int) $returnShipment->sellerOrder->order->user_id === (int) $request->user()->id,
            403
        );
        abort_unless($returnShipment->canVerifyPickup(), 404);

        $svg = (string) QrCode::format('svg')->size(240)->margin(4)
            ->generate($returnShipment->pickupCode());

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function pickup(Request $request, ReturnShipment $returnShipment)
    {
        $this->authorizeRider($request, $returnShipment);

        $validated = $request->validate(['pickup_code' => 'required|string|regex:/^[0-9A-Fa-f]{24}$/D']);

        if (!$returnShipment->canVerifyPickup()) {
            return back()->withErrors(['pickup_code' => 'This return has already been picked up.']);
        }

        if (!hash_equals($returnShipment->pickupCode(), strtoupper($validated['pickup_code']))) {
            return back()->withErrors(['pickup_code' => 'This code does not match the return. Ask the buyer for the pickup QR or code.']);
        }

        $updated = ReturnShipment::whereKey($returnShipment->id)
            ->where('rider_id', $request->user()->id)
            ->where('logistic_id', $request->user()->logistic_id)
            ->whereNull('picked_up_at')->whereNull('delivered_at')
            ->update(['picked_up_at' => now()]);

        return $updated === 1
            ? back()->with('success', 'Return marked as picked up.')
            : back()->withErrors(['pickup_code' => 'This return is no longer eligible for pickup.']);
    }

    public function deliver(Request $request, ReturnShipment $returnShipment)
    {
        $this->authorizeRider($request, $returnShipment);
        $request->validate(['handover_photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048']);
        $photo = $request->file('handover_photo')->store('return-handover-proofs', 'local');

        if (!$photo) {
            return back()->withErrors(['handover_photo' => 'The photo could not be saved.']);
        }

        try {
            DB::transaction(function () use ($request, $returnShipment, $photo) {
                $shipment = ReturnShipment::lockForUpdate()->findOrFail($returnShipment->id);
                if (!$shipment->picked_up_at || $shipment->delivered_at) {
                    throw ValidationException::withMessages([
                        'handover_photo' => 'Only a picked-up return can be delivered.',
                    ]);
                }

                $rider = User::lockForUpdate()->findOrFail($request->user()->id);
                $load = max(0, (int) $rider->current_load - 1);
                $rider->update([
                    'current_load' => $load,
                    'availability_status' => $rider->availability_status === 'busy'
                        && $load < (int) $rider->max_capacity ? 'available' : $rider->availability_status,
                ]);
                $sellerOrder = $shipment->sellerOrder;
                $sellerUserId = $sellerOrder->seller?->user_id;

                if ($sellerUserId) {
                    \App\Models\Notification::create([
                        'user_id' => $sellerUserId,
                        'title' => 'Return Delivered to Seller',
                        'message' => 'Return ' . $shipment->tracking_number . ' was delivered. Confirm receipt in the order details.',
                        'type' => 'return',
                        'link' => route('seller.orders.show', $sellerOrder->order_id, false),
                        'is_read' => false,
                    ]);
                }

                $deliveredAt = now();
                $shipment->update([
                    'delivered_at' => $deliveredAt,
                    'handover_photo' => $photo,
                    'seller_shipping_fee_posted_at' => $shipment->seller_shipping_fee_minor !== null
                        ? $deliveredAt
                        : null,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($photo);
            throw $exception;
        }

        return back()->with('success', 'Return delivered to seller.');
    }

    public function proof(Request $request, ReturnShipment $returnShipment)
    {
        $sellerOrder = $returnShipment->sellerOrder;
        $user = $request->user();
        abort_unless(
            (int) $sellerOrder->order->user_id === (int) $user->id
            || ($user->seller && (int) $sellerOrder->seller_id === (int) $user->seller->id),
            403
        );

        $disk = Storage::disk('local');
        abort_unless($returnShipment->handover_photo && $disk->exists($returnShipment->handover_photo), 404);

        return $disk->response($returnShipment->handover_photo, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function availableRiders(int $logisticId)
    {
        return User::query()->where('logistic_id', $logisticId)
            ->where('logistic_status', 'approved')->where('status', User::STATUS_ACTIVE)
            ->where('availability_status', 'available')
            ->whereHas('roles', fn ($roles) => $roles->where('name', 'rider'))
            ->where('max_capacity', '>', 0)->whereColumn('current_load', '<', 'max_capacity');
    }

    private function pickupHub(ReturnShipment $shipment): ?\App\Models\Hub
    {
        $hub = $shipment->sellerOrder->shipment?->destinationHub;

        return $hub && $hub->status === 'active' && (int) $hub->logistic_id === (int) $shipment->logistic_id
            ? $hub
            : null;
    }

    private function authorizeRider(Request $request, ReturnShipment $shipment): void
    {
        $rider = $request->user();
        abort_unless(
            $rider->status === User::STATUS_ACTIVE && $rider->logistic_status === 'approved'
            && (int) $shipment->rider_id === (int) $rider->id
            && (int) $shipment->logistic_id === (int) $rider->logistic_id,
            403
        );
    }
}
