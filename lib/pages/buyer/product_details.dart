import 'package:flutter/material.dart';

import '../../models/product_model.dart';
import '../../sevices/api_services.dart';
import '../../sevices/cart_service.dart';
import '../../styles/buyer_style.dart';

import 'checkout.dart';

class ProductDetails extends StatefulWidget {
  final ProductModel product;

  const ProductDetails({super.key, required this.product});

  @override
  State<ProductDetails> createState() => _ProductDetailsState();
}

class _ProductDetailsState extends State<ProductDetails> {
  late ProductModel _product;

  bool _isLoadingProduct = false;
  String? _loadError;

  @override
  void initState() {
    super.initState();

    // Use the product from the product list immediately
    // while the complete/fresh product data is loading.
    _product = widget.product;

    _loadProductDetails();
  }

  bool get isOutOfStock => _product.stock <= 0;

  // ============================================================
  // LOAD COMPLETE PRODUCT DETAILS
  // ============================================================

  Future<void> _loadProductDetails() async {
    if (_product.id <= 0) {
      return;
    }

    setState(() {
      _isLoadingProduct = true;
      _loadError = null;
    });

    try {
      final result = await ApiService.getProduct(_product.id);

      if (!mounted) {
        return;
      }

      if (result['success'] == true) {
        final dynamic rawData = result['data'];

        if (rawData is Map) {
          final freshProduct = ProductModel.fromJson(
            Map<String, dynamic>.from(rawData),
          );

          setState(() {
            _product = freshProduct;
            _isLoadingProduct = false;
            _loadError = null;
          });

          return;
        }
      }

      setState(() {
        _isLoadingProduct = false;
        _loadError =
            result['message']?.toString() ??
            'Unable to refresh product details.';
      });
    } catch (e) {
      if (!mounted) {
        return;
      }

      setState(() {
        _isLoadingProduct = false;
        _loadError = 'Unable to refresh product details.';
      });
    }
  }

  // ============================================================
  // ADD TO CART
  // ============================================================

  void _addToCart(BuildContext context) {
    if (isOutOfStock) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            content: Text("This product is currently out of stock."),
            behavior: SnackBarBehavior.floating,
          ),
        );

      return;
    }

    final currentQuantity = CartService.getQuantity(_product);

    if (currentQuantity >= _product.stock) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            content: Text("Maximum available stock reached."),
            behavior: SnackBarBehavior.floating,
          ),
        );

      return;
    }

    CartService.addToCart(_product);

    final updatedQuantity = CartService.getQuantity(_product);

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          backgroundColor: BuyerColors.primaryDark,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          content: Row(
            children: [
              const Icon(Icons.check_circle_outline, color: Colors.white),

              const SizedBox(width: 10),

              Expanded(
                child: Text(
                  updatedQuantity > 1
                      ? "Cart quantity updated to $updatedQuantity"
                      : "Added to cart",
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),
      );
  }

  // ============================================================
  // BUY NOW
  // ============================================================

  void _buyNow(BuildContext context) {
    if (isOutOfStock) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            content: Text("This product is currently out of stock."),
            behavior: SnackBarBehavior.floating,
          ),
        );

      return;
    }

    final buyNowItem = CartItem(product: _product, quantity: 1);

    Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => Checkout(items: [buyNowItem])),
    );
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;
    final height = MediaQuery.of(context).size.height;

    final product = _product;

    return Scaffold(
      backgroundColor: BuyerColors.background,

      // ============================================================
      // APP BAR
      // ============================================================
      appBar: AppBar(
        backgroundColor: BuyerColors.white,
        elevation: 0,
        centerTitle: false,
        iconTheme: const IconThemeData(color: BuyerColors.textDark),
        title: const Text("Product Details", style: BuyerStyle.title),
        actions: [
          IconButton(
            tooltip: "Refresh",
            onPressed: _isLoadingProduct
                ? null
                : () {
                    _loadProductDetails();
                  },
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),

      // ============================================================
      // BODY
      // ============================================================
      body: SafeArea(
        child: RefreshIndicator(
          color: BuyerColors.primary,
          onRefresh: _loadProductDetails,
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
            padding: EdgeInsets.symmetric(
              horizontal: width * 0.045,
              vertical: 16,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // ========================================================
                // API LOADING INDICATOR
                // ========================================================

                if (_isLoadingProduct) ...[
                  const LinearProgressIndicator(
                    color: BuyerColors.primary,
                    backgroundColor: BuyerColors.lightPurple,
                    minHeight: 2,
                  ),
                  const SizedBox(height: 14),
                ],

                // ========================================================
                // API ERROR / FALLBACK NOTICE
                // ========================================================
                if (_loadError != null && !_isLoadingProduct) ...[
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: BuyerColors.lightPurple,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: BuyerColors.border),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(
                          Icons.info_outline_rounded,
                          color: BuyerColors.primary,
                          size: 20,
                        ),

                        const SizedBox(width: 9),

                        Expanded(
                          child: Text(
                            "$_loadError Showing the available product information.",
                            style: const TextStyle(
                              color: BuyerColors.textDark,
                              fontSize: 12,
                              height: 1.4,
                            ),
                          ),
                        ),

                        const SizedBox(width: 5),

                        InkWell(
                          onTap: _loadProductDetails,
                          child: const Padding(
                            padding: EdgeInsets.all(4),
                            child: Icon(
                              Icons.refresh_rounded,
                              color: BuyerColors.primary,
                              size: 20,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 14),
                ],

                // ========================================================
                // PRODUCT IMAGE
                // ========================================================
                Container(
                  height: height * 0.30,
                  width: double.infinity,
                  decoration: BoxDecoration(
                    color: BuyerColors.lightPurple,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: BuyerColors.border),
                  ),
                  child: product.imageUrl.isEmpty
                      ? Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Container(
                              height: 75,
                              width: 75,
                              decoration: BoxDecoration(
                                color: BuyerColors.softPurple,
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: const Icon(
                                Icons.image_outlined,
                                size: 42,
                                color: BuyerColors.primary,
                              ),
                            ),

                            const SizedBox(height: 10),

                            const Text(
                              "Product Image",
                              style: BuyerStyle.small,
                            ),
                          ],
                        )
                      : ClipRRect(
                          borderRadius: BorderRadius.circular(20),
                          child: Image.network(
                            product.imageUrl,
                            fit: BoxFit.cover,
                            width: double.infinity,
                            errorBuilder: (context, error, stackTrace) {
                              return Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Container(
                                    height: 75,
                                    width: 75,
                                    decoration: BoxDecoration(
                                      color: BuyerColors.softPurple,
                                      borderRadius: BorderRadius.circular(20),
                                    ),
                                    child: const Icon(
                                      Icons.broken_image_outlined,
                                      size: 42,
                                      color: BuyerColors.primary,
                                    ),
                                  ),

                                  const SizedBox(height: 10),

                                  const Text(
                                    "Unable to load image",
                                    style: BuyerStyle.small,
                                  ),
                                ],
                              );
                            },
                          ),
                        ),
                ),

                // ========================================================
                // SECONDARY IMAGE
                // ========================================================
                if (product.hasSecondaryImage) ...[
                  const SizedBox(height: 12),

                  SizedBox(
                    height: 72,
                    child: Row(
                      children: [
                        _imageThumbnail(product.imageUrl, selected: true),

                        const SizedBox(width: 10),

                        _imageThumbnail(product.secondaryImageUrl),
                      ],
                    ),
                  ),
                ],

                const SizedBox(height: 22),

                // ========================================================
                // PRODUCT NAME
                // ========================================================
                Text(
                  product.name.isEmpty ? "Unnamed Product" : product.name,
                  style: BuyerStyle.largeTitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),

                const SizedBox(height: 10),

                // ========================================================
                // PRICE
                // ========================================================
                Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      "₱${product.sellingPrice.toStringAsFixed(2)}",
                      style: const TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.w800,
                        color: BuyerColors.primary,
                      ),
                    ),

                    if (product.hasDiscount) ...[
                      const SizedBox(width: 10),

                      Padding(
                        padding: const EdgeInsets.only(bottom: 2),
                        child: Text(
                          "₱${product.price.toStringAsFixed(2)}",
                          style: const TextStyle(
                            fontSize: 14,
                            color: BuyerColors.textGrey,
                            decoration: TextDecoration.lineThrough,
                            decorationThickness: 2,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),

                if (product.hasDiscount) ...[
                  const SizedBox(height: 7),

                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 5,
                        ),
                        decoration: BoxDecoration(
                          color: BuyerColors.lightPurple,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          "${product.discountPercent.toStringAsFixed(0)}% OFF",
                          style: const TextStyle(
                            color: BuyerColors.primary,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),

                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 5,
                        ),
                        decoration: BoxDecoration(
                          color: BuyerColors.lightPurple,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          "Save ₱${product.discountAmount.toStringAsFixed(2)}",
                          style: const TextStyle(
                            color: BuyerColors.primary,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],

                const SizedBox(height: 14),

                // ========================================================
                // RATING / SOLD
                // ========================================================
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 9,
                        vertical: 6,
                      ),
                      decoration: BoxDecoration(
                        color: BuyerColors.lightPurple,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.star_rounded,
                            size: 18,
                            color: BuyerColors.primary,
                          ),

                          const SizedBox(width: 4),

                          Text(
                            product.rating.toStringAsFixed(1),
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: BuyerColors.textDark,
                            ),
                          ),

                          if (product.reviewsCount > 0) ...[
                            const SizedBox(width: 4),

                            Text(
                              "(${product.reviewsCount})",
                              style: BuyerStyle.small,
                            ),
                          ],
                        ],
                      ),
                    ),

                    const SizedBox(width: 10),

                    Container(height: 20, width: 1, color: BuyerColors.border),

                    const SizedBox(width: 10),

                    Text("${product.soldCount} sold", style: BuyerStyle.small),
                  ],
                ),

                // ========================================================
                // CATEGORY
                // ========================================================
                if (product.categoryName.trim().isNotEmpty) ...[
                  const SizedBox(height: 12),

                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: BuyerColors.lightPurple,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      product.categoryName,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: BuyerColors.primary,
                      ),
                    ),
                  ),
                ],

                const SizedBox(height: 22),

                // ========================================================
                // STOCK
                // ========================================================
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: BuyerColors.lightPurple,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: BuyerColors.border),
                  ),
                  child: Row(
                    children: [
                      Container(
                        height: 40,
                        width: 40,
                        decoration: BoxDecoration(
                          color: BuyerColors.white,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Icon(
                          Icons.inventory_2_outlined,
                          color: isOutOfStock
                              ? BuyerColors.danger
                              : BuyerColors.primary,
                          size: 21,
                        ),
                      ),

                      const SizedBox(width: 12),

                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              "Available Stock",
                              style: TextStyle(
                                fontSize: 11,
                                color: BuyerColors.textGrey,
                              ),
                            ),

                            const SizedBox(height: 3),

                            Text(
                              isOutOfStock
                                  ? "Out of stock"
                                  : "${product.stock} items available",
                              style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: isOutOfStock
                                    ? BuyerColors.danger
                                    : BuyerColors.textDark,
                              ),
                            ),
                          ],
                        ),
                      ),

                      Icon(
                        isOutOfStock
                            ? Icons.cancel_outlined
                            : Icons.check_circle_outline,
                        color: isOutOfStock
                            ? BuyerColors.danger
                            : BuyerColors.success,
                        size: 21,
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 22),

                // ========================================================
                // DESCRIPTION
                // ========================================================
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(18),
                  decoration: BuyerStyle.card,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            height: 38,
                            width: 38,
                            decoration: BoxDecoration(
                              color: BuyerColors.lightPurple,
                              borderRadius: BorderRadius.circular(11),
                            ),
                            child: const Icon(
                              Icons.description_outlined,
                              color: BuyerColors.primary,
                              size: 20,
                            ),
                          ),

                          const SizedBox(width: 10),

                          const Text(
                            "Description",
                            style: BuyerStyle.sectionTitle,
                          ),
                        ],
                      ),

                      const SizedBox(height: 14),

                      Text(
                        product.description.trim().isEmpty
                            ? "No description available for this product."
                            : product.description,
                        style: const TextStyle(
                          fontSize: 13,
                          height: 1.5,
                          color: BuyerColors.textGrey,
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 22),

                // ========================================================
                // DELIVERY INFORMATION
                // ========================================================
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(18),
                  decoration: BuyerStyle.card,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        "Delivery Information",
                        style: BuyerStyle.sectionTitle,
                      ),

                      const SizedBox(height: 15),

                      _deliveryItem(
                        icon: Icons.local_shipping_outlined,
                        title: "Fast Delivery",
                        subtitle: "Get your order delivered to your address.",
                      ),

                      const SizedBox(height: 14),

                      _deliveryItem(
                        icon: Icons.verified_outlined,
                        title: "Verified Seller",
                        subtitle: "Products are listed by verified sellers.",
                      ),

                      const SizedBox(height: 14),

                      _deliveryItem(
                        icon: Icons.lock_outline,
                        title: "Secure Checkout",
                        subtitle: "Your checkout information is protected.",
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 25),

                // ========================================================
                // ADD TO CART
                // ========================================================
                SizedBox(
                  width: double.infinity,
                  height: 54,
                  child: ElevatedButton.icon(
                    onPressed: isOutOfStock || _isLoadingProduct
                        ? null
                        : () {
                            _addToCart(context);
                          },
                    icon: const Icon(Icons.shopping_cart_outlined, size: 20),
                    label: Text(
                      _isLoadingProduct
                          ? "Updating Product..."
                          : isOutOfStock
                          ? "Out of Stock"
                          : "Add to Cart",
                    ),
                    style: BuyerStyle.primaryButton,
                  ),
                ),

                const SizedBox(height: 12),

                // ========================================================
                // BUY NOW
                // ========================================================
                SizedBox(
                  width: double.infinity,
                  height: 54,
                  child: OutlinedButton.icon(
                    onPressed: isOutOfStock || _isLoadingProduct
                        ? null
                        : () {
                            _buyNow(context);
                          },
                    icon: const Icon(Icons.flash_on_outlined, size: 20),
                    label: const Text("Buy Now"),
                    style: BuyerStyle.outlinedButton,
                  ),
                ),

                const SizedBox(height: 20),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ================================================================
  // IMAGE THUMBNAIL
  // ================================================================

  Widget _imageThumbnail(String imageUrl, {bool selected = false}) {
    return Container(
      width: 72,
      height: 72,
      decoration: BoxDecoration(
        color: BuyerColors.lightPurple,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: selected ? BuyerColors.primary : BuyerColors.border,
          width: selected ? 2 : 1,
        ),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: imageUrl.trim().isEmpty
            ? const Icon(Icons.image_outlined, color: BuyerColors.primary)
            : Image.network(
                imageUrl,
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) {
                  return const Icon(
                    Icons.broken_image_outlined,
                    color: BuyerColors.primary,
                  );
                },
              ),
      ),
    );
  }

  // ================================================================
  // DELIVERY ITEM
  // ================================================================

  Widget _deliveryItem({
    required IconData icon,
    required String title,
    required String subtitle,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          height: 40,
          width: 40,
          decoration: BoxDecoration(
            color: BuyerColors.lightPurple,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, color: BuyerColors.primary, size: 20),
        ),

        const SizedBox(width: 12),

        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: BuyerStyle.cardTitle),

              const SizedBox(height: 3),

              Text(subtitle, style: BuyerStyle.small),
            ],
          ),
        ),
      ],
    );
  }
}
