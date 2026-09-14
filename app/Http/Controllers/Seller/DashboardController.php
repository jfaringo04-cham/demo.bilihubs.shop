<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use App\Models\Review;
use App\Models\Size;
use App\Models\User;
use App\Services\ComplianceMonitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    private function createNotification($userId, $title, $message, $type = 'order', $link = null)
    {
        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
        ]);
    }
    public function index()
    {
        $seller = Auth::user();
        $totalProducts = $seller->products()->count();
        $totalOrders = Order::whereHas('items.product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        })->count();
        $totalRevenue = Order::whereHas('items.product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        })->where('status', 'delivered')->sum('total');

        $recentOrders = Order::whereHas('items.product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        })->with('user')->latest()->take(5)->get();

        return view('seller.dashboard', compact(
            'totalProducts', 'totalOrders', 'totalRevenue', 'recentOrders'
        ));
    }

    public function products(Request $request)
    {
        $seller = Auth::user();
        $query = $seller->products()->with(['category', 'sizes']);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        $products = $query->latest()->paginate(20);
        $categories = Category::all();

        return view('seller.products.index', compact('products', 'categories'));
    }

    public function create()
    {
        $seller = Auth::user();
        $allowedCategoryIds = $seller->allowedCategoryIds();
        $categories = Category::whereIn('id', $allowedCategoryIds)->get();
        $sizes = \App\Models\Size::all();
        return view('seller.products.create', compact('categories', 'sizes', 'allowedCategoryIds'));
    }

    public function store(Request $request)
    {
        $seller = Auth::user();
        $allowedCategoryIds = $seller->allowedCategoryIds();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => ['required', 'exists:categories,id', Rule::in($allowedCategoryIds)],
            'images' => 'required|array|min:1',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'image_variations' => 'required|array|min:1',
            'image_variations.*.name' => 'required|string|max:255',
            'image_variations.*.price' => 'required|numeric|min:0|max:999999.99',
            'image_variations.*.stock' => 'required|integer|min:0',
            'image_variations.*.sku' => 'nullable|string|max:255',
            'image_variations.*.size_id' => 'nullable|exists:sizes,id',
            'variations' => 'nullable|array',
            'variations.*.name' => 'required_with:variations.*.price|string|max:255',
            'variations.*.price' => 'required_with:variations.*.name|numeric|min:0|max:999999.99',
            'variations.*.stock' => 'required_with:variations.*.name|integer|min:0',
            'sizes' => 'nullable|array',
            'sizes.*' => 'exists:sizes,id',
            'size_stock' => 'nullable|array',
            'size_stock.*' => 'integer|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:95',
            'discount_starts_at' => 'nullable|date',
            'discount_ends_at' => 'nullable|date|after_or_equal:discount_starts_at',
            'video' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm|max:10240',
            'secondary_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $imagePath = null;
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $stored = $img->store('products', 'public');
                $imagePaths[] = $stored;
            }
            $imagePath = $imagePaths[0] ?? null;
        }

        $videoPath = null;
        if ($request->hasFile('video')) {
            $videoPath = $request->file('video')->store('products', 'public');
        }

        $secondaryImagePath = null;
        if ($request->hasFile('secondary_image')) {
            $secondaryImagePath = $request->file('secondary_image')->store('products', 'public');
        }

        $firstVariation = $request->image_variations[0];

        $discountPercent = $request->filled('discount_percent') ? (float) $request->discount_percent : 0;
        $discountedPrice = $discountPercent > 0
            ? round($firstVariation['price'] * (1 - $discountPercent / 100), 2)
            : null;

        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $firstVariation['price'],
            'stock' => array_sum(array_column($request->image_variations, 'stock')),
            'category_id' => $request->category_id,
            'user_id' => Auth::id(),
            'image' => $imagePath,
            'alt_text' => $request->name,
            'image_path' => $imagePath,
            'video_path' => $videoPath,
            'secondary_image_path' => $secondaryImagePath,
            'discount_percent' => $discountPercent ?: null,
            'discounted_price' => $discountedPrice,
            'discount_starts_at' => $request->filled('discount_starts_at') ? $request->discount_starts_at : null,
            'discount_ends_at' => $request->filled('discount_ends_at') ? $request->discount_ends_at : null,
        ]);

        $savedImages = [];
        foreach ($imagePaths as $idx => $path) {
            $saved = $product->images()->create([
                'path' => $path,
                'alt_text' => $request->name,
                'is_primary' => $idx === 0,
                'sort_order' => $idx,
            ]);
            $savedImages[] = $saved;
        }

        foreach ($request->image_variations as $idx => $variationData) {
            if (!isset($savedImages[$idx])) {
                continue;
            }
            $varPrice = (float) $variationData['price'];
            $varDiscounted = $discountPercent > 0
                ? round($varPrice * (1 - $discountPercent / 100), 2)
                : null;
            $variation = $product->variations()->create([
                'name' => $variationData['name'],
                'price' => $varPrice,
                'stock' => $variationData['stock'],
                'sku' => $variationData['sku'] ?? null,
                'image' => $savedImages[$idx]->path,
                'image_id' => $savedImages[$idx]->id,
                'discount_percent' => $discountPercent ?: null,
                'discounted_price' => $varDiscounted,
            ]);
            if (!empty($variationData['size_id'])) {
                $variation->sizes()->attach($variationData['size_id']);
            }
        }

        if ($request->filled('variations')) {
            foreach ($request->variations as $variationData) {
                $product->variations()->create([
                    'name' => $variationData['name'],
                    'price' => $variationData['price'],
                    'stock' => $variationData['stock'],
                    'sku' => $variationData['sku'] ?? null,
                    'image' => null,
                ]);
            }
        }

        $productSizeStock = [];
        foreach ($request->image_variations as $variationData) {
            if (!empty($variationData['size_id'])) {
                $sizeId = $variationData['size_id'];
                $variationStock = (int) ($variationData['stock'] ?? 0);
                if (!isset($productSizeStock[$sizeId])) {
                    $productSizeStock[$sizeId] = 0;
                }
                $productSizeStock[$sizeId] += $variationStock;
            }
        }

        if ($request->filled('sizes')) {
            $syncData = [];
            foreach ($request->sizes as $sizeId) {
                $syncData[$sizeId] = ['stock' => $request->size_stock[$sizeId] ?? 0];
            }
            $product->sizes()->sync($syncData);
        } elseif (!empty($productSizeStock)) {
            $syncData = [];
            foreach ($productSizeStock as $sizeId => $stock) {
                $syncData[$sizeId] = ['stock' => $stock];
            }
            $product->sizes()->sync($syncData);
        }

        ComplianceMonitor::recordPriceSnapshot($product);
        ComplianceMonitor::checkProduct($product);

        return redirect()->route('seller.products')->with('success', 'Product created with ' . count($savedImages) . ' buyable image option(s).');
    }

    public function edit(Product $product)
    {
        if ($product->user_id !== Auth::id()) {
            abort(403);
        }
        $allowedCategoryIds = Auth::user()->allowedCategoryIds();
        $categories = Category::whereIn('id', $allowedCategoryIds)
            ->orWhere('id', $product->category_id)
            ->get();
        $sizes = \App\Models\Size::all();
        return view('seller.products.edit', compact('product', 'categories', 'sizes', 'allowedCategoryIds'));
    }

    public function update(Request $request, Product $product)
    {
        if ($product->user_id !== Auth::id()) {
            abort(403);
        }

        $allowedCategoryIds = Auth::user()->allowedCategoryIds();
        $allowedCategoryIds[] = $product->category_id;

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0|max:999999.99',
            'stock' => 'required|integer|min:0',
            'category_id' => ['required', 'exists:categories,id', Rule::in($allowedCategoryIds)],
            'new_images' => 'nullable|array',
            'new_images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'image_variations' => 'nullable|array',
            'image_variations.*.name' => 'required|string|max:255',
            'image_variations.*.price' => 'required|numeric|min:0|max:999999.99',
            'image_variations.*.stock' => 'required|integer|min:0',
            'image_variations.*.sku' => 'nullable|string|max:255',
            'image_variations.*.image_id' => 'required|integer|exists:product_images,id',
            'new_image_variations' => 'nullable|array',
            'new_image_variations.*.name' => 'required_with:new_image_variations|string|max:255',
            'new_image_variations.*.price' => 'required_with:new_image_variations|numeric|min:0|max:999999.99',
            'new_image_variations.*.stock' => 'required_with:new_image_variations|integer|min:0',
            'new_image_variations.*.sku' => 'nullable|string|max:255',
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'integer|exists:product_images,id',
            'sizes' => 'nullable|array',
            'sizes.*' => 'exists:sizes,id',
            'size_stock' => 'nullable|array',
            'size_stock.*' => 'integer|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:95',
            'discount_starts_at' => 'nullable|date',
            'discount_ends_at' => 'nullable|date|after_or_equal:discount_starts_at',
            'video' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm|max:10240',
            'secondary_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->only(['name', 'description', 'price', 'stock', 'category_id']);

        $newImagePaths = [];
        if ($request->hasFile('new_images')) {
            foreach ($request->file('new_images') as $img) {
                $newImagePaths[] = $img->store('products', 'public');
            }
        }

        if ($request->hasFile('video')) {
            if ($product->video_path) {
                \Storage::disk('public')->delete($product->video_path);
            }
            $data['video_path'] = $request->file('video')->store('products', 'public');
        }

        if ($request->filled('remove_video')) {
            if ($product->video_path) {
                \Storage::disk('public')->delete($product->video_path);
            }
            $data['video_path'] = null;
        }

        if ($request->hasFile('secondary_image')) {
            if ($product->secondary_image_path) {
                \Storage::disk('public')->delete($product->secondary_image_path);
            }
            $data['secondary_image_path'] = $request->file('secondary_image')->store('products', 'public');
        }

        if ($request->filled('remove_secondary_image')) {
            if ($product->secondary_image_path) {
                \Storage::disk('public')->delete($product->secondary_image_path);
            }
            $data['secondary_image_path'] = null;
        }

        if (!empty($request->remove_images)) {
            $toRemove = $product->images()->whereIn('id', $request->remove_images)->get();
            $removedPaths = $toRemove->pluck('path')->toArray();
            foreach ($toRemove as $img) {
                \Storage::disk('public')->delete($img->path);
                $product->variations()->where('image_id', $img->id)->delete();
                $img->delete();
            }
            if ($product->image && in_array($product->image, $removedPaths, true)) {
                $nextImg = $product->images()->orderBy('sort_order')->orderBy('id')->first();
                if ($nextImg) {
                    $nextImg->update(['is_primary' => true]);
                    $data['image'] = $nextImg->path;
                    $data['image_path'] = $nextImg->path;
                }
            }

            if ($product->images()->count() === 0 && empty($newImagePaths)) {
                return back()->withInput()->with('error', 'You cannot remove all images. A product must have at least one image. Please upload a new image before saving.');
            }
        }

        $existingCount = $product->images()->count();
        $newSavedImages = [];
        if (!empty($newImagePaths)) {
            foreach ($newImagePaths as $idx => $path) {
                $newImg = $product->images()->create([
                    'path' => $path,
                    'alt_text' => $request->name,
                    'is_primary' => $existingCount === 0 && $idx === 0,
                    'sort_order' => $existingCount + $idx,
                ]);
                $newSavedImages[] = $newImg;
            }
            if (!$product->image) {
                $first = $product->images()->orderBy('sort_order')->orderBy('id')->first();
                if ($first) {
                    $data['image'] = $first->path;
                    $data['image_path'] = $first->path;
                }
            }
        }

        if ($request->filled('image_variations')) {
            foreach ($request->image_variations as $imageId => $variationData) {
                $img = $product->images()->find($imageId);
                if (!$img) continue;
                $existingVar = $product->variations()->where('image_id', $imageId)->first();
                $payload = [
                    'name' => $variationData['name'],
                    'price' => $variationData['price'],
                    'stock' => $variationData['stock'],
                    'sku' => $variationData['sku'] ?? null,
                    'image' => $img->path,
                    'image_id' => $img->id,
                ];
                if ($existingVar) {
                    $existingVar->update($payload);
                } else {
                    $product->variations()->create($payload);
                }
            }
        }

        if (!empty($newSavedImages) && $request->filled('new_image_variations')) {
            foreach ($request->new_image_variations as $idx => $variationData) {
                if (!isset($newSavedImages[$idx])) continue;
                $newImg = $newSavedImages[$idx];
                $product->variations()->create([
                    'name' => $variationData['name'],
                    'price' => $variationData['price'],
                    'stock' => $variationData['stock'],
                    'sku' => $variationData['sku'] ?? null,
                    'image' => $newImg->path,
                    'image_id' => $newImg->id,
                ]);
            }
        } elseif (!empty($newSavedImages)) {
            foreach ($newSavedImages as $idx => $newImg) {
                $product->variations()->create([
                    'name' => $request->name . ' - Option ' . ($idx + 1),
                    'price' => $request->price,
                    'stock' => 1,
                    'image' => $newImg->path,
                    'image_id' => $newImg->id,
                ]);
            }
        }

        $wasFlagged = in_array($product->compliance_status, ['flagged', 'auto_flagged'], true);
        $deadlinePassed = $wasFlagged && $product->isResubmitDeadlinePassed();

        if ($deadlinePassed) {
            return back()->withInput()->with('error', 'The 7-day resubmission deadline has passed. Please contact admin support to restore this product.');
        }

        $data['compliance_status'] = $wasFlagged ? 'pending' : $product->compliance_status;
        if ($wasFlagged) {
            $data['flagged_reason'] = null;
            $data['admin_notes'] = null;
            $data['flagged_at'] = null;
        }

        $discountPercent = $request->filled('discount_percent') ? (float) $request->discount_percent : 0;
        $cheapestPrice = $product->variations()->min('price') ?? $request->price;
        $discountedPrice = $discountPercent > 0
            ? round((float) $cheapestPrice * (1 - $discountPercent / 100), 2)
            : null;
        $data['discount_percent'] = $discountPercent ?: null;
        $data['discounted_price'] = $discountedPrice;
        $data['discount_starts_at'] = $request->filled('discount_starts_at') ? $request->discount_starts_at : null;
        $data['discount_ends_at'] = $request->filled('discount_ends_at') ? $request->discount_ends_at : null;

        $product->update($data);

        $totalStock = (int) $product->variations()->sum('stock');
        $cheapestPrice = $product->variations()->min('price');
        $product->update([
            'stock' => $totalStock,
            'price' => $cheapestPrice ?? $request->price,
        ]);

        if ($request->filled('sizes')) {
            $syncData = [];
            foreach ($request->sizes as $sizeId) {
                $syncData[$sizeId] = ['stock' => $request->size_stock[$sizeId] ?? 0];
            }
            $product->sizes()->sync($syncData);
        } else {
            $product->sizes()->detach();
        }

        ComplianceMonitor::recordPriceSnapshot($product);
        ComplianceMonitor::checkProduct($product->fresh(), true);

        if ($wasFlagged) {
            $product = $product->fresh();
            $admins = \App\Models\User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                \App\Models\Notification::create([
                    'user_id' => $admin->id,
                    'title' => 'Product Resubmitted for Review',
                    'message' => 'Seller has updated and resubmitted "' . $product->name . '" for compliance review. Please verify the changes.',
                    'type' => 'product',
                    'link' => route('admin.compliance.show', $product),
                ]);
            }
        }

        $msg = $wasFlagged
            ? 'Product updated and resubmitted for admin review.'
            : 'Product updated successfully.';

        return redirect()->route('seller.products')->with('success', $msg);
    }

    public function resubmit(Product $product)
    {
        if ($product->user_id !== Auth::id()) {
            abort(403);
        }

        if (!in_array($product->compliance_status, ['flagged', 'auto_flagged'])) {
            return back()->with('error', 'Only flagged products can be resubmitted.');
        }

        if ($product->isResubmitDeadlinePassed()) {
            return back()->with('error', 'The 7-day resubmission deadline has passed. Please contact admin support to restore this product.');
        }

        $product->update([
            'compliance_status' => 'pending',
            'flagged_reason' => null,
            'admin_notes' => null,
            'flagged_at' => null,
        ]);

        $admins = \App\Models\User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::create([
                'user_id' => $admin->id,
                'title' => 'Product Resubmitted for Review',
                'message' => 'Seller has updated and resubmitted "' . $product->name . '" for compliance review. Please verify the changes.',
                'type' => 'product',
                'link' => route('admin.compliance.show', $product),
            ]);
        }

        return redirect()->route('seller.products')->with('success', 'Product resubmitted for admin review.');
    }

    public function markNotificationRead(\App\Models\Notification $notification, Request $request)
    {
        if ($notification->user_id !== Auth::id()) {
            abort(403);
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        if ($request->input('redirect_back')) {
            return back()->with('success', 'Notification marked as read.');
        }

        if ($notification->link) {
            return redirect($notification->link);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllNotificationsRead()
    {
        \App\Models\Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy(Product $product)
    {
        if ($product->user_id !== Auth::id()) {
            abort(403);
        }

        $hasOrders = $product->orderItems()->exists();
        if ($hasOrders) {
            return back()->with('error', 'Cannot remove this product because it has existing orders. Contact admin support to archive it instead.');
        }

        foreach ($product->images as $img) {
            \Storage::disk('public')->delete($img->path);
        }

        $name = $product->name;
        $product->delete();

        return redirect()->route('seller.products')->with('success', 'Product "' . $name . '" has been removed from your shop.');
    }

    public function orders()
    {
        $seller = Auth::user();
        $orders = Order::whereHas('items.product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        })->with(['user', 'items.product', 'items.size'])->latest()->paginate(20);

        return view('seller.orders', compact('orders'));
    }

    public function showOrder(Order $order)
    {
        $seller = Auth::user();

        $hasSellerProduct = $order->items()->whereHas('product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        })->exists();

        if (!$hasSellerProduct) {
            abort(403);
        }

        $order->load('user', 'items.product', 'items.size', 'shipment.rider');

        return view('seller.orders.show', compact('order'));
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        $seller = Auth::user();
        $hasSellerProduct = $order->items()->whereHas('product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        })->exists();

        if (!$hasSellerProduct) {
            abort(403);
        }

        $request->validate([
            'status' => 'required|in:confirmed,preparing,ready_for_pickup,cancelled',
        ]);

        $order->update([
            'status' => $request->status,
            'shipped_at' => $request->status === 'ready_for_pickup' ? now() : $order->shipped_at,
            'delivered_at' => $request->status === 'delivered' ? now() : $order->delivered_at,
        ]);

        if ($request->status === 'confirmed' && !$order->shipment) {
            $hub = \App\Services\HubAssignmentService::findBestHubForOrder($order);

            if ($hub) {
                $trackingNumber = 'SPE-' . strtoupper(uniqid());

                \App\Models\Shipment::create([
                    'logistic_id' => $hub->logistic_id,
                    'hub_id' => $hub->id,
                    'order_id' => $order->id,
                    'tracking_number' => $trackingNumber,
                    'status' => 'pending',
                    'pickup_address' => $seller->business_name . ', ' . $seller->street_address . ', ' . $seller->barangay . ', ' . $seller->municipality . ', ' . $seller->province,
                    'delivery_address' => $order->shipping_address,
                    'notes' => 'Auto-assigned hub based on order location: ' . $hub->name,
                ]);

                $this->createNotification(
                    $hub->logistic->owner_user_id,
                    'New Shipment Assignment',
                    'Order ' . $order->order_number . ' is being processed and has been auto-assigned to your hub (' . $hub->name . ') based on delivery location.',
                    'shipment',
                    route('logistic.shipments')
                );
            }
        }

        $statusMessages = [
            'confirmed' => 'Your order ' . $order->order_number . ' has been confirmed.',
            'preparing' => 'Your order ' . $order->order_number . ' is being prepared.',
            'ready_for_pickup' => 'Your order ' . $order->order_number . ' is ready for pickup.',
            'cancelled' => 'Your order ' . $order->order_number . ' has been cancelled.',
        ];

        if (isset($statusMessages[$request->status])) {
            $this->createNotification(
                $order->user_id,
                'Order Status Updated',
                $statusMessages[$request->status],
                'order',
                route('orders.show', $order)
            );
        }

        return back()->with('success', 'Order status updated.');
    }

    public function approveReturn(Request $request, Order $order)
    {
        $seller = Auth::user();
        $hasSellerProduct = $order->items()->whereHas('product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        })->exists();

        if (!$hasSellerProduct) {
            abort(403);
        }

        $order->update(['return_status' => 'approved']);

        $this->createNotification(
            $order->user_id,
            'Return Approved',
            'Your return request for order ' . $order->order_number . ' has been approved.',
            'return',
            route('orders.show', $order)
        );

        return back()->with('success', 'Return request approved.');
    }

    public function rejectReturn(Request $request, Order $order)
    {
        $seller = Auth::user();
        $hasSellerProduct = $order->items()->whereHas('product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        })->exists();

        if (!$hasSellerProduct) {
            abort(403);
        }

        $order->update(['return_status' => 'rejected']);

        $this->createNotification(
            $order->user_id,
            'Return Rejected',
            'Your return request for order ' . $order->order_number . ' has been rejected.',
            'return',
            route('orders.show', $order)
        );

        return back()->with('success', 'Return request rejected.');
    }

    public function approveReschedule(Request $request, Order $order)
    {
        $seller = Auth::user();
        $hasSellerProduct = $order->items()->whereHas('product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        })->exists();

        if (!$hasSellerProduct) {
            abort(403);
        }

        $shipment = $order->shipment;
        if ($shipment) {
            $bestRider = \App\Services\RiderAssignmentService::findBestRiderForShipment($shipment);
            if ($bestRider) {
                \App\Services\RiderAssignmentService::assignRiderToShipment($shipment, $bestRider);

                $this->createNotification(
                    $bestRider->id,
                    'Rescheduled Delivery Assigned',
                    'Order ' . $order->order_number . ' has been rescheduled for delivery. Destination: ' . ($shipment->delivery_zone ?: 'N/A') . '. Please proceed with the delivery.',
                    'delivery',
                    route('rider.pickups')
                );
            }
        }

        $this->createNotification(
            $order->user_id,
            'Reschedule Approved',
            'Your reschedule request for order ' . $order->order_number . ' has been approved. A rider will be assigned shortly.',
            'order',
            route('orders.show', $order)
        );

        return back()->with('success', 'Reschedule request approved.');
    }

    public function rejectReschedule(Request $request, Order $order)
    {
        $seller = Auth::user();
        $hasSellerProduct = $order->items()->whereHas('product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        })->exists();

        if (!$hasSellerProduct) {
            abort(403);
        }

        $order->update([
            'status' => 'return_requested',
            'return_status' => 'requested',
        ]);

        $this->createNotification(
            $order->user_id,
            'Reschedule Rejected',
            'Your reschedule request for order ' . $order->order_number . ' has been rejected. Please choose to return the item instead.',
            'order',
            route('orders.show', $order)
        );

        return back()->with('success', 'Reschedule request rejected. Buyer can now request a return.');
    }

    public function markReadyForPickup(Order $order)
    {
        $seller = Auth::user();
        $hasSellerProduct = $order->items()->whereHas('product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        })->exists();

        if (!$hasSellerProduct) {
            abort(403);
        }

        $order->items()->whereHas('product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        })->with('product')->each(function ($item) {
            if ($item->product) {
                $item->product->update([
                    'stock' => max(0, $item->product->stock - $item->quantity),
                ]);
            }
        });

        $order->update([
            'ready_for_pickup' => true,
            'status' => 'ready_for_pickup',
        ]);

        if (!$order->shipment) {
            $hub = \App\Services\HubAssignmentService::findBestHubForOrder($order);

            if ($hub) {
                $trackingNumber = 'SPE-' . strtoupper(uniqid());

                $pickupRider = \App\Models\User::where('role', 'rider')
                    ->where('logistic_id', $hub->logistic_id)
                    ->where('availability_status', 'available')
                    ->whereColumn('current_load', '<', 'max_capacity')
                    ->first();

                \App\Models\Shipment::create([
                    'logistic_id' => $hub->logistic_id,
                    'hub_id' => $hub->id,
                    'rider_id' => $pickupRider ? $pickupRider->id : null,
                    'order_id' => $order->id,
                    'tracking_number' => $trackingNumber,
                    'status' => $pickupRider ? 'assigned' : 'pending',
                    'pickup_address' => $seller->business_name . ', ' . $seller->street_address . ', ' . $seller->barangay . ', ' . $seller->municipality . ', ' . $seller->province,
                    'delivery_address' => $order->shipping_address,
                    'notes' => 'Auto-assigned hub based on order location: ' . $hub->name,
                ]);

                $this->createNotification(
                    $hub->logistic->owner_user_id,
                    'New Shipment Assignment',
                    'Order ' . $order->order_number . ' is ready for pickup and has been auto-assigned to your hub (' . $hub->name . ') based on delivery location.',
                    'shipment',
                    route('logistic.shipments')
                );

                if ($pickupRider) {
                    $this->createNotification(
                        $pickupRider->id,
                        'New Pickup Assignment',
                        'You have been assigned to pick up order ' . $order->order_number . ' from ' . $seller->business_name . '. Please proceed to the seller and scan the QR code to confirm pickup.',
                        'delivery',
                        route('rider.pickups')
                    );
                }
            }
        }

        return back()->with('success', 'Order marked as ready for pickup. ' . ($order->shipment ? 'Shipment assigned to ' . $order->shipment->logistic->company_name . '.' : 'Awaiting logistic company assignment.'));
    }

    public function notifications(Request $request)
    {
        $seller = Auth::user();

        $notifQuery = \App\Models\Notification::where('user_id', $seller->id);
        if ($request->input('filter') === 'unread') {
            $notifQuery->where('is_read', false);
        }
        $notifs = $notifQuery->latest()->get();

        $orderQuery = Order::whereHas('items.product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        })->with(['user', 'items.product', 'items.size']);

        if ($request->filled('status')) {
            $orderQuery->where('status', $request->status);
        }

        $orders = $orderQuery->latest()->paginate(20);

        return view('seller.notifications', compact('notifs', 'orders'));
    }

    public function reports(Request $request)
    {
        $seller = Auth::user();
        $query = Order::whereHas('items.product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        });

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $from = \Carbon\Carbon::parse($request->from_date)->startOfDay();
            $to = \Carbon\Carbon::parse($request->to_date)->endOfDay();
            $query->whereBetween('ordered_at', [$from, $to]);
        }

        $orders = $query->with(['user', 'items.product'])->latest()->get();

        $totalSales = $orders->where('status', 'delivered')->sum('total');
        $totalOrders = $orders->count();
        $deliveredOrders = $orders->where('status', 'delivered')->count();
        $cancelledOrders = $orders->where('status', 'cancelled')->count();
        $pendingOrders = $orders->whereIn('status', ['placed', 'confirmed', 'preparing'])->count();
        $processingOrders = $orders->whereIn('status', ['ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery'])->count();
        $shippedOrders = $orders->where('status', 'delivered')->count();

        $chartData = $orders->groupBy(function ($order) {
            return $order->ordered_at->format('M d, Y');
        })->map(function ($group) {
            return $group->where('status', 'delivered')->sum('total');
        })->sortKeys();

        return view('seller.reports', compact(
            'orders', 'totalSales', 'totalOrders', 'deliveredOrders',
            'cancelledOrders', 'pendingOrders', 'processingOrders', 'shippedOrders', 'chartData'
        ));
    }

    public function storefront(User $seller)
    {
        if ($seller->role !== 'seller') {
            abort(404);
        }

        $products = $seller->products()
            ->with(['category', 'sizes', 'reviews'])
            ->where('compliance_status', 'approved')
            ->latest()
            ->paginate(12);

        $totalProducts = $seller->products()->where('compliance_status', 'approved')->count();
        $averageRating = Review::whereIn('product_id', $seller->products()->pluck('id'))->avg('rating');
        $totalReviews = Review::whereIn('product_id', $seller->products()->pluck('id'))->count();

        return view('seller.storefront', compact('seller', 'products', 'totalProducts', 'averageRating', 'totalReviews'));
    }

    public function messages()
    {
        return view('seller.messages');
    }

    public function account()
    {
        $user = Auth::user();
        return view('seller.account', compact('user'));
    }

    public function updateAccount(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'sex' => ['required', 'in:Male,Female'],
            'email' => ['required', 'email', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:20'],
            'birthday' => ['required', 'date'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'region' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'municipality' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif', 'max:2048'],
            'preferred_logistic_id' => ['nullable', 'exists:logistics,id'],
        ]);

        $userData = [
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'name' => $request->last_name . ', ' . $request->first_name . ' ' . $request->middle_name,
            'sex' => $request->sex,
            'email' => $request->email,
            'mobile_number' => $request->mobile_number,
            'birthday' => $request->birthday,
            'age' => $request->age,
            'region' => $request->region,
            'province' => $request->province,
            'municipality' => $request->municipality,
            'barangay' => $request->barangay,
            'house_number' => $request->house_number,
            'street_address' => $request->street_address,
            'business_name' => $request->business_name,
            'preferred_logistic_id' => $request->preferred_logistic_id ?: null,
        ];

        if ($request->hasFile('logo')) {
            $userData['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $user->update($userData);

        return back()->with('success', 'Account updated successfully.');
    }

    public function submitAppeal(Request $request)
    {
        $user = Auth::user();

        if ($user->status !== User::STATUS_SUSPENDED) {
            return back()->with('error', 'Your account is not suspended.');
        }

        if ($user->appeal_submitted_at) {
            return back()->with('error', 'You have already submitted an appeal. Please wait for admin review.');
        }

        $request->validate([
            'appeal_message' => ['required', 'string', 'max:2000'],
        ]);

        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => 'Account Appeal - ' . $user->name,
                'message' => 'Seller ' . $user->name . ' has submitted an appeal for account suspension. Message: ' . $request->appeal_message,
                'type' => 'account',
                'link' => route('admin.users.show', $user),
            ]);
        }

        $user->update([
            'appeal_submitted_at' => now(),
            'appeal_message' => $request->appeal_message,
        ]);

        return redirect()->route('login')->with('success', 'Your appeal has been submitted. You will be notified once it has been reviewed.');
    }
}
