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
$order_id = isset($payload['order_id']) ? (int)$payload['order_id'] : 0;
$user_id = (int)($_SESSION['user']['id'] ?? 0);

if ($order_id <= 0) {
  http_response_code(422);
  echo json_encode(['error' => 'Pedido inválido']);
  exit;
}

$stmt = $pdo->prepare("SELECT id, buyer_id, seller_id, status FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  http_response_code(404);
  echo json_encode(['error' => 'Pedido no encontrado']);
  exit;
}

$isBuyer = ((int)$order['buyer_id'] === $user_id);
$isSeller = ((int)$order['seller_id'] === $user_id);

if (!$isBuyer && !$isSeller) {
  http_response_code(403);
  echo json_encode(['error' => 'No puedes cancelar este pedido']);
  exit;
}

$status = $order['status'] ?? '';
if ($status === 'cancelled') {
  echo json_encode(['message' => 'El pedido ya estaba cancelado', 'status' => $status]);
  exit;
}

if ($status === 'delivered') {
  http_response_code(422);
  echo json_encode(['error' => 'No puedes cancelar un pedido entregado']);
  exit;
}

$pdo->beginTransaction();

// Si hay un pago con tarjeta, registrar transacción de reembolso
$payStmt = $pdo->prepare("
  SELECT p.id, p.payment_method_id, p.amount_cents, p.status, pm.type AS method_type
  FROM payments p
  LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
  WHERE p.order_id = ?
  ORDER BY p.paid_at DESC NULLS LAST, p.created_at DESC
  LIMIT 1
");
$payStmt->execute([$order_id]);
$payment = $payStmt->fetch(PDO::FETCH_ASSOC);

if ($payment && in_array(($payment['method_type'] ?? ''), ['card', 'paypal'], true) && ($payment['status'] ?? '') === 'captured') {
  $refund = $pdo->prepare("
    INSERT INTO payments (order_id, payment_method_id, amount_cents, status, paid_at)
    VALUES (:order_id, :payment_method_id, :amount_cents, 'refunded', NOW())
  ");
  $refund->execute([
    ':order_id' => $order_id,
    ':payment_method_id' => $payment['payment_method_id'],
    ':amount_cents' => $payment['amount_cents'],
  ]);
  // Actualiza el pago original a reembolsado para reflejar el estado
  $pdo->prepare("UPDATE payments SET status = 'refunded' WHERE id = ?")->execute([(int)$payment['id']]);
}

$pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$order_id]);
$pdo->commit();

echo json_encode(['message' => 'Pedido cancelado', 'status' => 'cancelled']);
