class ProductModel {
  final int id;

  final String name;

  final String description;

  final double price;

  final String imageUrl;

  final double rating;

  final int soldCount;

  final int stock;

  ProductModel({
    required this.id,

    required this.name,

    required this.description,

    required this.price,

    required this.imageUrl,

    required this.rating,

    required this.soldCount,

    required this.stock,
  });

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    return ProductModel(
      id: json["id"] ?? 0,

      name: json["name"] ?? "",

      description: json["description"] ?? "",

      price: double.tryParse(json["price"].toString()) ?? 0.0,

      imageUrl: json["image"] ?? json["image_url"] ?? "",

      rating: double.tryParse(json["rating"].toString()) ?? 0.0,

      soldCount: json["sold_count"] ?? json["sold"] ?? 0,

      stock: json["stock"] ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      "id": id,

      "name": name,

      "description": description,

      "price": price,

      "image": imageUrl,

      "rating": rating,

      "sold_count": soldCount,

      "stock": stock,
    };
  }
}
