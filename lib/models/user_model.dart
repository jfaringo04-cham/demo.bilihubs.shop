class UserModel {
  final int id;

  final String name;

  final String email;

  final String role;

  UserModel({
    required this.id,

    required this.name,

    required this.email,

    required this.role,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json["id"] ?? 0,

      name: json["name"] ?? "",

      email: json["email"] ?? "",

      role: json["role"] ?? "",
    );
  }

  Map<String, dynamic> toJson() {
    return {"id": id, "name": name, "email": email, "role": role};
  }

  bool get isBuyer {
    return role.toLowerCase() == "buyer";
  }

  bool get isRider {
    return role.toLowerCase() == "rider";
  }

  bool get isLogistics {
    return role.toLowerCase() == "logistics";
  }
}
