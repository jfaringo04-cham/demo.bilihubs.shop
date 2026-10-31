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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
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
                $query->whereIn('status', ['delivered', 'completed']);
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
                    $query->whereIn('status', ['delivered', 'completed'])
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
            fn($amount) => round($amount, 2)
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
        $configuredSubcategorySlugs = collect(config('categories.subcategories', []))
            ->flatten(1)
            ->pluck('slug')
            ->filter()
            ->values();
        $subcategoryGroups = Category::whereNotNull('parent_id')
            ->whereIn('slug', $configuredSubcategorySlugs)
            ->get()
            ->groupBy('parent_id');
        $sizes = Size::all();
        return view('seller.products.create', compact(
            'categories',
            'sizes',
            'allowedCategoryIds',
            'subcategoryGroups'
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
            'subcategory_id' => [
                'nullable',
                Rule::exists('categories', 'id')
                    ->where('parent_id', $request->input('category_id')),
            ],
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
            $videoPath = $request->file('video')->store('products', 's3');
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
            'compliance_status' => 'pending',
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
        $configuredSubcategorySlugs = collect(config('categories.subcategories', []))
            ->flatten(1)
            ->pluck('slug')
            ->filter()
            ->values();
        $subcategoryGroups = Category::whereNotNull('parent_id')
            ->where(function ($query) use ($configuredSubcategorySlugs, $product) {
                $query->whereIn('slug', $configuredSubcategorySlugs);

                if ($product->subcategory_id) {
                    $query->orWhere('id', $product->subcategory_id);
                }
            })
            ->get()
            ->groupBy('parent_id');
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
        $requestedStatus = $request->input('status', $product->status);

        $isDraft = $requestedStatus === 'draft';

        $isSubmittingDraft = $product->status === 'draft'
            && $requestedStatus === 'published';

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0|max:999999.99',
            'stock' => 'required|integer|min:0',
            'category_id' => ['required', 'exists:categories,id', Rule::in($allowedCategoryIds)],
            'subcategory_id' => [
                'nullable',
                Rule::exists('categories', 'id')
                    ->where('parent_id', $request->input('category_id')),
            ],
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
            'name',
            'description',
            'stock',
            'category_id',
            'subcategory_id',
            'sku',
            'low_stock_threshold',
            'attributes',
            'status',
        ]);
        $priceMinor = (int) round((float) $request->price * 100);
        $data['price_minor'] = $priceMinor;

        $newImagePaths = [];
        if ($request->hasFile('new_images')) {
            foreach ($request->file('new_images') as $img) {
                $newImagePaths[] = $img->store('products', 's3');
            }
        }

        $oldVideoPath = null;

        if ($request->hasFile('video')) {
            $newVideoPath = $request->file('video')->store('products', 's3');

            $oldVideoPath = $product->video_path;
            $data['video_path'] = $newVideoPath;
        }

        if ($request->boolean('remove_video') && ! $request->hasFile('video')) {
            $oldVideoPath = $product->video_path;
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

        $data['status'] = $requestedStatus;

        $data['compliance_status'] = ($isSubmittingDraft || (!$isDraft && $wasFlagged))
            ? 'pending'
            : $product->compliance_status;
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
            $admins = \App\Models\User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->get();
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

        // Clean up the old video only after the product update workflow succeeds.
        if ($oldVideoPath !== null) {
            try {
                Storage::disk('s3')->delete($oldVideoPath);
            } catch (\Throwable $e) {
                \Log::warning('Could not delete old product video.', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

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

        $admins = \App\Models\User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->get();
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
        $shopId = $seller->seller?->id;

        if (!$shopId) {
            return view('seller.orders', [
                'orders' => Order::whereRaw('1 = 0')->paginate(20),
            ]);
        }

        $orders = Order::query()
            ->whereHas('sellerOrders', function ($query) use ($shopId) {
                $query->where('seller_id', $shopId);
            })
            ->with([
                'user',
                'sellerOrders' => function ($query) use ($shopId) {
                    $query->where('seller_id', $shopId)
                        ->with([
                            'items.product',
                            'items.size',
                            'shipment.rider',
                            'shipment.logistic',
                        ]);
                },
            ])
            ->latest()
            ->paginate(20);

        return view('seller.orders', compact('orders'));
    }



    public function showOrder(Order $order)
    {
        $seller = Auth::user();
        $shopId = $seller->seller?->id;

        if (!$shopId) {
            abort(403, 'No seller shop is linked to this account.');
        }

        // Verify ownership through seller_orders, not product ownership.
        $sellerOrder = $order->sellerOrders()
            ->where('seller_id', $shopId)
            ->first();

        if (!$sellerOrder) {
            abort(403, 'You are not authorized to view this seller order.');
        }

        // Load only the authenticated seller's order and its items.
        $order->load([
            'user',
            'sellerOrders' => function ($query) use ($shopId) {
                $query->where('seller_id', $shopId)
                    ->with([
                        'items.product',
                        'items.size',
                        'items.variation',
                        'shipment.logistic',
                        'shipment.rider',
                    ]);
            },
        ]);

        return view('seller.orders.show', compact('order'));
    }




    public function updateOrderStatus(Request $request, Order $order)
    {
        $seller = Auth::user();

        $validated = $request->validate([
            'status' => 'required|in:confirmed,preparing',
        ]);

        $sellerOrder = $this->sellerOrderForAuthenticatedSeller(
            $order,
            $seller
        );

        if (!$sellerOrder) {
            abort(403, 'You are not authorized to update this seller order.');
        }

        $newStatus = $validated['status'];

        $changed = \Illuminate\Support\Facades\DB::transaction(
            function () use ($sellerOrder, $newStatus) {

                $lockedOrder = \App\Models\SellerOrder::query()
                    ->whereKey($sellerOrder->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $allowedTransitions = [
                    'pending' => 'confirmed',
                    'placed' => 'confirmed',
                    'confirmed' => 'preparing',
                ];

                $currentStatus = $lockedOrder->status;

                if ($currentStatus === $newStatus) {
                    return false;
                }

                if (
                    !isset($allowedTransitions[$currentStatus]) ||
                    $allowedTransitions[$currentStatus] !== $newStatus
                ) {
                    abort(422, 'Invalid seller order status transition.');
                }

                $lockedOrder->update([
                    'status' => $newStatus,
                ]);

                return true;
            }
        );

        if (!$changed) {
            return back()->with(
                'success',
                'Seller order is already in this status.'
            );
        }

        // Keep the buyer-facing parent order status in sync with
        // the slowest seller portion of the order.
        $this->syncParentOrderStatus($order);

        $this->createNotification(
            $order->user_id,
            'Seller Order Status Updated',
            'Seller order #' . $sellerOrder->id
                . ' for order ' . $order->order_number
                . ' is now ' . str_replace('_', ' ', $newStatus) . '.',
            'order',
            route('orders.show', $order)
        );

        return back()->with(
            'success',
            'Seller order updated to ' . ucfirst($newStatus) . '.'
        );
    }

    public function approveReturn(Request $request, Order $order)
    {
        $validated = $request->validate([
            'seller_order_id' => 'required|integer',
        ]);

        $shopId = Auth::user()->seller?->id;

        if (!$shopId) {
            abort(403, 'No seller shop is linked to this account.');
        }

        $sellerOrder = $order->sellerOrders()
            ->where('seller_id', $shopId)
            ->whereKey($validated['seller_order_id'])
            ->firstOrFail();

        $updated = $order->sellerOrders()
            ->whereKey($sellerOrder->id)
            ->where('seller_id', $shopId)
            ->where('return_status', 'requested')
            ->update(['return_status' => 'approved']);

        if ($updated !== 1) {
            return back()->with('error', 'This return request is no longer pending.');
        }

        $this->createNotification(
            $order->user_id,
            'Return Approved',
            'Your return request for seller order #' . $sellerOrder->id
                . ' under order ' . $order->order_number . ' has been approved.',
            'return',
            route('orders.show', $order)
        );

        return back()->with('success', 'Return request approved.');
    }

    public function rejectReturn(Request $request, Order $order)
    {
        $validated = $request->validate([
            'seller_order_id' => 'required|integer',
        ]);

        $shopId = Auth::user()->seller?->id;

        if (!$shopId) {
            abort(403, 'No seller shop is linked to this account.');
        }

        $sellerOrder = $order->sellerOrders()
            ->where('seller_id', $shopId)
            ->whereKey($validated['seller_order_id'])
            ->firstOrFail();

        $updated = $order->sellerOrders()
            ->whereKey($sellerOrder->id)
            ->where('seller_id', $shopId)
            ->where('return_status', 'requested')
            ->update(['return_status' => 'rejected']);

        if ($updated !== 1) {
            return back()->with('error', 'This return request is no longer pending.');
        }

        $this->createNotification(
            $order->user_id,
            'Return Rejected',
            'Your return request for seller order #' . $sellerOrder->id
                . ' under order ' . $order->order_number . ' has been rejected.',
            'return',
            route('orders.show', $order)
        );

        return back()->with('success', 'Return request rejected.');
    }


    public function approveReschedule(Request $request, Order $order)
    {
        $validated = $request->validate([
            'seller_order_id' => 'required|integer',
        ]);

        $shopId = Auth::user()->seller?->id;

        if (!$shopId) {
            abort(403, 'No seller shop is linked to this account.');
        }

        $sellerOrder = $order->sellerOrders()
            ->with('shipment')
            ->where('seller_id', $shopId)
            ->whereKey($validated['seller_order_id'])
            ->firstOrFail();

        if (
            $sellerOrder->status !== 'delivery_failed' ||
            $sellerOrder->reschedule_status !== 'requested'
        ) {
            return back()->with(
                'error',
                'This seller order has no eligible pending reschedule request.'
            );
        }

        $shipment = $sellerOrder->shipment;

        if (!$shipment) {
            return back()->with(
                'error',
                'No shipment was found for this seller order.'
            );
        }

        if (
            $shipment->status !== 'failed_return_at_sorting_center' ||
            is_null($shipment->failed_return_received_at)
        ) {
            return back()->with(
                'error',
                'Logistics must confirm receipt of the failed parcel before approval.'
            );
        }

        // Update only this seller order.
        $updated = $order->sellerOrders()
            ->whereKey($sellerOrder->id)
            ->where('seller_id', $shopId)
            ->where('status', 'delivery_failed')
            ->where('reschedule_status', 'requested')
            ->whereHas('shipment', function ($query) {
                $query->where(
                    'status',
                    'failed_return_at_sorting_center'
                )->whereNotNull('failed_return_received_at');
            })
            ->update([
                'reschedule_status' => 'approved',
                'rescheduled_at' => now(),
            ]);

        if ($updated !== 1) {
            return back()->with(
                'error',
                'This reschedule request is no longer eligible for approval.'
            );
        }

        $this->createNotification(
            $order->user_id,
            'Reschedule Approved',
            'Your reschedule request for seller order #' .
                $sellerOrder->id .
                ' under order ' . $order->order_number .
                ' has been approved. Awaiting rider assignment.',
            'order',
            route('orders.show', $order)
        );

        // Notify only the logistics provider for this shipment.
        $logisticOwner = $shipment->logistic?->owner;

        if ($logisticOwner) {
            $this->createNotification(
                $logisticOwner->id,
                'Rescheduled Parcel Ready for Assignment',
                'Seller order #' . $sellerOrder->id .
                    ' under order ' . $order->order_number .
                    ' is approved for redelivery.',
                'shipment',
                route('logistic.rider-assignment')
            );
        }

        return back()->with(
            'success',
            'Reschedule approved for this seller order.'
        );
    }

    public function rejectReschedule(Request $request, Order $order)
    {
        $validated = $request->validate([
            'seller_order_id' => 'required|integer',
        ]);

        $shopId = Auth::user()->seller?->id;

        if (!$shopId) {
            abort(403, 'No seller shop is linked to this account.');
        }

        $sellerOrder = $order->sellerOrders()
            ->where('seller_id', $shopId)
            ->whereKey($validated['seller_order_id'])
            ->firstOrFail();

        // Update only a pending request belonging to this seller.
        $updated = $order->sellerOrders()
            ->whereKey($sellerOrder->id)
            ->where('seller_id', $shopId)
            ->where('status', 'delivery_failed')
            ->where('reschedule_status', 'requested')
            ->update([
                'reschedule_status' => 'rejected',
            ]);

        if ($updated !== 1) {
            return back()->with(
                'error',
                'This seller order has no eligible pending reschedule request.'
            );
        }

        $this->createNotification(
            $order->user_id,
            'Reschedule Rejected',
            'Your reschedule request for seller order #' .
                $sellerOrder->id .
                ' under order ' . $order->order_number .
                ' has been rejected. You may contact support.',
            'order',
            route('orders.show', $order)
        );

        return back()->with(
            'success',
            'Reschedule request rejected for this seller order.'
        );
    }



    public function markReadyForPickup(Order $order)
    {
        $seller = Auth::user();

        $sellerOrder = $this->sellerOrderForAuthenticatedSeller(
            $order,
            $seller
        );

        if (!$sellerOrder) {
            abort(403, 'You do not own this seller order.');
        }

        $result = \Illuminate\Support\Facades\DB::transaction(
            function () use ($sellerOrder, $order, $seller) {

                $sellerOrder = \App\Models\SellerOrder::query()
                    ->whereKey($sellerOrder->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($sellerOrder->status === 'ready_for_pickup') {
                    return [
                        'message' => 'This seller order is already ready for pickup.',
                        'shipment_id' => $sellerOrder->shipment?->id,
                    ];
                }

                if (!in_array($sellerOrder->status, ['confirmed', 'preparing'], true)) {
                    abort(422, 'Confirm the seller order before marking it ready.');
                }

                $shipment = $sellerOrder->shipment;

                if (!$shipment) {
                    $route = \App\Services\HubAssignmentService::findRoutingHubsForSellerOrder(
                        $sellerOrder
                    );

                    $originHub = $route['origin'];
                    $destinationHub = $route['destination'];

                    if (!$originHub || !$destinationHub) {
                        abort(
                            422,
                            'No valid origin/destination logistics hubs are available for this seller order.'
                        );
                    }

                    $trackingNumber = 'SPE-' . strtoupper(uniqid());

                    $shipment = \App\Models\Shipment::create([
                        'seller_order_id' => $sellerOrder->id,
                        'logistic_id' => $originHub->logistic_id,
                        'hub_id' => $originHub->id,
                        'origin_hub_id' => $originHub->id,
                        'destination_hub_id' => $destinationHub->id,
                        'routing_status' => 'awaiting_pickup',
                        'tracking_number' => $trackingNumber,
                        'tracking_code' => $trackingNumber,
                        'status' => 'pending',
                        'pickup_address' => $this->sellerPickupAddress(
                            $sellerOrder,
                            $seller
                        ),
                        'delivery_address' => $order->shipping_address,
                        'notes' => 'Route: '
                            . $originHub->name
                            . ' -> '
                            . $destinationHub->name,
                    ]);

                    if (!$sellerOrder->logistic_id) {
                        $sellerOrder->update([
                            'logistic_id' => $originHub->logistic_id,
                        ]);
                    }
                }

                $sellerOrder->update([
                    'status' => 'ready_for_pickup',
                ]);

                return [
                    'message' => 'Seller order marked ready for pickup. Tracking: '
                        . $shipment->tracking_number,
                    'shipment_id' => $shipment->id,
                ];
            }
        );

        // The buyer tracking page reads the parent Order status.
        // Sync it after this seller portion becomes ready for pickup.
        $this->syncParentOrderStatus($order);

        $message = $result['message'];

        if (!empty($result['shipment_id'])) {
            try {
                $shipment = \App\Models\Shipment::findOrFail(
                    $result['shipment_id']
                );

                $offer = \App\Services\PickupOfferService::offerNext($shipment);

                if ($offer) {
                    $message .= ' A pickup request has been sent to an eligible rider.';
                } else {
                    $message .= ' Awaiting an available pickup rider.';
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error(
                    'Automatic pickup offer failed.',
                    [
                        'shipment_id' => $result['shipment_id'],
                        'error' => $e->getMessage(),
                    ]
                );

                $message .= ' Pickup dispatch is temporarily unavailable. Logistics can review this shipment.';
            }
        }

        return back()->with('success', $message);
    }


    /**
     * Synchronize the buyer-facing parent order status from its seller orders.
     *
     * A buyer order may contain items from multiple sellers. The parent order
     * should only advance as far as the least-progressed seller portion so the
     * tracking UI never claims the whole order is ready before every seller is.
     *
     * Logistics/rider statuses are not downgraded here.
     */
    private function syncParentOrderStatus(Order $order): void
    {
        $order->refresh();

        // Once logistics/rider flow has advanced the parent order, seller-side
        // updates must never move it backwards.
        $protectedStatuses = [
            'picked_up',
            'at_sorting_center',
            'sorted',
            'assigned_to_rider',
            'out_for_delivery',
            'in_transit',
            'partially_delivered',
            'delivered',
            'completed',
            'delivery_failed',
            'returned',
            'cancelled',
            'reschedule_requested',
            'rescheduled',
        ];

        if (in_array($order->status, $protectedStatuses, true)) {
            return;
        }

        $sellerStatuses = $order->sellerOrders()
            ->pluck('status')
            ->filter()
            ->values();

        if ($sellerStatuses->isEmpty()) {
            return;
        }

        $rank = [
            'pending' => 0,
            'placed' => 0,
            'confirmed' => 1,
            'preparing' => 2,
            'ready_for_pickup' => 3,
            'picked_up' => 4,
            'at_sorting_center' => 5,
            'sorted' => 5,
            'assigned_to_rider' => 5,
            'out_for_delivery' => 6,
            'in_transit' => 6,
            'partially_delivered' => 6,
            'delivered' => 7,
            'completed' => 7,
        ];

        $minimumRank = $sellerStatuses
            ->map(fn ($status) => $rank[$status] ?? 0)
            ->min();

        $parentStatus = match (true) {
            $minimumRank >= 3 => 'ready_for_pickup',
            $minimumRank >= 2 => 'preparing',
            $minimumRank >= 1 => 'confirmed',
            default => 'placed',
        };

        $allReadyForPickup = $minimumRank >= 3;

        $updates = [];

        if ($order->status !== $parentStatus) {
            $updates['status'] = $parentStatus;
        }

        if ((bool) $order->ready_for_pickup !== $allReadyForPickup) {
            $updates['ready_for_pickup'] = $allReadyForPickup;
        }

        if ($updates !== []) {
            $order->update($updates);
        }
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
            $owner?->house_number,
            $owner?->street_address,
            $owner?->barangay_name ?: $owner?->barangay,
            $owner?->municipality_name ?: $owner?->municipality,
            $owner?->province_name ?: $owner?->province,
            $owner?->region_name ?: $owner?->region,
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
                $orderQuery->whereIn('status', ['delivered', 'completed']);

                if ($request->filled('from_date') && $request->filled('to_date')) {
                    $from = \Carbon\Carbon::parse($request->from_date)->startOfDay();
                    $to = \Carbon\Carbon::parse($request->to_date)->endOfDay();
                    $orderQuery->whereBetween('ordered_at', [$from, $to]);
                }
            });

        $totalSales = $sellerOrdersQuery->sum('total_minor') / 100;
        $totalOrders = $orders->count();
        $deliveredOrders = $orders->whereIn('status', ['delivered', 'completed'])->count();
        $cancelledOrders = $orders->where('status', 'cancelled')->count();
        $pendingOrders = $orders->whereIn('status', ['placed', 'confirmed', 'preparing'])->count();
        $processingOrders = $orders->whereIn('status', ['ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery'])->count();
        $shippedOrders = $orders->whereIn('status', ['delivered', 'completed'])->count();

        $chartData = SellerOrder::where('seller_id', $shopId ?? 0)
            ->whereHas('order', function ($orderQuery) use ($request) {
                $orderQuery->whereIn('status', ['delivered', 'completed']);

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
            'orders',
            'totalSales',
            'totalOrders',
            'deliveredOrders',
            'cancelledOrders',
            'pendingOrders',
            'processingOrders',
            'shippedOrders',
            'chartData'
        ));
    }

    public function storefront(Request $request, User $seller)
    {
        if (!$seller->hasRole('seller')) {
            abort(404);
        }

        $allowedCategoryIds = collect($seller->allowedCategoryIds())
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values();

        $sellerCategoriesById = Category::query()
            ->whereNull('parent_id')
            ->whereIn('id', $allowedCategoryIds)
            ->with(['subcategories' => fn ($query) => $query->orderBy('name')])
            ->get()
            ->keyBy('id');

        // Preserve the seller's registration order when possible.
        $sellerCategories = $allowedCategoryIds
            ->map(fn ($id) => $sellerCategoriesById->get($id))
            ->filter()
            ->values();

        // Fallback for legacy seller records that do not have selling_categories.
        if ($sellerCategories->isEmpty()) {
            $categoryIdsFromProducts = $seller->products()
                ->whereNotNull('category_id')
                ->pluck('category_id');

            $categoriesFromProducts = Category::query()
                ->whereIn('id', $categoryIdsFromProducts)
                ->with('parent')
                ->get();

            $topLevelIds = $categoriesFromProducts
                ->map(fn ($category) => $category->parent_id ?: $category->id)
                ->unique()
                ->values();

            $sellerCategories = Category::query()
                ->whereNull('parent_id')
                ->whereIn('id', $topLevelIds)
                ->with(['subcategories' => fn ($query) => $query->orderBy('name')])
                ->orderBy('name')
                ->get();
        }

        $selectedCategory = null;
        if ($request->filled('category')) {
            $selectedCategory = $sellerCategories->firstWhere('id', (int) $request->category);
        }

        $primaryCategory = $selectedCategory ?: $sellerCategories->first();

        $selectedSubcategory = null;
        if ($primaryCategory && $request->filled('subcategory')) {
            $selectedSubcategory = $primaryCategory->subcategories
                ->firstWhere('id', (int) $request->subcategory);
        }

        $productsQuery = $seller->products()
            ->with(['category.parent', 'sizes', 'reviews'])
            ->where('compliance_status', 'approved')
            ->where('status', 'published');

        if ($selectedSubcategory) {
            $productsQuery->where('category_id', $selectedSubcategory->id);
        } elseif ($selectedCategory) {
            $categoryIds = $selectedCategory->subcategories
                ->pluck('id')
                ->push($selectedCategory->id)
                ->unique()
                ->values();

            $productsQuery->whereIn('category_id', $categoryIds);
        }

        $products = $productsQuery
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $publishedProductIds = $seller->products()
            ->where('compliance_status', 'approved')
            ->where('status', 'published')
            ->pluck('id');

        $totalProducts = $publishedProductIds->count();
        $averageRating = Review::whereIn('product_id', $publishedProductIds)->avg('rating');
        $totalReviews = Review::whereIn('product_id', $publishedProductIds)->count();

        $themeMap = [
            'womens-apparel' => 'fashion',
            'mens-apparel' => 'fashion',
            'electronics-and-gadgets' => 'tech',
            'food-and-gourmet' => 'food',
            'home-and-garden' => 'home',
            'kids-and-baby' => 'kids',
            'pet-supplies' => 'pets',
            'health-and-beauty' => 'beauty',
            'jewelry-and-watches' => 'luxury',
            'sports-and-outdoors' => 'sport',
            'books-and-media' => 'books',
            'furniture-and-office-equipment' => 'office',
        ];

        $theme = $primaryCategory
            ? ($themeMap[$primaryCategory->slug] ?? 'default')
            : 'default';

        return view('seller.storefront', compact(
            'seller',
            'products',
            'totalProducts',
            'averageRating',
            'totalReviews',
            'sellerCategories',
            'primaryCategory',
            'selectedCategory',
            'selectedSubcategory',
            'theme'
        ));
    }

    public function messages()
    {
        return view('seller.messages');
    }

    public function account()
    {
        $user = Auth::user();
        $shopId = $user->seller?->id;

        /*
    |--------------------------------------------------------------------------
    | Active Logistics Companies
    |--------------------------------------------------------------------------
    */

        $logistics = \App\Models\Logistic::query()
            ->where('status', 'active')
            ->orderBy('company_name')
            ->get();

        /*
    |--------------------------------------------------------------------------
    | Product Statistics
    |--------------------------------------------------------------------------
    */

        $totalProducts = $user->products()->count();

        /*
    |--------------------------------------------------------------------------
    | Seller Order Statistics
    |--------------------------------------------------------------------------
    */

        if ($shopId) {
            $sellerOrdersQuery = SellerOrder::query()
                ->where('seller_id', $shopId);

            $totalOrders = (clone $sellerOrdersQuery)->count();

            $activeOrders = (clone $sellerOrdersQuery)
                ->whereIn('status', [
                    'pending',
                    'placed',
                    'confirmed',
                    'preparing',
                    'ready_for_pickup',
                    'picked_up',
                    'at_sorting_center',
                    'sorted',
                    'assigned_to_rider',
                    'out_for_delivery',
                    'in_transit',
                    'delivery_failed',
                ])
                ->count();

            $completedOrders = (clone $sellerOrdersQuery)
                ->whereIn('status', [
                    'delivered',
                    'completed',
                ])
                ->count();

            $cancelledOrders = (clone $sellerOrdersQuery)
                ->where('status', 'cancelled')
                ->count();
        } else {
            $totalOrders = 0;
            $activeOrders = 0;
            $completedOrders = 0;
            $cancelledOrders = 0;
        }

        /*
    |--------------------------------------------------------------------------
    | Notification Statistics
    |--------------------------------------------------------------------------
    */

        $unreadNotifications = Notification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        /*
    |--------------------------------------------------------------------------
    | Message Statistics
    |--------------------------------------------------------------------------
    |
    | Keep compatibility with the existing User model. If an unread message
    | helper exists, use it. Otherwise the account page safely displays zero.
    |
    */

        $unreadMessages = method_exists(
            $user,
            'unreadMessagesCount'
        )
            ? (int) $user->unreadMessagesCount()
            : 0;

        /*
    |--------------------------------------------------------------------------
    | Seller Wallet
    |--------------------------------------------------------------------------
    |
    | Seller wallets use the seller/shop ID, which is also the ID stored in
    | seller_orders.seller_id and seller_settlements.seller_id.
    |
    */

        $wallet = $shopId
            ? \App\Models\SellerWallet::query()
            ->where('seller_id', $shopId)
            ->first()
            : null;

        $availableWalletMinor = (int) (
            $wallet?->available_balance_minor ?? 0
        );

        $pendingWalletMinor = (int) (
            $wallet?->pending_balance_minor ?? 0
        );

        /*
    |--------------------------------------------------------------------------
    | Current Preferred Logistics
    |--------------------------------------------------------------------------
    */

        $preferredLogistic = null;

        if ($user->preferred_logistic_id) {
            $preferredLogistic = $logistics->firstWhere(
                'id',
                (int) $user->preferred_logistic_id
            );

            if (!$preferredLogistic) {
                $preferredLogistic = \App\Models\Logistic::query()
                    ->find($user->preferred_logistic_id);
            }
        }

        return view('seller.account', compact(
            'user',
            'logistics',
            'preferredLogistic',
            'totalProducts',
            'totalOrders',
            'activeOrders',
            'completedOrders',
            'cancelledOrders',
            'unreadNotifications',
            'unreadMessages',
            'availableWalletMinor',
            'pendingWalletMinor',
        ));
    }


    public function updateAccount(Request $request)
    {
        $user = Auth::user();

        /*
    |--------------------------------------------------------------------------
    | Validate Account Information
    |--------------------------------------------------------------------------
    */

        $validated = $request->validateWithBag(
            'updateAccount',
            [
                'first_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'last_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'middle_name' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'sex' => [
                    'required',
                    Rule::in([
                        'Male',
                        'Female',
                    ]),
                ],

                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')
                        ->ignore($user->id),
                ],

                'mobile_number' => [
                    'required',
                    'string',
                    'max:20',
                ],

                'birthday' => [
                    'required',
                    'date',
                    'before_or_equal:today',
                ],

                'region' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'province' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'municipality' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'barangay' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'house_number' => [
                    'nullable',
                    'string',
                    'max:50',
                ],

                'street_address' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'business_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'logo' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:2048',
                ],

                'remove_logo' => [
                    'nullable',
                    'boolean',
                ],

                'preferred_logistic_id' => [
                    'nullable',
                    Rule::exists('logistics', 'id')
                        ->where(
                            fn($query) =>
                            $query->where(
                                'status',
                                'active'
                            )
                        ),
                ],
            ]
        );

        /*
    |--------------------------------------------------------------------------
    | Prepare Seller Name
    |--------------------------------------------------------------------------
    */

        $middleName = trim(
            (string) (
                $validated['middle_name'] ?? ''
            )
        );

        $displayName = trim(
            $validated['last_name']
                . ', '
                . $validated['first_name']
                . (
                    $middleName !== ''
                    ? ' ' . $middleName
                    : ''
                )
        );

        /*
    |--------------------------------------------------------------------------
    | Detect Email Change
    |--------------------------------------------------------------------------
    */

        $newEmail = strtolower(
            trim($validated['email'])
        );

        $currentEmail = strtolower(
            trim((string) $user->email)
        );

        $emailChanged = $currentEmail !== $newEmail;

        /*
    |--------------------------------------------------------------------------
    | Prepare User Data
    |--------------------------------------------------------------------------
    */

        $userData = [
            'first_name' =>
            $validated['first_name'],

            'middle_name' =>
            $middleName !== ''
                ? $middleName
                : null,

            'last_name' =>
            $validated['last_name'],

            'name' =>
            $displayName,

            'sex' =>
            $validated['sex'],

            'email' =>
            $newEmail,

            'mobile_number' =>
            $validated['mobile_number'],

            'phone' =>
            $validated['mobile_number'],

            'birthday' =>
            $validated['birthday'],

            'age' =>
            Carbon::parse(
                $validated['birthday']
            )->age,

            'region' =>
            $validated['region'],

            'province' =>
            $validated['province'],

            'municipality' =>
            $validated['municipality'],

            'barangay' =>
            $validated['barangay'],

            'house_number' =>
            $validated['house_number']
                ?? null,

            'street_address' =>
            $validated['street_address']
                ?? null,

            'business_name' =>
            $validated['business_name'],

            'store_name' =>
            $validated['business_name'],

            'preferred_logistic_id' =>
            $validated['preferred_logistic_id']
                ?? null,
        ];

        /*
    |--------------------------------------------------------------------------
    | Email Verification
    |--------------------------------------------------------------------------
    |
    | A verified old email must not automatically make a newly-entered email
    | verified.
    |
    */

        if ($emailChanged) {
            $userData['email_verified_at'] = null;
        }

        /*
    |--------------------------------------------------------------------------
    | Remove Current Shop Logo
    |--------------------------------------------------------------------------
    */

        if ($request->boolean('remove_logo')) {
            if ($user->logo) {
                $this->deleteSellerLogo($user->logo);
            }

            $userData['logo'] = null;
        }

        /*
    |--------------------------------------------------------------------------
    | Upload New Shop Logo
    |--------------------------------------------------------------------------
    */

        if ($request->hasFile('logo')) {
            $logoPath = $request
                ->file('logo')
                ->store(
                    'logos',
                    's3'
                );

            if (!$logoPath) {
                return back()
                    ->withInput()
                    ->withErrors(
                        [
                            'logo' =>
                            'Unable to upload the shop logo. Please try again.',
                        ],
                        'updateAccount'
                    );
            }

            /*
        | Delete the old image only after the new image has been stored.
        | This prevents losing the existing logo when the upload fails.
        */

            if ($user->logo) {
                $this->deleteSellerLogo($user->logo);
            }

            $userData['logo'] = $logoPath;
        }

        /*
    |--------------------------------------------------------------------------
    | Save Account
    |--------------------------------------------------------------------------
    */

        $user->update($userData);

        /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

        if ($emailChanged) {
            return back()->with(
                'success',
                'Account updated successfully. '
                    . 'Please verify your new email address.'
            );
        }

        return back()->with(
            'success',
            'Account updated successfully.'
        );
    }


    /**
     * Delete a seller logo from whichever storage disk currently contains it.
     */
    private function deleteSellerLogo(?string $path): void
    {
        if (!$path) {
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | Supabase / S3
    |--------------------------------------------------------------------------
    */

        try {
            if (
                Storage::disk('s3')
                ->exists($path)
            ) {
                Storage::disk('s3')
                    ->delete($path);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        /*
    |--------------------------------------------------------------------------
    | Legacy Local Storage
    |--------------------------------------------------------------------------
    */

        try {
            if (
                Storage::disk('public')
                ->exists($path)
            ) {
                Storage::disk('public')
                    ->delete($path);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validateWithBag('passwordUpdate', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = Auth::user();
        $user->password = $validated['password'];
        $user->save();

        return back()->with('password_success', 'Password changed successfully.');
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

        $admins = User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->get();
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
