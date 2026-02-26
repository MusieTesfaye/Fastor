<?php

use Examples\SocialHub\RegisterRequest;
use Examples\SocialHub\UserResponse;

function registerSocialHubServices(\Fastor\App $app) {
    // Register PDO as a singleton dependency
    $app->registerDependency(PDO::class, function() {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Initial schema
        $pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT,
            email TEXT,
            password TEXT,
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        return $pdo;
    });
}

if (!class_exists('UserService')) {
class UserService {
    public function __construct(private PDO $db) {}

    public function register(RegisterRequest $req): UserResponse {
        $stmt = $this->db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$req->username, $req->email, password_hash($req->password, PASSWORD_DEFAULT)]);
        
        $id = (int)$this->db->lastInsertId();
        return $this->getById($id);
    }

    public function getById(int $id): UserResponse {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new \Fastor\Exceptions\HttpException(404, "User not found");
        }

        // Mapping to DTO
        $res = new UserResponse();
        $res->id = (int)$user['id'];
        $res->username = $user['username'];
        $res->email = $user['email'];
        $res->joined_at = $user['joined_at'];
        
        return $res;
    }
}
}
