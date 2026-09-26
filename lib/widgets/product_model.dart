class ProductModel {
  final int id;

  final String name;

  final String description;

  final String imageUrl;

  final double price;

  final int stock;

  final double rating;

  final int soldCount;

  final String category;

  ProductModel({
    required this.id,

    required this.name,

    required this.description,

    required this.imageUrl,

    required this.price,

    required this.stock,

    required this.rating,

    required this.soldCount,

    required this.category,
  });

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    return ProductModel(
      id: json['id'] ?? 0,

      name: json['name'] ?? "",

      description: json['description'] ?? "",

      imageUrl: json['image_url'] ?? "",

      price: double.tryParse(json['price'].toString()) ?? 0.0,

      stock: json['stock'] ?? 0,

      rating: double.tryParse(json['rating'].toString()) ?? 0.0,

      soldCount: json['sold_count'] ?? 0,

      category: json['category'] ?? "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      "id": id,

      "name": name,

      "description": description,

      "image_url": imageUrl,

      "price": price,

      "stock": stock,

      "rating": rating,

      "sold_count": soldCount,

      "category": category,
    };
  }
}
