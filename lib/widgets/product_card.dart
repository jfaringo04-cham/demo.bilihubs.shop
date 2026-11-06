import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../models/product_model.dart';
import '../styles/buyer_style.dart';
import '../utils/money_formatter.dart';

class ProductCard extends StatelessWidget {
  final ProductModel product;
  final VoidCallback onTap;

  const ProductCard({super.key, required this.product, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final bool inStock = product.stock > 0;

    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          decoration: BuyerStyle.card,
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AspectRatio(
                aspectRatio: 1.25,
                child: _ProductImage(product: product),
              ),

              Expanded(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(10, 9, 10, 9),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _productName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: BuyerStyle.cardTitle,
                      ),

                      const SizedBox(height: 4),

                      Text(
                        MoneyFormatter.peso(product.effectivePrice),
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
                              _soldText,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: BuyerStyle.small,
                            ),
                          ),
                        ],
                      ),

                      const Spacer(),

                      Row(
                        children: [
                          Icon(
                            inStock
                                ? Icons.inventory_2_outlined
                                : Icons.remove_shopping_cart_outlined,
                            size: 13,
                            color: inStock
                                ? BuyerColors.textGrey
                                : BuyerColors.danger,
                          ),

                          const SizedBox(width: 4),

                          Expanded(
                            child: Text(
                              inStock
                                  ? '${product.stock} in stock'
                                  : 'Out of stock',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                fontSize: 10.5,
                                fontWeight: FontWeight.w600,
                                color: inStock
                                    ? BuyerColors.textGrey
                                    : BuyerColors.danger,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String get _productName {
    final String name = product.name.trim();

    return name.isEmpty ? 'Unnamed Product' : name;
  }

  String get _soldText {
    final int sold = product.soldCount;

    return '$sold sold';
  }
}

// ============================================================================
// PRODUCT IMAGE
// ============================================================================

class _ProductImage extends StatefulWidget {
  final ProductModel product;

  const _ProductImage({required this.product});

  @override
  State<_ProductImage> createState() => _ProductImageState();
}

class _ProductImageState extends State<_ProductImage> {
  late List<String> _imageCandidates;

  int _currentImageIndex = 0;

  @override
  void initState() {
    super.initState();

    _imageCandidates = _buildImageCandidates();
  }

  @override
  void didUpdateWidget(covariant _ProductImage oldWidget) {
    super.didUpdateWidget(oldWidget);

    if (oldWidget.product.id != widget.product.id ||
        oldWidget.product.imageUrl != widget.product.imageUrl ||
        oldWidget.product.secondaryImageUrl !=
            widget.product.secondaryImageUrl) {
      _currentImageIndex = 0;
      _imageCandidates = _buildImageCandidates();
    }
  }

  List<String> _buildImageCandidates() {
    final List<String> candidates = <String>[];

    void addCandidate(String value) {
      final String url = value.trim();

      if (!_isValidNetworkUrl(url)) {
        return;
      }

      if (!candidates.contains(url)) {
        candidates.add(url);
      }
    }

    addCandidate(widget.product.imageUrl);
    addCandidate(widget.product.secondaryImageUrl);

    return candidates;
  }

  @override
  Widget build(BuildContext context) {
    if (_imageCandidates.isEmpty) {
      if (kDebugMode) {
        debugPrint(
          'NO VALID PRODUCT IMAGE | '
          'ID: ${widget.product.id} | '
          'NAME: ${widget.product.name}',
        );
      }

      return _imagePlaceholder();
    }

    final String imageUrl = _imageCandidates[_currentImageIndex];

    if (kDebugMode) {
      debugPrint(
        'PRODUCT IMAGE | '
        'ID: ${widget.product.id} | '
        'NAME: ${widget.product.name} | '
        'INDEX: $_currentImageIndex | '
        'URL: $imageUrl',
      );
    }

    return Container(
      width: double.infinity,
      height: double.infinity,
      color: const Color(0xffEEEEEE),
      child: Image.network(
        imageUrl,
        key: ValueKey<String>(imageUrl),
        width: double.infinity,
        height: double.infinity,
        fit: BoxFit.cover,
        gaplessPlayback: true,
        filterQuality: FilterQuality.medium,
        loadingBuilder:
            (
              BuildContext context,
              Widget child,
              ImageChunkEvent? loadingProgress,
            ) {
              if (loadingProgress == null) {
                return child;
              }

              final int? expectedBytes = loadingProgress.expectedTotalBytes;

              final double? progress =
                  expectedBytes != null && expectedBytes > 0
                  ? loadingProgress.cumulativeBytesLoaded / expectedBytes
                  : null;

              return Container(
                width: double.infinity,
                height: double.infinity,
                color: const Color(0xffEEEEEE),
                alignment: Alignment.center,
                child: SizedBox(
                  width: 22,
                  height: 22,
                  child: CircularProgressIndicator(
                    value: progress,
                    strokeWidth: 2,
                    color: BuyerColors.primary,
                  ),
                ),
              );
            },
        errorBuilder:
            (BuildContext context, Object error, StackTrace? stackTrace) {
              if (kDebugMode) {
                debugPrint(
                  'PRODUCT IMAGE ERROR | '
                  'ID: ${widget.product.id} | '
                  'URL: $imageUrl | '
                  'ERROR: $error',
                );
              }

              final bool hasNextImage =
                  _currentImageIndex + 1 < _imageCandidates.length;

              if (hasNextImage) {
                WidgetsBinding.instance.addPostFrameCallback((_) {
                  if (!mounted) {
                    return;
                  }

                  setState(() {
                    _currentImageIndex++;
                  });
                });

                return _imagePlaceholder(loading: true);
              }

              return _imagePlaceholder(broken: true);
            },
      ),
    );
  }

  // ============================================================
  // PLACEHOLDER
  // ============================================================

  Widget _imagePlaceholder({bool broken = false, bool loading = false}) {
    return Container(
      width: double.infinity,
      height: double.infinity,
      color: const Color(0xffEEEEEE),
      alignment: Alignment.center,
      child: loading
          ? const SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: BuyerColors.primary,
              ),
            )
          : Icon(
              broken ? Icons.broken_image_outlined : Icons.image_outlined,
              size: 46,
              color: Colors.grey,
            ),
    );
  }

  // ============================================================
  // URL VALIDATION
  // ============================================================

  static bool _isValidNetworkUrl(String value) {
    final String cleaned = value.trim();

    if (cleaned.isEmpty) {
      return false;
    }

    final Uri? uri = Uri.tryParse(cleaned);

    if (uri == null || !uri.hasScheme || !uri.hasAuthority) {
      return false;
    }

    final String scheme = uri.scheme.toLowerCase();

    return scheme == 'http' || scheme == 'https';
  }
}
