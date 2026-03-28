<?php
require __DIR__ . '/../db.php';
require_once __DIR__ . '/lib/auth_codes.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$email = normalize_auth_email($data['email'] ?? '');
$code = trim($data['code'] ?? '');
$password = $data['password'] ?? '';

if (!$email || !$code || !$password) {
  echo json_encode(['error' => 'Completa correo, codigo y nueva contrasena.']);
  exit;
}

if (strlen($password) < 6) {
  echo json_encode(['error' => 'La contrasena debe tener al menos 6 caracteres.']);
  exit;
}

try {
  $consumed = consume_email_code($pdo, $email, EMAIL_CODE_RESET, $code);
  if (!$consumed) {
    echo json_encode(['error' => 'El codigo es incorrecto o ya vencio.']);
    exit;
  }

  $hash = password_hash($password, PASSWORD_BCRYPT);
  $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
  $stmt->execute([$hash, $email]);

  echo json_encode(['success' => true, 'message' => 'Contrasena actualizada correctamente.']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'No se pudo restablecer la contrasena.']);
}
