import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';

import '../../models/product_model.dart';
import '../../services/api_services.dart';
import '../../styles/buyer_style.dart';
import '../../widgets/app_empty_state.dart';
import '../../widgets/app_error_state.dart';
import '../../widgets/app_skeleton.dart';
import '../../widgets/product_card.dart';

import 'product_details.dart';

class ProductList extends StatefulWidget {
  const ProductList({super.key});

  @override
  State<ProductList> createState() => _ProductListState();
}

class _ProductListState extends State<ProductList> {
  static const Duration _requestTimeout = Duration(seconds: 25);
  static const Duration _searchDelay = Duration(milliseconds: 500);

  final TextEditingController searchController = TextEditingController();
  final ScrollController _scrollController = ScrollController();

  Timer? _searchDebounce;

  List<ProductModel> products = <ProductModel>[];
  List<Map<String, dynamic>> categories = <Map<String, dynamic>>[];

  bool isLoading = true;
  bool isLoadingCategories = true;
  bool isRefreshing = false;
  bool isRefreshingCategories = false;

  bool _hasLoadedProducts = false;
  bool _hasLoadedCategories = false;

  String? errorMessage;
  String? categoryError;

  String searchQuery = '';
  int? selectedCategoryId;
  String selectedCategoryName = 'All';

  bool inStockOnly = false;
  String sortMode = 'default';

  String? _loadedQuerySignature;

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
    _scrollController.dispose();

    super.dispose();
  }

  // ============================================================
  // INITIAL DATA
  // ============================================================

  Future<void> _loadInitialData() async {
    await Future.wait<void>([_loadCategories(), _loadProducts()]);
  }

  Future<void> _refreshProducts() async {
    _searchDebounce?.cancel();

    await Future.wait<void>([_loadCategories(), _loadProducts()]);
  }

  // ============================================================
  // REQUEST SIGNATURE
  // ============================================================

  String get _currentQuerySignature {
    return [
      searchQuery.trim(),
      selectedCategoryId?.toString() ?? 'all',
      sortMode,
    ].join('|');
  }

  bool get _hasCurrentResults {
    return _hasLoadedProducts &&
        _loadedQuerySignature == _currentQuerySignature;
  }

  bool get _hasActiveFilters {
    return searchQuery.trim().isNotEmpty ||
        selectedCategoryId != null ||
        inStockOnly ||
        sortMode != 'default';
  }

  bool get _isUpdatingResults {
    return isLoading || isRefreshing || _searchDebounce?.isActive == true;
  }

  // ============================================================
  // LOAD CATEGORIES
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
          _responseMessage(result, 'Unable to load categories.'),
        );
        return;
      }

      final dynamic data = result['data'];

      List<dynamic> rawCategories = <dynamic>[];

      if (data is List) {
        rawCategories = data;
      } else if (data is Map) {
        if (data['data'] is List) {
          rawCategories = data['data'] as List<dynamic>;
        } else if (data['categories'] is List) {
          rawCategories = data['categories'] as List<dynamic>;
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
      debugPrint('Product List categories error: $error');

      _setCategoryError(
        requestId,
        'Unable to load categories. '
        'Please check your internet connection.',
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
  // LOAD PRODUCTS
  // ============================================================

  Future<void> _loadProducts() async {
    if (!mounted) return;

    final int requestId = ++_productRequestId;

    final String requestedSearch = searchQuery.trim();
    final int? requestedCategoryId = selectedCategoryId;
    final String requestedSortMode = sortMode;
    final String requestedSignature = _currentQuerySignature;

    setState(() {
      isLoading = !_hasCurrentResults;
      isRefreshing = _hasCurrentResults;
      errorMessage = null;
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
          _responseMessage(result, 'Unable to load products.'),
        );
        return;
      }

      final dynamic data = result['data'];

      List<dynamic> rawProducts = <dynamic>[];

      if (data is List) {
        rawProducts = data;
      } else if (data is Map) {
        if (data['data'] is List) {
          rawProducts = data['data'] as List<dynamic>;
        } else if (data['products'] is List) {
          rawProducts = data['products'] as List<dynamic>;
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
          debugPrint('Product List parsing error: $error');
        }
      }

      // The current API has no sold-count sort parameter.
      // Sorting by sold count is applied to loaded records.
      if (requestedSortMode == 'sold') {
        loadedProducts.sort(
          (ProductModel a, ProductModel b) =>
              b.soldCount.compareTo(a.soldCount),
        );
      }

      if (!mounted || requestId != _productRequestId) return;

      setState(() {
        products = loadedProducts;

        _loadedQuerySignature = requestedSignature;
        _hasLoadedProducts = true;

        isLoading = false;
        isRefreshing = false;
        errorMessage = null;
      });
    } on TimeoutException {
      _setProductError(
        requestId,
        'The server took too long to respond. '
        'Please check your connection and retry.',
      );
    } catch (error) {
      debugPrint('Product List products error: $error');

      _setProductError(
        requestId,
        'Unable to connect to the server. '
        'Please check your internet connection.',
      );
    }
  }

  void _setProductError(int requestId, String message) {
    if (!mounted || requestId != _productRequestId) return;

    setState(() {
      isLoading = false;
      isRefreshing = false;
      errorMessage = message;
    });
  }

  String _responseMessage(Map<String, dynamic> result, String fallback) {
    final String message = result['message']?.toString().trim() ?? '';

    return message.isEmpty ? fallback : message;
  }

  // ============================================================
  // DISPLAYED PRODUCTS
  // ============================================================

  List<ProductModel> get visibleProducts {
    if (!_hasCurrentResults) {
      return <ProductModel>[];
    }

    List<ProductModel> result = List<ProductModel>.from(products);

    if (inStockOnly) {
      result = result
          .where((ProductModel product) => product.stock > 0)
          .toList();
    }

    return result;
  }

  // ============================================================
  // SEARCH
  // ============================================================

  void _onSearchChanged(String value) {
    _searchDebounce?.cancel();

    // Invalidate previous requests immediately, rather
    // than waiting for the next debounce callback.
    _productRequestId++;

    setState(() {
      searchQuery = value;
      errorMessage = null;
      isLoading = !_hasCurrentResults;
      isRefreshing = false;
    });

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

  // ============================================================
  // CATEGORY FILTER
  // ============================================================

  void _selectCategory({required int? id, required String name}) {
    if (selectedCategoryId == id) return;

    _searchDebounce?.cancel();

    setState(() {
      selectedCategoryId = id;
      selectedCategoryName = name;
    });

    _loadProducts();
  }

  // ============================================================
  // RESET FILTERS
  // ============================================================

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
  // PRODUCT NAVIGATION
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
                          'Hide products that are out of stock.',
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
  // MAIN BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BuyerColors.background,
      appBar: AppBar(
        backgroundColor: BuyerColors.white,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        title: const Text('Products', style: BuyerStyle.title),
      ),
      body: RefreshIndicator(
        color: BuyerColors.primary,
        onRefresh: _refreshProducts,
        child: _buildBody(),
      ),
    );
  }

  // ============================================================
  // BODY
  // ============================================================

  Widget _buildBody() {
    final List<ProductModel> displayedProducts = visibleProducts;

    return CustomScrollView(
      controller: _scrollController,
      physics: const AlwaysScrollableScrollPhysics(
        parent: BouncingScrollPhysics(),
      ),
      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
      slivers: [
        // Search is always available, even when loading.
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            child: _buildSearchBar(),
          ),
        ),

        // Categories, including loading/error states.
        SliverToBoxAdapter(child: _buildCategorySection()),

        if (isRefreshing && _hasCurrentResults)
          const SliverToBoxAdapter(
            child: Padding(
              padding: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: LinearProgressIndicator(
                minHeight: 2,
                color: BuyerColors.primary,
              ),
            ),
          ),

        if (errorMessage != null && _hasCurrentResults)
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
              child: _refreshWarning(
                'Showing previously loaded products. '
                '$errorMessage',
                _loadProducts,
              ),
            ),
          ),

        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
            child: _buildResultsHeader(displayedProducts.length),
          ),
        ),

        // Initial fetch or a new search/filter request.
        if (!_hasCurrentResults && _isUpdatingResults)
          const SliverToBoxAdapter(
            child: Padding(
              padding: EdgeInsets.symmetric(horizontal: 16, vertical: 5),
              child: AppSkeleton(
                type: AppSkeletonType.product,
                itemCount: 6,
                color: BuyerColors.primary,
                compact: true,
              ),
            ),
          )
        // Error for a query with no successful results.
        else if (!_hasCurrentResults && errorMessage != null)
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 35, 16, 40),
              child: AppErrorState(
                title: 'Unable to Load Products',
                message: errorMessage!,
                color: BuyerColors.primary,
                onRetry: _loadProducts,
              ),
            ),
          )
        // No products matched.
        else if (displayedProducts.isEmpty)
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 40, 16, 30),
              child: AppEmptyState(
                title: 'No Products Found',
                message: 'Try another search, category, or filter.',
                icon: Icons.search_off_rounded,
                color: BuyerColors.primary,
                actionLabel: _hasActiveFilters ? 'Clear Filters' : null,
                onAction: _hasActiveFilters ? _clearFilters : null,
              ),
            ),
          )
        // Product grid.
        else
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 25),
            sliver: SliverLayoutBuilder(
              builder: (BuildContext context, SliverConstraints constraints) {
                final double availableWidth = constraints.crossAxisExtent;

                final int crossAxisCount = availableWidth < 300 ? 1 : 2;

                return SliverGrid(
                  delegate: SliverChildBuilderDelegate((
                    BuildContext context,
                    int index,
                  ) {
                    final ProductModel product = displayedProducts[index];

                    return ProductCard(
                      product: product,
                      onTap: () => _openProduct(product),
                    );
                  }, childCount: displayedProducts.length),
                  gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: crossAxisCount,
                    crossAxisSpacing: 12,
                    mainAxisSpacing: 12,
                    childAspectRatio: crossAxisCount == 1 ? 1.1 : 0.65,
                  ),
                );
              },
            ),
          ),
      ],
    );
  }

  // ============================================================
  // SEARCH BAR
  // ============================================================

  Widget _buildSearchBar() {
    return Container(
      height: 55,
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

          const SizedBox(width: 8),

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
  // CATEGORIES
  // ============================================================

  Widget _buildCategorySection() {
    if (isLoadingCategories && !_hasLoadedCategories) {
      return _categorySkeleton();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (isRefreshingCategories)
          const Padding(
            padding: EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: LinearProgressIndicator(
              color: BuyerColors.primary,
              minHeight: 2,
            ),
          ),

        if (categoryError != null)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
            child: _refreshWarning(categoryError!, _loadCategories),
          ),

        SizedBox(
          height: 70,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            physics: const BouncingScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 14, 6, 8),
            itemCount: categories.length + 1,
            itemBuilder: (BuildContext context, int index) {
              if (index == 0) {
                return _categoryChip(
                  name: 'All',
                  icon: Icons.apps_rounded,
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

              return _categoryChip(
                name: name,
                icon: _categoryIcon(name),
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
      height: 70,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
        itemCount: 5,
        separatorBuilder: (BuildContext context, int index) {
          return const SizedBox(width: 8);
        },
        itemBuilder: (BuildContext context, int index) {
          return Container(
            width: 96,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            decoration: BoxDecoration(
              color: BuyerColors.white,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: BuyerColors.border),
            ),
            child: Row(
              children: [
                Container(
                  height: 18,
                  width: 18,
                  decoration: BoxDecoration(
                    color: placeholder,
                    borderRadius: BorderRadius.circular(5),
                  ),
                ),
                const SizedBox(width: 7),
                Expanded(
                  child: Container(
                    height: 10,
                    decoration: BoxDecoration(
                      color: placeholder,
                      borderRadius: BorderRadius.circular(5),
                    ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _categoryChip({
    required String name,
    required IconData icon,
    required bool selected,
    required VoidCallback onTap,
  }) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(horizontal: 14),
          decoration: BoxDecoration(
            color: selected ? BuyerColors.primary : BuyerColors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: selected ? BuyerColors.primary : BuyerColors.border,
            ),
          ),
          child: Row(
            children: [
              Icon(
                icon,
                size: 17,
                color: selected ? Colors.white : BuyerColors.primary,
              ),
              const SizedBox(width: 6),
              Text(
                name,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: selected ? Colors.white : BuyerColors.textDark,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // RESULTS HEADER
  // ============================================================

  Widget _buildResultsHeader(int count) {
    return Row(
      children: [
        Expanded(
          child: Text(
            _isUpdatingResults && !_hasCurrentResults
                ? 'Loading products...'
                : _hasCurrentResults
                ? '$count ${count == 1 ? 'Product' : 'Products'}'
                : 'Products',
            style: BuyerStyle.sectionTitle,
          ),
        ),

        if (_hasActiveFilters)
          TextButton(onPressed: _clearFilters, child: const Text('Clear')),
      ],
    );
  }

  // ============================================================
  // REFRESH WARNING
  // ============================================================

  Widget _refreshWarning(String message, VoidCallback onRetry) {
    return Container(
      width: double.infinity,
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
}
