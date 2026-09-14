<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function index()
    {
        $reviews = Review::where('user_id', Auth::id())->with('product')->latest()->get();
        return view('buyer.reviews.index', compact('reviews'));
    }

    public function create(Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        $product = $order->items->first()->product ?? null;
        if (!$product) {
            return redirect()->route('orders.index')->with('error', 'No products to review.');
        }

        return view('buyer.reviews.create', compact('order', 'product'));
    }

    public function store(Request $request, Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        foreach ($order->items as $item) {
            Review::updateOrCreate(
                ['user_id' => Auth::id(), 'product_id' => $item->product_id, 'order_id' => $order->id],
                ['rating' => $request->rating, 'comment' => $request->comment]
            );
        }

        return redirect()->route('orders.index')->with('success', 'Review submitted successfully!');
    }
}
