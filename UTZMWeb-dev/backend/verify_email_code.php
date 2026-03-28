<?php
require __DIR__ . '/../db.php';
require_once __DIR__ . '/lib/auth_codes.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$email = normalize_auth_email($data['email'] ?? '');
$code = trim($data['code'] ?? '');

if (!$email || !$code) {
  echo json_encode(['error' => 'Ingresa el correo y el codigo.']);
  exit;
}

try {
  $consumed = consume_email_code($pdo, $email, EMAIL_CODE_VERIFY, $code);
  if (!$consumed) {
    echo json_encode(['error' => 'El codigo es incorrecto o ya vencio.']);
    exit;
  }

  $userId = (int) ($consumed['user_id'] ?? 0);
  if ($userId <= 0) {
    echo json_encode(['error' => 'No se encontro la cuenta asociada al codigo.']);
    exit;
  }

  $pdo->prepare('UPDATE users SET email_verified = true WHERE id = ?')->execute([$userId]);
  $user = fetch_public_user($pdo, $userId);

  if (!$user) {
    echo json_encode(['error' => 'No se encontro la cuenta asociada al codigo.']);
    exit;
  }

  $_SESSION['user'] = $user;

  echo json_encode(['success' => true, 'user' => $user]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'No se pudo verificar el codigo.']);
}
