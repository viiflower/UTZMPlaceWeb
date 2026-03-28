<?php
require __DIR__ . '/../db.php';
require_once __DIR__ . '/lib/cvv_storage.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
  http_response_code(401);
  echo json_encode(['error' => 'No autenticado']);
  exit;
}

$user_id = (int)($_SESSION['user']['id'] ?? 0);

$stmt = $pdo->prepare("
  SELECT id, type, COALESCE(label, INITCAP(type)) AS label, last4, created_at
  FROM payment_methods
  WHERE user_id = :uid
  ORDER BY created_at DESC NULLS LAST, id DESC
");
$stmt->execute([':uid' => $user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$methods = array_map(function($row){
  $type = $row['type'] ?? '';
  $rawLabel = $row['label'] ?? $type;
  $cleanLabel = preg_replace('/\bdemo\b/i', '', (string)$rawLabel);
  $cleanLabel = preg_replace('/^tarjeta\s*/i', '', $cleanLabel);
  $cleanLabel = trim(preg_replace('/\s{2,}/', ' ', $cleanLabel));
  if ($cleanLabel === '') {
    if ($type === 'card') {
      $cleanLabel = 'Tarjeta';
    } elseif ($type === 'cash') {
      $cleanLabel = 'Efectivo';
    } elseif ($type === 'paypal') {
      $cleanLabel = 'PayPal';
    } else {
      $cleanLabel = ucfirst($type);
    }
  }
  $cvvLength = cvv_storage_length((int)$row['id']);
  return [
    'id' => (int)$row['id'],
    'type' => $type,
    'label' => $cleanLabel,
    'last4' => $row['last4'] ?? null,
    'cvv_length' => $cvvLength,
  ];
}, $rows);

echo json_encode(['methods' => $methods]);
