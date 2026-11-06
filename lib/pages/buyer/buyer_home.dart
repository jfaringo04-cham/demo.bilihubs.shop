import 'dart:async';

import 'package:flutter/material.dart';

import '../../models/product_model.dart';
import '../../services/api_services.dart';
import '../../styles/buyer_style.dart';
import '../../widgets/app_empty_state.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_skeleton.dart';
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
  static const Duration _requestTimeout = Duration(seconds: 25);
  static const Duration _searchDelay = Duration(milliseconds: 500);

  final TextEditingController searchController = TextEditingController();

  Timer? _searchDebounce;

  List<ProductModel> products = <ProductModel>[];
  List<Map<String, dynamic>> categories = <Map<String, dynamic>>[];

  bool isLoadingProducts = true;
  bool isLoadingCategories = true;
  bool isRefreshingProducts = false;
  bool isRefreshingCategories = false;

  bool _hasLoadedProducts = false;
  bool _hasLoadedCategories = false;

  String? productError;
  String? categoryError;

  String searchQuery = '';
  int? selectedCategoryId;
  String selectedCategoryName = 'All';

  bool inStockOnly = false;
  String sortMode = 'default';

  int _productRequestId = 0;
  int _categoryRequestId = 0;

  @override
  void initState() {
    super.initState();
    _loadInitialData();
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _productRequestId++;
    _categoryRequestId++;
    searchController.dispose();
    super.dispose();
  }

  // ============================================================
  // INITIAL DATA AND REFRESH
  // ============================================================

  Future<void> _loadInitialData() async {
    await Future.wait<void>([_loadCategories(), _loadProducts()]);
  }

  Future<void> _refreshHome() async {
    _searchDebounce?.cancel();

    await Future.wait<void>([_loadCategories(), _loadProducts()]);
  }

  // ============================================================
  // CATEGORIES API
  // ============================================================

  Future<void> _loadCategories() async {
    if (!mounted) return;

    final int requestId = ++_categoryRequestId;

    setState(() {
      isLoadingCategories = !_hasLoadedCategories;
      isRefreshingCategories = _hasLoadedCategories;
      categoryError = null;
    });

    try {
      final Map<String, dynamic> result = await ApiService.getCategories()
          .timeout(_requestTimeout);

      if (!mounted || requestId != _categoryRequestId) return;

      if (result['success'] != true) {
        _setCategoryError(
          requestId,
          result['message']?.toString().trim().isNotEmpty == true
              ? result['message'].toString()
              : 'Unable to load categories.',
        );
        return;
      }

      final dynamic responseData = result['data'];

      List<dynamic> rawCategories = <dynamic>[];

      if (responseData is List) {
        rawCategories = responseData;
      } else if (responseData is Map) {
        if (responseData['data'] is List) {
          rawCategories = responseData['data'] as List<dynamic>;
        } else if (responseData['categories'] is List) {
          rawCategories = responseData['categories'] as List<dynamic>;
        }
      }

      final List<Map<String, dynamic>> loadedCategories =
          <Map<String, dynamic>>[];

      for (final dynamic item in rawCategories) {
        if (item is Map) {
          loadedCategories.add(Map<String, dynamic>.from(item));
        }
      }

      if (!mounted || requestId != _categoryRequestId) return;

      setState(() {
        categories = loadedCategories;
        _hasLoadedCategories = true;
        isLoadingCategories = false;
        isRefreshingCategories = false;
        categoryError = null;
      });
    } on TimeoutException {
      _setCategoryError(
        requestId,
        'Loading categories took too long. Please retry.',
      );
    } catch (error) {
      debugPrint('Buyer categories error: $error');

      _setCategoryError(
        requestId,
        'Unable to load categories. Check your internet connection.',
      );
    }
  }

  void _setCategoryError(int requestId, String message) {
    if (!mounted || requestId != _categoryRequestId) return;

    setState(() {
      isLoadingCategories = false;
      isRefreshingCategories = false;
      categoryError = message;
    });
  }

  // ============================================================
  // PRODUCTS API
  // ============================================================

  Future<void> _loadProducts() async {
    if (!mounted) return;

    final int requestId = ++_productRequestId;

    final String requestedSearch = searchQuery.trim();
    final int? requestedCategoryId = selectedCategoryId;
    final String requestedSortMode = sortMode;

    setState(() {
      isLoadingProducts = !_hasLoadedProducts;
      isRefreshingProducts = _hasLoadedProducts;
      productError = null;
    });

    String? sortBy;
    String? sortOrder;

    switch (requestedSortMode) {
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

    try {
      final Map<String, dynamic> result = await ApiService.getProducts(
        search: requestedSearch.isEmpty ? null : requestedSearch,
        categoryId: requestedCategoryId,
        sortBy: sortBy,
        sortOrder: sortOrder,
        perPage: 100,
      ).timeout(_requestTimeout);

      if (!mounted || requestId != _productRequestId) return;

      if (result['success'] != true) {
        _setProductError(
          requestId,
          result['message']?.toString().trim().isNotEmpty == true
              ? result['message'].toString()
              : 'Unable to load products.',
        );
        return;
      }

      final dynamic responseData = result['data'];

      List<dynamic> rawProducts = <dynamic>[];

      if (responseData is List) {
        rawProducts = responseData;
      } else if (responseData is Map) {
        if (responseData['data'] is List) {
          rawProducts = responseData['data'] as List<dynamic>;
        } else if (responseData['products'] is List) {
          rawProducts = responseData['products'] as List<dynamic>;
        }
      }

      final List<ProductModel> loadedProducts = <ProductModel>[];

      for (final dynamic item in rawProducts) {
        if (item is! Map) continue;

        try {
          loadedProducts.add(
            ProductModel.fromJson(Map<String, dynamic>.from(item)),
          );
        } catch (error) {
          debugPrint('Unable to parse product: $error');
        }
      }

      if (requestedSortMode == 'sold') {
        loadedProducts.sort(
          (ProductModel a, ProductModel b) =>
              b.soldCount.compareTo(a.soldCount),
        );
      }

      if (!mounted || requestId != _productRequestId) return;

      setState(() {
        products = loadedProducts;
        _hasLoadedProducts = true;
        isLoadingProducts = false;
        isRefreshingProducts = false;
        productError = null;
      });
    } on TimeoutException {
      _setProductError(
        requestId,
        'The server took too long to respond. Please retry.',
      );
    } catch (error) {
      debugPrint('Buyer products error: $error');

      _setProductError(
        requestId,
        'Unable to connect to the server. '
        'Check your internet connection.',
      );
    }
  }

  void _setProductError(int requestId, String message) {
    if (!mounted || requestId != _productRequestId) return;

    setState(() {
      isLoadingProducts = false;
      isRefreshingProducts = false;
      productError = message;
    });
  }

  // ============================================================
  // FILTERS
  // ============================================================

  List<ProductModel> get visibleProducts {
    List<ProductModel> result = List<ProductModel>.from(products);

    if (inStockOnly) {
      result = result
          .where((ProductModel product) => product.stock > 0)
          .toList();
    }

    return result;
  }

  bool get _hasActiveFilters {
    return searchQuery.trim().isNotEmpty ||
        selectedCategoryId != null ||
        inStockOnly ||
        sortMode != 'default';
  }

  void _onSearchChanged(String value) {
    setState(() {
      searchQuery = value;
    });

    // Invalidate the previous request immediately.
    // This prevents old search results from appearing
    // after the user has typed a different query.
    _productRequestId++;

    _searchDebounce?.cancel();

    _searchDebounce = Timer(_searchDelay, () {
      if (!mounted) return;
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

  void _selectCategory({required int? id, required String name}) {
    setState(() {
      selectedCategoryId = id;
      selectedCategoryName = name;
    });

    _loadProducts();
  }

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

  // ============================================================
  // NAVIGATION
  // ============================================================

  void _openProduct(ProductModel product) {
    Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder: (BuildContext context) {
          return ProductDetails(product: product);
        },
      ),
    );
  }

  void _openCart() {
    Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder: (BuildContext context) {
          return const CartPage();
        },
      ),
    );
  }

  void _openProducts() {
    Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder: (BuildContext context) {
          return const ProductList();
        },
      ),
    );
  }

  // ============================================================
  // CATEGORY ICON
  // ============================================================

  IconData _categoryIcon(String categoryName) {
    final String name = categoryName.toLowerCase();

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

  // ============================================================
  // FILTER BOTTOM SHEET
  // ============================================================

  void _showFilterSheet() {
    bool temporaryInStock = inStockOnly;
    String temporarySort = sortMode;

    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (BuildContext sheetContext) {
        return StatefulBuilder(
          builder: (BuildContext context, StateSetter setModalState) {
            return Container(
              padding: const EdgeInsets.fromLTRB(20, 14, 20, 25),
              decoration: const BoxDecoration(
                color: BuyerColors.background,
                borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
              ),
              child: SafeArea(
                top: false,
                child: SingleChildScrollView(
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
                      const Text('Filter Products', style: BuyerStyle.title),
                      const SizedBox(height: 5),
                      const Text(
                        'Choose how products should be displayed.',
                        style: BuyerStyle.subtitle,
                      ),
                      const SizedBox(height: 22),
                      const Text(
                        'Availability',
                        style: BuyerStyle.sectionTitle,
                      ),
                      SwitchListTile(
                        contentPadding: EdgeInsets.zero,
                        activeThumbColor: BuyerColors.primary,
                        title: const Text(
                          'In-stock products only',
                          style: BuyerStyle.cardTitle,
                        ),
                        subtitle: const Text(
                          'Hide products that are currently out of stock.',
                          style: BuyerStyle.small,
                        ),
                        value: temporaryInStock,
                        onChanged: (bool value) {
                          setModalState(() {
                            temporaryInStock = value;
                          });
                        },
                      ),
                      const SizedBox(height: 10),
                      const Text('Sort By', style: BuyerStyle.sectionTitle),
                      const SizedBox(height: 5),
                      RadioGroup<String>(
                        groupValue: temporarySort,
                        onChanged: (String? value) {
                          if (value == null) return;

                          setModalState(() {
                            temporarySort = value;
                          });
                        },
                        child: Column(
                          children: [
                            _sortOption(title: 'Newest', value: 'default'),
                            _sortOption(
                              title: 'Price: Low to High',
                              value: 'price_low',
                            ),
                            _sortOption(
                              title: 'Price: High to Low',
                              value: 'price_high',
                            ),
                            _sortOption(
                              title: 'Highest Rating',
                              value: 'rating',
                            ),
                            _sortOption(title: 'Most Sold', value: 'sold'),
                          ],
                        ),
                      ),
                      const SizedBox(height: 20),
                      SizedBox(
                        width: double.infinity,
                        height: 52,
                        child: ElevatedButton(
                          style: BuyerStyle.primaryButton,
                          onPressed: () {
                            final bool sortChanged = sortMode != temporarySort;

                            setState(() {
                              inStockOnly = temporaryInStock;
                              sortMode = temporarySort;
                            });

                            Navigator.pop(sheetContext);

                            if (sortChanged) {
                              _loadProducts();
                            }
                          },
                          child: const Text('Apply Filters'),
                        ),
                      ),
                      const SizedBox(height: 10),
                      SizedBox(
                        width: double.infinity,
                        height: 50,
                        child: OutlinedButton(
                          style: BuyerStyle.outlinedButton,
                          onPressed: () {
                            Navigator.pop(sheetContext);
                            _clearFilters();
                          },
                          child: const Text('Reset Filters'),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _sortOption({required String title, required String value}) {
    return RadioListTile<String>(
      contentPadding: EdgeInsets.zero,
      activeColor: BuyerColors.primary,
      title: Text(title, style: BuyerStyle.cardTitle),
      value: value,
    );
  }

  // ============================================================
  // MAIN SCREEN
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final List<ProductModel> displayedProducts = visibleProducts;

    return Scaffold(
      backgroundColor: BuyerColors.background,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (BuildContext context, BoxConstraints constraints) {
            return RefreshIndicator(
              color: BuyerColors.primary,
              onRefresh: _refreshHome,
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
                    _buildHeader(),

                    const SizedBox(height: 25),

                    _buildWelcome(),

                    const SizedBox(height: 19),

                    _buildSearchBar(),

                    if (_hasActiveFilters) ...[
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              isLoadingProducts || isRefreshingProducts
                                  ? 'Updating results...'
                                  : '${displayedProducts.length} '
                                        'product${displayedProducts.length == 1 ? '' : 's'} found',
                              style: BuyerStyle.small,
                            ),
                          ),
                          TextButton(
                            onPressed: _clearFilters,
                            child: const Text('Clear Filters'),
                          ),
                        ],
                      ),
                    ],

                    const SizedBox(height: 20),

                    _buildBenefits(),

                    const SizedBox(height: 27),

                    _buildPromo(),

                    const SizedBox(height: 28),

                    _sectionHeader(
                      title: 'Shop by Category',
                      action: 'View All',
                      onTap: () {
                        _selectCategory(id: null, name: 'All');
                      },
                    ),

                    const SizedBox(height: 12),

                    _buildCategorySection(),

                    const SizedBox(height: 28),

                    _sectionHeader(
                      title: searchQuery.isNotEmpty
                          ? 'Search Results'
                          : selectedCategoryId != null
                          ? selectedCategoryName
                          : 'Featured Picks',
                      action: 'View All',
                      onTap: _openProducts,
                    ),

                    const SizedBox(height: 12),

                    _buildProductSection(
                      constraints: constraints,
                      displayedProducts: displayedProducts,
                    ),

                    const SizedBox(height: 28),

                    _buildShoppingHighlight(),

                    const SizedBox(height: 28),

                    _buildWhyBiliHub(),

                    const SizedBox(height: 25),

                    _buildFooter(),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  // ============================================================
  // HEADER
  // ============================================================

  Widget _buildHeader() {
    return Row(
      children: [
        Container(
          height: 46,
          width: 46,
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [BuyerColors.primary, BuyerColors.primaryDark],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(15),
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
                'BiliHub',
                style: TextStyle(
                  fontSize: 19,
                  fontWeight: FontWeight.w800,
                  color: BuyerColors.textDark,
                  letterSpacing: -0.3,
                ),
              ),
              SizedBox(height: 2),
              Text('Shop smart. Shop easy.', style: BuyerStyle.small),
            ],
          ),
        ),
        _headerButton(
          icon: Icons.notifications_none_rounded,
          onTap: () {
            ScaffoldMessenger.of(context)
              ..hideCurrentSnackBar()
              ..showSnackBar(
                const SnackBar(
                  content: Text('Notifications are not connected yet.'),
                  behavior: SnackBarBehavior.floating,
                ),
              );
          },
        ),
        const SizedBox(width: 7),
        _headerButton(icon: Icons.shopping_cart_outlined, onTap: _openCart),
      ],
    );
  }

  Widget _headerButton({required IconData icon, required VoidCallback onTap}) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(13),
        child: Container(
          height: 43,
          width: 43,
          decoration: BoxDecoration(
            color: BuyerColors.white,
            borderRadius: BorderRadius.circular(13),
            border: Border.all(color: BuyerColors.border),
          ),
          child: Icon(icon, size: 21, color: BuyerColors.textDark),
        ),
      ),
    );
  }

  // ============================================================
  // WELCOME
  // ============================================================

  Widget _buildWelcome() {
    return const Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('WELCOME TO BILIHUB', style: BuyerStyle.welcome),
        SizedBox(height: 6),
        Text(
          'Find something\nworth bringing home.',
          style: BuyerStyle.largeTitle,
        ),
        SizedBox(height: 9),
        Text(
          'Discover products from different sellers '
          'and find something made for you.',
          style: BuyerStyle.subtitle,
        ),
      ],
    );
  }

  // ============================================================
  // SEARCH
  // ============================================================

  Widget _buildSearchBar() {
    return Container(
      height: 56,
      decoration: BoxDecoration(
        color: BuyerColors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: BuyerColors.border),
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
              onSubmitted: (String value) {
                _searchDebounce?.cancel();
                _loadProducts();
              },
              decoration: InputDecoration(
                hintText: 'Search products...',
                hintStyle: const TextStyle(
                  color: BuyerColors.textGrey,
                  fontSize: 13,
                ),
                border: InputBorder.none,
                isDense: true,
                suffixIcon: searchQuery.isNotEmpty
                    ? IconButton(
                        tooltip: 'Clear search',
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
    );
  }

  // ============================================================
  // SHOPPING BENEFITS
  // ============================================================

  Widget _buildBenefits() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 13),
      decoration: BoxDecoration(
        color: BuyerColors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: BuyerColors.border),
      ),
      child: Row(
        children: [
          _miniBenefit(
            icon: Icons.local_shipping_outlined,
            title: 'Fast Delivery',
          ),
          _benefitDivider(),
          _miniBenefit(
            icon: Icons.verified_user_outlined,
            title: 'Verified Sellers',
          ),
          _benefitDivider(),
          _miniBenefit(
            icon: Icons.lock_outline_rounded,
            title: 'Secure Payment',
          ),
        ],
      ),
    );
  }

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

  // ============================================================
  // PROMO
  // ============================================================

  Widget _buildPromo() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [BuyerColors.primary, BuyerColors.primaryDark],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
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
                  'Made for everyday finds.',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                SizedBox(height: 5),
                Text(
                  'Shop products from different sellers '
                  'in one place.',
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
    );
  }

  // ============================================================
  // CATEGORIES
  // ============================================================

  Widget _buildCategorySection() {
    if (isLoadingCategories && !_hasLoadedCategories) {
      return _categorySkeleton();
    }

    if (categoryError != null && !_hasLoadedCategories) {
      return AppErrorState(
        title: 'Categories Unavailable',
        message: categoryError!,
        compact: true,
        color: BuyerColors.primary,
        onRetry: _loadCategories,
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (isRefreshingCategories) ...[
          const LinearProgressIndicator(
            color: BuyerColors.primary,
            minHeight: 2,
          ),
          const SizedBox(height: 10),
        ],
        if (categoryError != null && _hasLoadedCategories)
          _refreshWarning(categoryError!, _loadCategories),
        SizedBox(
          height: 112,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            physics: const BouncingScrollPhysics(),
            itemCount: categories.length + 1,
            itemBuilder: (BuildContext context, int index) {
              if (index == 0) {
                return _categoryCard(
                  'All',
                  Icons.apps_rounded,
                  selected: selectedCategoryId == null,
                  onTap: () {
                    _selectCategory(id: null, name: 'All');
                  },
                );
              }

              final Map<String, dynamic> category = categories[index - 1];

              final int id =
                  int.tryParse(category['id']?.toString() ?? '') ?? 0;

              final String name = category['name']?.toString() ?? 'Category';

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
      ],
    );
  }

  Widget _categorySkeleton() {
    final Color placeholder = Theme.of(context)
        .colorScheme
        .surfaceContainerHighest;

    return SizedBox(
      height: 112,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        physics: const NeverScrollableScrollPhysics(),
        itemCount: 4,
        separatorBuilder: (_, _) => const SizedBox(width: 10),
        itemBuilder: (BuildContext context, int index) {
          return Container(
            width: 96,
            padding: const EdgeInsets.symmetric(vertical: 12),
            decoration: BoxDecoration(
              color: BuyerColors.white,
              borderRadius: BorderRadius.circular(17),
              border: Border.all(color: BuyerColors.border),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: placeholder,
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
                const SizedBox(height: 12),
                Container(
                  width: 58,
                  height: 11,
                  decoration: BoxDecoration(
                    color: placeholder,
                    borderRadius: BorderRadius.circular(6),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

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

  // ============================================================
  // PRODUCT SECTION WITH SKELETON
  // ============================================================

  Widget _buildProductSection({
    required BoxConstraints constraints,
    required List<ProductModel> displayedProducts,
  }) {
    if (isLoadingProducts && !_hasLoadedProducts) {
      return const AppSkeleton(
        type: AppSkeletonType.product,
        itemCount: 2,
        color: BuyerColors.primary,
        compact: true,
      );
    }

    if (productError != null && !_hasLoadedProducts) {
      return SizedBox(
        height: 260,
        child: AppErrorState(
          title: 'Unable to Load Products',
          message: productError!,
          color: BuyerColors.primary,
          compact: true,
          onRetry: _loadProducts,
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (isRefreshingProducts) ...[
          const LinearProgressIndicator(
            color: BuyerColors.primary,
            minHeight: 2,
          ),
          const SizedBox(height: 12),
        ],

        if (productError != null && _hasLoadedProducts)
          _refreshWarning(productError!, _loadProducts),

        if (displayedProducts.isEmpty)
          SizedBox(
            height: 250,
            child: AppEmptyState(
              title: 'No Products Found',
              message: 'Try another search, category, or filter.',
              icon: Icons.search_off_rounded,
              color: BuyerColors.primary,
              compact: true,
              actionLabel: 'Clear Filters',
              onAction: _clearFilters,
            ),
          )
        else
          SizedBox(
            height: 290,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              physics: const BouncingScrollPhysics(),
              itemCount: displayedProducts.length,
              itemBuilder: (BuildContext context, int index) {
                final ProductModel product = displayedProducts[index];

                return Padding(
                  padding: EdgeInsets.only(
                    right: index == displayedProducts.length - 1 ? 0 : 13,
                  ),
                  child: SizedBox(
                    width: constraints.maxWidth * 0.48,
                    child: ProductCard(
                      product: product,
                      onTap: () => _openProduct(product),
                    ),
                  ),
                );
              },
            ),
          ),
      ],
    );
  }

  // ============================================================
  // REFRESH WARNING
  // ============================================================

  Widget _refreshWarning(String message, VoidCallback onRetry) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: BuyerColors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: BuyerColors.border),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.wifi_off_rounded,
            color: BuyerColors.primary,
            size: 19,
          ),
          const SizedBox(width: 9),
          Expanded(child: Text(message, style: BuyerStyle.small)),
          TextButton(onPressed: onRetry, child: const Text('Retry')),
        ],
      ),
    );
  }

  // ============================================================
  // SECTION HEADER
  // ============================================================

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

  // ============================================================
  // SHOPPING HIGHLIGHT
  // ============================================================

  Widget _buildShoppingHighlight() {
    return InkWell(
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
                    'Discover something new',
                    style: BuyerStyle.productTitle,
                  ),
                  SizedBox(height: 4),
                  Text(
                    'Explore products selected for '
                    'your everyday needs.',
                    style: BuyerStyle.small,
                  ),
                ],
              ),
            ),
            const Icon(Icons.arrow_forward_rounded, color: BuyerColors.primary),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // WHY BILIHUB
  // ============================================================

  Widget _buildWhyBiliHub() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Why Shop With BiliHub?', style: BuyerStyle.sectionTitle),
        const SizedBox(height: 12),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 10),
          decoration: BoxDecoration(
            color: BuyerColors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: BuyerColors.border),
          ),
          child: Row(
            children: [
              _trustItem(Icons.local_shipping_outlined, 'Fast\nDelivery'),
              _trustItem(Icons.verified_outlined, 'Verified\nSellers'),
              _trustItem(Icons.lock_outline_rounded, 'Secure\nPayments'),
            ],
          ),
        ),
      ],
    );
  }

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

  // ============================================================
  // FOOTER
  // ============================================================

  Widget _buildFooter() {
    return const Center(
      child: Column(
        children: [
          Text(
            'BiliHub',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w800,
              color: BuyerColors.textDark,
            ),
          ),
          SizedBox(height: 7),
          Text(
            'Shop smart. Shop easy.',
            style: TextStyle(fontSize: 10, color: BuyerColors.textGrey),
          ),
          SizedBox(height: 4),
          Text(
            '© 2026 BILIHUB. All rights reserved.',
            style: TextStyle(fontSize: 9, color: BuyerColors.textGrey),
          ),
        ],
      ),
    );
  }
}
