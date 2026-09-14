<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\Logistic;
use App\Models\User;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Shipment::with(['logistic', 'rider', 'order']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('logistic_id')) {
            $query->where('logistic_id', $request->logistic_id);
        }

        $shipments = $query->latest()->paginate(20);
        $logistics = Logistic::all();

        return view('admin.shipments.index', compact('shipments', 'logistics'));
    }

    public function show(Shipment $shipment)
    {
        $shipment->load(['logistic', 'rider', 'order']);
        $riders = User::where('role', 'rider')->get();

        return view('admin.shipments.show', compact('shipment', 'riders'));
    }

    public function updateStatus(Request $request, Shipment $shipment)
    {
        $request->validate([
            'status' => ['required', 'in:pending,assigned,picked_up,in_transit,delivered,cancelled,delayed'],
        ]);

        $shipment->update(['status' => $request->status]);

        if ($request->status === 'delivered') {
            $shipment->update(['delivered_at' => now()]);
        }

        return back()->with('success', 'Shipment status updated successfully.');
    }

    public function assignRider(Request $request, Shipment $shipment)
    {
        $request->validate([
            'rider_id' => ['required', 'exists:users,id'],
        ]);

        $shipment->update([
            'rider_id' => $request->rider_id,
            'status' => 'assigned',
        ]);

        return back()->with('success', 'Rider assigned successfully.');
    }

    public function reports(Request $request)
    {
        $query = Shipment::with(['logistic', 'rider']);

        if ($request->filled('logistic_id')) {
            $query->where('logistic_id', $request->logistic_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $shipments = $query->latest()->paginate(20);
        $logistics = Logistic::all();

        $totalShipments = Shipment::count();
        $deliveredShipments = Shipment::where('status', 'delivered')->count();
        $cancelledShipments = Shipment::where('status', 'cancelled')->count();
        $delayedShipments = Shipment::where('status', 'delayed')->count();
        $pendingShipments = Shipment::where('status', 'pending')->count();
        $successRate = $totalShipments > 0 ? round(($deliveredShipments / $totalShipments) * 100, 1) : 0;

        $logisticsPerformance = Logistic::withCount(['shipments as total_count'])
            ->withCount(['shipments as delivered_count' => function ($query) {
                $query->where('status', 'delivered');
            }])
            ->withCount(['shipments as cancelled_count' => function ($query) {
                $query->where('status', 'cancelled');
            }])
            ->withCount(['shipments as delayed_count' => function ($query) {
                $query->where('status', 'delayed');
            }])
            ->get()
            ->map(function ($logistic) {
                $logistic->success_rate = $logistic->total_count > 0 ? round(($logistic->delivered_count / $logistic->total_count) * 100, 1) : 0;
                return $logistic;
            });

        $dailyReports = Shipment::select(
            \Illuminate\Support\Facades\DB::raw('DATE(created_at) as date'),
            \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'),
            \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered'),
            \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled'),
            \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN status = "delayed" THEN 1 ELSE 0 END) as `delayed`')
        )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        return view('admin.logistics.reports', compact(
            'shipments',
            'logistics',
            'totalShipments',
            'deliveredShipments',
            'cancelledShipments',
            'delayedShipments',
            'pendingShipments',
            'successRate',
            'logisticsPerformance',
            'dailyReports'
        ));
    }
}
