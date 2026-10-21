import 'dart:async';

import 'package:flutter/material.dart';

import '../../models/product_model.dart';
import '../../sevices/api_services.dart';
import '../../styles/buyer_style.dart';
import '../../widgets/product_card.dart';

import 'cart.dart';
import 'product_details.dart';
import 'product_list.dart';

class BuyerHome extends StatefulWidget {
  const BuyerHome({super.key});

  @override
  State<BuyerHome> createState() => _BuyerHomeState();
}

class _BuyerHomeState extends State<BuyerHome> {
  final TextEditingController searchController = TextEditingController();

  Timer? _searchDebounce;

  List<ProductModel> products = [];

  List<Map<String, dynamic>> categories = [];

  bool isLoadingProducts = true;
  bool isLoadingCategories = true;

  String? productError;

  String searchQuery = '';

  int? selectedCategoryId;
  String selectedCategoryName = 'All';

  bool inStockOnly = false;

  String sortMode = 'default';

  @override
  void initState() {
    super.initState();

    _loadInitialData();
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    searchController.dispose();

    super.dispose();
  }

  // ================================================================
  // INITIAL LOAD
  // ================================================================

  Future<void> _loadInitialData() async {
    await Future.wait([_loadCategories(), _loadProducts()]);
  }

  // ================================================================
  // LOAD CATEGORIES
  // ================================================================

  Future<void> _loadCategories() async {
    setState(() {
      isLoadingCategories = true;
    });

    final result = await ApiService.getCategories();

    if (!mounted) return;

    if (result['success'] == true) {
      try {
        final dynamic responseData = result['data'];

        final List<Map<String, dynamic>> loadedCategories = [];

        if (responseData is List) {
          for (final item in responseData) {
            if (item is Map<String, dynamic>) {
              loadedCategories.add(item);
            } else if (item is Map) {
              loadedCategories.add(Map<String, dynamic>.from(item));
            }
          }
        }

        setState(() {
          categories = loadedCategories;
          isLoadingCategories = false;
        });
      } catch (_) {
        setState(() {
          categories = [];
          isLoadingCategories = false;
        });
      }
    } else {
      setState(() {
        categories = [];
        isLoadingCategories = false;
      });
    }
  }

  // ================================================================
  // LOAD PRODUCTS FROM LARAVEL
  // ================================================================

  Future<void> _loadProducts() async {
    setState(() {
      isLoadingProducts = true;
      productError = null;
    });

    String? sortBy;
    String? sortOrder;

    switch (sortMode) {
      case 'price_low':
        sortBy = 'price';
        sortOrder = 'asc';
        break;

      case 'price_high':
        sortBy = 'price';
        sortOrder = 'desc';
        break;

      case 'rating':
        sortBy = 'rating';
        sortOrder = 'desc';
        break;

      default:
        sortBy = null;
        sortOrder = null;
    }

    final result = await ApiService.getProducts(
      search: searchQuery.trim().isEmpty ? null : searchQuery.trim(),
      categoryId: selectedCategoryId,
      sortBy: sortBy,
      sortOrder: sortOrder,
      perPage: 100,
    );

    if (!mounted) return;

    if (result['success'] == true) {
      try {
        final dynamic responseData = result['data'];

        final List<ProductModel> loadedProducts = [];

        if (responseData is List) {
          for (final item in responseData) {
            if (item is Map<String, dynamic>) {
              loadedProducts.add(ProductModel.fromJson(item));
            } else if (item is Map) {
              loadedProducts.add(
                ProductModel.fromJson(Map<String, dynamic>.from(item)),
              );
            }
          }
        }

        // Backend currently does not support "most sold" sorting,
        // so we sort this one locally.
        if (sortMode == 'sold') {
          loadedProducts.sort((a, b) => b.soldCount.compareTo(a.soldCount));
        }

        setState(() {
          products = loadedProducts;
          isLoadingProducts = false;
        });
      } catch (e) {
        setState(() {
          isLoadingProducts = false;
          productError = 'Invalid product data: $e';
        });
      }
    } else {
      setState(() {
        isLoadingProducts = false;

        productError =
            result['message']?.toString() ?? 'Unable to load products.';
      });
    }
  }

  // ================================================================
  // PRODUCTS DISPLAYED ON SCREEN
  // ================================================================

  List<ProductModel> get visibleProducts {
    List<ProductModel> result = List<ProductModel>.from(products);

    // Laravel currently does not have an in-stock query parameter,
    // so this filter stays local.
    if (inStockOnly) {
      result = result.where((product) {
        return product.stock > 0;
      }).toList();
    }

    return result;
  }

  // ================================================================
  // SEARCH
  // ================================================================

  void _onSearchChanged(String value) {
    setState(() {
      searchQuery = value;
    });

    _searchDebounce?.cancel();

    _searchDebounce = Timer(const Duration(milliseconds: 500), () {
      _loadProducts();
    });
  }

  void _clearSearch() {
    _searchDebounce?.cancel();

    searchController.clear();

    setState(() {
      searchQuery = '';
    });

    _loadProducts();
  }

  // ================================================================
  // CATEGORY
  // ================================================================

  void _selectCategory({required int? id, required String name}) {
    setState(() {
      selectedCategoryId = id;
      selectedCategoryName = name;
    });

    _loadProducts();
  }

  // ================================================================
  // CLEAR ALL FILTERS
  // ================================================================

  void _clearFilters() {
    _searchDebounce?.cancel();

    searchController.clear();

    setState(() {
      searchQuery = '';

      selectedCategoryId = null;
      selectedCategoryName = 'All';

      inStockOnly = false;

      sortMode = 'default';
    });

    _loadProducts();
  }

  // ================================================================
  // PRODUCT DETAILS
  // ================================================================

  void _openProduct(ProductModel product) {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => ProductDetails(product: product)),
    );
  }

  // ================================================================
  // CART
  // ================================================================

  void _openCart() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => const CartPage()),
    );
  }

  // ================================================================
  // PRODUCTS PAGE
  // ================================================================

  void _openProducts() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => const ProductList()),
    );
  }

  // ================================================================
  // CATEGORY ICON
  // ================================================================

  IconData _categoryIcon(String categoryName) {
    final name = categoryName.toLowerCase();

    if (name.contains('fashion') || name.contains('clothing')) {
      return Icons.checkroom_outlined;
    }

    if (name.contains('electronic') || name.contains('gadget')) {
      return Icons.headphones_outlined;
    }

    if (name.contains('home')) {
      return Icons.home_outlined;
    }

    if (name.contains('beauty') || name.contains('cosmetic')) {
      return Icons.face_retouching_natural;
    }

    if (name.contains('food')) {
      return Icons.fastfood_outlined;
    }

    if (name.contains('accessor')) {
      return Icons.shopping_bag_outlined;
    }

    if (name.contains('sport')) {
      return Icons.sports_basketball_outlined;
    }

    if (name.contains('book')) {
      return Icons.menu_book_outlined;
    }

    return Icons.category_outlined;
  }

  // ================================================================
  // FILTER SHEET
  // ================================================================

  void _showFilterSheet() {
    bool temporaryInStock = inStockOnly;

    String temporarySort = sortMode;

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (bottomSheetContext) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return Container(
              padding: const EdgeInsets.fromLTRB(20, 14, 20, 25),
              decoration: const BoxDecoration(
                color: BuyerColors.background,
                borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
              ),
              child: SafeArea(
                top: false,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Center(
                      child: Container(
                        height: 4,
                        width: 45,
                        decoration: BoxDecoration(
                          color: BuyerColors.border,
                          borderRadius: BorderRadius.circular(10),
                        ),
                      ),
                    ),

                    const SizedBox(height: 20),

                    const Text("Filter Products", style: BuyerStyle.title),

                    const SizedBox(height: 5),

                    const Text(
                      "Choose how products should be displayed.",
                      style: BuyerStyle.subtitle,
                    ),

                    const SizedBox(height: 22),

                    const Text("Availability", style: BuyerStyle.sectionTitle),

                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      activeColor: BuyerColors.primary,
                      title: const Text(
                        "In-stock products only",
                        style: BuyerStyle.cardTitle,
                      ),
                      subtitle: const Text(
                        "Hide products that are currently out of stock.",
                        style: BuyerStyle.small,
                      ),
                      value: temporaryInStock,
                      onChanged: (value) {
                        setModalState(() {
                          temporaryInStock = value;
                        });
                      },
                    ),

                    const SizedBox(height: 10),

                    const Text("Sort By", style: BuyerStyle.sectionTitle),

                    const SizedBox(height: 5),

                    _sortOption(
                      title: "Newest",
                      value: "default",
                      selectedValue: temporarySort,
                      onChanged: (value) {
                        setModalState(() {
                          temporarySort = value;
                        });
                      },
                    ),

                    _sortOption(
                      title: "Price: Low to High",
                      value: "price_low",
                      selectedValue: temporarySort,
                      onChanged: (value) {
                        setModalState(() {
                          temporarySort = value;
                        });
                      },
                    ),

                    _sortOption(
                      title: "Price: High to Low",
                      value: "price_high",
                      selectedValue: temporarySort,
                      onChanged: (value) {
                        setModalState(() {
                          temporarySort = value;
                        });
                      },
                    ),

                    _sortOption(
                      title: "Highest Rating",
                      value: "rating",
                      selectedValue: temporarySort,
                      onChanged: (value) {
                        setModalState(() {
                          temporarySort = value;
                        });
                      },
                    ),

                    _sortOption(
                      title: "Most Sold",
                      value: "sold",
                      selectedValue: temporarySort,
                      onChanged: (value) {
                        setModalState(() {
                          temporarySort = value;
                        });
                      },
                    ),

                    const SizedBox(height: 20),

                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton(
                        style: BuyerStyle.primaryButton,
                        onPressed: () {
                          setState(() {
                            inStockOnly = temporaryInStock;

                            sortMode = temporarySort;
                          });

                          Navigator.pop(bottomSheetContext);

                          _loadProducts();
                        },
                        child: const Text("Apply Filters"),
                      ),
                    ),

                    const SizedBox(height: 10),

                    SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: OutlinedButton(
                        style: BuyerStyle.outlinedButton,
                        onPressed: () {
                          Navigator.pop(bottomSheetContext);

                          _clearFilters();
                        },
                        child: const Text("Reset Filters"),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _sortOption({
    required String title,
    required String value,
    required String selectedValue,
    required ValueChanged<String> onChanged,
  }) {
    return RadioListTile<String>(
      contentPadding: EdgeInsets.zero,
      activeColor: BuyerColors.primary,
      title: Text(title, style: BuyerStyle.cardTitle),
      value: value,
      groupValue: selectedValue,
      onChanged: (newValue) {
        if (newValue == null) {
          return;
        }

        onChanged(newValue);
      },
    );
  }

  // ================================================================
  // BUILD
  // ================================================================

  @override
  Widget build(BuildContext context) {
    final displayedProducts = visibleProducts;

    return Scaffold(
      backgroundColor: BuyerColors.background,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return RefreshIndicator(
              color: BuyerColors.primary,
              onRefresh: () async {
                await Future.wait([_loadCategories(), _loadProducts()]);
              },
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(
                  parent: BouncingScrollPhysics(),
                ),
                keyboardDismissBehavior:
                    ScrollViewKeyboardDismissBehavior.onDrag,
                padding: const EdgeInsets.fromLTRB(18, 14, 18, 30),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // ======================================================
                    // HEADER
                    // ======================================================

                    Row(
                      children: [
                        Container(
                          height: 46,
                          width: 46,
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [
                                BuyerColors.primary,
                                BuyerColors.primaryDark,
                              ],
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                            ),
                            borderRadius: BorderRadius.circular(15),
                            boxShadow: [
                              BoxShadow(
                                color: BuyerColors.primary.withValues(
                                  alpha: 0.20,
                                ),
                                blurRadius: 12,
                                offset: const Offset(0, 5),
                              ),
                            ],
                          ),
                          child: const Icon(
                            Icons.shopping_bag_outlined,
                            color: Colors.white,
                            size: 24,
                          ),
                        ),

                        const SizedBox(width: 12),

                        const Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                "BiliHub",
                                style: TextStyle(
                                  fontSize: 19,
                                  fontWeight: FontWeight.w800,
                                  color: BuyerColors.textDark,
                                  letterSpacing: -0.3,
                                ),
                              ),

                              SizedBox(height: 2),

                              Text(
                                "Shop smart. Shop easy.",
                                style: BuyerStyle.small,
                              ),
                            ],
                          ),
                        ),

                        _headerButton(
                          icon: Icons.notifications_none_rounded,
                          badge: true,
                          onTap: () {
                            ScaffoldMessenger.of(context)
                              ..hideCurrentSnackBar()
                              ..showSnackBar(
                                const SnackBar(
                                  content: Text(
                                    "Notifications are not connected yet.",
                                  ),
                                  behavior: SnackBarBehavior.floating,
                                ),
                              );
                          },
                        ),

                        const SizedBox(width: 7),

                        _headerButton(
                          icon: Icons.shopping_cart_outlined,
                          onTap: _openCart,
                        ),
                      ],
                    ),

                    const SizedBox(height: 25),

                    // ======================================================
                    // HERO
                    // ======================================================
                    const Text("WELCOME TO BILIHUB", style: BuyerStyle.welcome),

                    const SizedBox(height: 6),

                    const Text(
                      "Find something\nworth bringing home.",
                      style: BuyerStyle.largeTitle,
                    ),

                    const SizedBox(height: 9),

                    const Text(
                      "Discover products from different sellers "
                      "and find something made for you.",
                      style: BuyerStyle.subtitle,
                    ),

                    const SizedBox(height: 19),

                    // ======================================================
                    // SEARCH
                    // ======================================================
                    Container(
                      height: 56,
                      decoration: BoxDecoration(
                        color: BuyerColors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: BuyerColors.border),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.025),
                            blurRadius: 12,
                            offset: const Offset(0, 5),
                          ),
                        ],
                      ),
                      child: Row(
                        children: [
                          const SizedBox(width: 15),

                          const Icon(
                            Icons.search_rounded,
                            color: BuyerColors.primary,
                            size: 23,
                          ),

                          const SizedBox(width: 10),

                          Expanded(
                            child: TextField(
                              controller: searchController,
                              textInputAction: TextInputAction.search,
                              onChanged: _onSearchChanged,
                              onSubmitted: (_) {
                                _searchDebounce?.cancel();

                                _loadProducts();
                              },
                              decoration: InputDecoration(
                                hintText: "Search products...",
                                hintStyle: const TextStyle(
                                  color: BuyerColors.textGrey,
                                  fontSize: 13,
                                ),
                                border: InputBorder.none,
                                isDense: true,
                                suffixIcon: searchQuery.isNotEmpty
                                    ? IconButton(
                                        tooltip: "Clear",
                                        onPressed: _clearSearch,
                                        icon: const Icon(
                                          Icons.close_rounded,
                                          size: 19,
                                          color: BuyerColors.textGrey,
                                        ),
                                      )
                                    : null,
                              ),
                            ),
                          ),

                          InkWell(
                            onTap: _showFilterSheet,
                            borderRadius: BorderRadius.circular(12),
                            child: Container(
                              height: 40,
                              width: 40,
                              margin: const EdgeInsets.only(right: 7),
                              decoration: BoxDecoration(
                                color: BuyerColors.lightPurple,
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: const Icon(
                                Icons.tune_rounded,
                                color: BuyerColors.primary,
                                size: 20,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),

                    if (searchQuery.isNotEmpty ||
                        selectedCategoryId != null ||
                        inStockOnly ||
                        sortMode != 'default') ...[
                      const SizedBox(height: 10),

                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              "${displayedProducts.length} product${displayedProducts.length == 1 ? '' : 's'} found",
                              style: BuyerStyle.small,
                            ),
                          ),

                          TextButton(
                            onPressed: _clearFilters,
                            child: const Text("Clear Filters"),
                          ),
                        ],
                      ),
                    ],

                    const SizedBox(height: 20),

                    // ======================================================
                    // QUICK BENEFITS
                    // ======================================================
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 13,
                      ),
                      decoration: BoxDecoration(
                        color: BuyerColors.white,
                        borderRadius: BorderRadius.circular(17),
                        border: Border.all(color: BuyerColors.border),
                      ),
                      child: Row(
                        children: [
                          _miniBenefit(
                            icon: Icons.local_shipping_outlined,
                            title: "Fast Delivery",
                          ),

                          _benefitDivider(),

                          _miniBenefit(
                            icon: Icons.verified_user_outlined,
                            title: "Verified Sellers",
                          ),

                          _benefitDivider(),

                          _miniBenefit(
                            icon: Icons.lock_outline_rounded,
                            title: "Secure Payment",
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 27),

                    // ======================================================
                    // PROMO
                    // ======================================================
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [
                            BuyerColors.primary,
                            BuyerColors.primaryDark,
                          ],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(22),
                        boxShadow: [
                          BoxShadow(
                            color: BuyerColors.primary.withValues(alpha: 0.22),
                            blurRadius: 18,
                            offset: const Offset(0, 8),
                          ),
                        ],
                      ),
                      child: Row(
                        children: [
                          Container(
                            height: 58,
                            width: 58,
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.14),
                              borderRadius: BorderRadius.circular(17),
                            ),
                            child: const Icon(
                              Icons.local_mall_outlined,
                              color: Colors.white,
                              size: 29,
                            ),
                          ),

                          const SizedBox(width: 15),

                          const Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  "Made for everyday finds.",
                                  style: TextStyle(
                                    color: Colors.white,
                                    fontSize: 17,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),

                                SizedBox(height: 5),

                                Text(
                                  "Shop products from different sellers in one place.",
                                  style: TextStyle(
                                    color: Colors.white70,
                                    fontSize: 11.5,
                                    height: 1.4,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 28),

                    // ======================================================
                    // REAL API CATEGORIES
                    // ======================================================
                    _sectionHeader(
                      title: "Shop by Category",
                      action: "View All",
                      onTap: () {
                        _selectCategory(id: null, name: 'All');
                      },
                    ),

                    const SizedBox(height: 12),

                    if (isLoadingCategories)
                      const SizedBox(
                        height: 105,
                        child: Center(
                          child: CircularProgressIndicator(
                            color: BuyerColors.primary,
                            strokeWidth: 2,
                          ),
                        ),
                      )
                    else
                      SizedBox(
                        height: 112,
                        child: ListView.builder(
                          scrollDirection: Axis.horizontal,
                          physics: const BouncingScrollPhysics(),
                          itemCount: categories.length + 1,
                          itemBuilder: (context, index) {
                            if (index == 0) {
                              return _categoryCard(
                                "All",
                                Icons.apps_rounded,
                                selected: selectedCategoryId == null,
                                onTap: () {
                                  _selectCategory(id: null, name: 'All');
                                },
                              );
                            }

                            final category = categories[index - 1];

                            final id =
                                int.tryParse(
                                  category['id']?.toString() ?? '',
                                ) ??
                                0;

                            final name =
                                category['name']?.toString() ?? 'Category';

                            return _categoryCard(
                              name,
                              _categoryIcon(name),
                              selected: selectedCategoryId == id,
                              onTap: () {
                                _selectCategory(id: id, name: name);
                              },
                            );
                          },
                        ),
                      ),

                    const SizedBox(height: 28),

                    // ======================================================
                    // PRODUCTS
                    // ======================================================
                    _sectionHeader(
                      title: searchQuery.isNotEmpty
                          ? "Search Results"
                          : selectedCategoryId != null
                          ? selectedCategoryName
                          : "Featured Picks",
                      action: "View All",
                      onTap: _openProducts,
                    ),

                    const SizedBox(height: 12),

                    _buildProductSection(
                      constraints: constraints,
                      products: displayedProducts,
                    ),

                    const SizedBox(height: 28),

                    // ======================================================
                    // SHOPPING HIGHLIGHT
                    // ======================================================
                    InkWell(
                      onTap: _openProducts,
                      borderRadius: BorderRadius.circular(20),
                      child: Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(18),
                        decoration: BoxDecoration(
                          color: BuyerColors.lightPurple,
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: BuyerColors.softPurple),
                        ),
                        child: Row(
                          children: [
                            Container(
                              height: 52,
                              width: 52,
                              decoration: BoxDecoration(
                                color: BuyerColors.white,
                                borderRadius: BorderRadius.circular(15),
                              ),
                              child: const Icon(
                                Icons.auto_awesome_outlined,
                                color: BuyerColors.primary,
                                size: 26,
                              ),
                            ),

                            const SizedBox(width: 14),

                            const Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    "Discover something new",
                                    style: BuyerStyle.productTitle,
                                  ),

                                  SizedBox(height: 4),

                                  Text(
                                    "Explore products selected for your everyday needs.",
                                    style: BuyerStyle.small,
                                  ),
                                ],
                              ),
                            ),

                            Container(
                              height: 34,
                              width: 34,
                              decoration: BoxDecoration(
                                color: BuyerColors.white,
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: const Icon(
                                Icons.arrow_forward_rounded,
                                color: BuyerColors.primary,
                                size: 18,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                    const SizedBox(height: 28),

                    // ======================================================
                    // WHY BILIHUB
                    // ======================================================
                    const Text(
                      "Why Shop With BiliHub?",
                      style: BuyerStyle.sectionTitle,
                    ),

                    const SizedBox(height: 12),

                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(
                        vertical: 20,
                        horizontal: 10,
                      ),
                      decoration: BoxDecoration(
                        color: BuyerColors.white,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: BuyerColors.border),
                      ),
                      child: Row(
                        children: [
                          _trustItem(
                            Icons.local_shipping_outlined,
                            "Fast\nDelivery",
                          ),

                          _trustItem(
                            Icons.verified_outlined,
                            "Verified\nSellers",
                          ),

                          _trustItem(
                            Icons.lock_outline_rounded,
                            "Secure\nPayments",
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 25),

                    // ======================================================
                    // FOOTER
                    // ======================================================
                    const Center(
                      child: Column(
                        children: [
                          Text(
                            "BiliHub",
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                              color: BuyerColors.textDark,
                            ),
                          ),

                          SizedBox(height: 7),

                          Text(
                            "Shop smart. Shop easy.",
                            style: TextStyle(
                              fontSize: 10,
                              color: BuyerColors.textGrey,
                            ),
                          ),

                          SizedBox(height: 4),

                          Text(
                            "© 2026 BILIHUB. All rights reserved.",
                            style: TextStyle(
                              fontSize: 9,
                              color: BuyerColors.textGrey,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  // ================================================================
  // PRODUCT SECTION
  // ================================================================

  Widget _buildProductSection({
    required BoxConstraints constraints,
    required List<ProductModel> products,
  }) {
    if (isLoadingProducts) {
      return const SizedBox(
        height: 290,
        child: Center(
          child: CircularProgressIndicator(color: BuyerColors.primary),
        ),
      );
    }

    if (productError != null) {
      return SizedBox(
        height: 290,
        child: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(
                Icons.error_outline_rounded,
                color: BuyerColors.textGrey,
                size: 42,
              ),

              const SizedBox(height: 10),

              Text(
                productError!,
                textAlign: TextAlign.center,
                style: BuyerStyle.small,
              ),

              const SizedBox(height: 12),

              ElevatedButton.icon(
                onPressed: _loadProducts,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text("Retry"),
              ),
            ],
          ),
        ),
      );
    }

    if (products.isEmpty) {
      return SizedBox(
        height: 250,
        child: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(
                Icons.search_off_rounded,
                color: BuyerColors.textGrey,
                size: 45,
              ),

              const SizedBox(height: 10),

              const Text("No products found", style: BuyerStyle.sectionTitle),

              const SizedBox(height: 6),

              const Text(
                "Try another search, category, or filter.",
                textAlign: TextAlign.center,
                style: BuyerStyle.small,
              ),

              const SizedBox(height: 15),

              TextButton(
                onPressed: _clearFilters,
                child: const Text("Clear Filters"),
              ),
            ],
          ),
        ),
      );
    }

    return SizedBox(
      height: 290,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        physics: const BouncingScrollPhysics(),
        itemCount: products.length,
        itemBuilder: (context, index) {
          final product = products[index];

          return Padding(
            padding: EdgeInsets.only(
              right: index == products.length - 1 ? 0 : 13,
            ),
            child: SizedBox(
              width: constraints.maxWidth * 0.48,
              child: ProductCard(
                product: product,
                onTap: () {
                  _openProduct(product);
                },
              ),
            ),
          );
        },
      ),
    );
  }

  // ================================================================
  // SECTION HEADER
  // ================================================================

  Widget _sectionHeader({
    required String title,
    required String action,
    required VoidCallback onTap,
  }) {
    return Row(
      children: [
        Expanded(child: Text(title, style: BuyerStyle.sectionTitle)),

        InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(10),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 5),
            child: Row(
              children: [
                Text(
                  action,
                  style: const TextStyle(
                    color: BuyerColors.primary,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),

                const SizedBox(width: 3),

                const Icon(
                  Icons.arrow_forward_ios_rounded,
                  color: BuyerColors.primary,
                  size: 11,
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  // ================================================================
  // HEADER BUTTON
  // ================================================================

  Widget _headerButton({
    required IconData icon,
    required VoidCallback onTap,
    bool badge = false,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(13),
        child: Stack(
          children: [
            Container(
              height: 43,
              width: 43,
              decoration: BoxDecoration(
                color: BuyerColors.white,
                borderRadius: BorderRadius.circular(13),
                border: Border.all(color: BuyerColors.border),
              ),
              child: Icon(icon, size: 21, color: BuyerColors.textDark),
            ),

            if (badge)
              Positioned(
                top: 7,
                right: 7,
                child: Container(
                  height: 7,
                  width: 7,
                  decoration: const BoxDecoration(
                    color: BuyerColors.danger,
                    shape: BoxShape.circle,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  // ================================================================
  // MINI BENEFIT
  // ================================================================

  Widget _miniBenefit({required IconData icon, required String title}) {
    return Expanded(
      child: Column(
        children: [
          Container(
            height: 31,
            width: 31,
            decoration: BoxDecoration(
              color: BuyerColors.lightPurple,
              borderRadius: BorderRadius.circular(9),
            ),
            child: Icon(icon, color: BuyerColors.primary, size: 17),
          ),

          const SizedBox(height: 5),

          Text(
            title,
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 8.5,
              height: 1.2,
              fontWeight: FontWeight.w600,
              color: BuyerColors.textGrey,
            ),
          ),
        ],
      ),
    );
  }

  Widget _benefitDivider() {
    return Container(height: 35, width: 1, color: BuyerColors.border);
  }

  // ================================================================
  // CATEGORY CARD
  // ================================================================

  Widget _categoryCard(
    String title,
    IconData icon, {
    required bool selected,
    required VoidCallback onTap,
  }) {
    return Padding(
      padding: const EdgeInsets.only(right: 10),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(17),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            width: 96,
            padding: const EdgeInsets.symmetric(vertical: 10),
            decoration: BoxDecoration(
              color: selected ? BuyerColors.lightPurple : BuyerColors.white,
              borderRadius: BorderRadius.circular(17),
              border: Border.all(
                color: selected ? BuyerColors.primary : BuyerColors.border,
                width: selected ? 1.5 : 1,
              ),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  height: 46,
                  width: 46,
                  decoration: BoxDecoration(
                    color: BuyerColors.lightPurple,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(icon, color: BuyerColors.primary, size: 23),
                ),

                const SizedBox(height: 8),

                Text(
                  title,
                  textAlign: TextAlign.center,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: BuyerStyle.category,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ================================================================
  // TRUST ITEM
  // ================================================================

  Widget _trustItem(IconData icon, String title) {
    return Expanded(
      child: Column(
        children: [
          Container(
            height: 39,
            width: 39,
            decoration: BoxDecoration(
              color: BuyerColors.lightPurple,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: BuyerColors.primary, size: 20),
          ),

          const SizedBox(height: 7),

          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 9.5,
              height: 1.25,
              fontWeight: FontWeight.w600,
              color: BuyerColors.textGrey,
            ),
          ),
        ],
      ),
    );
  }
}
