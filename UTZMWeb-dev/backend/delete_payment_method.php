<?php
require __DIR__ . '/../db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Método no permitido']);
  exit;
}

if (!isset($_SESSION['user'])) {
  http_response_code(401);
  echo json_encode(['error' => 'No autenticado']);
  exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$id = isset($payload['id']) ? (int)$payload['id'] : 0;
$user_id = (int)($_SESSION['user']['id'] ?? 0);

if ($id <= 0) {
  http_response_code(422);
  echo json_encode(['error' => 'ID inválido']);
  exit;
}

$stmt = $pdo->prepare("SELECT id FROM payment_methods WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$method = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$method) {
  http_response_code(404);
  echo json_encode(['error' => 'M\u00e9todo no encontrado']);
  exit;
}

// Evitar violar FK si hay pagos que referencian este método
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE payment_method_id = ?");
$countStmt->execute([$id]);
$used = (int)$countStmt->fetchColumn();
if ($used > 0) {
  http_response_code(422);
  echo json_encode(['error' => 'No puedes quitar un m\u00e9todo que ya tiene transacciones asociadas']);
  exit;
}

$del = $pdo->prepare("DELETE FROM payment_methods WHERE id = ? AND user_id = ?");
$del->execute([$id, $user_id]);

echo json_encode(['message' => 'M\u00e9todo eliminado']);
