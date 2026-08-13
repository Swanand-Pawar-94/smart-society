class Session {
  const Session({required this.token, required this.user});

  final String token;
  final AppUser user;

  factory Session.fromLoginJson(Map<String, dynamic> json) => Session(
        token: json['token'] as String,
        user: AppUser.fromJson(json['user'] as Map<String, dynamic>),
      );
}

class AppUser {
  const AppUser(
      {required this.id,
      required this.name,
      required this.email,
      required this.role});
  final int id;
  final String name;
  final String email;
  final String role;

  factory AppUser.fromJson(Map<String, dynamic> json) => AppUser(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        email: json['email'] as String? ?? '',
        role: json['role'] as String? ?? '',
      );
}
