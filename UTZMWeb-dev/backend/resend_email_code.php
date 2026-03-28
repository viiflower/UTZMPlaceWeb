<?php
require __DIR__ . '/../db.php';
require_once __DIR__ . '/lib/auth_codes.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$email = normalize_auth_email($data['email'] ?? '');
$purpose = $data['purpose'] ?? EMAIL_CODE_VERIFY;

if (!$email || !in_array($purpose, [EMAIL_CODE_VERIFY, EMAIL_CODE_RESET], true)) {
  echo json_encode(['error' => 'Solicitud invalida.']);
  exit;
}

try {
  $stmt = $pdo->prepare('SELECT id, email_verified FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    echo json_encode(['success' => true, 'message' => 'Si el correo existe, enviamos un nuevo codigo.']);
    exit;
  }

  if ($purpose === EMAIL_CODE_VERIFY && filter_var($user['email_verified'], FILTER_VALIDATE_BOOLEAN)) {
    echo json_encode(['error' => 'Esta cuenta ya fue verificada.']);
    exit;
  }

  $code = issue_email_code($pdo, $email, $purpose, (int) $user['id']);
  send_auth_code_email($email, $purpose, $code);

  echo json_encode(['success' => true, 'message' => 'Enviamos un nuevo codigo a tu correo.']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'No se pudo reenviar el codigo.']);
}
