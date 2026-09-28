<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
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

    $shopId = $seller->seller?->id;

    // Total revenue: delivered seller orders only
    $totalRevenue = $shopId
        ? SellerOrder::where('seller_id', $shopId)
            ->whereHas('order', function ($query) {
                $query->where('status', 'delivered');
            })
            ->sum('total_minor') / 100
        : 0;

    // Revenue graph: use the same source as Total Revenue
   // Revenue graph: always show the last 7 calendar days
$revenueData = collect();

for ($i = 6; $i >= 0; $i--) {
    $date = now()->subDays($i);
    $revenueData->put($date->format('M d'), 0);
}

if ($shopId) {
    $deliveredSellerOrders = SellerOrder::where('seller_id', $shopId)
        ->whereHas('order', function ($query) {
            $query->where('status', 'delivered')
                ->whereDate('ordered_at', '>=', now()->subDays(6)->startOfDay());
        })
        ->with('order')
        ->get();

    foreach ($deliveredSellerOrders as $sellerOrder) {
        $date = $sellerOrder->order?->ordered_at
            ?? $sellerOrder->order?->created_at;

        if (!$date) {
            continue;
        }

        $label = $date->format('M d');

        if ($revenueData->has($label)) {
            $revenueData[$label] += $sellerOrder->total_minor / 100;
        }
    }
}

$revenueData = $revenueData->map(
    fn ($amount) => round($amount, 2)
);

    $recentOrders = Order::whereHas('items.product', function ($query) use ($seller) {
        $query->where('user_id', $seller->id);
    })
        ->with(['user', 'items.product'])
        ->latest()
        ->take(5)
        ->get();

    $totalCustomers = Order::whereHas('items.product', function ($query) use ($seller) {
        $query->where('user_id', $seller->id);
    })
        ->where('status', '!=', 'cancelled')
        ->distinct('user_id')
        ->count('user_id');

    $lowStockProducts = $seller->products()
    ->where('stock', '>', 0)
    ->whereRaw('stock <= COALESCE(low_stock_threshold, 10)')
    ->orderBy('stock')
    ->limit(5)
    ->get();

    $reviewsCount = Review::whereHas('product', function ($query) use ($seller) {
        $query->where('user_id', $seller->id);
    })->count();

    return view('seller.dashboard', compact(
        'totalProducts',
        'totalOrders',
        'totalRevenue',
        'revenueData',
        'recentOrders',
        'totalCustomers',
        'lowStockProducts',
        'reviewsCount'
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
        $categories = Category::whereIn('id', $allowedCategoryIds)->whereNull('parent_id')->get();
        $subcategoryGroups = Category::whereNotNull('parent_id')->get()->groupBy('parent_id');
        $sizes = Size::all();
        return view('seller.products.create', compact(
            'categories', 'sizes', 'allowedCategoryIds', 'subcategoryGroups'
        ));
    }

    public function store(Request $request)
    {
        $seller = Auth::user();
        $allowedCategoryIds = $seller->allowedCategoryIds();
        $isDraft = $request->input('status') === 'draft';

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => ['required', 'exists:categories,id', Rule::in($allowedCategoryIds)],
            'subcategory_id' => ['nullable', 'exists:categories,id'],
            'price' => 'required|numeric|min:0|max:999999.99',
            'stock' => 'required|integer|min:0',
            'sku' => 'nullable|string|max:255',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'attributes' => 'nullable|array',
            'status' => ['in:draft,published'],
            'images' => 'required|array|min:1|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'variations' => 'nullable|array',
            'variations.*.name' => 'nullable|string|max:255',
            'variations.*.color' => 'nullable|string|max:100',
            'variations.*.size' => 'nullable|string|max:100',
            'variations.*.price' => 'nullable|numeric|min:0|max:999999.99',
            'variations.*.stock' => 'nullable|integer|min:0',
            'variations.*.sku' => 'nullable|string|max:255',
            'variations.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'variations.*.attributes' => 'nullable|array',
            'sizes' => 'nullable|array',
            'sizes.*' => 'exists:sizes,id',
            'size_stock' => 'nullable|array',
            'size_stock.*' => 'integer|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_starts_at' => 'nullable|date',
            'discount_ends_at' => 'nullable|date|after_or_equal:discount_starts_at',
            'video' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm|max:10240',
            'secondary_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $discountPercent = $request->filled('discount_percent') && $request->discount_percent !== ''
            ? (float) $request->discount_percent
            : 0;

        if ($discountPercent > 0 && $request->filled('discount_ends_at') && now()->gt(\Carbon\Carbon::parse($request->discount_ends_at))) {
            return back()->withInput()->with('error', 'The discount end date must be in the future. An already-expired promotion cannot be set active.');
        }

        $imagePath = null;
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $stored = $img->store('products', 's3');
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
            $secondaryImagePath = $request->file('secondary_image')->store('products', 's3');
        }

        $priceMinor = (int) round((float) $request->price * 100);
        $discountedPriceMinor = $discountPercent > 0
            ? (int) round($priceMinor * (1 - $discountPercent / 100))
            : null;

        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'price_minor' => $priceMinor,
            'stock' => $request->stock,
            'category_id' => $request->category_id,
            'subcategory_id' => $request->subcategory_id,
            'user_id' => Auth::id(),
            'seller_id' => $seller->seller?->id,
            'sku' => $request->sku,
            'low_stock_threshold' => $request->low_stock_threshold,
            'attributes' => $request->attributes,
            'status' => $isDraft ? 'draft' : 'published',
            'image' => $imagePath,
            'alt_text' => $request->name,
            'image_path' => $imagePath,
            'video_path' => $videoPath,
            'secondary_image_path' => $secondaryImagePath,
            'discount_percent' => $discountPercent ?: null,
            'discounted_price_minor' => $discountedPriceMinor,
            'discount_starts_at' => $request->filled('discount_starts_at') ? $request->discount_starts_at : null,
            'discount_ends_at' => $request->filled('discount_ends_at') ? $request->discount_ends_at : null,
        ]);

        foreach ($imagePaths as $idx => $path) {
            $product->images()->create([
                'path' => $path,
                'alt_text' => $request->name,
                'is_primary' => $idx === 0,
                'sort_order' => $idx,
            ]);
        }

        if ($request->filled('variations')) {
            $sizesMap = null;
            foreach ($request->variations as $variationData) {
                $color = trim($variationData['color'] ?? '');
                $size = trim($variationData['size'] ?? '');
                $variantName = trim($variationData['name'] ?? '');

                if ($color && $size) {
                    $name = $color . ' / ' . $size;
                } elseif ($color) {
                    $name = $color;
                } elseif ($size) {
                    $name = $size;
                } elseif ($variantName) {
                    $name = $variantName;
                } else {
                    if (empty($variationData['stock']) || $variationData['stock'] === '') {
                        continue;
                    }
                    $name = 'Variant';
                }

                $varPriceMinor = isset($variationData['price']) && $variationData['price'] !== ''
                    ? (int) round((float) $variationData['price'] * 100)
                    : null;
                $varDiscountedMinor = $discountPercent > 0 && $varPriceMinor !== null
                    ? (int) round($varPriceMinor * (1 - $discountPercent / 100))
                    : null;

                $varImage = null;
                if (isset($variationData['image']) && $variationData['image']) {
                    $varImage = $variationData['image']->store('products', 's3');
                }

                $variation = $product->variations()->create([
                    'name' => $name,
                    'color' => $color ?: null,
                    'size' => $size ?: null,
                    'price_minor' => $varPriceMinor,
                    'stock' => (int) ($variationData['stock'] ?? 0),
                    'sku' => $variationData['sku'] ?? null,
                    'image' => $varImage,
                    'image_id' => null,
                    'discount_percent' => $discountPercent ?: null,
                    'discounted_price_minor' => $varDiscountedMinor,
                    'attributes' => $variationData['attributes'] ?? null,
                ]);

                if (!empty($variationData['size_id']) && $variation) {
                    if ($sizesMap === null) {
                        $sizesMap = Size::all()->keyBy('id');
                    }
                    $variation->sizes()->sync([(int) $variationData['size_id'] => ['stock' => (int) ($variationData['stock'] ?? 0)]]);
                }
            }
        }

        if ($request->filled('sizes')) {
            $syncData = [];
            foreach ($request->sizes as $sizeId) {
                $syncData[$sizeId] = ['stock' => $request->size_stock[$sizeId] ?? 0];
            }
            $product->sizes()->sync($syncData);
        }

        if ($product->variations()->count() > 0) {
            $minimumPriceMinor = (int) ($product->variations()->min('price_minor') ?? $priceMinor);
            $minimumDiscountedPriceMinor = $discountPercent > 0
                ? (int) round($minimumPriceMinor * (1 - $discountPercent / 100))
                : null;

            $product->update([
                'stock' => (int) $product->variations()->sum('stock'),
                'price_minor' => $minimumPriceMinor,
                'discounted_price_minor' => $minimumDiscountedPriceMinor,
            ]);
        }

        if (!$isDraft) {
            ComplianceMonitor::recordPriceSnapshot($product);
            \App\Jobs\RunProductComplianceScan::dispatch($product->id);
        }

        $message = $isDraft ? 'Product saved as draft.' : 'Product published successfully.';
        return redirect()->route('seller.products')->with('success', $message);
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
        $subcategoryGroups = Category::whereNotNull('parent_id')->get()->groupBy('parent_id');
        $sizes = Size::all();
        $product->load(['images', 'variations', 'sizes', 'subcategory']);
        return view('seller.products.edit', compact('product', 'categories', 'sizes', 'allowedCategoryIds', 'subcategoryGroups'));
    }

    public function update(Request $request, Product $product)
    {
        if ($product->user_id !== Auth::id()) {
            abort(403);
        }

        $allowedCategoryIds = Auth::user()->allowedCategoryIds();
        $allowedCategoryIds[] = $product->category_id;
        $isDraft = $request->input('status') === 'draft';

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0|max:999999.99',
            'stock' => 'required|integer|min:0',
            'category_id' => ['required', 'exists:categories,id', Rule::in($allowedCategoryIds)],
            'subcategory_id' => ['nullable', 'exists:categories,id'],
            'sku' => 'nullable|string|max:255',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'attributes' => 'nullable|array',
            'status' => ['in:draft,published'],
            'new_images' => 'nullable|array',
            'new_images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'integer|exists:product_images,id',
            'primary_image_id' => 'nullable|integer|exists:product_images,id',
            'variations' => 'nullable|array',
            'variations.*.id' => 'nullable|integer|exists:product_variants,id',
            'variations.*.name' => 'nullable|string|max:255',
            'variations.*.color' => 'nullable|string|max:100',
            'variations.*.size' => 'nullable|string|max:100',
            'variations.*.price' => 'nullable|numeric|min:0|max:999999.99',
            'variations.*.stock' => 'nullable|integer|min:0',
            'variations.*.sku' => 'nullable|string|max:255',
            'variations.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'variations.*.attributes' => 'nullable|array',
            'sizes' => 'nullable|array',
            'sizes.*' => 'exists:sizes,id',
            'size_stock' => 'nullable|array',
            'size_stock.*' => 'integer|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_starts_at' => 'nullable|date',
            'discount_ends_at' => 'nullable|date|after_or_equal:discount_starts_at',
            'remove_video' => 'nullable|in:1,true',
            'video' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm|max:10240',
            'remove_secondary_image' => 'nullable|in:1,true',
            'secondary_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $discountPercent = $request->filled('discount_percent') && $request->discount_percent !== ''
            ? (float) $request->discount_percent
            : 0;

        if ($discountPercent > 0 && $request->filled('discount_ends_at') && now()->gt(\Carbon\Carbon::parse($request->discount_ends_at))) {
            return back()->withInput()->with('error', 'The discount end date must be in the future. An already-expired promotion cannot be set active.');
        }

        $data = $request->only([
            'name', 'description', 'stock', 'category_id', 'subcategory_id',
            'sku', 'low_stock_threshold', 'attributes', 'status',
        ]);
        $priceMinor = (int) round((float) $request->price * 100);
        $data['price_minor'] = $priceMinor;

        $newImagePaths = [];
        if ($request->hasFile('new_images')) {
            foreach ($request->file('new_images') as $img) {
                $newImagePaths[] = $img->store('products', 's3');
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
        try {
            if (\Storage::disk('s3')->exists($product->secondary_image_path)) {
                \Storage::disk('s3')->delete($product->secondary_image_path);
            } elseif (\Storage::disk('public')->exists($product->secondary_image_path)) {
                \Storage::disk('public')->delete($product->secondary_image_path);
            }
        } catch (\Throwable $e) {
            \Log::warning('Could not delete old secondary product image.', [
                'product_id' => $product->id,
                'image' => $product->secondary_image_path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    $data['secondary_image_path'] = $request
        ->file('secondary_image')
        ->store('products', 's3');
}

        if ($request->filled('remove_secondary_image')) {
    if ($product->secondary_image_path) {
        try {
            if (\Storage::disk('s3')->exists($product->secondary_image_path)) {
                \Storage::disk('s3')->delete($product->secondary_image_path);
            } elseif (\Storage::disk('public')->exists($product->secondary_image_path)) {
                \Storage::disk('public')->delete($product->secondary_image_path);
            }
        } catch (\Throwable $e) {
            \Log::warning('Could not delete secondary product image.', [
                'product_id' => $product->id,
                'image' => $product->secondary_image_path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    $data['secondary_image_path'] = null;
}

        if (!empty($request->remove_images)) {
            $toRemove = $product->images()->whereIn('id', $request->remove_images)->get();
            foreach ($toRemove as $img) {
    try {
        if (\Storage::disk('s3')->exists($img->path)) {
            \Storage::disk('s3')->delete($img->path);
        } elseif (\Storage::disk('public')->exists($img->path)) {
            \Storage::disk('public')->delete($img->path);
        }
    } catch (\Throwable $e) {
        \Log::warning('Could not delete product image.', [
            'product_id' => $product->id,
            'image_id' => $img->id,
            'image' => $img->path,
            'error' => $e->getMessage(),
        ]);
    }

    $product->variations()->where('image_id', $img->id)->delete();
    $img->delete();
}
            $removedPaths = $toRemove->pluck('path')->toArray();
            if ($product->image && in_array($product->image, $removedPaths, true)) {
                $nextImg = $product->fresh()->images()->orderBy('sort_order')->orderBy('id')->first();
                if ($nextImg) {
                    $nextImg->update(['is_primary' => true]);
                    $data['image'] = $nextImg->path;
                    $data['image_path'] = $nextImg->path;
                }
            }

            if ($product->fresh()->images()->count() === 0 && empty($newImagePaths)) {
                return back()->withInput()->with('error', 'You cannot remove all images. A product must have at least one image. Please upload a new image before saving.');
            }
        }

        $existingCount = $product->fresh()->images()->count();
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
                $first = $product->fresh()->images()->orderBy('sort_order')->orderBy('id')->first();
                if ($first) {
                    $data['image'] = $first->path;
                    $data['image_path'] = $first->path;
                }
            }
        }

        if ($request->filled('primary_image_id') && $request->primary_image_id) {
            $product->fresh()->images()->update(['is_primary' => false]);
            $primaryImg = $product->fresh()->images()->find($request->primary_image_id);
            if ($primaryImg) {
                $primaryImg->update(['is_primary' => true]);
                $data['image'] = $primaryImg->path;
                $data['image_path'] = $primaryImg->path;
            }
        }

        $submittedVariationIds = [];
        if ($request->filled('variations')) {
            $sizesMap = null;
            foreach ($request->variations as $variationData) {
                $color = trim($variationData['color'] ?? '');
                $size = trim($variationData['size'] ?? '');
                $variantName = trim($variationData['name'] ?? '');

                if ($color && $size) {
                    $name = $color . ' / ' . $size;
                } elseif ($color) {
                    $name = $color;
                } elseif ($size) {
                    $name = $size;
                } elseif ($variantName) {
                    $name = $variantName;
                } else {
                    if (empty($variationData['stock']) || $variationData['stock'] === '') {
                        continue;
                    }
                    $name = 'Variant';
                }

                $varPriceMinor = isset($variationData['price']) && $variationData['price'] !== ''
                    ? (int) round((float) $variationData['price'] * 100)
                    : null;
                $varDiscountedMinor = $discountPercent > 0 && $varPriceMinor !== null
                    ? (int) round($varPriceMinor * (1 - $discountPercent / 100))
                    : null;

                $payload = [
                    'name' => $name,
                    'color' => $color ?: null,
                    'size' => $size ?: null,
                    'price_minor' => $varPriceMinor,
                    'stock' => (int) ($variationData['stock'] ?? 0),
                    'sku' => $variationData['sku'] ?? null,
                    'discount_percent' => $discountPercent ?: null,
                    'discounted_price_minor' => $varDiscountedMinor,
                    'attributes' => $variationData['attributes'] ?? null,
                ];

                if (!empty($variationData['id'])) {
                    $existingVar = $product->variations()->where('id', $variationData['id'])->first();
                    if ($existingVar) {
                        if (isset($variationData['image']) && $variationData['image']) {
                            if ($existingVar->image) {
    try {
        if (\Storage::disk('s3')->exists($existingVar->image)) {
            \Storage::disk('s3')->delete($existingVar->image);
        } elseif (\Storage::disk('public')->exists($existingVar->image)) {
            \Storage::disk('public')->delete($existingVar->image);
        }
    } catch (\Throwable $e) {
        \Log::warning('Could not delete old variant image.', [
            'variant_id' => $existingVar->id,
            'image' => $existingVar->image,
            'error' => $e->getMessage(),
        ]);
    }
}
                            $payload['image'] = $variationData['image']->store('products', 's3');
                        }
                        $existingVar->update($payload);
                    }
                    $submittedVariationIds[] = $variationData['id'];
                } else {
                    $varImage = null;
                    if (isset($variationData['image']) && $variationData['image']) {
                        $varImage = $variationData['image']->store('products', 's3');
                    }
                    $payload['image'] = $varImage;
                    $payload['image_id'] = null;
                    $variationModel = $product->variations()->create($payload);
                    $submittedVariationIds[] = $variationModel->id;
                }
                if (!empty($variationData['size_id']) && isset($variationModel)) {
                    $variationModel->sizes()->sync([(int) $variationData['size_id'] => ['stock' => (int) ($variationData['stock'] ?? 0)]]);
                }
            }
        }

        if (empty($submittedVariationIds)) {
            $product->variations()->delete();
        } else {
            $product->variations()->whereNotIn('id', $submittedVariationIds)->delete();
        }

        $wasFlagged = in_array($product->compliance_status, ['flagged', 'auto_flagged'], true);
        $deadlinePassed = $wasFlagged && $product->isResubmitDeadlinePassed();

        if ($deadlinePassed) {
            return back()->withInput()->with('error', 'The 7-day resubmission deadline has passed. Please contact admin support to restore this product.');
        }

        $data['compliance_status'] = $isDraft ? $product->compliance_status : ($wasFlagged ? 'pending' : $product->compliance_status);
        if ($wasFlagged) {
            $data['flagged_reason'] = null;
            $data['admin_notes'] = null;
            $data['flagged_at'] = null;
        }

        $data['discount_percent'] = $discountPercent ?: null;
        $basePriceMinor = (int) ($product->variations()->min('price_minor') ?? $priceMinor);
        $data['discounted_price_minor'] = $discountPercent > 0
            ? (int) round($basePriceMinor * (1 - $discountPercent / 100))
            : null;
        $data['discount_starts_at'] = $request->filled('discount_starts_at') ? $request->discount_starts_at : null;
        $data['discount_ends_at'] = $request->filled('discount_ends_at') ? $request->discount_ends_at : null;

        if ($request->input('sizes') !== null) {
            if ($request->input('sizes') === '' || $request->input('sizes') === []) {
                $product->sizes()->detach();
            } else {
                $syncData = [];
                foreach ($request->sizes as $sizeId) {
                    $syncData[$sizeId] = ['stock' => $request->size_stock[$sizeId] ?? 0];
                }
                $product->sizes()->sync($syncData);
            }
        }

        $product->update($data);

        $variationCount = $product->fresh()->variations()->count();
        if ($variationCount > 0) {
            $minimumPriceMinor = (int) ($product->variations()->min('price_minor') ?? $priceMinor);
            $product->update([
                'stock' => (int) $product->variations()->sum('stock'),
                'price_minor' => $minimumPriceMinor,
            ]);
        } else {
            $minimumPriceMinor = $priceMinor;
            $product->update([
                'stock' => (int) $request->stock,
                'price_minor' => $minimumPriceMinor,
            ]);
        }

        if ($discountPercent > 0) {
            $finalDiscountedPriceMinor = (int) round($minimumPriceMinor * (1 - $discountPercent / 100));
            $product->update([
                'discounted_price_minor' => $finalDiscountedPriceMinor,
            ]);
        } else {
            $product->update([
                'discounted_price_minor' => null,
            ]);
        }

        if (!$isDraft) {
            ComplianceMonitor::recordPriceSnapshot($product);
            \App\Jobs\RunProductComplianceScan::dispatch($product->id);
        }

        if ($wasFlagged && !$isDraft) {
            $product = $product->fresh();
            $admins = \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get();
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

        $msg = $isDraft
            ? 'Product saved as draft.'
            : ($wasFlagged ? 'Product updated and resubmitted for admin review.' : 'Product updated successfully.');

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

        $admins = \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get();
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
    try {
        if (\Storage::disk('s3')->exists($img->path)) {
            \Storage::disk('s3')->delete($img->path);
        } elseif (\Storage::disk('public')->exists($img->path)) {
            \Storage::disk('public')->delete($img->path);
        }
    } catch (\Throwable $e) {
        \Log::warning('Could not delete product image during product deletion.', [
            'product_id' => $product->id,
            'image_id' => $img->id,
            'image' => $img->path,
            'error' => $e->getMessage(),
        ]);
    }
}

        foreach ($product->variations as $variant) {
    if (!$variant->image) {
        continue;
    }

    try {
        if (\Storage::disk('s3')->exists($variant->image)) {
            \Storage::disk('s3')->delete($variant->image);
        } elseif (\Storage::disk('public')->exists($variant->image)) {
            \Storage::disk('public')->delete($variant->image);
        }
    } catch (\Throwable $e) {
        \Log::warning('Could not delete variant image during product deletion.', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'image' => $variant->image,
            'error' => $e->getMessage(),
        ]);
    }
}

        if ($product->secondary_image_path) {
    try {
        if (\Storage::disk('s3')->exists($product->secondary_image_path)) {
            \Storage::disk('s3')->delete($product->secondary_image_path);
        } elseif (\Storage::disk('public')->exists($product->secondary_image_path)) {
            \Storage::disk('public')->delete($product->secondary_image_path);
        }
    } catch (\Throwable $e) {
        \Log::warning('Could not delete secondary image during product deletion.', [
            'product_id' => $product->id,
            'image' => $product->secondary_image_path,
            'error' => $e->getMessage(),
        ]);
    }
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

    // Security: siguraduhin na may product ang logged-in seller sa order na ito.
    $hasSellerProduct = $order->items()
        ->whereHas('product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        })
        ->exists();

    if (!$hasSellerProduct) {
        abort(403);
    }

    // Load order information.
    // Shipment belongs to SellerOrder, not directly to Order.
    $order->load([
        'user',
        'items.product',
        'items.variation',
        'items.size',
        'sellerOrders.seller',
        'sellerOrders.logistic',
        'sellerOrders.shipment.rider',
    ]);

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

        if ($request->status === 'confirmed') {
            $sellerOrder = $this->sellerOrderForAuthenticatedSeller($order, $seller);

            if ($sellerOrder && !$sellerOrder->shipment) {
                $hub = \App\Services\HubAssignmentService::findBestHubForSellerOrder($sellerOrder);

                if ($hub) {
                    $trackingNumber = 'SPE-' . strtoupper(uniqid());

                    \App\Models\Shipment::create([
                        'seller_order_id' => $sellerOrder->id,
                        'logistic_id' => $hub->logistic_id,
                        'hub_id' => $hub->id,
                        'tracking_number' => $trackingNumber,
                        'tracking_code' => $trackingNumber,
                        'status' => 'pending',
                        'pickup_address' => $this->sellerPickupAddress($sellerOrder, $seller),
                        'delivery_address' => $order->shipping_address,
                        'notes' => 'Auto-assigned hub based on seller pickup location: ' . $hub->name,
                    ]);

                    $this->createNotification(
                        $hub->logistic->owner_user_id,
                        'New Shipment Assignment',
                        'Order ' . $order->order_number . ' is being processed and has been auto-assigned to your hub (' . $hub->name . ') based on seller pickup location.',
                        'shipment',
                        route('logistic.shipments')
                    );
                }
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

if ($order->ready_for_pickup) {
    $sellerOrder = $this->sellerOrderForAuthenticatedSeller($order, $seller);

    if ($sellerOrder?->shipment) {
        return back()->with(
            'success',
            'This order is already ready for pickup and already has a shipment.'
        );
    }
}
       if (!$order->ready_for_pickup) {
    $order->items()
        ->whereHas('product', function ($query) use ($seller) {
            $query->where('user_id', $seller->id);
        })
        ->with(['product', 'variant'])
        ->each(function ($item) {

            if (!$item->product) {
                return;
            }

            // If the order item has a variant, deduct from that exact variant.
            if ($item->variant) {
                $item->variant->update([
                    'stock' => max(
                        0,
                        $item->variant->stock - $item->quantity
                    ),
                ]);

                // Product stock = total remaining stock of all variants.
                $item->product->update([
                    'stock' => (int) $item->product
                        ->variations()
                        ->sum('stock'),
                ]);

                return;
            }

            // Products without variants use the main product stock.
            $item->product->update([
                'stock' => max(
                    0,
                    $item->product->stock - $item->quantity
                ),
            ]);
        });
}


        $order->update([
            'ready_for_pickup' => true,
            'status' => 'ready_for_pickup',
        ]);

        $sellerOrder = $this->sellerOrderForAuthenticatedSeller($order, $seller);

        if ($sellerOrder && !$sellerOrder->shipment) {
            $hub = \App\Services\HubAssignmentService::findBestHubForSellerOrder($sellerOrder);

            if ($hub) {
                $trackingNumber = 'SPE-' . strtoupper(uniqid());

                $pickupRider = \App\Models\User::whereHas('roles', function ($query) {
                    $query->where('name', 'rider');
                })
                    ->where('logistic_id', $hub->logistic_id)
                    ->where('availability_status', 'available')
                    ->whereColumn('current_load', '<', 'max_capacity')
                    ->first();

                \App\Models\Shipment::create([
                    'seller_order_id' => $sellerOrder->id,
                    'logistic_id' => $hub->logistic_id,
                    'hub_id' => $hub->id,
                    'rider_id' => $pickupRider ? $pickupRider->id : null,
                    'tracking_number' => $trackingNumber,
                    'tracking_code' => $trackingNumber,
                    'status' => $pickupRider ? 'assigned' : 'pending',
                    'pickup_address' => $this->sellerPickupAddress($sellerOrder, $seller),
                    'delivery_address' => $order->shipping_address,
                    'notes' => 'Auto-assigned hub based on seller pickup location: ' . $hub->name,
                ]);

                $this->createNotification(
                    $hub->logistic->owner_user_id,
                    'New Shipment Assignment',
                    'Order ' . $order->order_number . ' is ready for pickup and has been auto-assigned to your hub (' . $hub->name . ') based on seller pickup location.',
                    'shipment',
                    route('logistic.shipments')
                );

                if ($pickupRider) {
                    $this->createNotification(
                        $pickupRider->id,
                        'New Pickup Assignment',
                        'You have been assigned to pick up order ' . $order->order_number . ' from ' . ($sellerOrder->seller?->name ?? $seller->business_name ?? $seller->name) . '. Please proceed to the seller and scan the QR code to confirm pickup.',
                        'delivery',
                        route('rider.pickups')
                    );
                }
            }
        }

        $sellerOrder?->load('shipment.logistic');
        $shipment = $sellerOrder?->shipment;

        return back()->with(
            'success',
            'Order marked as ready for pickup. ' .
            ($shipment?->logistic
                ? 'Shipment assigned to ' . $shipment->logistic->company_name . '.'
                : 'Awaiting logistic company assignment.')
        );
    }

    private function sellerOrderForAuthenticatedSeller(Order $order, User $seller): ?SellerOrder
    {
        $shopId = $seller->seller?->id;

        if (!$shopId) {
            return null;
        }

        return $order->sellerOrders()
            ->with(['seller.owner', 'seller.pickupAddress', 'shipment.logistic'])
            ->where('seller_id', $shopId)
            ->first();
    }

    private function sellerPickupAddress(SellerOrder $sellerOrder, User $legacySeller): string
    {
        $sellerOrder->loadMissing(['seller.owner', 'seller.pickupAddress']);

        $pickupAddress = $sellerOrder->seller?->pickupAddress;

        if ($pickupAddress) {
            $address = collect([
                $pickupAddress->address_line1,
                $pickupAddress->address_line2,
                $pickupAddress->city,
                $pickupAddress->province,
                $pickupAddress->postal_code,
                $pickupAddress->country,
            ])->filter()->implode(', ');

            if ($address !== '') {
                return $address;
            }
        }

        $owner = $sellerOrder->seller?->owner ?? $legacySeller;

        $address = collect([
            $owner?->business_name,
            $owner?->street_address,
            $owner?->barangay,
            $owner?->municipality,
            $owner?->province,
        ])->filter()->implode(', ');

        return $address !== '' ? $address : 'Seller address';
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

        $shopId = $seller->seller?->id;

        $sellerOrdersQuery = SellerOrder::where('seller_id', $shopId ?? 0)
            ->whereHas('order', function ($orderQuery) use ($request) {
                $orderQuery->where('status', 'delivered');

                if ($request->filled('from_date') && $request->filled('to_date')) {
                    $from = \Carbon\Carbon::parse($request->from_date)->startOfDay();
                    $to = \Carbon\Carbon::parse($request->to_date)->endOfDay();
                    $orderQuery->whereBetween('ordered_at', [$from, $to]);
                }
            });

        $totalSales = $sellerOrdersQuery->sum('total_minor') / 100;
        $totalOrders = $orders->count();
        $deliveredOrders = $orders->where('status', 'delivered')->count();
        $cancelledOrders = $orders->where('status', 'cancelled')->count();
        $pendingOrders = $orders->whereIn('status', ['placed', 'confirmed', 'preparing'])->count();
        $processingOrders = $orders->whereIn('status', ['ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery'])->count();
        $shippedOrders = $orders->where('status', 'delivered')->count();

        $chartData = SellerOrder::where('seller_id', $shopId ?? 0)
            ->whereHas('order', function ($orderQuery) use ($request) {
                $orderQuery->where('status', 'delivered');

                if ($request->filled('from_date') && $request->filled('to_date')) {
                    $from = \Carbon\Carbon::parse($request->from_date)->startOfDay();
                    $to = \Carbon\Carbon::parse($request->to_date)->endOfDay();
                    $orderQuery->whereBetween('ordered_at', [$from, $to]);
                }
            })
            ->with('order:id,ordered_at')
            ->get()
            ->groupBy(function ($sellerOrder) {
                return $sellerOrder->order->ordered_at->format('M d, Y');
            })
            ->map(function ($group) {
                return $group->sum('total_minor') / 100;
            })
            ->sortKeys();

        return view('seller.reports', compact(
            'orders', 'totalSales', 'totalOrders', 'deliveredOrders',
            'cancelledOrders', 'pendingOrders', 'processingOrders', 'shippedOrders', 'chartData'
        ));
    }

    public function storefront(User $seller)
    {
        if (!$seller->hasRole('seller')) {
            abort(404);
        }

        $products = $seller->products()
            ->with(['category', 'sizes', 'reviews'])
            ->where('compliance_status', 'approved')
            ->where('status', 'published')
            ->latest()
            ->paginate(12);

        $totalProducts = $seller->products()->where('compliance_status', 'approved')->where('status', 'published')->count();
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

        $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get();
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

