<?php
require __DIR__ . '/../db.php';
require_once __DIR__ . '/lib/auth_codes.php';

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$fullName = trim($data['full_name'] ?? '');
$email = normalize_auth_email($data['email'] ?? '');
$password = $data['password'] ?? '';

if (!$fullName || !$email || !$password) {
  echo json_encode(['error' => 'Completa todos los campos']);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['error' => 'Ingresa un correo valido (ejemplo@dominio)']);
  exit;
}

if (strlen($password) < 6) {
  echo json_encode(['error' => 'La contrasena debe tener al menos 6 caracteres']);
  exit;
}

$defaultAvatar = '/public/uploads/blank-profile.png';

try {
  $stmt = $pdo->prepare('SELECT id, email_verified FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($existingUser && filter_var($existingUser['email_verified'], FILTER_VALIDATE_BOOLEAN)) {
    echo json_encode(['error' => 'Este correo ya esta registrado']);
    exit;
  }

  if ($existingUser) {
    $userId = (int) $existingUser['id'];
  } else {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare(
      "INSERT INTO users (full_name, email, password_hash, avatar_url, role, email_verified)
       VALUES (?, ?, ?, ?, 'customer', false)
       RETURNING id"
    );
    $stmt->execute([$fullName, $email, $hash, $defaultAvatar]);
    $userId = (int) $stmt->fetchColumn();
  }

  $code = issue_email_code($pdo, $email, EMAIL_CODE_VERIFY, $userId);
  send_auth_code_email($email, EMAIL_CODE_VERIFY, $code);

  echo json_encode([
    'success' => true,
    'verification_required' => true,
    'email' => $email,
    'message' => 'Te enviamos un codigo para verificar tu cuenta.',
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'No se pudo crear la cuenta o enviar el codigo.']);
}
