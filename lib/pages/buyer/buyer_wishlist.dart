import 'package:flutter/material.dart';

import '../../models/product_model.dart';
import '../../services/wishlist_service.dart';
import '../../styles/buyer_style.dart';

import 'product_details.dart';

class BuyerWishlistPage extends StatefulWidget {
  const BuyerWishlistPage({super.key});

  @override
  State<BuyerWishlistPage> createState() => _BuyerWishlistPageState();
}

class _BuyerWishlistPageState extends State<BuyerWishlistPage> {
  final WishlistService _wishlist = WishlistService.instance;

  @override
  void initState() {
    super.initState();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        _loadWishlist();
      }
    });
  }

  // ============================================================
  // LOAD WISHLIST
  // ============================================================

  Future<void> _loadWishlist() async {
    await _wishlist.load(forceRefresh: true);
  }

  // ============================================================
  // OPEN PRODUCT DETAILS
  // ============================================================

  Future<void> _openProduct(Map<String, dynamic> item) async {
    final int productId = _productId(item);
    final Map<String, dynamic> productData = _productData(item);

    if (productId <= 0 || productData.isEmpty || !_isAvailable(productData)) {
      _showMessage('This product is currently unavailable.', isError: true);
      return;
    }

    try {
      final Map<String, dynamic> normalizedProduct = Map<String, dynamic>.from(
        productData,
      );

      normalizedProduct['id'] = productId;

      final ProductModel product = ProductModel.fromJson(normalizedProduct);

      if (!mounted) {
        return;
      }

      await Navigator.of(context).push<void>(
        MaterialPageRoute<void>(
          builder: (BuildContext context) {
            return ProductDetails(product: product);
          },
        ),
      );

      if (!mounted) {
        return;
      }

      await _wishlist.refresh();
    } catch (error) {
      debugPrint('Open wishlist product error: $error');

      _showMessage(
        'Unable to open this product. Please try again.',
        isError: true,
      );
    }
  }

  // ============================================================
  // REMOVE PRODUCT
  // ============================================================

  Future<void> _removeProduct(int productId) async {
    if (productId <= 0 || _wishlist.isUpdating(productId)) {
      return;
    }

    final Map<String, dynamic> result = await _wishlist.remove(productId);

    if (!mounted) {
      return;
    }

    if (result['success'] == true) {
      _showMessage('Product removed from your wishlist.', isError: false);

      await _wishlist.refresh();
    } else {
      _showMessage(
        result['message']?.toString() ??
            'Unable to remove product from wishlist.',
        isError: true,
      );

      if (result['result_uncertain'] == true) {
        await _wishlist.refresh();
      }
    }
  }

  // ============================================================
  // MESSAGES
  // ============================================================

  void _showMessage(String message, {required bool isError}) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: isError
              ? BuyerColors.danger
              : BuyerColors.primaryDark,
          content: Text(message),
        ),
      );
  }

  // ============================================================
  // PRODUCT DATA HELPERS
  // ============================================================

  int _productId(Map<String, dynamic> item) {
    return int.tryParse(
          item['product_id']?.toString() ??
              (item['product'] is Map
                  ? item['product']['id']?.toString() ?? ''
                  : ''),
        ) ??
        0;
  }

  Map<String, dynamic> _productData(Map<String, dynamic> item) {
    final dynamic rawProduct = item['product'];

    if (rawProduct is Map) {
      return Map<String, dynamic>.from(rawProduct);
    }

    return <String, dynamic>{};
  }

  double _price(dynamic value) {
    if (value is num) {
      return value.toDouble();
    }

    return double.tryParse(value?.toString().replaceAll(',', '') ?? '') ?? 0;
  }

  String _money(double value) {
    return '₱${value.toStringAsFixed(2)}';
  }

  String _imageUrl(Map<String, dynamic> product) {
    final String primary = product['image_url']?.toString().trim() ?? '';

    if (primary.isNotEmpty) {
      return primary;
    }

    return product['primary_image_url']?.toString().trim() ?? '';
  }

  bool _isAvailable(Map<String, dynamic> product) {
    final dynamic value = product['is_available'];

    return value != false && value?.toString().toLowerCase() != 'false';
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BuyerColors.background,
      appBar: AppBar(
        title: const Text(
          'My Wishlist',
          style: TextStyle(
            fontWeight: FontWeight.w800,
            color: BuyerColors.textDark,
          ),
        ),
        centerTitle: false,
        backgroundColor: BuyerColors.background,
        foregroundColor: BuyerColors.textDark,
        elevation: 0,
        actions: [
          IconButton(
            tooltip: 'Refresh wishlist',
            onPressed: _loadWishlist,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        child: AnimatedBuilder(
          animation: _wishlist,
          builder: (BuildContext context, Widget? child) {
            final List<Map<String, dynamic>> items = _wishlist.items;

            if (_wishlist.isLoading && items.isEmpty) {
              return const Center(
                child: CircularProgressIndicator(color: BuyerColors.primary),
              );
            }

            if (_wishlist.errorMessage != null && items.isEmpty) {
              return _buildErrorState(_wishlist.errorMessage!);
            }

            if (items.isEmpty) {
              return _buildEmptyState();
            }

            return RefreshIndicator(
              color: BuyerColors.primary,
              onRefresh: _loadWishlist,
              child: ListView.separated(
                physics: const AlwaysScrollableScrollPhysics(
                  parent: BouncingScrollPhysics(),
                ),
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
                itemCount: items.length + 1,
                separatorBuilder: (BuildContext context, int index) {
                  return const SizedBox(height: 12);
                },
                itemBuilder: (BuildContext context, int index) {
                  if (index == 0) {
                    return _buildSummary();
                  }

                  return _buildWishlistCard(items[index - 1]);
                },
              ),
            );
          },
        ),
      ),
    );
  }

  // ============================================================
  // SUMMARY
  // ============================================================

  Widget _buildSummary() {
    final int count = _wishlist.count;

    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Saved Products',
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w800,
              color: BuyerColors.textDark,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            '$count ${count == 1 ? 'product' : 'products'} saved',
            style: const TextStyle(fontSize: 13, color: BuyerColors.textGrey),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // WISHLIST PRODUCT CARD
  // ============================================================

  Widget _buildWishlistCard(Map<String, dynamic> item) {
    final int productId = _productId(item);

    final Map<String, dynamic> product = _productData(item);

    final String name =
        product['name']?.toString().trim() ?? 'Product unavailable';

    final double originalPrice = _price(product['price']);

    final double effectivePrice = _price(
      product['effective_price'] ?? product['price'],
    );

    final bool hasDiscount =
        originalPrice > effectivePrice && effectivePrice > 0;

    final String imageUrl = _imageUrl(product);

    final bool isAvailable = product.isNotEmpty && _isAvailable(product);

    final bool isUpdating = _wishlist.isUpdating(productId);

    final bool canOpen = productId > 0 && isAvailable && !isUpdating;

    return Material(
      color: BuyerColors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: canOpen ? () => _openProduct(item) : null,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: BuyerColors.border),
          ),
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // ====================================================
                // PRODUCT IMAGE
                // ====================================================

                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: SizedBox(
                    width: 100,
                    height: 110,
                    child: imageUrl.isEmpty
                        ? _buildImagePlaceholder()
                        : Image.network(
                            imageUrl,
                            fit: BoxFit.cover,
                            errorBuilder:
                                (
                                  BuildContext context,
                                  Object error,
                                  StackTrace? stackTrace,
                                ) {
                                  return _buildImagePlaceholder();
                                },
                          ),
                  ),
                ),

                const SizedBox(width: 12),

                // ====================================================
                // PRODUCT INFORMATION
                // ====================================================
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        name.isEmpty ? 'Product unavailable' : name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: BuyerColors.textDark,
                        ),
                      ),

                      const SizedBox(height: 8),

                      if (product.isNotEmpty)
                        Text(
                          _money(effectivePrice),
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: BuyerColors.primary,
                          ),
                        ),

                      if (hasDiscount) ...[
                        const SizedBox(height: 3),
                        Text(
                          _money(originalPrice),
                          style: const TextStyle(
                            fontSize: 12,
                            color: BuyerColors.textGrey,
                            decoration: TextDecoration.lineThrough,
                          ),
                        ),
                      ],

                      if (!isAvailable) ...[
                        const SizedBox(height: 6),
                        const Text(
                          'Currently unavailable',
                          style: TextStyle(
                            fontSize: 12,
                            color: BuyerColors.danger,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],

                      if (canOpen) ...[
                        const SizedBox(height: 6),
                        const Text(
                          'Tap to view product details',
                          style: TextStyle(
                            fontSize: 11,
                            color: BuyerColors.textGrey,
                          ),
                        ),
                      ],

                      const SizedBox(height: 12),

                      // ============================================
                      // REMOVE BUTTON
                      // ============================================
                      Align(
                        alignment: Alignment.centerLeft,
                        child: OutlinedButton.icon(
                          onPressed: isUpdating || productId <= 0
                              ? null
                              : () => _removeProduct(productId),
                          icon: isUpdating
                              ? const SizedBox(
                                  width: 14,
                                  height: 14,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Icon(Icons.favorite, size: 16),
                          label: Text(isUpdating ? 'Removing...' : 'Remove'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: BuyerColors.danger,
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 8,
                            ),
                            visualDensity: VisualDensity.compact,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // IMAGE PLACEHOLDER
  // ============================================================

  Widget _buildImagePlaceholder() {
    return Container(
      color: BuyerColors.lightPurple,
      alignment: Alignment.center,
      child: const Icon(
        Icons.image_not_supported_outlined,
        size: 34,
        color: BuyerColors.primary,
      ),
    );
  }

  // ============================================================
  // EMPTY STATE
  // ============================================================

  Widget _buildEmptyState() {
    return RefreshIndicator(
      color: BuyerColors.primary,
      onRefresh: _loadWishlist,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          SizedBox(
            height: MediaQuery.of(context).size.height * 0.58,
            child: Center(
              child: Padding(
                padding: const EdgeInsets.all(28),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      height: 90,
                      width: 90,
                      decoration: BoxDecoration(
                        color: BuyerColors.lightPurple,
                        borderRadius: BorderRadius.circular(26),
                      ),
                      child: const Icon(
                        Icons.favorite_border_rounded,
                        size: 45,
                        color: BuyerColors.primary,
                      ),
                    ),
                    const SizedBox(height: 20),
                    const Text(
                      'Your wishlist is empty',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        color: BuyerColors.textDark,
                      ),
                    ),
                    const SizedBox(height: 10),
                    const Text(
                      'Save products you love and find them here anytime.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 14,
                        color: BuyerColors.textGrey,
                      ),
                    ),
                    const SizedBox(height: 24),
                    ElevatedButton.icon(
                      onPressed: () {
                        Navigator.of(context).pop();
                      },
                      icon: const Icon(Icons.shopping_bag_outlined),
                      label: const Text('Continue Shopping'),
                      style: BuyerStyle.primaryButton,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // ERROR STATE
  // ============================================================

  Widget _buildErrorState(String message) {
    return RefreshIndicator(
      color: BuyerColors.primary,
      onRefresh: _loadWishlist,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          SizedBox(
            height: MediaQuery.of(context).size.height * 0.58,
            child: Center(
              child: Padding(
                padding: const EdgeInsets.all(28),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.wifi_off_rounded,
                      size: 55,
                      color: BuyerColors.textGrey,
                    ),
                    const SizedBox(height: 18),
                    const Text(
                      'Unable to load wishlist',
                      style: TextStyle(
                        fontSize: 19,
                        fontWeight: FontWeight.w800,
                        color: BuyerColors.textDark,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      message,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 14,
                        color: BuyerColors.textGrey,
                      ),
                    ),
                    const SizedBox(height: 22),
                    ElevatedButton.icon(
                      onPressed: _loadWishlist,
                      icon: const Icon(Icons.refresh_rounded),
                      label: const Text('Try Again'),
                      style: BuyerStyle.primaryButton,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
