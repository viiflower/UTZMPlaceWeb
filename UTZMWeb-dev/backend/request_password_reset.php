<?php
require __DIR__ . '/../db.php';
require_once __DIR__ . '/lib/auth_codes.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$email = normalize_auth_email($data['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['error' => 'Ingresa un correo valido.']);
  exit;
}

try {
  $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($user) {
    $code = issue_email_code($pdo, $email, EMAIL_CODE_RESET, (int) $user['id']);
    send_auth_code_email($email, EMAIL_CODE_RESET, $code);
  }

  echo json_encode([
    'success' => true,
    'message' => 'Si el correo existe, enviamos un codigo para restablecer la contrasena.',
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'No se pudo procesar la solicitud.']);
}
