<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    public const IMAGE_BANNED_KEYWORDS = [
        'weapon', 'gun', 'pistol', 'rifle', 'bullet', 'ammo', 'drug', 'cocaine',
        'heroin', 'meth', 'nude', 'nsfw', 'xxx', 'porn', 'gore', 'blood',
        'violence', 'explosive', 'bomb', 'tnt', 'kill', 'murder', 'assault',
        'terror', 'isis', 'al-qaeda', 'marijuana', 'weed', 'cannabis',
        'methamphetamine', 'fentanyl', 'ecstasy', 'lsd', 'ketamine',
        'sex', 'pussy', 'dick', 'penis', 'vagina', 'breast', 'boob',
        'hentai', 'fetish', 'bdsm', 'rape', 'child', 'minor', 'infant',
        'baby', 'toddler', 'kid', 'underage', 'jailbait', 'loli',
        'racist', 'nazi', 'kkk', 'hitler', 'slave',
        'counterfeit', 'replica', 'fake', 'bootleg', 'knockoff',
        'stolen', 'illegal', 'contraband', 'smuggle',
    ];

    public const NUDITY_KEYWORDS = [
        'nude', 'naked', 'nsfw', 'porn', 'xxx', 'sex', 'pussy', 'breast',
        'boob', 'penis', 'vagina', 'hentai', 'fetish', 'bdsm', 'topless',
        'bottomless', 'lingerie', 'underwear', 'bikini', 'swimsuit',
        'undressed', 'strip', 'stripper', 'escort', 'prostitute',
    ];

    public const VIOLENCE_KEYWORDS = [
        'weapon', 'gun', 'pistol', 'rifle', 'shotgun', 'bullet', 'ammo',
        'grenade', 'bomb', 'tnt', 'explosive', 'knife', 'machete', 'sword',
        'blood', 'gore', 'wound', 'corpse', 'dead body', 'murder', 'kill',
        'assault', 'rape', 'torture', 'beheading', 'fight', 'war',
        'soldier', 'terrorist', 'terror', 'isis', 'al-qaeda',
        'baril', 'sable', 'pistola', 'sandata', 'armadong',
    ];

    public const DRUGS_KEYWORDS = [
        'drug', 'cocaine', 'heroin', 'meth', 'methamphetamine', 'fentanyl',
        'ecstasy', 'mdma', 'lsd', 'ketamine', 'marijuana', 'weed', 'cannabis',
        'thc', 'cbd', 'pill', 'substance', 'narcotic', 'dealer',
        'shabu', 'bato', 'tiktok',
    ];

    public const BRAND_COUNTERFEIT_KEYWORDS = [
        'nike', 'adidas', 'puma', 'gucci', 'prada', 'louis vuitton', 'lv',
        'apple', 'samsung', 'sony', 'lego', 'disney', 'hello kitty',
        'supreme', 'rolex', 'chanel', 'hermes', 'versace', 'balenciaga',
        'coach', 'michael kors', 'ray-ban', 'oakley', 'jordans',
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
        $images = $product->images;
        if ($images->isEmpty() && $product->image) {
            $images = collect([(object)['path' => $product->image, 'alt_text' => $product->alt_text]]);
        }
        if ($images->isEmpty()) {
            return null;
        }

        $reasons = [];
        $severities = [];
        $blacklistHashes = self::getBlacklistedImageHashes();

        foreach ($images as $img) {
            $imagePath = storage_path('app/public/' . $img->path);
            $fileName = strtolower(pathinfo($img->path, PATHINFO_FILENAME));
            $altText = strtolower($img->alt_text ?? $product->alt_text ?? $product->name ?? '');

            $deobfuscated = self::deobfuscateText($fileName . ' ' . $altText);
            $combinedText = $deobfuscated . ' ' . $fileName . ' ' . $altText;

            foreach (self::NUDITY_KEYWORDS as $keyword) {
                if (str_contains($combinedText, $keyword)) {
                    $reasons[] = "image references nudity/sexual content ('{$keyword}')";
                    $severities[] = 'critical';
                    break;
                }
            }

            foreach (self::VIOLENCE_KEYWORDS as $keyword) {
                if (str_contains($combinedText, $keyword)) {
                    $reasons[] = "image references violence/weapons ('{$keyword}')";
                    $severities[] = 'critical';
                    break;
                }
            }

            foreach (self::DRUGS_KEYWORDS as $keyword) {
                if (str_contains($combinedText, $keyword)) {
                    $reasons[] = "image references drugs/illegal substances ('{$keyword}')";
                    $severities[] = 'critical';
                    break;
                }
            }

            foreach (self::BRAND_COUNTERFEIT_KEYWORDS as $keyword) {
                if (str_contains($combinedText, $keyword)) {
                    $reasons[] = "image references branded/counterfeit product ('{$keyword}')";
                    $severities[] = 'high';
                    break;
                }
            }

            if (preg_match('/\b(underage|child|minor|kid|baby|toddler|infant)\b/i', $combinedText)) {
                $reasons[] = "image references minors (CSAM risk)";
                $severities[] = 'critical';
            }

            $metadataFindings = self::analyzeImageMetadata($imagePath, $fileName, $altText);
            foreach ($metadataFindings as $finding) {
                $reasons[] = $finding['reason'];
                $severities[] = $finding['severity'];
            }

            if (file_exists($imagePath)) {
                $hash = self::hashImage($imagePath);
                if (in_array($hash, $blacklistHashes, true)) {
                    $reasons[] = 'image matches a blacklisted hash (counterfeit/illegal content)';
                    $severities[] = 'critical';
                }

                $phash = self::perceptualHash($imagePath);
                if ($phash && self::matchesPerceptualBlacklist($phash)) {
                    $reasons[] = 'image is a near-duplicate of a blacklisted image';
                    $severities[] = 'critical';
                }

                $pixelFindings = self::analyzePixels($imagePath);
                foreach ($pixelFindings as $finding) {
                    $reasons[] = $finding['reason'];
                    $severities[] = $finding['severity'];
                }

                $sizeFindings = self::analyzeFileSize($imagePath);
                foreach ($sizeFindings as $finding) {
                    $reasons[] = $finding['reason'];
                    $severities[] = $finding['severity'];
                }
            } else {
                $reasons[] = "image file not found on disk: " . basename($imagePath);
                $severities[] = 'high';
            }
        }

        $category = $product->category;
        if ($category && in_array(strtolower($category->name), self::RESTRICTED_CATEGORIES, true)) {
            $reasons[] = "product is in a restricted category ('{$category->name}') that requires manual review";
            $severities[] = 'high';
        }

        if (!empty($reasons)) {
            $hasCritical = in_array('critical', $severities, true);
            return [
                'bucket' => 'automated_bot',
                'trigger' => 'image_content_violation',
                'message' => 'Image scan flagged this listing: ' . implode('; ', array_slice(array_unique($reasons), 0, 4)) . '.',
                'severity' => $hasCritical ? 'critical' : max($severities),
                'auto_flag' => true,
                'auto_suspend' => $hasCritical,
                'reasons' => array_values(array_unique($reasons)),
            ];
        }

        return null;
    }

    public static function deobfuscateText(string $text): string
    {
        $leet = [
            '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's',
            '7' => 't', '8' => 'b', '9' => 'g', '@' => 'a', '$' => 's',
            '!' => 'i', '+' => 't', '|' => 'i', '(' => 'c', ')' => 'o',
        ];
        $normalized = strtr($text, $leet);
        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return trim($normalized);
    }

    protected static function analyzeImageMetadata(string $imagePath, string $fileName, string $altText): array
    {
        $findings = [];
        if (!file_exists($imagePath)) {
            return $findings;
        }

        try {
            $exif = function_exists('exif_read_data') ? @exif_read_data($imagePath) : false;
        } catch (\Throwable $e) {
            $exif = false;
        }

        if (is_array($exif) && !empty($exif)) {
            if (!empty($exif['GPS'])) {
                $findings[] = [
                    'reason' => 'image contains GPS location data (privacy risk)',
                    'severity' => 'medium',
                ];
            }
            $cameraMake = strtolower($exif['Make'] ?? '');
            $cameraModel = strtolower($exif['Model'] ?? '');
            $software = strtolower($exif['Software'] ?? '');

            $suspiciousMakers = ['darktable', 'gimp', 'photoshop'];
            foreach ($suspiciousMakers as $m) {
                if (str_contains($software, $m) || str_contains($cameraMake, $m)) {
                    $findings[] = [
                        'reason' => "image appears edited with '{$m}' (possible deepfake/manipulation)",
                        'severity' => 'medium',
                    ];
                    break;
                }
            }
        }

        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowedExts, true)) {
            $findings[] = [
                'reason' => "image has unusual file extension '{$ext}' (possible malware/spoofing)",
                'severity' => 'high',
            ];
        }

        $mime = function_exists('mime_content_type') ? @mime_content_type($imagePath) : false;
        if ($mime && !str_starts_with($mime, 'image/')) {
            $findings[] = [
                'reason' => "file is not a real image (detected MIME: {$mime})",
                'severity' => 'critical',
            ];
        }

        $sizeBytes = @getimagesize($imagePath);
        if ($sizeBytes !== false) {
            $width = $sizeBytes[0] ?? 0;
            $height = $sizeBytes[1] ?? 0;
            if ($width > 0 && $height > 0) {
                $ratio = $width / $height;
                if ($ratio > 5 || $ratio < 0.2) {
                    $findings[] = [
                        'reason' => "image has unusual aspect ratio ({$width}x{$height}) — may be cropped banned content",
                        'severity' => 'medium',
                    ];
                }
                if ($width < 50 || $height < 50) {
                    $findings[] = [
                        'reason' => "image is too small ({$width}x{$height}) — likely a placeholder or icon, not a real product photo",
                        'severity' => 'low',
                    ];
                }
            }
        }

        return $findings;
    }

    protected static function analyzeFileSize(string $imagePath): array
    {
        $findings = [];
        $size = @filesize($imagePath);
        if ($size === false) {
            return $findings;
        }
        if ($size > 8 * 1024 * 1024) {
            $findings[] = [
                'reason' => 'image file is unusually large (>8MB) — possible hidden data or non-image payload',
                'severity' => 'medium',
            ];
        }
        return $findings;
    }

    protected static function analyzePixels(string $imagePath): array
    {
        $findings = [];
        if (!function_exists('getimagesize')) {
            return $findings;
        }
        $info = @getimagesize($imagePath);
        if ($info === false) {
            return $findings;
        }
        $width = $info[0];
        $height = $info[1];
        $mime = $info['mime'] ?? '';

        try {
            $img = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($imagePath),
                'image/png' => @imagecreatefrompng($imagePath),
                'image/gif' => @imagecreatefromgif($imagePath),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($imagePath) : false,
                default => false,
            };
        } catch (\Throwable $e) {
            $img = false;
        }

        if (!$img) {
            return $findings;
        }

        $samplePoints = 200;
        $redDominant = 0;
        $darkCount = 0;
        $totalSampled = 0;

        for ($i = 0; $i < $samplePoints; $i++) {
            $x = mt_rand(0, $width - 1);
            $y = mt_rand(0, $height - 1);
            $rgb = imagecolorat($img, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            $totalSampled++;

            if ($r > 150 && $r > $g * 1.5 && $r > $b * 1.5) {
                $redDominant++;
            }
            if ($r < 30 && $g < 30 && $b < 30) {
                $darkCount++;
            }
        }

        imagedestroy($img);

        if ($totalSampled > 0) {
            $darkPct = $darkCount / $totalSampled;
            $redPct = $redDominant / $totalSampled;

            if ($redPct > 0.30) {
                $findings[] = [
                    'reason' => sprintf('image has high red-dominance (%.0f%%) — possible blood/violence content', $redPct * 100),
                    'severity' => 'high',
                ];
            }

            if ($darkPct > 0.70) {
                $findings[] = [
                    'reason' => sprintf('image is very dark (%.0f%% near-black pixels) — possible hidden content', $darkPct * 100),
                    'severity' => 'medium',
                ];
            }
        }

        return $findings;
    }

    public static function hashImage(string $path): string
    {
        if (!file_exists($path)) {
            return '';
        }
        return hash_file('sha256', $path);
    }

    public static function perceptualHash(string $path): string
    {
        if (!function_exists('getimagesize') || !file_exists($path)) {
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
        imagedestroy($img);
        imagedestroy($small);
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

    protected static function matchesPerceptualBlacklist(string $phash): bool
    {
        $blacklisted = \DB::table('blacklisted_images')
            ->whereNotNull('phash')
            ->pluck('phash')
            ->toArray();
        foreach ($blacklisted as $b) {
            if (self::hammingDistance($phash, $b) <= 5) {
                return true;
            }
        }
        return false;
    }

    protected static function getBlacklistedImageHashes(): array
    {
        return \DB::table('blacklisted_images')->pluck('image_hash')->toArray();
    }

    protected static function isExifGpsEnabled(string $path): bool
    {
        if (!function_exists('exif_read_data') || !file_exists($path)) {
            return false;
        }
        try {
            $exif = @exif_read_data($path, 'GPS');
            return !empty($exif);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected static function hasFaceInImage(string $path): bool
    {
        return false;
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

        $textTrigger = self::checkProductText($product);
        if ($textTrigger) {
            $triggers[] = $textTrigger;
        }

        $categoryTrigger = self::checkRestrictedCategory($product);
        if ($categoryTrigger) {
            $triggers[] = $categoryTrigger;
        }

        if (!empty($triggers)) {
            self::notifyAdmins($product, $triggers);
        }

        return $triggers;
    }

    public static function checkProductText(Product $product): ?array
    {
        $text = strtolower($product->name . ' ' . ($product->description ?? ''));
        $matched = [];
        $matchedSet = [];

        $keywordGroups = [
            'violence/weapons' => self::VIOLENCE_KEYWORDS,
            'drugs/illegal substances' => self::DRUGS_KEYWORDS,
            'banned items' => self::BANNED_KEYWORDS,
            'nudity/sexual content' => self::NUDITY_KEYWORDS,
        ];

        foreach ($keywordGroups as $label => $keywords) {
            foreach ($keywords as $keyword) {
                if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $text)) {
                    if (!in_array($keyword, $matchedSet, true)) {
                        $matchedSet[] = $keyword;
                        $matched[] = "{$label} ('{$keyword}')";
                    }
                }
            }
        }

        if (!empty($matched)) {
            return [
                'bucket' => 'automated_bot',
                'trigger' => 'restricted_product_text',
                'message' => 'Product name/description contains restricted keywords (' . implode(', ', array_slice($matched, 0, 5)) . ').',
                'severity' => 'critical',
                'auto_flag' => true,
                'auto_suspend' => true,
                'reasons' => array_values(array_unique($matched)),
            ];
        }

        return null;
    }

    public static function checkRestrictedCategory(Product $product): ?array
    {
        $category = $product->category;
        if (!$category) {
            return null;
        }

        $categoryName = strtolower($category->name);

        if (in_array($categoryName, self::BANNED_CATEGORIES, true)) {
            return [
                'bucket' => 'automated_bot',
                'trigger' => 'banned_category',
                'message' => "Product is in a banned category ('{$category->name}') and cannot be listed on the platform.",
                'severity' => 'critical',
                'auto_flag' => true,
                'auto_suspend' => true,
                'reasons' => ["product is in banned category '{$category->name}'"],
            ];
        }

        return null;
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
        $items = OrderItem::where('product_id', $product->id)->get();

        if ($items->isEmpty()) {
            return null;
        }

        $totalOrders = $items->count();
        $returnCount = 0;

        foreach ($items as $item) {
            $order = $item->order;
            if ($order && ($order->return_status === 'requested' || $order->return_status === 'approved')) {
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
                    'message' => "Return rate spike detected: {$returnCount}/{$totalOrders} orders ({$percent}%) have return requests (threshold: " . round(self::RETURN_RATE_THRESHOLD * 100) . "%).",
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
            if (preg_match('/\b' . preg_quote($brand, '/') . '\b/i', $text)) {
                $matchedBrands[] = $brand;
            }
        }

        $wordCount = str_word_count($product->description ?? '');
        $isKeywordStuffing = $wordCount > 0
            && substr_count(strtolower($product->description ?? ''), $product->name) > max(5, $wordCount * 0.1);

        if (!empty($matchedBrands) || $isKeywordStuffing) {
            $reasons = [];
            if (!empty($matchedBrands)) {
                $reasons[] = 'unauthorized brand usage (' . implode(', ', array_unique($matchedBrands)) . ')';
            }
            if ($isKeywordStuffing) {
                $reasons[] = 'possible keyword stuffing';
            }
            return [
                'bucket' => 'automated_bot',
                'trigger' => 'ip_text_matching',
                'message' => 'IP/Text scan flagged this listing: ' . implode('; ', $reasons) . '.',
                'severity' => 'high',
                'auto_flag' => true,
            ];
        }

        return null;
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
                'message' => 'Prohibited medical/health claim detected: "' . implode('", "', array_slice($matched, 0, 3)) . '". Listing auto-suspended pending admin review.',
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

        $oldPrice = (float) $previous->price;
        $newPrice = (float) $product->price;

        if ($oldPrice <= 0 || $newPrice <= 0) {
            return null;
        }

        $dropPercent = (($oldPrice - $newPrice) / $oldPrice) * 100;

        if ($dropPercent >= self::PRICE_DROP_PERCENT_THRESHOLD && $newPrice < 100) {
            return [
                'bucket' => 'automated_bot',
                'trigger' => 'price_spam',
                'message' => "Possible price spam: price dropped from ₱" . number_format($oldPrice, 2) . " to ₱" . number_format($newPrice, 2) . " (" . round($dropPercent, 1) . "% decrease).",
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
        $autoFlag = collect($triggers)->contains(fn($t) => $t['auto_flag'] ?? false);
        $highSeverity = collect($triggers)->contains(fn($t) => in_array($t['severity'] ?? '', ['high', 'critical']));
        $autoSuspend = collect($triggers)->contains(fn($t) => $t['auto_suspend'] ?? false);
        $criticalImage = collect($triggers)->firstWhere('trigger', 'image_content_violation')
            && collect($triggers)->firstWhere('trigger', 'image_content_violation')['severity'] === 'critical';

        $admins = User::where('role', 'admin')->get();

        $summary = collect($triggers)->map(function ($t) {
            return '• [' . strtoupper($t['severity']) . '] ' . $t['message'];
        })->implode("\n");

        $title = 'Automated Compliance Alert: ' . $product->name;

        if ($autoFlag) {
            $imageTrigger = collect($triggers)->firstWhere('trigger', 'image_content_violation');
            $flagReason = 'Automated compliance scan flagged this product. Triggers: ' . collect($triggers)->pluck('trigger')->implode(', ');
            $adminNotes = $summary;
            if ($imageTrigger && !empty($imageTrigger['reasons'])) {
                $adminNotes .= "\n\nImage scan findings:\n• " . implode("\n• ", $imageTrigger['reasons']);
            }

            $product->update([
                'compliance_status' => 'auto_flagged',
                'flagged_reason' => $flagReason,
                'admin_notes' => $adminNotes,
                'flagged_at' => now(),
            ]);

            if ($autoSuspend && $product->seller) {
                $product->seller->update(['status' => User::STATUS_SUSPENDED]);
                Notification::create([
                    'user_id' => $product->seller->id,
                    'title' => 'CRITICAL: Account Auto-Suspended',
                    'message' => "Your seller account has been automatically suspended and the product \"{$product->name}\" has been hidden from buyers because the image scan detected critical violations (nudity, violence, drugs, CSAM, or other illegal content). Please contact admin support immediately.",
                    'type' => 'account',
                ]);
            }
        }

        foreach ($admins as $admin) {
            $imageTrigger = collect($triggers)->firstWhere('trigger', 'image_content_violation');
            $msg = "Product \"{$product->name}\" triggered " . count($triggers) . " compliance check(s):\n\n" . $summary;
            if ($imageTrigger && !empty($imageTrigger['reasons'])) {
                $msg .= "\n\nImage scan findings:\n• " . implode("\n• ", $imageTrigger['reasons']);
            }
            $msg .= "\n\n" . ($autoSuspend ? '🚨 SELLER AUTO-SUSPENDED (critical image violation).' : ($autoFlag ? '⚠ Product has been auto-flagged for review.' : ''));

            Notification::create([
                'user_id' => $admin->id,
                'title' => $title,
                'message' => $msg,
                'type' => 'compliance_alert',
                'link' => route('admin.compliance.show', $product),
            ]);
        }

        Log::info("Compliance alert for product #{$product->id} ({$product->name}): " . count($triggers) . " trigger(s)" . ($autoSuspend ? ' [SELLER AUTO-SUSPENDED]' : ''));
    }

    public static function recordPriceSnapshot(Product $product): void
    {
        $last = DB::table('product_price_history')
            ->where('product_id', $product->id)
            ->orderBy('changed_at', 'desc')
            ->first();

        if (!$last || (float) $last->price !== (float) $product->price) {
            DB::table('product_price_history')->insert([
                'product_id' => $product->id,
                'price' => $product->price,
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
