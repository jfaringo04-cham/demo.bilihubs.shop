<?php

namespace App\Http\Controllers\Logistic;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Services\PickupExceptionService;
use App\Services\PickupOfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'No pickup offer could be created. Check the origin hub, rider availability, approval and capacity.'
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
