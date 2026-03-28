<?php
require __DIR__ . '/../db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

$defaultAvatar = '/public/uploads/blank-profile.png';
$data = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

$stmt = $pdo->prepare('SELECT id, full_name, email, password_hash, avatar_url, role, email_verified, store_name, seller_bio FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'])) {
  echo json_encode(['error' => 'Credenciales incorrectas']);
  exit;
}

if (!filter_var($user['email_verified'], FILTER_VALIDATE_BOOLEAN)) {
  http_response_code(403);
  echo json_encode([
    'error' => 'Debes verificar tu correo antes de iniciar sesion.',
    'verification_required' => true,
    'email' => $user['email'],
  ]);
  exit;
}

if (empty($user['avatar_url'])) {
  $user['avatar_url'] = $defaultAvatar;
}

unset($user['password_hash'], $user['email_verified']);
$_SESSION['user'] = $user;

echo json_encode(['success' => true, 'user' => $user]);
