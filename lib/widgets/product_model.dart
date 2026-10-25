class ProductModel {
  final int id;

  final String name;

  final String description;

  final double price;

  final double effectivePrice;

  final String imageUrl;

  final String secondaryImageUrl;

  final double rating;

  final int reviewsCount;

  final int soldCount;

  final int stock;

  final int categoryId;

  final String categoryName;

  ProductModel({
    required this.id,
    required this.name,
    required this.description,
    required this.price,
    required this.effectivePrice,
    required this.imageUrl,
    required this.secondaryImageUrl,
    required this.rating,
    required this.reviewsCount,
    required this.soldCount,
    required this.stock,
    required this.categoryId,
    required this.categoryName,
  });

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    // ============================================================
    // CATEGORY
    // ============================================================

    final dynamic category = json['category'];

    int parsedCategoryId = 0;

    String parsedCategoryName = '';

    if (category is Map) {
      final categoryMap = Map<String, dynamic>.from(category);

      parsedCategoryId = int.tryParse(categoryMap['id']?.toString() ?? '') ?? 0;

      parsedCategoryName = categoryMap['name']?.toString().trim() ?? '';
    } else {
      parsedCategoryId =
          int.tryParse(json['category_id']?.toString() ?? '') ?? 0;

      parsedCategoryName = json['category_name']?.toString().trim() ?? '';
    }

    // ============================================================
    // PRICE
    // ============================================================

    final double parsedPrice = _parsePrice(json);

    final double parsedEffectivePrice = _parseEffectivePrice(
      json,
      fallbackPrice: parsedPrice,
    );

    // ============================================================
    // IMAGES
    // ============================================================

    final String parsedImageUrl = _parsePrimaryImage(json);

    final String parsedSecondaryImageUrl = _parseSecondaryImage(json);

    // ============================================================
    // MODEL
    // ============================================================

    return ProductModel(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,

      name: json['name']?.toString().trim() ?? '',

      description: json['description']?.toString().trim() ?? '',

      price: parsedPrice,

      effectivePrice: parsedEffectivePrice,

      imageUrl: parsedImageUrl,

      secondaryImageUrl: parsedSecondaryImageUrl,

      rating: double.tryParse(json['rating']?.toString() ?? '0') ?? 0.0,

      reviewsCount: int.tryParse(json['reviews_count']?.toString() ?? '0') ?? 0,

      soldCount:
          int.tryParse((json['sold_count'] ?? json['sold'] ?? 0).toString()) ??
          0,

      stock: int.tryParse(json['stock']?.toString() ?? '0') ?? 0,

      categoryId: parsedCategoryId,

      categoryName: parsedCategoryName,
    );
  }

  // ============================================================
  // PRICE
  // ============================================================

  static double _parsePrice(Map<String, dynamic> json) {
    final dynamic price = json['price'];

    if (price != null) {
      final parsed = double.tryParse(price.toString());

      if (parsed != null) {
        return parsed;
      }
    }

    final dynamic priceMinor = json['price_minor'];

    if (priceMinor != null) {
      final parsed = double.tryParse(priceMinor.toString());

      if (parsed != null) {
        return parsed / 100;
      }
    }

    return 0.0;
  }

  // ============================================================
  // EFFECTIVE PRICE
  // ============================================================

  static double _parseEffectivePrice(
    Map<String, dynamic> json, {
    required double fallbackPrice,
  }) {
    final dynamic effectivePrice = json['effective_price'];

    if (effectivePrice != null) {
      final parsed = double.tryParse(effectivePrice.toString());

      if (parsed != null) {
        return parsed;
      }
    }

    final dynamic effectivePriceMinor = json['effective_price_minor'];

    if (effectivePriceMinor != null) {
      final parsed = double.tryParse(effectivePriceMinor.toString());

      if (parsed != null) {
        return parsed / 100;
      }
    }

    return fallbackPrice;
  }

  // ============================================================
  // PRIMARY IMAGE
  // ============================================================

  static String _parsePrimaryImage(Map<String, dynamic> json) {
    // ==========================================================
    // 1. TOP-LEVEL image_url
    // ==========================================================
    //
    // Preferred API field.
    //

    final String imageUrl = _cleanUrl(json['image_url']);

    if (_isAbsoluteNetworkUrl(imageUrl)) {
      return imageUrl;
    }

    // ==========================================================
    // 2. TOP-LEVEL primary_image_url
    // ==========================================================

    final String primaryImageUrl = _cleanUrl(json['primary_image_url']);

    if (_isAbsoluteNetworkUrl(primaryImageUrl)) {
      return primaryImageUrl;
    }

    // ==========================================================
    // 3. images[]
    // ==========================================================
    //
    // IMPORTANT:
    // Laravel currently returns the full Supabase URL
    // inside images[] while the top-level "image" field can
    // contain only:
    //
    // products/example.jpg
    //
    // So images[] MUST be checked before json['image'].
    //

    final dynamic images = json['images'];

    if (images is List && images.isNotEmpty) {
      Map<String, dynamic>? firstImage;

      Map<String, dynamic>? primaryImage;

      for (final dynamic item in images) {
        if (item is! Map) {
          continue;
        }

        final Map<String, dynamic> imageMap = Map<String, dynamic>.from(item);

        firstImage ??= imageMap;

        final dynamic isPrimary = imageMap['is_primary'];

        if (_isTrue(isPrimary)) {
          primaryImage = imageMap;

          break;
        }
      }

      final Map<String, dynamic>? selectedImage = primaryImage ?? firstImage;

      if (selectedImage != null) {
        final String selectedUrl = _extractImageUrl(selectedImage);

        if (_isAbsoluteNetworkUrl(selectedUrl)) {
          return selectedUrl;
        }
      }
    }

    // ==========================================================
    // 4. LEGACY image FIELD
    // ==========================================================
    //
    // Only accept this when it already contains a full
    // http:// or https:// URL.
    //
    // Relative paths like:
    //
    // products/example.jpg
    //
    // are intentionally ignored because Image.network()
    // cannot load them directly.
    //

    final String image = _cleanUrl(json['image']);

    if (_isAbsoluteNetworkUrl(image)) {
      return image;
    }

    return '';
  }

  // ============================================================
  // SECONDARY IMAGE
  // ============================================================

  static String _parseSecondaryImage(Map<String, dynamic> json) {
    final String explicitSecondary = _cleanUrl(json['secondary_image_url']);

    if (_isAbsoluteNetworkUrl(explicitSecondary)) {
      return explicitSecondary;
    }

    final dynamic images = json['images'];

    if (images is! List || images.length < 2) {
      return '';
    }

    final List<Map<String, dynamic>> parsedImages = <Map<String, dynamic>>[];

    for (final dynamic item in images) {
      if (item is Map) {
        parsedImages.add(Map<String, dynamic>.from(item));
      }
    }

    if (parsedImages.length < 2) {
      return '';
    }

    // ==========================================================
    // PRIMARY IMAGE FIRST
    // ==========================================================

    parsedImages.sort((a, b) {
      final bool aPrimary = _isTrue(a['is_primary']);

      final bool bPrimary = _isTrue(b['is_primary']);

      if (aPrimary && !bPrimary) {
        return -1;
      }

      if (!aPrimary && bPrimary) {
        return 1;
      }

      final int aSort = int.tryParse(a['sort_order']?.toString() ?? '0') ?? 0;

      final int bSort = int.tryParse(b['sort_order']?.toString() ?? '0') ?? 0;

      return aSort.compareTo(bSort);
    });

    for (int index = 1; index < parsedImages.length; index++) {
      final String secondaryUrl = _extractImageUrl(parsedImages[index]);

      if (_isAbsoluteNetworkUrl(secondaryUrl)) {
        return secondaryUrl;
      }
    }

    return '';
  }

  // ============================================================
  // EXTRACT IMAGE URL
  // ============================================================

  static String _extractImageUrl(Map<String, dynamic> image) {
    // ==========================================================
    // url
    // ==========================================================

    final String url = _cleanUrl(image['url']);

    if (_isAbsoluteNetworkUrl(url)) {
      return url;
    }

    // ==========================================================
    // image_url
    // ==========================================================

    final String imageUrl = _cleanUrl(image['image_url']);

    if (_isAbsoluteNetworkUrl(imageUrl)) {
      return imageUrl;
    }

    // ==========================================================
    // primary_image_url
    // ==========================================================

    final String primaryImageUrl = _cleanUrl(image['primary_image_url']);

    if (_isAbsoluteNetworkUrl(primaryImageUrl)) {
      return primaryImageUrl;
    }

    // ==========================================================
    // image
    // ==========================================================

    final String imageValue = _cleanUrl(image['image']);

    if (_isAbsoluteNetworkUrl(imageValue)) {
      return imageValue;
    }

    return '';
  }

  // ============================================================
  // CLEAN URL
  // ============================================================

  static String _cleanUrl(dynamic value) {
    if (value == null) {
      return '';
    }

    String url = value.toString().trim();

    if (url.isEmpty || url.toLowerCase() == 'null') {
      return '';
    }

    // JSON escaping protection.
    url = url.replaceAll(r'\/', '/');

    // Remove accidental quotes.
    if (url.length >= 2) {
      if ((url.startsWith('"') && url.endsWith('"')) ||
          (url.startsWith("'") && url.endsWith("'"))) {
        url = url.substring(1, url.length - 1);
      }
    }

    return url.trim();
  }

  // ============================================================
  // ABSOLUTE NETWORK URL CHECK
  // ============================================================

  static bool _isAbsoluteNetworkUrl(String value) {
    if (value.trim().isEmpty) {
      return false;
    }

    final Uri? uri = Uri.tryParse(value.trim());

    if (uri == null) {
      return false;
    }

    if (!uri.hasScheme || !uri.hasAuthority) {
      return false;
    }

    final String scheme = uri.scheme.toLowerCase();

    return scheme == 'http' || scheme == 'https';
  }

  // ============================================================
  // BOOLEAN HELPER
  // ============================================================

  static bool _isTrue(dynamic value) {
    if (value == true) {
      return true;
    }

    if (value == 1) {
      return true;
    }

    final String normalized = value?.toString().trim().toLowerCase() ?? '';

    return normalized == '1' || normalized == 'true';
  }

  // ============================================================
  // HELPERS
  // ============================================================

  bool get hasImage {
    return imageUrl.trim().isNotEmpty;
  }

  bool get hasSecondaryImage {
    return secondaryImageUrl.trim().isNotEmpty;
  }

  bool get hasDiscount {
    return effectivePrice > 0 && effectivePrice < price;
  }

  // ============================================================
  // BUYER / SELLING PRICE
  // ============================================================

  double get sellingPrice {
    if (effectivePrice > 0) {
      return effectivePrice;
    }

    return price;
  }

  bool get isInStock {
    return stock > 0;
  }

  double get discountAmount {
    if (!hasDiscount) {
      return 0.0;
    }

    return price - effectivePrice;
  }

  double get discountPercent {
    if (!hasDiscount || price <= 0) {
      return 0.0;
    }

    return ((price - effectivePrice) / price) * 100;
  }

  // ============================================================
  // TO JSON
  // ============================================================

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'description': description,
      'price': price,
      'effective_price': effectivePrice,
      'image_url': imageUrl,
      'secondary_image_url': secondaryImageUrl,
      'rating': rating,
      'reviews_count': reviewsCount,
      'sold_count': soldCount,
      'stock': stock,
      'category_id': categoryId,
      'category_name': categoryName,
    };
  }
}
