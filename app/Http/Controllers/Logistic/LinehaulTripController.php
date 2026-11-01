<?php

namespace App\Http\Controllers\Logistic;

use App\Http\Controllers\Controller;
use App\Models\LinehaulTrip;
use App\Models\Shipment;
use App\Models\User;
use App\Services\LinehaulTripService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LinehaulTripController extends Controller
{
    public function index()
    {
        $logistic = Auth::user()->ownedLogistic;

        abort_unless(
            $logistic !== null,
            403,
            'No logistics company found.'
        );

        $trips = LinehaulTrip::query()
            ->where('logistic_id', $logistic->id)
            ->with([
                'originHub',
                'destinationHub',
                'driver',
            ])
            ->withCount('shipments')
            ->latest()
            ->paginate(15);

        $waitingShipments = Shipment::query()
            ->where('logistic_id', $logistic->id)
            ->where(
                'routing_status',
                'at_origin_hub'
            )
            ->whereNotNull('origin_hub_received_at')
            ->whereNull('rider_id')
            ->whereNull('linehaul_rider_id')
            ->whereNotNull('origin_hub_id')
            ->whereNotNull('destination_hub_id')
            ->whereColumn(
                'origin_hub_id',
                '!=',
                'destination_hub_id'
            )
            ->whereNotIn(
                'id',
                function ($query) {
                    $query
                        ->select('shipment_id')
                        ->from(
                            'linehaul_trip_shipments'
                        );
                }
            )
            ->with([
                'originHub',
                'destinationHub',
                'sellerOrder.order.user',
            ])
            ->orderBy('origin_hub_id')
            ->orderBy('destination_hub_id')
            ->orderBy('origin_hub_received_at')
            ->get();

        $truckDrivers = User::query()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->where(
                'logistic_id',
                $logistic->id
            )
            ->where(
                'logistic_status',
                'approved'
            )
            ->where(
                'status',
                User::STATUS_ACTIVE
            )
            ->where(
                'availability_status',
                'available'
            )
            ->whereRaw(
                'LOWER(vehicle_type) = ?',
                ['truck']
            )
            ->whereNotNull('hub_id')
            ->where('max_capacity', '>', 0)
            ->whereColumn(
                'current_load',
                '<',
                'max_capacity'
            )
            ->orderBy('name')
            ->get();

        return view(
            'logistic.linehaul-trips.index',
            compact(
                'logistic',
                'trips',
                'waitingShipments',
                'truckDrivers'
            )
        );
    }

    public function store(Request $request)
    {
        $logistic = Auth::user()->ownedLogistic;

        abort_unless(
            $logistic !== null,
            403,
            'No logistics company found.'
        );

        $validated = $request->validate([
            'origin_hub_id' => [
                'required',
                'integer',
                'exists:hubs,id',
            ],

            'destination_hub_id' => [
                'required',
                'integer',
                'exists:hubs,id',
                'different:origin_hub_id',
            ],

            'driver_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'minimum_load' => [
                'required',
                'integer',
                'min:1',
            ],

            'scheduled_departure_at' => [
                'required',
                'date',
            ],
        ]);

        try {
            $trip = LinehaulTripService::create(
                (int) $logistic->id,
                (int) $validated['origin_hub_id'],
                (int) $validated['destination_hub_id'],
                (int) $validated['driver_id'],
                (int) $validated['minimum_load'],
                $validated['scheduled_departure_at']
            );
        } catch (\RuntimeException $e) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    $e->getMessage()
                );
        }

        return redirect()
            ->route(
                'logistic.linehaul-trips.index'
            )
            ->with(
                'success',
                'Truck trip '
                    . $trip->trip_number
                    . ' created. '
                    . $trip->shipments()->count()
                    . ' parcel(s) loaded.'
            );
    }

    public function load(LinehaulTrip $trip)
    {
        $this->authorizeTrip($trip);

        try {
            $loaded =
                LinehaulTripService::loadAvailableParcels(
                    $trip
                );
        } catch (\RuntimeException $e) {
            return back()->with(
                'error',
                $e->getMessage()
            );
        }

        if ($loaded === 0) {
            return back()->with(
                'success',
                'No new eligible parcels are waiting '
                    . 'for this route.'
            );
        }

        return back()->with(
            'success',
            $loaded
                . ' new parcel(s) loaded into '
                . $trip->trip_number
                . '.'
        );
    }

    public function dispatch(LinehaulTrip $trip)
    {
        $this->authorizeTrip($trip);

        try {
            LinehaulTripService::dispatch(
                $trip
            );
        } catch (\RuntimeException $e) {
            return back()->with(
                'error',
                $e->getMessage()
            );
        }

        return back()->with(
            'success',
            'Truck '
                . $trip->trip_number
                . ' dispatched successfully.'
        );
    }

    public function receive(LinehaulTrip $trip)
    {
        $this->authorizeTrip($trip);

        try {
            LinehaulTripService::receive(
                $trip
            );
        } catch (\RuntimeException $e) {
            return back()->with(
                'error',
                $e->getMessage()
            );
        }

        return back()->with(
            'success',
            'Truck received at destination hub. '
                . 'All parcels are ready for sorting.'
        );
    }

    private function authorizeTrip(
        LinehaulTrip $trip
    ): void {
        $logistic =
            Auth::user()->ownedLogistic;

        abort_unless(
            $logistic !== null
                && (int) $trip->logistic_id
                === (int) $logistic->id,
            403
        );
    }
}
