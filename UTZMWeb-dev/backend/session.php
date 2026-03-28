<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../db.php';

$defaultAvatar = "/public/uploads/blank-profile.png";

if (!isset($_SESSION['user'])) {
  echo json_encode([
    "logged_in" => false,
    "user" => null
  ]);
  exit;
}

// Refresh data from DB so avatar changes made elsewhere are reflected in the web session
$sessionUser = $_SESSION['user'];
try {
  $stmt = $pdo->prepare("SELECT id, full_name, email, avatar_url, role, store_name, seller_bio FROM users WHERE id = ? LIMIT 1");
  $stmt->execute([(int)($sessionUser['id'] ?? 0)]);
  $fresh = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($fresh) {
    $sessionUser = $fresh;
    $_SESSION['user'] = $fresh;
  }
} catch (Exception $e) {
  // Keep using the cached session data if DB lookup fails
}

$avatar = $sessionUser['avatar_url'] ?? null;

echo json_encode([
  "logged_in" => true,
  "user" => [
    "id" => $sessionUser['id'] ?? null,
    "full_name" => $sessionUser['full_name'] ?? '',
    "email" => $sessionUser['email'] ?? '',
    "avatar_url" => $avatar ?: $defaultAvatar,
    "role" => $sessionUser['role'] ?? 'customer',
    "store_name" => $sessionUser['store_name'] ?? '',
    "seller_bio" => $sessionUser['seller_bio'] ?? ''
  ]
]);
