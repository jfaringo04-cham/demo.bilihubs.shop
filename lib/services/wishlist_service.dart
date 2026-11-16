import 'package:flutter/foundation.dart';

import 'api_services.dart';
import 'auth_storage.dart';

class WishlistService extends ChangeNotifier {
  WishlistService._();

  static final WishlistService instance = WishlistService._();

  // ============================================================
  // WISHLIST STATE
  // ============================================================

  final Set<int> _productIds = <int>{};
  final Set<int> _pendingProductIds = <int>{};

  List<Map<String, dynamic>> _items = <Map<String, dynamic>>[];

  String? _sessionToken;
  String? _errorMessage;

  bool _isLoading = false;
  bool _isLoaded = false;

  int _generation = 0;
  int _mutationVersion = 0;

  // ============================================================
  // PUBLIC GETTERS
  // ============================================================

  bool get isLoading => _isLoading;

  bool get isLoaded => _isLoaded;

  String? get errorMessage => _errorMessage;

  List<Map<String, dynamic>> get items =>
      List<Map<String, dynamic>>.unmodifiable(_items);

  Set<int> get productIds => Set<int>.unmodifiable(_productIds);

  int get count => _productIds.length;

  bool isWishlisted(int productId) {
    return _productIds.contains(productId);
  }

  bool isUpdating(int productId) {
    return _pendingProductIds.contains(productId);
  }

  // ============================================================
  // SESSION PROTECTION
  // ============================================================

  Future<String?> _checkSession() async {
    final String? token = await AuthStorage.getToken();

    if (token != _sessionToken) {
      _generation++;
      _sessionToken = token;
      _resetState();
      notifyListeners();
    }

    return token;
  }

  void _resetState() {
    _productIds.clear();
    _pendingProductIds.clear();
    _items = <Map<String, dynamic>>[];

    _errorMessage = null;
    _isLoading = false;
    _isLoaded = false;

    _mutationVersion++;
  }

  /// Call this when the buyer logs out or changes accounts.
  void clear() {
    _generation++;
    _sessionToken = null;
    _resetState();
    notifyListeners();
  }

  // ============================================================
  // LOAD WISHLIST
  // ============================================================

  Future<bool> load({bool forceRefresh = false}) async {
    final String? token = await _checkSession();

    if (token == null || token.isEmpty) {
      _errorMessage = 'Please login to access your wishlist.';
      notifyListeners();
      return false;
    }

    if (_isLoading) {
      return false;
    }

    if (_isLoaded && !forceRefresh) {
      return true;
    }

    final int requestGeneration = _generation;
    final int requestMutationVersion = _mutationVersion;

    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    final List<Map<String, dynamic>> loadedItems = <Map<String, dynamic>>[];

    final Set<int> loadedIds = <int>{};

    int page = 1;
    bool success = true;
    String? failureMessage;

    try {
      while (true) {
        final Map<String, dynamic> response = await ApiService.getBuyerWishlist(
          page: page,
          perPage: 100,
        );

        if (_generation != requestGeneration) {
          return false;
        }

        final String? currentToken = await AuthStorage.getToken();

        if (currentToken != token) {
          await _checkSession();
          return false;
        }

        if (response['success'] != true) {
          success = false;
          failureMessage =
              response['message']?.toString() ??
              'Unable to load your wishlist.';
          break;
        }

        final dynamic rawItems = response['data'];

        if (rawItems is! List) {
          success = false;
          failureMessage = 'Invalid wishlist data from server.';
          break;
        }

        for (final dynamic rawItem in rawItems) {
          if (rawItem is! Map) {
            continue;
          }

          final Map<String, dynamic> item = Map<String, dynamic>.from(rawItem);

          final int? productId = int.tryParse(
            item['product_id']?.toString() ?? '',
          );

          if (productId == null || productId <= 0) {
            continue;
          }

          if (loadedIds.add(productId)) {
            loadedItems.add(item);
          }
        }

        final dynamic rawMeta = response['meta'];

        final Map<String, dynamic> meta = rawMeta is Map
            ? Map<String, dynamic>.from(rawMeta)
            : <String, dynamic>{};

        final int lastPage =
            int.tryParse(meta['last_page']?.toString() ?? '') ?? page;

        if (rawItems.isEmpty || page >= lastPage) {
          break;
        }

        page++;
      }
    } catch (error) {
      debugPrint('Wishlist load error: $error');

      success = false;
      failureMessage = 'Unable to load your wishlist.';
    } finally {
      if (_generation == requestGeneration) {
        _isLoading = false;
        notifyListeners();
      }
    }

    if (_generation != requestGeneration) {
      return false;
    }

    if (await AuthStorage.getToken() != token) {
      await _checkSession();
      return false;
    }

    // A mutation occurred while loading.
    // Do not overwrite newer heart states with stale data.
    if (_mutationVersion != requestMutationVersion) {
      _isLoaded = false;
      return false;
    }

    if (!success) {
      _errorMessage = failureMessage;
      notifyListeners();
      return false;
    }

    _items = loadedItems;

    _productIds
      ..clear()
      ..addAll(loadedIds);

    _isLoaded = true;
    _errorMessage = null;

    notifyListeners();
    return true;
  }

  Future<bool> refresh() {
    return load(forceRefresh: true);
  }

  // ============================================================
  // ADD PRODUCT TO WISHLIST
  // ============================================================

  Future<Map<String, dynamic>> add(int productId) {
    return _updateWishlist(productId: productId, shouldWishlist: true);
  }

  // ============================================================
  // REMOVE PRODUCT FROM WISHLIST
  // ============================================================

  Future<Map<String, dynamic>> remove(int productId) {
    return _updateWishlist(productId: productId, shouldWishlist: false);
  }

  // ============================================================
  // TOGGLE WISHLIST
  // ============================================================

  Future<Map<String, dynamic>> toggle(int productId) {
    if (_pendingProductIds.contains(productId)) {
      return Future<Map<String, dynamic>>.value({
        'success': false,
        'message': 'Wishlist update is already in progress.',
      });
    }

    return _updateWishlist(
      productId: productId,
      shouldWishlist: !_productIds.contains(productId),
    );
  }

  // ============================================================
  // SHARED ADD / REMOVE REQUEST
  // ============================================================

  Future<Map<String, dynamic>> _updateWishlist({
    required int productId,
    required bool shouldWishlist,
  }) async {
    if (productId <= 0) {
      return {
        'success': false,
        'status_code': 422,
        'message': 'Invalid product ID.',
      };
    }

    final String? token = await _checkSession();

    if (token == null || token.isEmpty) {
      return {
        'success': false,
        'status_code': 401,
        'message': 'Please login to manage your wishlist.',
      };
    }

    if (!_pendingProductIds.add(productId)) {
      return {
        'success': false,
        'message': 'Wishlist update is already in progress.',
      };
    }

    final int requestGeneration = _generation;

    notifyListeners();

    try {
      final Map<String, dynamic> response = shouldWishlist
          ? await ApiService.addBuyerWishlist(productId)
          : await ApiService.removeBuyerWishlist(productId);

      if (_generation != requestGeneration) {
        return {'success': false, 'message': 'Your login session changed.'};
      }

      final String? currentToken = await AuthStorage.getToken();

      if (currentToken != token) {
        await _checkSession();

        return {'success': false, 'message': 'Your login session changed.'};
      }

      if (response['success'] == true &&
          response['wishlisted'] == shouldWishlist) {
        _mutationVersion++;

        if (shouldWishlist) {
          _productIds.add(productId);
        } else {
          _productIds.remove(productId);

          _items.removeWhere((Map<String, dynamic> item) {
            final int? itemProductId = int.tryParse(
              item['product_id']?.toString() ?? '',
            );

            return itemProductId == productId;
          });
        }

        // Refresh from Laravel before treating the full list
        // as synchronized, including product details.
        _isLoaded = false;
        _errorMessage = null;

        notifyListeners();
      } else {
        _errorMessage = response['message']?.toString();

        notifyListeners();
      }

      return response;
    } catch (error) {
      debugPrint('Wishlist update error: $error');

      return {
        'success': false,
        'result_uncertain': true,
        'message':
            'Unable to confirm the wishlist update. '
            'Please refresh and try again.',
      };
    } finally {
      if (_generation == requestGeneration) {
        _pendingProductIds.remove(productId);
        notifyListeners();
      }
    }
  }
}
