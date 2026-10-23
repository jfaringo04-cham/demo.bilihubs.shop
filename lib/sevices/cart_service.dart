import 'package:flutter/foundation.dart';

import '../models/product_model.dart';

class CartItem {
  final ProductModel product;
  int quantity;

  CartItem({required this.product, this.quantity = 1});

  double get unitPrice {
    return product.sellingPrice;
  }

  double get subtotal {
    return unitPrice * quantity;
  }
}

class CartService {
  static final List<CartItem> _items = [];

  static final ValueNotifier<int> itemCountNotifier = ValueNotifier<int>(0);

  static List<CartItem> get items {
    return List.unmodifiable(_items);
  }

  static int get itemCount {
    int count = 0;

    for (final item in _items) {
      count += item.quantity;
    }

    return count;
  }

  static double get total {
    double sum = 0;

    for (final item in _items) {
      sum += item.subtotal;
    }

    return sum;
  }

  static void addToCart(ProductModel product, {int quantity = 1}) {
    if (product.stock <= 0) {
      return;
    }

    final index = _items.indexWhere((item) => item.product.id == product.id);

    if (index >= 0) {
      final currentQuantity = _items[index].quantity;

      final newQuantity = currentQuantity + quantity;

      if (newQuantity <= product.stock) {
        _items[index].quantity = newQuantity;
      } else {
        _items[index].quantity = product.stock;
      }
    } else {
      final safeQuantity = quantity > product.stock ? product.stock : quantity;

      _items.add(
        CartItem(
          product: product,
          quantity: safeQuantity < 1 ? 1 : safeQuantity,
        ),
      );
    }

    _updateItemCount();
  }

  static void increaseQuantity(int index) {
    if (index < 0 || index >= _items.length) {
      return;
    }

    final item = _items[index];

    if (item.quantity >= item.product.stock) {
      return;
    }

    item.quantity++;

    _updateItemCount();
  }

  static void decreaseQuantity(int index) {
    if (index < 0 || index >= _items.length) {
      return;
    }

    final item = _items[index];

    if (item.quantity > 1) {
      item.quantity--;
    } else {
      _items.removeAt(index);
    }

    _updateItemCount();
  }

  static void removeAt(int index) {
    if (index < 0 || index >= _items.length) {
      return;
    }

    _items.removeAt(index);

    _updateItemCount();
  }

  static void removeFromCart(ProductModel product) {
    _items.removeWhere((item) => item.product.id == product.id);

    _updateItemCount();
  }

  static bool contains(ProductModel product) {
    return _items.any((item) => item.product.id == product.id);
  }

  static int getQuantity(ProductModel product) {
    final index = _items.indexWhere((item) => item.product.id == product.id);

    if (index < 0) {
      return 0;
    }

    return _items[index].quantity;
  }

  static void clearCart() {
    _items.clear();

    _updateItemCount();
  }

  static void _updateItemCount() {
    itemCountNotifier.value = itemCount;
  }
}
