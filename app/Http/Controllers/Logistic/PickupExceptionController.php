<?php

namespace App\Http\Controllers\Logistic;

use App\Http\Controllers\Controller;
use App\Models\PickupRequest;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Services\PickupExceptionService;
use App\Services\PickupOfferService;
use App\Services\PickupWeightCapacity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class PickupExceptionController extends Controller
{
    /**
     * Display seller pickup monitoring and unresolved routing.
     */
    public function index(Request $request): View
    {
        $logistic = $request->user()?->ownedLogistic;

        abort_unless($logistic, 403);

        // Only show shipments belonging to this logistics company.
        $shipments = Shipment::query()
            ->where('logistic_id', $logistic->id)
            ->whereNull('picked_up_at')
            ->whereIn('status', ['pending', 'assigned'])
            ->whereHas('sellerOrder', function ($query) {
                $query->where('status', 'ready_for_pickup');
            })
            ->with([
                'sellerOrder.order',
                'sellerOrder.seller',
                'originHub',
                'destinationHub',
                'logistic',
            ])
            ->latest('id')
            ->paginate(15, ['*'], 'shipments_page')
            ->withQueryString();

        // Compute the pickup status for each displayed shipment.
        $shipments->getCollection()->each(function ($shipment) {
            $shipment->pickup_exception =
                PickupExceptionService::forShipment($shipment);
        });

        // Find seller orders without a shipment whose route cannot
        // currently be resolved. Never display another company's orders.
        $unroutedCandidates = SellerOrder::query()
            ->where('logistic_id', $logistic->id)
            ->whereIn('status', [
                'confirmed',
                'preparing',
                'ready_for_pickup',
            ])
            ->whereDoesntHave('shipment')
            ->with([
                'order',
                'seller.owner',
                'seller.pickupAddress',
            ])
            ->latest('id')
            ->get();

        $routingIssues = $unroutedCandidates
            ->filter(function (SellerOrder $sellerOrder) {
                return PickupExceptionService::forSellerOrder(
                    $sellerOrder
                )['code'] === 'no_valid_hub';
            });

        $summary = [
            'awaiting_pickup' => Shipment::query()
                ->where('logistic_id', $logistic->id)
                ->whereNull('picked_up_at')
                ->whereIn('status', ['pending', 'assigned'])
                ->whereHas('sellerOrder', function ($query) {
                    $query->where('status', 'ready_for_pickup');
                })
                ->count(),

            'routing_issues' => $routingIssues->count(),
        ];

        return view('logistic.pickup-exceptions', compact(
            'logistic',
            'shipments',
            'routingIssues',
            'summary'
        ));
    }

    /**
     * Safely retry offering a pickup to an eligible rider.
     *
     * This does not assign a rider directly or confirm parcel pickup.
     */
    public function retry(
        Request $request,
        Shipment $shipment
    ): RedirectResponse {
        $logistic = $request->user()?->ownedLogistic;

        abort_unless($logistic, 403);

        // Security: prevent other logistics companies from retrying.
        abort_unless(
            (int) $shipment->logistic_id === (int) $logistic->id,
            403,
            'This shipment does not belong to your logistics company.'
        );

        $shipment->refresh();

        // Pickup can only be retried before rider assignment
        // and before the parcel is physically collected.
        if (
            $shipment->status !== 'pending' ||
            $shipment->picked_up_at !== null ||
            $shipment->pickup_rider_id !== null ||
            $shipment->rider_id !== null ||
            $shipment->received_by_sorting_center
        ) {
            return back()->with(
                'error',
                'Pickup retry is not available for this shipment.'
            );
        }

        // Seller must explicitly mark the order ready for pickup.
        if ($shipment->sellerOrder?->status !== 'ready_for_pickup') {
            return back()->with(
                'error',
                'The seller order must be Ready for Pickup.'
            );
        }

        $weight = $request->validate([
            'packed_weight_kg' => ['nullable', 'numeric', 'min:0.001', 'max:99999.999', 'decimal:0,3'],
        ])['packed_weight_kg'] ?? null;

        if ($weight !== null) {
            $saved = DB::transaction(function () use ($shipment, $logistic, $weight) {
                $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
                abort_unless((int) $locked->logistic_id === (int) $logistic->id, 403);

                if ($locked->status !== 'pending' || $locked->picked_up_at !== null
                    || $locked->pickup_rider_id !== null || $locked->rider_id !== null
                    || $locked->received_by_sorting_center || $locked->sellerOrder?->status !== 'ready_for_pickup'
                    || !PickupWeightCapacity::enabled($locked) || PickupWeightCapacity::weightKg($locked) !== null
                    || PickupRequest::query()->where('shipment_id', $locked->id)
                        ->where('status', 'offered')->where('expires_at', '>', now())->exists()) {
                    return false;
                }

                return $locked->update(['estimated_weight_kg' => $weight]);
            });

            if (!$saved) {
                return back()->with('error', 'The packed weight can only be entered for a pending pickup with no saved weight or active offer.');
            }
        }

        try {
            // Existing service enforces hub eligibility,
            // rider availability, capacity and active-offer protection.
            $offer = PickupOfferService::offerNext($shipment);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'Pickup dispatch could not be completed. Please review the shipment and try again.'
            );
        }

        if (!$offer) {
            return back()->with(
                'error',
                PickupExceptionService::forShipment($shipment->fresh())['message']
            );
        }

        return back()->with(
            'success',
            'An active pickup offer is available for shipment ' .
                ($shipment->tracking_number ?: '#' . $shipment->id) .
                '. Awaiting rider response.'
        );
    }
}
