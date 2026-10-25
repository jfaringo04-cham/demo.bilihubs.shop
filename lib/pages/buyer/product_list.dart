import 'dart:async';

import 'package:flutter/material.dart';

import '../../models/product_model.dart';
import '../../sevices/api_services.dart';
import '../../styles/buyer_style.dart';
import '../../widgets/product_card.dart';

import 'product_details.dart';

class ProductList extends StatefulWidget {
  const ProductList({super.key});

  @override
  State<ProductList> createState() => _ProductListState();
}

class _ProductListState extends State<ProductList> {
  final TextEditingController searchController = TextEditingController();

  Timer? _searchDebounce;

  List<ProductModel> products = [];

  List<Map<String, dynamic>> categories = [];

  bool isLoading = true;

  bool isLoadingCategories = true;

  String? errorMessage;

  String searchQuery = '';

  int? selectedCategoryId;

  String selectedCategoryName = 'All';

  bool inStockOnly = false;

  String sortMode = 'default';

  // ================================================================
  // INITIALIZE
  // ================================================================

  @override
  void initState() {
    super.initState();

    _loadInitialData();
  }

  // ================================================================
  // DISPOSE
  // ================================================================

  @override
  void dispose() {
    _searchDebounce?.cancel();

    searchController.dispose();

    super.dispose();
  }

  // ================================================================
  // INITIAL DATA
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

    final Map<String, dynamic> result = await ApiService.getCategories();

    if (!mounted) {
      return;
    }

    if (result['success'] == true) {
      try {
        final dynamic responseData = result['data'];

        final List<Map<String, dynamic>> loadedCategories =
            <Map<String, dynamic>>[];

        if (responseData is List) {
          for (final dynamic item in responseData) {
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
  // LOAD PRODUCTS
  // ================================================================

  Future<void> _loadProducts() async {
    setState(() {
      isLoading = true;

      errorMessage = null;
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

    final Map<String, dynamic> result = await ApiService.getProducts(
      search: searchQuery.trim().isEmpty ? null : searchQuery.trim(),
      categoryId: selectedCategoryId,
      sortBy: sortBy,
      sortOrder: sortOrder,
      perPage: 100,
    );

    if (!mounted) {
      return;
    }

    if (result['success'] == true) {
      try {
        final dynamic responseData = result['data'];

        final List<ProductModel> loadedProducts = <ProductModel>[];

        if (responseData is List) {
          for (final dynamic item in responseData) {
            if (item is Map<String, dynamic>) {
              loadedProducts.add(ProductModel.fromJson(item));
            } else if (item is Map) {
              loadedProducts.add(
                ProductModel.fromJson(Map<String, dynamic>.from(item)),
              );
            }
          }
        }

        // Backend currently does not support "sold"
        // as a sort_by value, so this stays local.
        if (sortMode == 'sold') {
          loadedProducts.sort((ProductModel a, ProductModel b) {
            return b.soldCount.compareTo(a.soldCount);
          });
        }

        setState(() {
          products = loadedProducts;

          isLoading = false;
        });
      } catch (error) {
        setState(() {
          isLoading = false;

          errorMessage = 'Invalid product data: $error';
        });
      }
    } else {
      setState(() {
        isLoading = false;

        errorMessage =
            result['message']?.toString() ?? 'Unable to load products.';
      });
    }
  }

  // ================================================================
  // DISPLAYED PRODUCTS
  // ================================================================

  List<ProductModel> get visibleProducts {
    List<ProductModel> result = List<ProductModel>.from(products);

    // Backend currently has no in_stock parameter.
    if (inStockOnly) {
      result = result.where((ProductModel product) {
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
  // RESET FILTERS
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
  // CATEGORY ICON
  // ================================================================

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

  // ================================================================
  // FILTER SHEET
  // ================================================================

  void _showFilterSheet() {
    bool temporaryInStock = inStockOnly;

    String temporarySort = sortMode;

    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (BuildContext bottomSheetContext) {
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

                    const Text('Availability', style: BuyerStyle.sectionTitle),

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
                        if (value == null) {
                          return;
                        }

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

                          _sortOption(title: 'Highest Rating', value: 'rating'),

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
                          setState(() {
                            inStockOnly = temporaryInStock;

                            sortMode = temporarySort;
                          });

                          Navigator.pop(bottomSheetContext);

                          _loadProducts();
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
                          Navigator.pop(bottomSheetContext);

                          _clearFilters();
                        },
                        child: const Text('Reset Filters'),
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

  // ================================================================
  // SORT OPTION
  // ================================================================

  Widget _sortOption({required String title, required String value}) {
    return RadioListTile<String>(
      contentPadding: EdgeInsets.zero,
      activeColor: BuyerColors.primary,
      title: Text(title, style: BuyerStyle.cardTitle),
      value: value,
    );
  }

  // ================================================================
  // BUILD
  // ================================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BuyerColors.background,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: const Text('Products', style: BuyerStyle.title),
      ),
      body: RefreshIndicator(
        color: BuyerColors.primary,
        onRefresh: () async {
          await Future.wait([_loadCategories(), _loadProducts()]);
        },
        child: _buildBody(),
      ),
    );
  }

  // ================================================================
  // BODY
  // ================================================================

  Widget _buildBody() {
    if (isLoading) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const [
          SizedBox(height: 250),
          Center(child: CircularProgressIndicator(color: BuyerColors.primary)),
        ],
      );
    }

    if (errorMessage != null) {
      final String message = errorMessage ?? '';

      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: [
          const SizedBox(height: 120),

          const Icon(
            Icons.error_outline_rounded,
            size: 55,
            color: BuyerColors.textGrey,
          ),

          const SizedBox(height: 15),

          const Text(
            'Unable to load products',
            textAlign: TextAlign.center,
            style: BuyerStyle.sectionTitle,
          ),

          const SizedBox(height: 8),

          Text(message, textAlign: TextAlign.center, style: BuyerStyle.small),

          const SizedBox(height: 20),

          Center(
            child: ElevatedButton.icon(
              onPressed: _loadProducts,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Retry'),
            ),
          ),
        ],
      );
    }

    final List<ProductModel> displayedProducts = visibleProducts;

    return CustomScrollView(
      physics: const AlwaysScrollableScrollPhysics(
        parent: BouncingScrollPhysics(),
      ),
      slivers: [
        // ============================================================
        // SEARCH
        // ============================================================

        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            child: Container(
              height: 55,
              decoration: BoxDecoration(
                color: Colors.white,
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
                                tooltip: 'Clear',
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
          ),
        ),

        // ============================================================
        // REAL API CATEGORIES
        // ============================================================
        SliverToBoxAdapter(
          child: SizedBox(
            height: 70,
            child: isLoadingCategories
                ? const Center(
                    child: SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: BuyerColors.primary,
                      ),
                    ),
                  )
                : ListView.builder(
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

                      final Map<String, dynamic> category =
                          categories[index - 1];

                      final int id =
                          int.tryParse(category['id']?.toString() ?? '') ?? 0;

                      final String name =
                          category['name']?.toString() ?? 'Category';

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
        ),

        // ============================================================
        // RESULT COUNT
        // ============================================================
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 6, 16, 12),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    '${displayedProducts.length} '
                    'product${displayedProducts.length == 1 ? '' : 's'}',
                    style: BuyerStyle.sectionTitle,
                  ),
                ),

                if (searchQuery.isNotEmpty ||
                    selectedCategoryId != null ||
                    inStockOnly ||
                    sortMode != 'default')
                  TextButton(
                    onPressed: _clearFilters,
                    child: const Text('Clear'),
                  ),
              ],
            ),
          ),
        ),

        // ============================================================
        // EMPTY RESULT
        // ============================================================
        if (displayedProducts.isEmpty)
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.all(35),
              child: Column(
                children: [
                  const SizedBox(height: 70),

                  const Icon(
                    Icons.search_off_rounded,
                    size: 55,
                    color: BuyerColors.textGrey,
                  ),

                  const SizedBox(height: 15),

                  const Text(
                    'No products found',
                    style: BuyerStyle.sectionTitle,
                  ),

                  const SizedBox(height: 7),

                  const Text(
                    'Try another search, category, or filter.',
                    textAlign: TextAlign.center,
                    style: BuyerStyle.small,
                  ),

                  const SizedBox(height: 15),

                  TextButton(
                    onPressed: _clearFilters,
                    child: const Text('Clear Filters'),
                  ),
                ],
              ),
            ),
          )
        // ============================================================
        // PRODUCT GRID
        // ============================================================
        else
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 25),
            sliver: SliverGrid(
              delegate: SliverChildBuilderDelegate((
                BuildContext context,
                int index,
              ) {
                final ProductModel product = displayedProducts[index];

                return ProductCard(
                  product: product,
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (BuildContext context) {
                          return ProductDetails(product: product);
                        },
                      ),
                    );
                  },
                );
              }, childCount: displayedProducts.length),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
                childAspectRatio: 0.65,
              ),
            ),
          ),
      ],
    );
  }

  // ================================================================
  // CATEGORY CHIP
  // ================================================================

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
            color: selected ? BuyerColors.primary : Colors.white,
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
}
