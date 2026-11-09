<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ComplianceMonitor
{
    public const REPORT_THRESHOLD = 3;
    public const RETURN_RATE_THRESHOLD = 0.30;
    public const MIN_ORDERS_FOR_RETURN_RATE = 5;
    public const NEGATIVE_KEYWORD_THRESHOLD = 2;
    public const PRICE_DROP_PERCENT_THRESHOLD = 90;
    public const LISTING_SWITCH_KEYWORD_CHANGE = 0.7;

    public const BANNED_KEYWORDS = [
        'fake', 'counterfeit', 'scam', 'broken', 'toxic', 'stolen',
    ];

    public const RESTRICTED_CATEGORIES = [
        'weapons', 'firearms', 'drugs', 'alcohol', 'tobacco', 'adult',
        'ammunition', 'explosives', 'vapes',
    ];

    public const BANNED_CATEGORIES = [
        'weapons', 'firearms', 'drugs', 'alcohol', 'tobacco', 'adult',
    ];

    public static function checkImageContent(Product $product): ?array
    {
        $product->loadMissing(['images', 'variants']);

        // Scan every image path supported by the seller product workflow.
        // Product names, alt text, and filenames are intentionally excluded:
        // they describe listings and are not evidence of image content.
        $imagePaths = collect()
            ->merge($product->images->pluck('path'))
            ->push(
                $product->image,
                $product->image_path,
                $product->secondary_image_path
            )
            ->merge($product->variants->pluck('image'))
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->map(fn ($path) => trim($path))
            ->unique()
            ->values();

        if ($imagePaths->isEmpty()) {
            return null;
        }

        $blacklistedImages = DB::table('blacklisted_images')
            ->get(['image_hash', 'phash', 'reason']);
        $blacklistedHashes = $blacklistedImages
            ->pluck('image_hash')
            ->filter()
            ->all();
        $blacklistedPerceptualHashes = $blacklistedImages
            ->pluck('phash')
            ->filter(fn ($hash) => is_string($hash) && $hash !== '')
            ->values();

        $reasons = [];
        $severities = [];
        $autoFlag = false;

        foreach ($imagePaths as $imagePath) {
            $resolvedImage = self::resolveImageForScan($imagePath, $product->id);

            // A remote storage timeout or missing legacy file is an operational
            // issue, not proof that a seller uploaded prohibited content.
            if ($resolvedImage === null) {
                continue;
            }

            [$localImagePath, $isTemporary] = $resolvedImage;

            try {
                $imageInfo = @getimagesize($localImagePath);
                $mimeType = function_exists('mime_content_type')
                    ? @mime_content_type($localImagePath)
                    : ($imageInfo['mime'] ?? null);

                if (
                    $imageInfo === false
                    || !is_string($mimeType)
                    || !str_starts_with($mimeType, 'image/')
                ) {
                    $reasons[] = 'stored product image could not be validated as a readable image';
                    $severities[] = 'high';
                    continue;
                }

                $imageHash = self::hashImage($localImagePath);

                if ($imageHash !== '' && in_array($imageHash, $blacklistedHashes, true)) {
                    $blacklistEntry = $blacklistedImages->first(
                        fn ($entry) => hash_equals((string) $entry->image_hash, $imageHash)
                    );
                    $blacklistReason = trim((string) ($blacklistEntry->reason ?? ''));

                    $reasons[] = $blacklistReason !== ''
                        ? 'image exactly matches an administrator-blacklisted image: ' . $blacklistReason
                        : 'image exactly matches an image manually blacklisted by an administrator';
                    $severities[] = 'critical';
                    $autoFlag = true;
                    continue;
                }

                // Perceptual hashes are approximate and can match unrelated
                // product photos. They may alert admins, but must never hide a
                // listing automatically.
                $perceptualHash = self::perceptualHash($localImagePath);
                if ($perceptualHash !== '') {
                    $nearMatch = $blacklistedPerceptualHashes->contains(
                        fn ($candidate) => self::hammingDistance(
                            $perceptualHash,
                            (string) $candidate
                        ) <= 5
                    );

                    if ($nearMatch) {
                        $reasons[] = 'image resembles an administrator-blacklisted image and requires manual verification';
                        $severities[] = 'medium';
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Compliance scan could not validate a product image.', [
                    'product_id' => $product->id,
                    'image_path' => $imagePath,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                if ($isTemporary && is_file($localImagePath)) {
                    @unlink($localImagePath);
                }
            }
        }

        if ($reasons === []) {
            return null;
        }

        $severity = in_array('critical', $severities, true)
            ? 'critical'
            : (in_array('high', $severities, true) ? 'high' : 'medium');

        return [
            'bucket' => 'automated_bot',
            'trigger' => 'image_content_violation',
            'message' => 'Image compliance review found: ' . implode('; ', array_slice(array_unique($reasons), 0, 4)) . '.',
            'severity' => $severity,
            'auto_flag' => $autoFlag,
            'manual_review' => !$autoFlag,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * Resolve a stored product image to a local file for content validation.
     * Returns [path, isTemporary], or null when storage is unavailable.
     */
    protected static function resolveImageForScan(string $path, ?int $productId): ?array
    {
        $normalizedPath = str_replace('\\', '/', trim($path));
        $normalizedPath = ltrim($normalizedPath, '/');

        if (
            $normalizedPath === ''
            || str_contains($normalizedPath, "\0")
            || preg_match('#(^|/)\.\.?(?:/|$)#', $normalizedPath)
        ) {
            Log::warning('Compliance scan skipped an invalid product image path.', [
                'product_id' => $productId,
            ]);

            return null;
        }

        try {
            $publicDisk = Storage::disk('public');
            $publicPath = $publicDisk->path($normalizedPath);

            if (is_file($publicPath)) {
                return [$publicPath, false];
            }
        } catch (\Throwable $e) {
            // Continue to the configured object-storage disk.
        }

        foreach (['s3', 'public'] as $diskName) {
            try {
                $disk = Storage::disk($diskName);

                if (!$disk->exists($normalizedPath)) {
                    continue;
                }

                $contents = $disk->get($normalizedPath);
                $temporaryPath = tempnam(sys_get_temp_dir(), 'bilihub_compliance_');

                if ($temporaryPath === false) {
                    return null;
                }

                if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
                    @unlink($temporaryPath);
                    return null;
                }

                return [$temporaryPath, true];
            } catch (\Throwable $e) {
                Log::warning('Compliance scan could not retrieve a product image from storage.', [
                    'product_id' => $productId,
                    'image_path' => $normalizedPath,
                    'disk' => $diskName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::warning('Compliance scan skipped an unavailable product image.', [
            'product_id' => $productId,
            'image_path' => $normalizedPath,
        ]);

        return null;
    }

    public static function hashImage(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        $hash = hash_file('sha256', $path);

        return is_string($hash) ? $hash : '';
    }

    public static function perceptualHash(string $path): string
    {
        if (
            !function_exists('getimagesize')
            || !function_exists('imagecreatetruecolor')
            || !function_exists('imagecopyresampled')
            || !function_exists('imagecolorat')
            || !is_file($path)
        ) {
            return '';
        }
        $info = @getimagesize($path);
        if ($info === false) {
            return '';
        }
        $mime = $info['mime'] ?? '';
        try {
            $img = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($path),
                'image/png' => @imagecreatefrompng($path),
                'image/gif' => @imagecreatefromgif($path),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
                default => false,
            };
        } catch (\Throwable $e) {
            return '';
        }
        if (!$img) {
            return '';
        }
        $small = imagecreatetruecolor(8, 8);
        imagecopyresampled($small, $img, 0, 0, 0, 0, 8, 8, imagesx($img), imagesy($img));
        $pixels = [];
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $pixels[] = (int) (($r + $g + $b) / 3);
            }
        }
       
        $avg = array_sum($pixels) / count($pixels);
        $bits = '';
        foreach ($pixels as $p) {
            $bits .= $p >= $avg ? '1' : '0';
        }
        return $bits;
    }

    public static function hammingDistance(string $a, string $b): int
    {
        if (strlen($a) !== strlen($b) || $a === '') {
            return PHP_INT_MAX;
        }
        $distance = 0;
        for ($i = 0; $i < strlen($a); $i++) {
            if ($a[$i] !== $b[$i]) {
                $distance++;
            }
        }
        return $distance;
    }

    public static function blacklistImage(string $imageHash, string $reason, ?string $phash = null): void
    {
        \DB::table('blacklisted_images')->insertOrIgnore([
            'image_hash' => $imageHash,
            'phash' => $phash,
            'reason' => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public const MEDICAL_CLAIMS = [
        'cures cancer', 'cure cancer', 'cures diabetes', 'cures covid',
        'cures aids', 'cures hiv', 'lose 10lbs', 'lose 10 lbs', 'lose 10 pounds',
        'lose weight fast', 'fda approved', 'miracle cure', 'guaranteed cure',
        '100% cure', 'doctor approved', 'no side effects guaranteed',
    ];

    public const BRAND_BLACKLIST = [
        'nike', 'adidas', 'puma', 'gucci', 'prada', 'louis vuitton', 'lv',
        'apple', 'samsung', 'sony', 'lego', 'disney', 'hello kitty',
    ];

    public static function checkProduct(Product $product, bool $skipHistoryChecks = false): array
    {
        $triggers = [];

        $reportTrigger = self::checkUserReports($product);
        if ($reportTrigger) {
            $triggers[] = $reportTrigger;
        }

        $reviewTrigger = self::checkNegativeReviewKeywords($product);
        if ($reviewTrigger) {
            $triggers[] = $reviewTrigger;
        }

        $returnTrigger = self::checkReturnRate($product);
        if ($returnTrigger) {
            $triggers[] = $returnTrigger;
        }

        $ipTrigger = self::checkIpAndTextMatching($product);
        if ($ipTrigger) {
            $triggers[] = $ipTrigger;
        }

        $medicalTrigger = self::checkMedicalClaims($product);
        if ($medicalTrigger) {
            $triggers[] = $medicalTrigger;
        }

        if (!$skipHistoryChecks) {
            $priceTrigger = self::checkPriceManipulation($product);
            if ($priceTrigger) {
                $triggers[] = $priceTrigger;
            }

            $switchTrigger = self::checkListingSwitch($product);
            if ($switchTrigger) {
                $triggers[] = $switchTrigger;
            }
        }

        $imageTrigger = self::checkImageContent($product);
        if ($imageTrigger) {
            $triggers[] = $imageTrigger;
        }

        if (!empty($triggers)) {
            self::notifyAdmins($product, $triggers);
        }

        return $triggers;
    }

    public static function checkUserReports(Product $product): ?array
    {
        $count = SupportTicket::where('type', 'complaint')
            ->where('subject', 'like', '%' . $product->name . '%')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($count >= self::REPORT_THRESHOLD) {
            return [
                'bucket' => 'user_customer',
                'trigger' => 'high_volume_reports',
                'message' => "Product has received {$count} customer reports in the last 30 days (threshold: " . self::REPORT_THRESHOLD . ").",
                'severity' => 'high',
                'auto_flag' => true,
            ];
        }

        return null;
    }

    public static function checkNegativeReviewKeywords(Product $product): ?array
    {
        $reviews = Review::where('product_id', $product->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->get(['comment']);

        if ($reviews->isEmpty()) {
            return null;
        }

        $negativeCount = 0;
        $matchedKeywords = [];

        foreach ($reviews as $review) {
            $comment = strtolower($review->comment ?? '');
            foreach (self::BANNED_KEYWORDS as $keyword) {
                if (str_contains($comment, $keyword)) {
                    $negativeCount++;
                    $matchedKeywords[] = $keyword;
                }
            }
        }

        if ($negativeCount >= self::NEGATIVE_KEYWORD_THRESHOLD) {
            $unique = array_unique($matchedKeywords);
            return [
                'bucket' => 'user_customer',
                'trigger' => 'negative_keyword_spike',
                'message' => "Sentiment analysis detected {$negativeCount} negative keyword occurrences in recent reviews (keywords: " . implode(', ', array_slice($unique, 0, 5)) . ").",
                'severity' => 'medium',
                'auto_flag' => true,
            ];
        }

        return null;
    }

    public static function checkReturnRate(Product $product): ?array
    {
        $items = OrderItem::with('sellerOrder')
            ->where('product_id', $product->id)
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        $totalOrders = $items->count();
        $returnCount = 0;

        foreach ($items as $item) {
            $sellerOrder = $item->sellerOrder;

            if (
                $sellerOrder &&
                in_array(
                    $sellerOrder->return_status,
                    ['requested', 'approved'],
                    true
                )
            ) {
                $returnCount++;
            }
        }

        if ($totalOrders >= self::MIN_ORDERS_FOR_RETURN_RATE) {
            $rate = $returnCount / $totalOrders;

            if ($rate >= self::RETURN_RATE_THRESHOLD) {
                $percent = round($rate * 100, 1);

                return [
                    'bucket' => 'user_customer',
                    'trigger' => 'return_rate_spike',
                    'message' => "Return rate spike detected: {$returnCount}/{$totalOrders} order items ({$percent}%) have return requests (threshold: " . round(self::RETURN_RATE_THRESHOLD * 100) . "%).",
                    'severity' => 'high',
                    'auto_flag' => true,
                ];
            }
        }

        return null;
    }

    public static function checkIpAndTextMatching(Product $product): ?array
    {
        $text = strtolower($product->name . ' ' . ($product->description ?? ''));
        $matchedBrands = [];

        foreach (self::BRAND_BLACKLIST as $brand) {
            if (preg_match('/\\b' . preg_quote($brand, '/') . '\\b/i', $text)) {
                $matchedBrands[] = $brand;
            }
        }

        $counterfeitTerms = ['fake', 'counterfeit', 'replica', 'bootleg', 'knockoff'];
        $matchedCounterfeitTerms = [];

        foreach ($counterfeitTerms as $term) {
            if (preg_match('/\\b' . preg_quote($term, '/') . '\\b/i', $text)) {
                $matchedCounterfeitTerms[] = $term;
            }
        }

        $description = strtolower($product->description ?? '');
        $wordCount = str_word_count($description);
        $productName = strtolower(trim((string) $product->name));
        $isKeywordStuffing = $wordCount > 0
            && $productName !== ''
            && substr_count($description, $productName) > max(5, $wordCount * 0.1);

        $reasons = [];

        if ($matchedCounterfeitTerms !== []) {
            $brandContext = $matchedBrands !== []
                ? ' involving brand reference(s): ' . implode(', ', array_unique($matchedBrands))
                : '';
            $reasons[] = 'counterfeit-related wording (' . implode(', ', $matchedCounterfeitTerms) . ')' . $brandContext;
        }

        if ($isKeywordStuffing) {
            $reasons[] = 'possible keyword stuffing';
        }

        if ($reasons === []) {
            // Mentioning a brand is not evidence that a product is counterfeit.
            return null;
        }

        return [
            'bucket' => 'automated_bot',
            'trigger' => 'ip_text_matching',
            'message' => 'Listing text requires compliance review: ' . implode('; ', $reasons) . '.',
            'severity' => 'high',
            // Text-only brand/counterfeit signals are not sufficient to hide a
            // listing automatically. Keyword stuffing retains its existing hold.
            'auto_flag' => $isKeywordStuffing,
            'manual_review' => $matchedCounterfeitTerms !== [],
        ];
    }

    public static function checkMedicalClaims(Product $product): ?array
    {
        $text = strtolower($product->name . ' ' . ($product->description ?? ''));
        $matched = [];

        foreach (self::MEDICAL_CLAIMS as $claim) {
            if (str_contains($text, $claim)) {
                $matched[] = $claim;
            }
        }

        if (!empty($matched)) {
            return [
                'bucket' => 'automated_bot',
                'trigger' => 'medical_claim_violation',
                'message' => 'Potentially prohibited medical/health claim detected: "' . implode('", "', array_slice($matched, 0, 3)) . '". Admin review is required.',
                'severity' => 'critical',
                'auto_flag' => true,
            ];
        }

        return null;
    }

    public static function checkPriceManipulation(Product $product): ?array
    {
        $previous = DB::table('product_price_history')
            ->where('product_id', $product->id)
            ->orderBy('changed_at', 'desc')
            ->first();

        if (!$previous) {
            return null;
        }

        // Integer centavos are the only source of truth for price comparison.
        $oldPriceMinor = (int) $previous->price_minor;
        $newPriceMinor = (int) $product->price_minor;

        if ($oldPriceMinor <= 0 || $newPriceMinor <= 0) {
            return null;
        }

        $dropPercent = (($oldPriceMinor - $newPriceMinor) / $oldPriceMinor) * 100;

        // â‚±100.00 = 10,000 centavos.
        if ($dropPercent >= self::PRICE_DROP_PERCENT_THRESHOLD && $newPriceMinor < 10000) {
            $oldPrice = $oldPriceMinor / 100;
            $newPrice = $newPriceMinor / 100;

            return [
                'bucket' => 'automated_bot',
                'trigger' => 'price_spam',
                'message' => "Possible price spam: price dropped from â‚±" . number_format($oldPrice, 2) . " to â‚±" . number_format($newPrice, 2) . " (" . round($dropPercent, 1) . "% decrease).",
                'severity' => 'high',
                'auto_flag' => true,
            ];
        }

        return null;
    }

    public static function checkListingSwitch(Product $product): ?array
    {
        $previous = DB::table('product_listing_snapshots')
            ->where('product_id', $product->id)
            ->orderBy('snapshot_at', 'desc')
            ->first();

        if (!$previous) {
            return null;
        }

        $oldWords = array_unique(str_word_count(strtolower($previous->name . ' ' . $previous->description), 1));
        $newWords = array_unique(str_word_count(strtolower($product->name . ' ' . ($product->description ?? '')), 1));

        $oldFiltered = array_filter($oldWords, fn($w) => strlen($w) > 3);
        $newFiltered = array_filter($newWords, fn($w) => strlen($w) > 3);

        if (empty($oldFiltered)) {
            return null;
        }

        $overlap = count(array_intersect($oldFiltered, $newFiltered)) / count($oldFiltered);

        if ($overlap < self::LISTING_SWITCH_KEYWORD_CHANGE) {
            return [
                'bucket' => 'automated_bot',
                'trigger' => 'listing_switch',
                'message' => 'Possible listing switch detected: ' . round((1 - $overlap) * 100, 1) . '% of original content changed. This may be an attempt to inherit previous positive reviews.',
                'severity' => 'high',
                'auto_flag' => true,
            ];
        }

        return null;
    }

    public static function notifyAdmins(Product $product, array $triggers): void
    {
        $autoFlag = collect($triggers)->contains(fn ($trigger) => $trigger['auto_flag'] ?? false);
        $wasAlreadyAutoFlagged = $product->compliance_status === 'auto_flagged';

        $admins = User::whereHas('roles', fn ($query) => $query->where('name', 'admin'))->get();

        $summary = collect($triggers)->map(function ($trigger) {
            return '[' . strtoupper($trigger['severity'] ?? 'info') . '] '
                . ($trigger['message'] ?? 'Compliance review requested.');
        })->implode("\n");

        $title = 'Automated Compliance Alert: ' . $product->name;

        if ($autoFlag) {
            $imageTrigger = collect($triggers)->firstWhere('trigger', 'image_content_violation');
            $flagReason = 'Automated compliance scan flagged this product for admin review. Triggers: '
                . collect($triggers)->pluck('trigger')->filter()->implode(', ');
            $adminNotes = $summary;

            if ($imageTrigger && !empty($imageTrigger['reasons'])) {
                $adminNotes .= "\n\nImage scan findings:\n- "
                    . implode("\n- ", $imageTrigger['reasons']);
            }

            $product->update([
                'compliance_status' => 'auto_flagged',
                'flagged_reason' => $flagReason,
                'admin_notes' => $adminNotes,
                'flagged_at' => $wasAlreadyAutoFlagged && $product->flagged_at
                    ? $product->flagged_at
                    : now(),
            ]);

            // A product-level finding must never change the seller account state.
            // Seller suspension remains an explicit admin action in ComplianceController.
            $sellerUser = $product->sellerUser;

            if ($sellerUser && !$wasAlreadyAutoFlagged) {
                Notification::create([
                    'user_id' => $sellerUser->id,
                    'title' => 'Product Temporarily Hidden for Compliance Review',
                    'message' => "Your product \"{$product->name}\" has been placed on hold while an administrator reviews an automated compliance finding. Your seller account remains active. You will be notified after the review.",
                    'type' => 'product',
                    'link' => route('seller.products.edit', $product),
                ]);
            }
        }

        foreach ($admins as $admin) {
            $imageTrigger = collect($triggers)->firstWhere('trigger', 'image_content_violation');
            $message = "Product \"{$product->name}\" triggered " . count($triggers)
                . " compliance check(s):\n\n" . $summary;

            if ($imageTrigger && !empty($imageTrigger['reasons'])) {
                $message .= "\n\nImage scan findings:\n- "
                    . implode("\n- ", $imageTrigger['reasons']);
            }

            $message .= $autoFlag
                ? "\n\nThe product was automatically placed on hold for admin review. The seller account was not suspended."
                : "\n\nManual review requested. No automatic product or seller status change was made.";

            Notification::create([
                'user_id' => $admin->id,
                'title' => $title,
                'message' => $message,
                'type' => 'compliance_alert',
                'link' => route('admin.compliance.show', $product),
            ]);
        }

        Log::info(
            "Compliance alert for product #{$product->id} ({$product->name}): "
            . count($triggers) . ' trigger(s)'
            . ($autoFlag ? ' [PRODUCT AUTO-FLAGGED]' : ' [MANUAL REVIEW ONLY]')
        );
    }

    public static function recordPriceSnapshot(Product $product): void
    {
        $last = DB::table('product_price_history')
            ->where('product_id', $product->id)
            ->orderBy('changed_at', 'desc')
            ->first();

        $currentPriceMinor = (int) $product->price_minor;

        $lastPriceMinor = $last
            ? (int) $last->price_minor
            : null;

        if (!$last || $lastPriceMinor !== $currentPriceMinor) {
            DB::table('product_price_history')->insert([
                'product_id' => $product->id,
                'price_minor' => $currentPriceMinor,
                'changed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $lastSnapshot = DB::table('product_listing_snapshots')
            ->where('product_id', $product->id)
            ->orderBy('snapshot_at', 'desc')
            ->first();

        if (!$lastSnapshot
            || $lastSnapshot->name !== $product->name
            || $lastSnapshot->description !== ($product->description ?? '')) {
            DB::table('product_listing_snapshots')->insert([
                'product_id' => $product->id,
                'name' => $product->name,
                'description' => $product->description ?? '',
                'snapshot_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public static function scanAllProducts(): int
    {
        $count = 0;
        Product::where('compliance_status', 'approved')
            ->chunk(50, function ($products) use (&$count) {
                foreach ($products as $product) {
                    $triggers = self::checkProduct($product);
                    if (!empty($triggers)) {
                        $count++;
                    }
                }
            });
        return $count;
    }
}