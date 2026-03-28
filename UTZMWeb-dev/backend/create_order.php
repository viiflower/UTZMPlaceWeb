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
  echo json_encode(['error' => 'Debes iniciar sesión']);
  exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$seller_id = isset($payload['seller_id']) ? (int)$payload['seller_id'] : 0;
$items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];
$buyer_id = (int)($_SESSION['user']['id'] ?? 0);

if ($seller_id <= 0 || empty($items)) {
  http_response_code(422);
  echo json_encode(['error' => 'Datos de la orden incompletos']);
  exit;
}

try {
  $pdo->beginTransaction();

  $total = 0;
  $preparedProduct = $pdo->prepare("SELECT id, name, price_cents, seller_id, stock FROM products WHERE id = ? FOR UPDATE");
  $updateStock = $pdo->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id AND stock >= :qty");

  $normalizedItems = [];
  foreach ($items as $it) {
    $product_id = isset($it['product_id']) ? (int)$it['product_id'] : 0;
    $qty = isset($it['qty']) ? (int)$it['qty'] : 0;
    if ($product_id <= 0 || $qty <= 0) {
      throw new Exception('Producto inválido en la orden');
    }

    $preparedProduct->execute([$product_id]);
    $product = $preparedProduct->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
      throw new Exception('Producto no encontrado');
    }
    if ((int)$product['seller_id'] !== $seller_id) {
      throw new Exception('Todos los productos deben pertenecer al mismo vendedor');
    }
    if ((int)$product['stock'] < $qty) {
      throw new Exception('Producto sin stock suficiente');
    }

    $lineTotal = (int)$product['price_cents'] * $qty;
    $total += $lineTotal;
    $normalizedItems[] = [
      'product_id' => $product_id,
      'name' => $product['name'],
      'price_cents' => (int)$product['price_cents'],
      'qty' => $qty
    ];
  }

  $stmt = $pdo->prepare("
    INSERT INTO orders (buyer_id, seller_id, status, total_cents)
    VALUES (:buyer_id, :seller_id, 'pending', :total)
    RETURNING id
  ");
  $stmt->execute([
    ':buyer_id' => $buyer_id,
    ':seller_id' => $seller_id,
    ':total' => $total
  ]);
  $order_id = (int)$stmt->fetchColumn();

  $insertItem = $pdo->prepare("
    INSERT INTO order_items (order_id, product_id, name, price_cents, qty)
    VALUES (:order_id, :product_id, :name, :price_cents, :qty)
  ");
  foreach ($normalizedItems as $it) {
    $insertItem->execute([
      ':order_id' => $order_id,
      ':product_id' => $it['product_id'],
      ':name' => $it['name'],
      ':price_cents' => $it['price_cents'],
      ':qty' => $it['qty']
    ]);
    // descontar stock
    $updateStock->execute([
      ':qty' => $it['qty'],
      ':id' => $it['product_id']
    ]);
    if ($updateStock->rowCount() === 0) {
      throw new Exception('No se pudo actualizar el stock, intenta de nuevo');
    }
  }

  $pdo->commit();
  echo json_encode([
    'order_id' => $order_id,
    'total_cents' => $total
  ]);
} catch (Exception $e) {
  $pdo->rollBack();
  http_response_code(400);
  echo json_encode(['error' => $e->getMessage()]);
}
