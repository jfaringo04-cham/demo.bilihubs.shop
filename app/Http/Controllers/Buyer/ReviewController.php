<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Services\ComplianceMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    /**
     * Display reviews submitted by the authenticated buyer.
     */
    public function index()
    {
        $reviews = Review::where('user_id', Auth::id())
            ->with(['product', 'order'])
            ->latest()
            ->get();

        return view('buyer.reviews.index', compact('reviews'));
    }

    /**
     * Display all reviewable products from an order.
     */
    public function create(Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        if (!in_array($order->status, ['completed', 'delivered'])) {
            return redirect()
                ->route('orders.show', $order)
                ->with('error', 'You can only review products from completed or delivered orders.');
        }

        $order->load([
            'items.product.images',
        ]);

        $existingReviews = Review::where('user_id', Auth::id())
            ->where('order_id', $order->id)
            ->get()
            ->keyBy('product_id');

        return view(
            'buyer.reviews.create',
            compact('order', 'existingReviews')
        );
    }

    /**
     * Store/update the review for one specific product in the order.
     */
    public function store(Request $request, Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        if (!in_array($order->status, ['completed', 'delivered'])) {
            return back()->with(
                'error',
                'You can only review products from completed or delivered orders.'
            );
        }

        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
            'rating' => [
                'required',
                'integer',
                'min:1',
                'max:5',
            ],
            'comment' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        // Make sure this product actually belongs to this order.
        $orderItem = $order->items()
            ->where('product_id', $validated['product_id'])
            ->first();

        if (!$orderItem) {
            abort(403);
        }

        $review = Review::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'product_id' => $validated['product_id'],
                'order_id' => $order->id,
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]
        );

        // Keep the existing compliance monitoring behavior.
        ComplianceMonitor::checkNegativeReviewKeywords(
            $review->product()->first()
        );

        return redirect()
            ->route('buyer.reviews.create', $order)
            ->with('success', 'Review submitted successfully!');
    }
}