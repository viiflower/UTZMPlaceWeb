<?php
require __DIR__ . '/../db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['id'])) {
  echo json_encode(["error" => "No autorizado"]);
  exit;
}

$seller_id = (int) $_SESSION['user']['id'];

try {
  $stmt = $pdo->prepare("\n    SELECT\n      p.id, p.name, p.description, p.price_cents, p.stock, p.category_id,\n      (SELECT url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image\n    FROM products p\n    WHERE p.seller_id = ?\n    ORDER BY p.created_at DESC\n  ");
  $stmt->execute([$seller_id]);
  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
  echo json_encode(["error" => $e->getMessage()]);
}
