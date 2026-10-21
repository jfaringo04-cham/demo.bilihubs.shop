class ProductModel {
  static const String serverUrl = 'https://demo-bilihubs-shop.onrender.com';

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
    final category = json['category'];

    int parsedCategoryId = 0;
    String parsedCategoryName = '';

    if (category is Map<String, dynamic>) {
      parsedCategoryId = int.tryParse(category['id']?.toString() ?? '') ?? 0;

      parsedCategoryName = category['name']?.toString() ?? '';
    } else {
      parsedCategoryId =
          int.tryParse(json['category_id']?.toString() ?? '') ?? 0;

      parsedCategoryName = json['category_name']?.toString() ?? '';
    }

    final parsedPrice = _parsePrice(json);

    final parsedEffectivePrice = _parseEffectivePrice(
      json,
      fallbackPrice: parsedPrice,
    );

    final parsedImageUrl = _parsePrimaryImage(json);

    final parsedSecondaryImage = _parseSecondaryImage(json);

    return ProductModel(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,

      name: json['name']?.toString() ?? '',

      description: json['description']?.toString() ?? '',

      price: parsedPrice,

      effectivePrice: parsedEffectivePrice,

      imageUrl: parsedImageUrl,

      secondaryImageUrl: parsedSecondaryImage,

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

  static double _parsePrice(Map<String, dynamic> json) {
    if (json['price'] != null) {
      return double.tryParse(json['price'].toString()) ?? 0.0;
    }

    if (json['price_minor'] != null) {
      final priceMinor = double.tryParse(json['price_minor'].toString());

      if (priceMinor != null) {
        return priceMinor / 100;
      }
    }

    return 0.0;
  }

  static double _parseEffectivePrice(
    Map<String, dynamic> json, {
    required double fallbackPrice,
  }) {
    if (json['effective_price'] != null) {
      return double.tryParse(json['effective_price'].toString()) ??
          fallbackPrice;
    }

    if (json['discounted_price_minor'] != null) {
      final discountedMinor = double.tryParse(
        json['discounted_price_minor'].toString(),
      );

      if (discountedMinor != null) {
        return discountedMinor / 100;
      }
    }

    if (json['effective_price_minor'] != null) {
      final effectiveMinor = double.tryParse(
        json['effective_price_minor'].toString(),
      );

      if (effectiveMinor != null) {
        return effectiveMinor / 100;
      }
    }

    return fallbackPrice;
  }

  static String _parsePrimaryImage(Map<String, dynamic> json) {
    final directImage =
        json['image_url'] ?? json['image'] ?? json['image_path'];

    if (directImage != null && directImage.toString().trim().isNotEmpty) {
      return _buildImageUrl(directImage.toString());
    }

    final images = json['images'];

    if (images is List && images.isNotEmpty) {
      Map<String, dynamic>? firstImage;
      Map<String, dynamic>? primaryImage;

      for (final image in images) {
        if (image is Map) {
          final mappedImage = Map<String, dynamic>.from(image);

          firstImage ??= mappedImage;

          final isPrimary = mappedImage['is_primary'];

          if (isPrimary == true ||
              isPrimary == 1 ||
              isPrimary.toString() == '1') {
            primaryImage = mappedImage;

            break;
          }
        }
      }

      final selectedImage = primaryImage ?? firstImage;

      if (selectedImage != null) {
        final path =
            selectedImage['url'] ??
            selectedImage['image_url'] ??
            selectedImage['image'] ??
            selectedImage['path'] ??
            selectedImage['image_path'];

        if (path != null && path.toString().trim().isNotEmpty) {
          return _buildImageUrl(path.toString());
        }
      }
    }

    return '';
  }

  static String _parseSecondaryImage(Map<String, dynamic> json) {
    final secondary =
        json['secondary_image_url'] ?? json['secondary_image_path'];

    if (secondary != null && secondary.toString().trim().isNotEmpty) {
      return _buildImageUrl(secondary.toString());
    }

    final images = json['images'];

    if (images is List && images.length > 1) {
      final secondImage = images[1];

      if (secondImage is Map) {
        final path =
            secondImage['url'] ??
            secondImage['image_url'] ??
            secondImage['image'] ??
            secondImage['path'] ??
            secondImage['image_path'];

        if (path != null && path.toString().trim().isNotEmpty) {
          return _buildImageUrl(path.toString());
        }
      }
    }

    return '';
  }

  static String _buildImageUrl(String value) {
    final path = value.trim();

    if (path.isEmpty) {
      return '';
    }

    if (path.startsWith('http://') || path.startsWith('https://')) {
      return path;
    }

    if (path.startsWith('/storage/')) {
      return '$serverUrl$path';
    }

    if (path.startsWith('storage/')) {
      return '$serverUrl/$path';
    }

    if (path.startsWith('/')) {
      return '$serverUrl$path';
    }

    return '$serverUrl/storage/$path';
  }

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
