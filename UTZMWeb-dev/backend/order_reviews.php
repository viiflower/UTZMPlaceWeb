<?php
require __DIR__ . '/../db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'No autenticado']);
  exit;
}

$userId = (int) ($_SESSION['user']['id'] ?? 0);

function load_order_for_review(PDO $pdo, int $orderId): ?array
{
  $stmt = $pdo->prepare('SELECT id, buyer_id, seller_id, status FROM orders WHERE id = ? LIMIT 1');
  $stmt->execute([$orderId]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);
  return $order ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
  if ($orderId <= 0) {
    echo json_encode(['error' => 'Pedido invalido']);
    exit;
  }

  $order = load_order_for_review($pdo, $orderId);
  if (!$order) {
    echo json_encode(['error' => 'Pedido no encontrado']);
    exit;
  }

  if ((int) $order['buyer_id'] !== $userId && (int) $order['seller_id'] !== $userId) {
    http_response_code(403);
    echo json_encode(['error' => 'No tienes acceso a este pedido']);
    exit;
  }

  $stmt = $pdo->prepare("
    SELECT
      r.id,
      r.order_id,
      r.reviewer_id,
      r.reviewee_id,
      r.rating,
      r.comment,
      r.created_at,
      reviewer.full_name AS reviewer_name,
      reviewee.full_name AS reviewee_name
    FROM order_reviews r
    JOIN users reviewer ON reviewer.id = r.reviewer_id
    JOIN users reviewee ON reviewee.id = r.reviewee_id
    WHERE r.order_id = ?
    ORDER BY r.created_at DESC
  ");
  $stmt->execute([$orderId]);

  $reviews = array_map(static function (array $row) use ($userId) {
    return [
      'id' => (int) $row['id'],
      'order_id' => (int) $row['order_id'],
      'reviewer_id' => (int) $row['reviewer_id'],
      'reviewee_id' => (int) $row['reviewee_id'],
      'reviewer_name' => $row['reviewer_name'],
      'reviewee_name' => $row['reviewee_name'],
      'rating' => (int) $row['rating'],
      'comment' => $row['comment'],
      'created_at' => $row['created_at'],
      'is_mine' => (int) $row['reviewer_id'] === $userId,
    ];
  }, $stmt->fetchAll(PDO::FETCH_ASSOC));

  echo json_encode(['reviews' => $reviews]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Metodo no permitido']);
  exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$orderId = isset($payload['order_id']) ? (int) $payload['order_id'] : 0;
$rating = isset($payload['rating']) ? (int) $payload['rating'] : 0;
$comment = trim((string) ($payload['comment'] ?? ''));

if ($orderId <= 0 || $rating < 1 || $rating > 5) {
  echo json_encode(['error' => 'Datos de resena invalidos']);
  exit;
}

$order = load_order_for_review($pdo, $orderId);
if (!$order) {
  echo json_encode(['error' => 'Pedido no encontrado']);
  exit;
}

if (($order['status'] ?? '') !== 'delivered') {
  echo json_encode(['error' => 'Solo puedes calificar pedidos entregados']);
  exit;
}

if ((int) $order['buyer_id'] !== $userId && (int) $order['seller_id'] !== $userId) {
  http_response_code(403);
  echo json_encode(['error' => 'No tienes acceso a este pedido']);
  exit;
}

$revieweeId = (int) $order['buyer_id'] === $userId ? (int) $order['seller_id'] : (int) $order['buyer_id'];

$stmt = $pdo->prepare("
  INSERT INTO order_reviews (order_id, reviewer_id, reviewee_id, rating, comment, updated_at)
  VALUES (:order_id, :reviewer_id, :reviewee_id, :rating, :comment, NOW())
  ON CONFLICT (order_id, reviewer_id)
  DO UPDATE SET rating = EXCLUDED.rating, comment = EXCLUDED.comment, updated_at = NOW()
  RETURNING id, created_at
");
$stmt->execute([
  ':order_id' => $orderId,
  ':reviewer_id' => $userId,
  ':reviewee_id' => $revieweeId,
  ':rating' => $rating,
  ':comment' => $comment !== '' ? $comment : null,
]);
$saved = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
  'success' => true,
  'review' => [
    'id' => (int) $saved['id'],
    'order_id' => $orderId,
    'reviewer_id' => $userId,
    'reviewee_id' => $revieweeId,
    'rating' => $rating,
    'comment' => $comment,
    'created_at' => $saved['created_at'],
    'is_mine' => true,
  ],
]);
