import 'package:flutter/material.dart';

import '../models/product_model.dart';
import '../styles/buyer_style.dart';

class ProductCard extends StatelessWidget {
  final ProductModel product;
  final VoidCallback onTap;

  const ProductCard({super.key, required this.product, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: BuyerStyle.card,
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ============================================================
            // PRODUCT IMAGE
            // ============================================================

            AspectRatio(aspectRatio: 1.25, child: _buildProductImage()),

            // ============================================================
            // PRODUCT INFORMATION
            // ============================================================
            Expanded(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(10, 9, 10, 9),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      product.name.trim().isEmpty
                          ? 'Unnamed Product'
                          : product.name.trim(),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: BuyerStyle.cardTitle,
                    ),

                    const SizedBox(height: 4),

                    Text(
                      '₱${product.effectivePrice.toStringAsFixed(2)}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 15,
                        color: BuyerColors.primary,
                      ),
                    ),

                    const SizedBox(height: 4),

                    Row(
                      children: [
                        const Icon(
                          Icons.star_rounded,
                          size: 15,
                          color: Colors.orange,
                        ),

                        const SizedBox(width: 3),

                        Text(
                          product.rating.toStringAsFixed(1),
                          style: BuyerStyle.small,
                        ),

                        const SizedBox(width: 7),

                        Expanded(
                          child: Text(
                            '${product.soldCount} sold',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: BuyerStyle.small,
                          ),
                        ),
                      ],
                    ),

                    const Spacer(),

                    Text(
                      product.stock > 0
                          ? '${product.stock} in stock'
                          : 'Out of stock',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w600,
                        color: product.stock > 0
                            ? BuyerColors.textGrey
                            : BuyerColors.danger,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // PRODUCT IMAGE
  // ============================================================

  Widget _buildProductImage() {
    final imageUrl = product.imageUrl.trim();

    if (!_isValidNetworkUrl(imageUrl)) {
      return _imagePlaceholder();
    }

    return Container(
      width: double.infinity,
      color: const Color(0xffEEEEEE),
      child: Image.network(
        imageUrl,
        fit: BoxFit.cover,
        width: double.infinity,
        height: double.infinity,

        errorBuilder: (context, error, stackTrace) {
          return _imagePlaceholder(broken: true);
        },

        loadingBuilder: (context, child, loadingProgress) {
          if (loadingProgress == null) {
            return child;
          }

          return const Center(
            child: SizedBox(
              height: 22,
              width: 22,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: BuyerColors.primary,
              ),
            ),
          );
        },
      ),
    );
  }

  // ============================================================
  // IMAGE PLACEHOLDER
  // ============================================================

  Widget _imagePlaceholder({bool broken = false}) {
    return Container(
      width: double.infinity,
      height: double.infinity,
      color: const Color(0xffEEEEEE),
      child: Center(
        child: Icon(
          broken ? Icons.broken_image_outlined : Icons.image_outlined,
          size: 48,
          color: Colors.grey,
        ),
      ),
    );
  }

  // ============================================================
  // URL VALIDATION
  // ============================================================

  bool _isValidNetworkUrl(String value) {
    if (value.isEmpty) {
      return false;
    }

    final uri = Uri.tryParse(value);

    if (uri == null) {
      return false;
    }

    if (!uri.hasScheme || !uri.hasAuthority) {
      return false;
    }

    return uri.scheme == 'http' || uri.scheme == 'https';
  }
}
