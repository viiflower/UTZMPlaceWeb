<?php
require __DIR__ . '/support_common.php';

$user = support_require_user($pdo);
support_require_admin($user);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  support_json(['error' => 'Método no permitido'], 405);
}

$payload = support_read_json();
$role = strtolower(trim((string)($payload['role'] ?? 'customer')));

if (!in_array($role, ['customer', 'support', 'admin'], true)) {
  support_json(['error' => 'Rol no válido'], 422);
}

$targetUserId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
$targetEmail = trim((string)($payload['email'] ?? ''));

if ($targetUserId <= 0 && $targetEmail === '') {
  support_json(['error' => 'Indica el usuario por id o correo'], 422);
}

if ($targetUserId > 0) {
  $stmt = $pdo->prepare('SELECT id, email, role FROM users WHERE id = ? LIMIT 1');
  $stmt->execute([$targetUserId]);
} else {
  $stmt = $pdo->prepare('SELECT id, email, role FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
  $stmt->execute([$targetEmail]);
}

$target = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$target) {
  support_json(['error' => 'No se encontró el usuario indicado'], 404);
}

if ((int)$target['id'] === (int)$user['id'] && $role !== 'admin') {
  support_json(['error' => 'No puedes quitarte el rol de administrador desde esta vista'], 422);
}

$updateStmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ? RETURNING id, full_name, email, role');
$updateStmt->execute([$role, (int)$target['id']]);
$updated = $updateStmt->fetch(PDO::FETCH_ASSOC);

support_json([
  'success' => true,
  'user' => [
    'id' => (int)$updated['id'],
    'full_name' => $updated['full_name'],
    'email' => $updated['email'],
    'role' => $updated['role'],
  ],
]);
