<?php
require __DIR__ . '/../db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
  http_response_code(401);
  echo json_encode(['error' => 'No autenticado']);
  exit;
}

function detect_brand($value){
  $text = strtolower(trim((string)$value));
  $digits = preg_replace('/\D/', '', (string)$value);
  if (strpos($text, 'paypal') !== false) return 'PayPal';
  if (preg_match('/^3[47]/', $digits)) return 'AMEX';
  if (preg_match('/^(5[1-5]|2[2-7])/', $digits)) return 'Mastercard';
  if (preg_match('/^4/', $digits)) return 'Visa';
  if (strpos($text, 'visa') !== false) return 'Visa';
  if (strpos($text, 'master') !== false) return 'Mastercard';
  if (strpos($text, 'amex') !== false || strpos($text, 'american express') !== false) return 'AMEX';
  return null;
}

$user_id = (int)($_SESSION['user']['id'] ?? 0);
$limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 100;
$role = isset($_GET['role']) ? strtolower(trim((string)$_GET['role'])) : 'buyer';

$where = 'o.buyer_id = :uid';
if ($role === 'seller') {
  $where = 'o.seller_id = :uid';
} elseif ($role === 'all') {
  $where = '(o.buyer_id = :uid OR o.seller_id = :uid)';
}

$sql = "
  SELECT
    p.id,
    p.order_id,
    p.amount_cents,
    p.status,
    p.paid_at,
    p.created_at,
    pm.type AS payment_method_type,
    COALESCE(pm.label, INITCAP(pm.type)) AS payment_method_label,
    pm.last4 AS payment_method_last4,
    o.buyer_id,
    o.seller_id,
    o.status AS order_status,
    CASE WHEN o.buyer_id = :uid THEN 'buyer' ELSE 'seller' END AS role
  FROM payments p
  JOIN orders o ON o.id = p.order_id
  LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
  WHERE $where
  ORDER BY COALESCE(p.paid_at, p.created_at) DESC
  LIMIT $limit
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['uid' => $user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$firstPaymentByOrder = [];
foreach ($rows as $row) {
  $orderId = (int)($row['order_id'] ?? 0);
  $paymentId = (int)($row['id'] ?? 0);
  if ($orderId <= 0 || $paymentId <= 0) {
    continue;
  }
  if (!isset($firstPaymentByOrder[$orderId]) || $paymentId < $firstPaymentByOrder[$orderId]) {
    $firstPaymentByOrder[$orderId] = $paymentId;
  }
}

$items = [];
foreach ($rows as $row) {
  $rawType = $row['payment_method_type'] ?? '';
  $rawLabel = $row['payment_method_label'] ?? $rawType;
  $cleanLabel = preg_replace('/\bdemo\b/i', '', (string)$rawLabel);
  $cleanLabel = preg_replace('/^tarjeta\s*/i', '', $cleanLabel);
  $cleanLabel = trim(preg_replace('/\s{2,}/', ' ', $cleanLabel));
  if ($cleanLabel === '') {
    if ($rawType === 'card') {
      $cleanLabel = 'Tarjeta';
    } elseif ($rawType === 'cash') {
      $cleanLabel = 'Efectivo';
    } elseif ($rawType === 'paypal') {
      $cleanLabel = 'PayPal';
    } else {
      $cleanLabel = ucfirst($rawType);
    }
  }
  $statusRaw = strtolower($row['status'] ?? '');
  $statusLabelMap = [
    'captured' => 'Completado',
    'refunded' => 'Reembolsado',
    'failed' => 'Fallido',
    'pending' => 'Pendiente',
    'cancelled' => 'Cancelado',
  ];
  $statusLabel = $statusLabelMap[$statusRaw] ?? ucfirst($statusRaw);
  $statusClassMap = [
    'captured' => 'pill-captured',
    'refunded' => 'pill-refunded',
    'failed' => 'pill-failed',
    'pending' => 'pill-pending',
    'cancelled' => 'pill-cancelled',
  ];
  $statusClass = $statusClassMap[$statusRaw] ?? 'pill-default';

  $orderStatus = strtolower($row['order_status'] ?? '');
  $role = $row['role'] === 'seller' ? 'seller' : 'buyer';
  $type = 'expense';
  $typeLabel = 'Gasto';
  $flow = 'out';

  $orderId = (int)($row['order_id'] ?? 0);
  $paymentId = (int)($row['id'] ?? 0);
  $isOriginalCharge = $orderId > 0
    && $paymentId > 0
    && isset($firstPaymentByOrder[$orderId])
    && $firstPaymentByOrder[$orderId] === $paymentId;

  if ($statusRaw === 'refunded') {
    if ($role === 'buyer' && $isOriginalCharge) {
      continue;
    } else {
      $type = 'refund';
      $typeLabel = 'Reembolso';
      $flow = 'in';
      $statusLabel = 'Reembolsado';
      $statusClass = 'pill-refunded';
    }
  } elseif ($role === 'seller') {
    $type = 'earning';
    $typeLabel = 'Ganancia';
    $flow = 'in';
    if ($orderStatus === 'delivered') {
      $statusLabel = 'Completado';
      $statusClass = 'pill-captured';
    } else {
      $statusLabel = 'Pendiente de entrega';
      $statusClass = 'pill-pending';
    }
  }

  $items[] = [
    'id' => (int)$row['id'],
    'order_id' => (int)$row['order_id'],
    'amount_cents' => isset($row['amount_cents']) ? (int)$row['amount_cents'] : 0,
    'status' => $row['status'],
    'status_label' => $statusLabel,
    'status_class' => $statusClass,
    'paid_at' => $row['paid_at'] ?? $row['created_at'],
    'payment_method_type' => $row['payment_method_type'],
    'payment_method_label' => $cleanLabel,
    'payment_method_last4' => $row['payment_method_last4'],
    'payment_method_brand' => detect_brand($cleanLabel) ?? detect_brand($row['payment_method_type']),
    'role' => $row['role'],
    'order_status' => $orderStatus,
    'type' => $type,
    'type_label' => $typeLabel,
    'flow' => $flow,
  ];
 }

echo json_encode(['transactions' => $items]);
