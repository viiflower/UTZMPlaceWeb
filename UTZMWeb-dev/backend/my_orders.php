<?php
require __DIR__ . '/../db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
  http_response_code(401);
  echo json_encode(['error' => 'No autenticado']);
  exit;
}

$user_id = (int)($_SESSION['user']['id'] ?? 0);
$role = isset($_GET['role']) ? strtolower(trim($_GET['role'])) : 'all';
$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : null;

$where = '(o.buyer_id = :uid OR o.seller_id = :uid)';
if ($role === 'buyer') {
  $where = 'o.buyer_id = :uid';
} elseif ($role === 'seller') {
  $where = 'o.seller_id = :uid';
}

$hasMessagesTable = false;
try {
  $check = $pdo->query("SELECT to_regclass('public.order_messages') IS NOT NULL");
  $hasMessagesTable = (bool)$check->fetchColumn();
} catch (Exception $e) {
  $hasMessagesTable = false;
}

$selectFields = [
  'o.id',
  'o.status',
  'o.total_cents',
  'o.created_at',
  'o.buyer_id',
  'buyer.full_name AS buyer_name',
  'o.seller_id',
  'seller.full_name AS seller_name',
  'pay.amount_cents AS payment_amount_cents',
  'pay.status AS payment_status',
  'pay.paid_at AS payment_paid_at',
  'pay.payment_method_type',
  'pay.payment_method_label',
  'pay.payment_method_last4'
];

if ($hasMessagesTable) {
  $selectFields[] = "
    (
      SELECT COUNT(*) FROM order_messages m
      WHERE m.order_id = o.id AND m.sender_id <> :uid AND m.read_at IS NULL
    ) AS unread_count";
  $selectFields[] = "
    (
      SELECT body FROM order_messages m2
      WHERE m2.order_id = o.id
      ORDER BY m2.created_at DESC
      LIMIT 1
    ) AS last_message";
  $selectFields[] = "
    (
      SELECT created_at FROM order_messages m3
      WHERE m3.order_id = o.id
      ORDER BY m3.created_at DESC
      LIMIT 1
    ) AS last_message_at";
}

$sql = "
  SELECT
    " . implode(",\n    ", $selectFields) . "
  FROM orders o
  JOIN users buyer ON buyer.id = o.buyer_id
  JOIN users seller ON seller.id = o.seller_id
  LEFT JOIN LATERAL (
    SELECT
      p.amount_cents,
      p.status,
      p.paid_at,
      pm.type AS payment_method_type,
      COALESCE(pm.label, INITCAP(pm.type)) AS payment_method_label,
      pm.last4 AS payment_method_last4
    FROM payments p
    LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
    WHERE p.order_id = o.id
    ORDER BY p.paid_at DESC NULLS LAST, p.created_at DESC
    LIMIT 1
  ) pay ON true
  WHERE $where
  ORDER BY o.created_at DESC
";

if ($limit) {
  $sql .= " LIMIT $limit";
}

$stmt = $pdo->prepare($sql);
$stmt->execute(['uid' => $user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$orders = array_map(function ($row) use ($hasMessagesTable) {
  return [
    'id' => isset($row['id']) ? (int)$row['id'] : null,
    'status' => $row['status'] ?? null,
    'total_cents' => isset($row['total_cents']) ? (int)$row['total_cents'] : null,
    'created_at' => $row['created_at'] ?? null,
    'buyer_id' => isset($row['buyer_id']) ? (int)$row['buyer_id'] : null,
    'buyer_name' => $row['buyer_name'] ?? null,
    'seller_id' => isset($row['seller_id']) ? (int)$row['seller_id'] : null,
    'seller_name' => $row['seller_name'] ?? null,
    'unread_count' => $hasMessagesTable && isset($row['unread_count']) ? (int)$row['unread_count'] : 0,
    'last_message' => $hasMessagesTable ? ($row['last_message'] ?? null) : null,
    'last_message_at' => $hasMessagesTable ? ($row['last_message_at'] ?? null) : null,
    'payment_amount_cents' => isset($row['payment_amount_cents']) ? (int)$row['payment_amount_cents'] : null,
    'payment_status' => $row['payment_status'] ?? null,
    'payment_paid_at' => $row['payment_paid_at'] ?? null,
    'payment_method_type' => $row['payment_method_type'] ?? null,
    'payment_method_label' => $row['payment_method_label'] ?? null,
    'payment_method_last4' => $row['payment_method_last4'] ?? null,
  ];
}, $rows);

echo json_encode($orders);
